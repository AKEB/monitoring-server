<?php

class MonitoringApiStatus {

	const STATUS_UP = 'up';
	const STATUS_DOWN = 'down';
	const STATUS_MAINTENANCE = 'maintenance';
	const STATUS_UNKNOWN = 'unknown';

	/**
	 * @return array{resources: list<array>, maintenances: list<array>}
	 */
	static public function buildForUser(array $user): array {
		$monitors = static::visibleMonitors($user);
		$resources = static::buildResources($monitors);
		$maintenances = static::buildMaintenances($monitors);
		static::attachMaintenancesToResources($resources, $maintenances);
		return [
			'resources' => $resources,
			'maintenances' => $maintenances,
		];
	}

	/**
	 * @param list<array> $monitors
	 * @return list<array>
	 */
	static private function buildResources(array $monitors): array {
		if (!$monitors) {
			return [];
		}

		$monitor_ids = array_map(static fn($m) => intval($m['id']), $monitors);
		$workers_hash = static::workersHash();
		$dockers_hash = static::dockersHash();
		$assigned = static::assignedWorkers($monitors);
		$last_logs = static::lastLogs($monitors);
		$availability = \MonitoringAvailabilityPercent::getWindowsByWorker($monitor_ids);
		$avg_response = \MonitoringAvailabilityPercent::avgResponseTimeMsByMonitor($monitor_ids);
		$ssl_expire = static::certExpireByMonitor($monitors);
		$now = time();

		$resources = [];
		foreach ($monitors as $monitor) {
			$monitor_id = intval($monitor['id']);
			$in_maintenance = (bool)\MonitoringMaintenances::getMaintenancesForMonitor($monitor_id);
			$repeat = static::repeatSeconds($monitor, $dockers_hash);
			$worker_ids = $assigned[$monitor_id] ?? [];
			$worker_rows = [];
			$worker_statuses = [];
			foreach ($worker_ids as $worker_id) {
				$last = $last_logs[$monitor_id][$worker_id] ?? null;
				$worker_status = static::workerStatusFromLog($in_maintenance, $last, $now, $repeat);
				$worker_statuses[] = $worker_status;
				$worker_avail = $availability[$monitor_id][$worker_id]['percent'] ?? \MonitoringAvailabilityPercent::emptyWindows();
				$worker_title = strval($workers_hash[$worker_id] ?? '');
				if ($worker_title === '') {
					$worker_title = '#'.$worker_id;
				}
				$worker_rows[] = [
					'id' => $worker_id,
					'title' => $worker_title,
					'status' => $worker_status,
					'availability' => $worker_avail,
				];
			}
			usort($worker_rows, static fn($a, $b) => strcasecmp($a['title'], $b['title']));

			$by_worker = $availability[$monitor_id] ?? [];
			$ssl = static::sslExpireFields($ssl_expire[$monitor_id] ?? null);
			$resources[] = [
				'id' => $monitor_id,
				'title' => strval($monitor['title'] ?? ''),
				'description_ru' => strval($monitor['api_description_ru'] ?? ''),
				'description_en' => strval($monitor['api_description_en'] ?? ''),
				'check_url' => static::checkUrl($monitor, $dockers_hash),
				'status' => static::aggregateResourceStatus($in_maintenance, $worker_statuses),
				'avg_response_time_ms' => array_key_exists($monitor_id, $avg_response) ? $avg_response[$monitor_id] : null,
				'ssl_expire_time' => $ssl['ssl_expire_time'],
				'ssl_expire_at' => $ssl['ssl_expire_at'],
				'availability' => \MonitoringAvailabilityPercent::rollupWindows($by_worker),
				'workers' => $worker_rows,
				'maintenances' => [],
			];
		}

		usort($resources, static fn($a, $b) => strcasecmp($a['title'], $b['title']));
		return $resources;
	}

	/**
	 * Current and upcoming maintenances that affect API-visible monitors.
	 *
	 * @param list<array> $visible_monitors
	 * @return list<array>
	 */
	static public function buildMaintenances(array $visible_monitors): array {
		if (!$visible_monitors) {
			return [];
		}

		$now = time();
		$rows = \Maintenances::data(
			false,
			sql_pholder(' AND `finish_time` >= ? ORDER BY `start_time` ASC, `id` ASC', $now),
			'`id`, `title`, `start_time`, `finish_time`'
		);
		if (!$rows || !is_array($rows)) {
			return [];
		}

		$links = \MonitoringMaintenances::data(false, ' ORDER BY NULL', '`maintenance_id`, `monitor_id`');
		$targets = [];
		if ($links) {
			foreach ($links as $link) {
				$maintenance_id = intval($link['maintenance_id'] ?? 0);
				$monitor_id = intval($link['monitor_id'] ?? 0);
				if ($maintenance_id < 1 || $monitor_id < 1) {
					continue;
				}
				$targets[$maintenance_id][$monitor_id] = true;
			}
		}

		$monitors_hash = \Monitors::get_hash();
		$out = [];
		foreach ($rows as $row) {
			$maintenance_id = intval($row['id'] ?? 0);
			if ($maintenance_id < 1) {
				continue;
			}
			$affected = [];
			$target_ids = $targets[$maintenance_id] ?? [];
			foreach ($visible_monitors as $monitor) {
				$monitor_id = intval($monitor['id'] ?? 0);
				if ($monitor_id < 1) {
					continue;
				}
				if (!static::monitorIsUnderTargets($monitor_id, $target_ids, $monitors_hash)) {
					continue;
				}
				$affected[] = [
					'id' => $monitor_id,
					'title' => strval($monitor['title'] ?? ''),
				];
			}
			if (!$affected) {
				continue;
			}
			usort($affected, static fn($a, $b) => strcasecmp($a['title'], $b['title']));
			$start = intval($row['start_time'] ?? 0);
			$finish = intval($row['finish_time'] ?? 0);
			$out[] = [
				'id' => $maintenance_id,
				'title' => strval($row['title'] ?? ''),
				'start_time' => $start,
				'finish_time' => $finish,
				'start_at' => $start > 0 ? date('Y-m-d H:i:s', $start) : '',
				'finish_at' => $finish > 0 ? date('Y-m-d H:i:s', $finish) : '',
				'active' => $start <= $now && $finish >= $now,
				'monitors' => $affected,
			];
		}
		return $out;
	}

	/**
	 * True if the monitor itself or any parent folder is in $target_ids.
	 *
	 * @param array<int, true> $target_ids
	 * @param array<int, array> $monitors_hash
	 */
	static public function monitorIsUnderTargets(int $monitor_id, array $target_ids, array $monitors_hash): bool {
		if (!$target_ids) {
			return false;
		}
		$id = $monitor_id;
		$guard = 0;
		while ($id > 0 && $guard++ < 100) {
			if (isset($target_ids[$id])) {
				return true;
			}
			$id = intval($monitors_hash[$id]['parent_id'] ?? 0);
		}
		return false;
	}

	/**
	 * @param list<array> $resources
	 * @param list<array> $maintenances
	 */
	static private function attachMaintenancesToResources(array &$resources, array $maintenances): void {
		if (!$resources || !$maintenances) {
			return;
		}
		$by_monitor = [];
		foreach ($maintenances as $item) {
			$compact = [
				'id' => $item['id'],
				'title' => $item['title'],
				'start_time' => $item['start_time'],
				'finish_time' => $item['finish_time'],
				'start_at' => $item['start_at'],
				'finish_at' => $item['finish_at'],
				'active' => $item['active'],
			];
			foreach ($item['monitors'] as $monitor) {
				$by_monitor[intval($monitor['id'])][] = $compact;
			}
		}
		foreach ($resources as &$resource) {
			$resource['maintenances'] = $by_monitor[intval($resource['id'])] ?? [];
		}
		unset($resource);
	}

	/**
	 * @return list<array>
	 */
	static public function visibleMonitors(array $user): array {
		$sql = sql_pholder(' AND `type` <> ? ORDER BY `title` ASC', \Monitors::TYPE_FOLDER);
		$rows = \Monitors::data(
			['api_enabled' => 1],
			$sql,
			'`id`, `title`, `type`, `url`, `host`, `port`, `docker`, `container`, `status`, `parent_id`, `repeat_seconds`, `api_description_ru`, `api_description_en`, `external_hash`'
		);
		if (!$rows || !is_array($rows)) {
			return [];
		}

		$dockers_hash = static::dockersHash();
		$visible = [];
		foreach ($rows as $monitor) {
			$monitor_id = intval($monitor['id'] ?? 0);
			if ($monitor_id < 1) {
				continue;
			}
			if (!\EntityValidation::isStatusApiCandidate($monitor)) {
				continue;
			}
			if (!\Monitors::is_enable($monitor_id)) {
				continue;
			}
			if (!\Sessions::checkPermission(\Monitors::PERMISSION_ACCESS, $monitor_id, READ, $user)) {
				continue;
			}
			if (intval($monitor['type']) === \Monitors::TYPE_DOCKER) {
				$docker_id = intval($monitor['docker'] ?? 0);
				if ($docker_id < 1 || !isset($dockers_hash[$docker_id])) {
					continue;
				}
				if (intval($dockers_hash[$docker_id]['status'] ?? 0) !== \Dockers::STATUS_ENABLED) {
					continue;
				}
			}
			$visible[] = $monitor;
		}
		return $visible;
	}

	static public function checkUrl(array $monitor, array $dockers_hash = []): string {
		$type = intval($monitor['type'] ?? 0);
		$url = trim(strval($monitor['url'] ?? ''));
		$host = trim(strval($monitor['host'] ?? ''));
		$port = intval($monitor['port'] ?? 0);
		switch ($type) {
			case \Monitors::TYPE_HTTPS:
			case \Monitors::TYPE_HTTP_JSON:
				return $url;
			case \Monitors::TYPE_TCP:
				return $port > 0 ? 'tcp://'.$host.':'.$port : 'tcp://'.$host;
			case \Monitors::TYPE_PING:
				return 'icmp://'.$host;
			case \Monitors::TYPE_DOCKER:
				$docker_id = intval($monitor['docker'] ?? 0);
				$docker_title = strval($dockers_hash[$docker_id]['title'] ?? $docker_id);
				$container = trim(strval($monitor['container'] ?? ''));
				return 'docker://'.$docker_title.($container !== '' ? '/'.$container : '');
			case \Monitors::TYPE_EXPORTER_DISK:
			case \Monitors::TYPE_EXPORTER_MEMORY:
			case \Monitors::TYPE_EXPORTER_CPU:
				if ($url !== '') {
					return $url;
				}
				return $port > 0 ? $host.':'.$port : $host;
			case \Monitors::TYPE_EXTERNAL:
				$hash = trim(strval($monitor['external_hash'] ?? ''));
				return $hash !== '' ? 'external://'.$hash : 'external://';
			default:
				return $url !== '' ? $url : $host;
		}
	}

	static public function aggregateResourceStatus(bool $in_maintenance, array $worker_statuses): string {
		if ($in_maintenance) {
			return self::STATUS_MAINTENANCE;
		}
		$has_down = false;
		$has_up = false;
		foreach ($worker_statuses as $status) {
			if ($status === self::STATUS_DOWN) {
				$has_down = true;
			} elseif ($status === self::STATUS_UP) {
				$has_up = true;
			}
		}
		if ($has_down) {
			return self::STATUS_DOWN;
		}
		if ($has_up) {
			return self::STATUS_UP;
		}
		return self::STATUS_UNKNOWN;
	}

	/**
	 * @return array{ssl_expire_time: int|null, ssl_expire_at: string|null}
	 */
	static public function sslExpireFields(?int $cert_expire): array {
		if ($cert_expire === null || $cert_expire < 1) {
			return [
				'ssl_expire_time' => null,
				'ssl_expire_at' => null,
			];
		}
		return [
			'ssl_expire_time' => $cert_expire,
			'ssl_expire_at' => date('Y-m-d', $cert_expire),
		];
	}

	static public function workerStatusFromLog(bool $in_maintenance, ?array $last_log, int $now, int $repeat_seconds): string {
		if ($in_maintenance) {
			return self::STATUS_MAINTENANCE;
		}
		if ($repeat_seconds < 1) {
			$repeat_seconds = 60;
		}
		if (!$last_log || !isset($last_log['update_time'])) {
			return self::STATUS_UNKNOWN;
		}
		$fresh_after = $now - (2 * $repeat_seconds);
		if (intval($last_log['update_time']) < $fresh_after) {
			return self::STATUS_UNKNOWN;
		}
		if (intval($last_log['status']) === \MonitoringLogs::STATUS_FAIL) {
			return self::STATUS_DOWN;
		}
		if (intval($last_log['status']) === \MonitoringLogs::STATUS_GOOD) {
			return self::STATUS_UP;
		}
		return self::STATUS_UNKNOWN;
	}

	/**
	 * @return array<int, array>
	 */
	static private function workersHash(): array {
		$rows = \Workers::data(false, ' ORDER BY NULL', '`id`, `title`');
		$hash = [];
		if ($rows) {
			foreach ($rows as $row) {
				$hash[intval($row['id'])] = strval($row['title'] ?? '');
			}
		}
		$hash[\Monitors::EXTERNAL_WORKER_ID] = \ExternalMonitors::workerTitle();
		return $hash;
	}

	/**
	 * @return array<int, array>
	 */
	static private function dockersHash(): array {
		static $hash = null;
		if ($hash !== null) {
			return $hash;
		}
		$hash = [];
		$rows = \Dockers::data(false, ' ORDER BY NULL', '`id`, `title`, `status`, `repeat_seconds`');
		if ($rows) {
			foreach ($rows as $row) {
				$hash[intval($row['id'])] = $row;
			}
		}
		return $hash;
	}

	/**
	 * @param list<array> $monitors
	 * @return array<int, list<int>>
	 */
	static private function assignedWorkers(array $monitors): array {
		$monitor_ids = [];
		$docker_monitor_ids = [];
		$docker_ids = [];
		foreach ($monitors as $monitor) {
			$id = intval($monitor['id']);
			$monitor_ids[$id] = $id;
			if (intval($monitor['type']) === \Monitors::TYPE_DOCKER) {
				$docker_id = intval($monitor['docker'] ?? 0);
				if ($docker_id > 0) {
					$docker_monitor_ids[$id] = $docker_id;
					$docker_ids[$docker_id] = $docker_id;
				}
			}
		}

		$assigned = [];
		foreach ($monitor_ids as $id) {
			$assigned[$id] = [];
		}

		if ($monitor_ids) {
			$jobs = \MonitoringJobs::data(
				false,
				sql_pholder(' AND `monitor_id` IN (?@) ORDER BY NULL', array_values($monitor_ids)),
				'`monitor_id`, `worker_id`'
			);
			if ($jobs) {
				foreach ($jobs as $job) {
					$monitor_id = intval($job['monitor_id']);
					$worker_id = intval($job['worker_id']);
					if ($monitor_id < 1 || $worker_id === 0) {
						continue;
					}
					if (isset($docker_monitor_ids[$monitor_id])) {
						continue;
					}
					$assigned[$monitor_id][$worker_id] = $worker_id;
				}
			}
		}

		$docker_workers = [];
		if ($docker_ids) {
			$djobs = \MonitoringDockers::data(
				false,
				sql_pholder(' AND `docker_id` IN (?@) ORDER BY NULL', array_values($docker_ids)),
				'`docker_id`, `worker_id`'
			);
			if ($djobs) {
				foreach ($djobs as $job) {
					$docker_id = intval($job['docker_id']);
					$worker_id = intval($job['worker_id']);
					if ($docker_id < 1 || $worker_id < 1) {
						continue;
					}
					$docker_workers[$docker_id][$worker_id] = $worker_id;
				}
			}
		}
		foreach ($docker_monitor_ids as $monitor_id => $docker_id) {
			$assigned[$monitor_id] = $docker_workers[$docker_id] ?? [];
		}

		foreach ($assigned as $monitor_id => $workers) {
			$assigned[$monitor_id] = array_values($workers);
		}
		return $assigned;
	}

	/**
	 * Soonest SSL expiry seen in recent checks, keyed by monitor_id.
	 * Ignores rows with no certificate (TCP/ping/failed handshake).
	 *
	 * @param list<array> $monitors
	 * @return array<int, int>
	 */
	static private function certExpireByMonitor(array $monitors): array {
		if (!$monitors) {
			return [];
		}
		$monitor_ids = [];
		$lookback = 3600;
		$dockers_hash = static::dockersHash();
		foreach ($monitors as $monitor) {
			$monitor_ids[] = intval($monitor['id']);
			$lookback = max($lookback, 2 * static::repeatSeconds($monitor, $dockers_hash));
		}
		$sql = sql_pholder(
			' AND `monitor_id` IN (?@) AND `update_time` >= ? AND `cert_expire` > 0 GROUP BY `monitor_id` ORDER BY NULL',
			$monitor_ids,
			time() - $lookback
		);
		$rows = \MonitoringLogs::data(false, $sql, '`monitor_id`, MIN(`cert_expire`) AS `cert_expire`');
		$result = [];
		if ($rows) {
			foreach ($rows as $row) {
				$monitor_id = intval($row['monitor_id'] ?? 0);
				$cert_expire = intval($row['cert_expire'] ?? 0);
				if ($monitor_id < 1 || $cert_expire < 1) {
					continue;
				}
				$result[$monitor_id] = $cert_expire;
			}
		}
		return $result;
	}

	/**
	 * @param list<array> $monitors
	 * @return array<int, array<int, array{status:int, update_time:int}>>
	 */
	static private function lastLogs(array $monitors): array {
		if (!$monitors) {
			return [];
		}
		$monitor_ids = [];
		$max_lookback = 120;
		$dockers_hash = static::dockersHash();
		foreach ($monitors as $monitor) {
			$monitor_ids[] = intval($monitor['id']);
			$repeat = static::repeatSeconds($monitor, $dockers_hash);
			$max_lookback = max($max_lookback, 2 * $repeat);
		}
		$sql = sql_pholder(
			' AND `monitor_id` IN (?@) AND `update_time` >= ? ORDER BY `update_time` DESC',
			$monitor_ids,
			time() - $max_lookback
		);
		$rows = \MonitoringLogs::data(false, $sql, '`monitor_id`, `worker_id`, `status`, `update_time`');
		$result = [];
		if ($rows) {
			foreach ($rows as $row) {
				$monitor_id = intval($row['monitor_id']);
				$worker_id = intval($row['worker_id']);
				if ($monitor_id < 1 || $worker_id === 0) {
					continue;
				}
				if (isset($result[$monitor_id][$worker_id])) {
					continue;
				}
				$result[$monitor_id][$worker_id] = [
					'status' => intval($row['status']),
					'update_time' => intval($row['update_time']),
				];
			}
		}
		return $result;
	}

	static private function repeatSeconds(array $monitor, array $dockers_hash): int {
		if (intval($monitor['type'] ?? 0) === \Monitors::TYPE_DOCKER) {
			$docker_id = intval($monitor['docker'] ?? 0);
			$repeat = intval($dockers_hash[$docker_id]['repeat_seconds'] ?? 0);
			return $repeat > 0 ? $repeat : 60;
		}
		$repeat = intval($monitor['repeat_seconds'] ?? 0);
		return $repeat > 0 ? $repeat : 60;
	}
}

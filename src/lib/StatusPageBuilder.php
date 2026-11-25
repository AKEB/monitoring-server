<?php

/**
 * Builds anonymous public status-page payloads.
 * Membership is independent of api_enabled / user ACL.
 */
class StatusPageBuilder {

	const CACHE_TTL_SEC = 45;
	const HEARTBEAT_LIMIT = 40;

	/**
	 * @return array<string,mixed>|null
	 */
	static public function buildBySlug(string $slug): ?array {
		$page = \StatusPages::getBySlug($slug);
		if (!$page || intval($page['status'] ?? 0) !== \StatusPages::STATUS_ENABLED) {
			return null;
		}
		return static::buildForPage($page);
	}

	/**
	 * @param array<string,mixed> $page
	 * @return array<string,mixed>
	 */
	static public function buildForPage(array $page): array {
		$pageId = intval($page['id'] ?? 0);
		$folderIds = [];
		foreach (\StatusPageFolders::data(['status_page_id' => $pageId]) ?: [] as $row) {
			$folderIds[] = intval($row['folder_id'] ?? 0);
		}
		$monitorIds = [];
		foreach (\StatusPageMonitors::data(['status_page_id' => $pageId]) ?: [] as $row) {
			$monitorIds[] = intval($row['monitor_id'] ?? 0);
		}

		$monitorsHash = [];
		foreach (\Monitors::data(false, '', '`id`, `title`, `type`, `url`, `host`, `port`, `docker`, `container`, `status`, `api_enabled`, `parent_id`, `repeat_seconds`, `api_description_ru`, `api_description_en`, `external_hash`') ?: [] as $row) {
			$monitorsHash[intval($row['id'])] = $row;
		}

		$resolved = \StatusPageMembership::resolveGroups($folderIds, $monitorIds, $monitorsHash);
		$leafMonitors = [];
		foreach ($resolved['monitor_ids'] as $mid) {
			if (isset($monitorsHash[$mid])) {
				$leafMonitors[] = $monitorsHash[$mid];
			}
		}

		$payload = \MonitoringApiStatus::buildPayloadForMonitors($leafMonitors);
		$repeatByMonitor = [];
		foreach ($leafMonitors as $monitor) {
			$repeatByMonitor[intval($monitor['id'])] = max(1, intval($monitor['repeat_seconds'] ?? 60));
		}
		$workersByMonitor = [];
		foreach ($payload['resources'] as $resource) {
			$mid = intval($resource['id']);
			$workersByMonitor[$mid] = [];
			foreach ($resource['workers'] ?? [] as $worker) {
				$wid = intval($worker['id'] ?? 0);
				if ($wid !== 0) {
					$workersByMonitor[$mid][$wid] = $wid;
				}
			}
		}
		$beatsByMonitor = static::recentBeats($workersByMonitor, $repeatByMonitor);
		$resourcesById = [];
		$allStatuses = [];
		foreach ($payload['resources'] as $resource) {
			$id = intval($resource['id']);
			$resource['beats'] = $beatsByMonitor[$id] ?? [];
			$resourcesById[$id] = $resource;
			$allStatuses[] = strval($resource['status'] ?? \MonitoringApiStatus::STATUS_UNKNOWN);
		}

		$groups = [];
		foreach ($resolved['groups'] as $group) {
			$items = [];
			foreach ($group['monitor_ids'] as $mid) {
				if (isset($resourcesById[$mid])) {
					$items[] = $resourcesById[$mid];
				}
			}
			$groups[] = [
				'id' => intval($group['id']),
				'title' => strval($group['title']),
				'resources' => $items,
			];
		}

		$incidents = [];
		foreach (\StatusPageIncidents::data(
			['status_page_id' => $pageId, 'active' => 1],
			' ORDER BY `id` DESC'
		) ?: [] as $inc) {
			$incidents[] = [
				'id' => intval($inc['id']),
				'title' => strval($inc['title'] ?? ''),
				'message' => strval($inc['message'] ?? ''),
				'state' => strval($inc['state'] ?? ''),
				'active' => 1,
				'create_time' => intval($inc['create_time'] ?? 0),
				'update_time' => intval($inc['update_time'] ?? 0),
			];
		}

		return [
			'id' => $pageId,
			'title' => strval($page['title'] ?? ''),
			'slug' => strval($page['slug'] ?? ''),
			'generated_at' => time(),
			'overall' => static::overallFromStatuses($allStatuses),
			'groups' => $groups,
			'maintenances' => $payload['maintenances'],
			'incidents' => $incidents,
		];
	}

	/**
	 * @param list<string> $statuses
	 * @return array{code:string,label:string}
	 */
	static public function overallFromStatuses(array $statuses): array {
		if (!$statuses) {
			return ['code' => 'empty', 'label' => 'No Services'];
		}
		$up = 0;
		$down = 0;
		$degraded = 0;
		$maint = 0;
		$unknown = 0;
		foreach ($statuses as $st) {
			switch ($st) {
				case \MonitoringApiStatus::STATUS_UP:
					$up++;
					break;
				case \MonitoringApiStatus::STATUS_DOWN:
					$down++;
					break;
				case \MonitoringApiStatus::STATUS_DEGRADED:
					$degraded++;
					break;
				case \MonitoringApiStatus::STATUS_MAINTENANCE:
					$maint++;
					break;
				default:
					$unknown++;
					break;
			}
		}
		$total = count($statuses);
		if ($down === 0 && $degraded === 0 && $unknown === 0 && $maint === 0 && $up === $total) {
			return ['code' => 'ok', 'label' => 'All Systems Operational'];
		}
		if ($down === $total) {
			return ['code' => 'down', 'label' => 'Degraded Service'];
		}
		if ($down > 0 || $degraded > 0) {
			return ['code' => 'partial', 'label' => 'Partially Degraded Service'];
		}
		if ($maint > 0 && $up + $maint === $total) {
			return ['code' => 'maint', 'label' => 'Under Maintenance'];
		}
		if ($unknown === $total) {
			return ['code' => 'unknown', 'label' => 'Status Unknown'];
		}
		if ($unknown > 0 && $down === 0 && $degraded === 0) {
			return ['code' => 'partial', 'label' => 'Partially Degraded Service'];
		}
		return ['code' => 'partial', 'label' => 'Partially Degraded Service'];
	}

	/**
	 * Recent heartbeat slots (oldest → newest), capped per monitor.
	 * Aggregates all workers in each time slot with the same yellow/red logic
	 * as the main monitor list.
	 *
	 * @param array<int, array<int, int>> $workersByMonitor monitor_id => [worker_id => worker_id]
	 * @param array<int, int> $repeatByMonitor monitor_id => native repeat_seconds
	 * @return array<int, list<array{status:string,time:int}>>
	 */
	static public function recentBeats(array $workersByMonitor, array $repeatByMonitor = []): array {
		$out = [];
		$monitorIds = [];
		foreach ($workersByMonitor as $mid => $workers) {
			$mid = intval($mid);
			if ($mid < 1) {
				continue;
			}
			$monitorIds[$mid] = $mid;
			$out[$mid] = [];
		}
		if (!$monitorIds) {
			return $out;
		}

		$now = time();
		$displayRepeatByMonitor = [];
		$slotTimesByMonitor = [];
		$globalMin = $now;
		$globalMax = 0;
		$carryLookback = 0;
		$intervalSeconds = [];

		foreach ($monitorIds as $mid) {
			$native = max(1, intval($repeatByMonitor[$mid] ?? 60));
			$display = \MonitoringLogs::listDisplayRepeatSeconds($native);
			$displayRepeatByMonitor[$mid] = $display;
			$intervalSeconds[$display] = $display;
			$carryLookback = max($carryLookback, \MonitoringLogs::listCarryLookbackSeconds($native));

			$maxTime = intval($now / $display) * $display;
			$minTime = $maxTime - (($display * (self::HEARTBEAT_LIMIT - 1)));
			$globalMin = min($globalMin, $minTime);
			$globalMax = max($globalMax, $maxTime);

			$slots = [];
			for ($t = $minTime; $t <= $maxTime; $t += $display) {
				if ($t > $now) {
					continue;
				}
				$workers = [];
				foreach ($workersByMonitor[$mid] ?? [] as $wid) {
					$workers[intval($wid)] = \MonitoringLogs::STATE_UNKNOWN;
				}
				$slots[$t] = $workers;
			}
			$slotTimesByMonitor[$mid] = $slots;
		}

		if (!$intervalSeconds) {
			return $out;
		}

		$fields = '`monitor_id`, `worker_id`, `status`, `update_time`';
		foreach ($intervalSeconds as $interval) {
			$fields .= ', FLOOR(`update_time` / '.intval($interval).') * '.intval($interval).' AS `interval_time_'.intval($interval).'`';
		}
		$rows = \MonitoringLogs::data(
			false,
			sql_pholder(
				' AND `monitor_id` IN (?@) AND `update_time` >= ? AND `update_time` <= ? ORDER BY NULL',
				array_values($monitorIds),
				$globalMin - intval($carryLookback),
				$globalMax
			),
			$fields
		) ?: [];

		$carrySeed = [];
		foreach ($rows as $row) {
			$mid = intval($row['monitor_id'] ?? 0);
			$wid = intval($row['worker_id'] ?? 0);
			$time = intval($row['update_time'] ?? 0);
			if ($mid < 1 || $wid === 0 || $time < 1 || !isset($slotTimesByMonitor[$mid])) {
				continue;
			}
			if (!isset($workersByMonitor[$mid][$wid]) && !isset($workersByMonitor[$mid][strval($wid)])) {
				continue;
			}
			$status = intval($row['status'] ?? -1);
			$state = match ($status) {
				\MonitoringLogs::STATUS_FAIL => \MonitoringLogs::STATE_ERROR,
				\MonitoringLogs::STATUS_GOOD => \MonitoringLogs::STATE_SUCCESS,
				default => \MonitoringLogs::STATE_UNKNOWN,
			};
			if ($state === \MonitoringLogs::STATE_UNKNOWN) {
				continue;
			}
			$display = $displayRepeatByMonitor[$mid];
			$slotTime = intval($row['interval_time_'.$display] ?? 0);
			if ($slotTime < 1) {
				continue;
			}
			$visibleMin = array_key_first($slotTimesByMonitor[$mid]);
			if ($visibleMin !== null && $slotTime < intval($visibleMin)) {
				$prevTime = intval($carrySeed[$mid]['_time'][$wid] ?? 0);
				if ($time >= $prevTime) {
					$carrySeed[$mid][$wid] = $state;
					$carrySeed[$mid]['_time'][$wid] = $time;
				}
				continue;
			}
			if (!isset($slotTimesByMonitor[$mid][$slotTime])) {
				continue;
			}
			$prev = intval($slotTimesByMonitor[$mid][$slotTime][$wid] ?? \MonitoringLogs::STATE_UNKNOWN);
			$slotTimesByMonitor[$mid][$slotTime][$wid] = max($prev, $state);
		}

		foreach ($slotTimesByMonitor as $mid => $slots) {
			$native = max(1, intval($repeatByMonitor[$mid] ?? 60));
			$display = $displayRepeatByMonitor[$mid];
			$carry = \MonitoringLogs::listShouldCarryStatus($native);
			$previousWorkers = [];
			if (!empty($carrySeed[$mid]) && is_array($carrySeed[$mid])) {
				foreach ($carrySeed[$mid] as $wid => $state) {
					if ($wid === '_time') {
						continue;
					}
					$previousWorkers[intval($wid)] = intval($state);
				}
			}
			$beats = [];
			foreach ($slots as $slotTime => $workerStates) {
				$isOpen = \MonitoringLogs::isOpenInterval(intval($slotTime), $display, $now);
				$expected = [];
				foreach ($workersByMonitor[$mid] ?? [] as $wid) {
					$expected[intval($wid)] = 1;
				}
				$hasRealData = false;
				foreach ($expected as $wid => $_) {
					$state = intval($workerStates[$wid] ?? \MonitoringLogs::STATE_UNKNOWN);
					if ($state === \MonitoringLogs::STATE_SUCCESS || $state === \MonitoringLogs::STATE_ERROR) {
						$hasRealData = true;
						break;
					}
				}
				if (($isOpen || $carry) && $previousWorkers) {
					$workerStates = \MonitoringLogs::fillUnknownFromPrevious($workerStates, $previousWorkers);
				}
				$counts = \MonitoringLogs::countWorkerStatesForSlot($expected, $workerStates, false);
				$slotClass = \MonitoringLogs::classForCountedStates($counts['error'], $counts['success']);
				if ($isOpen && !$hasRealData && !$carry && $slotClass !== 'bg-primary') {
					$slotClass = \MonitoringLogs::toSubtleStatusClass($slotClass);
				}
				$previousWorkers = $workerStates;
				$beats[] = [
					'status' => \MonitoringApiStatus::beatStatusFromClass($slotClass),
					'time' => intval($slotTime),
				];
			}
			$out[$mid] = $beats;
		}
		return $out;
	}

	static public function invalidateSlugCache(string $slug): void {
		$slug = \StatusPageMembership::normalizeSlug($slug);
		if ($slug === '') {
			return;
		}
		try {
			$cache = new \Cache('status_page_public_'.$slug);
			$cache->update('', 1);
		} catch (\Throwable $e) {
			// ignore
		}
	}

	/**
	 * @return array<string,mixed>|null
	 */
	static public function buildBySlugCached(string $slug): ?array {
		$slug = \StatusPageMembership::normalizeSlug($slug);
		if ($slug === '') {
			return null;
		}
		$cacheKey = 'status_page_public_'.$slug;
		try {
			$cache = new \Cache($cacheKey);
			if ($cache->isValid()) {
				$cached = $cache->get();
				if (is_string($cached) && $cached !== '') {
					$decoded = json_decode($cached, true);
					if (is_array($decoded)) {
						return $decoded;
					}
				}
			}
		} catch (\Throwable $e) {
			// fall through
		}
		$data = static::buildBySlug($slug);
		if ($data) {
			try {
				$payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
				if (is_string($payload) && $payload !== '') {
					$cache = new \Cache($cacheKey);
					$cache->update($payload, self::CACHE_TTL_SEC);
				}
			} catch (\Throwable $e) {
				// ignore
			}
		}
		return $data;
	}
}

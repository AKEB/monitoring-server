<?php

/**
 * Ingest + evaluate host agent reports.
 */
class ServerAgent {

	const MAX_PAYLOAD_BYTES = 512 * 1024;

	/**
	 * @param array<string,mixed> $data
	 * @return array{server_id:int}|null
	 */
	static public function applyReport(array $data): ?array {
		$key = trim(strval($data['agent_key_hash'] ?? ''));
		if ($key === '') {
			return null;
		}
		$server = \Servers::get(['agent_key_hash' => $key]);
		if (!$server || intval($server['status'] ?? 0) !== \Servers::STATUS_ENABLED) {
			return null;
		}
		$serverId = intval($server['id']);
		$encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if ($encoded === false) {
			$encoded = '{}';
		}
		if (strlen($encoded) > static::MAX_PAYLOAD_BYTES) {
			$encoded = substr($encoded, 0, static::MAX_PAYLOAD_BYTES);
		}

		$host = is_array($data['host'] ?? null) ? $data['host'] : [];
		$cpu = is_array($data['cpu'] ?? null) ? $data['cpu'] : [];
		$mem = is_array($data['memory'] ?? null) ? $data['memory'] : [];
		$disks = is_array($data['disks'] ?? null) ? $data['disks'] : [];
		$network = is_array($data['network'] ?? null) ? $data['network'] : [];

		$diskMax = 0.0;
		foreach ($disks as $d) {
			if (!is_array($d)) {
				continue;
			}
			$pct = floatval($d['used_pct'] ?? 0);
			if ($pct > $diskMax) {
				$diskMax = $pct;
			}
		}

		$ips = [];
		$netRx = 0;
		$netTx = 0;
		$ifaces = is_array($network['interfaces'] ?? null) ? $network['interfaces'] : [];
		foreach ($ifaces as $iface) {
			if (!is_array($iface)) {
				continue;
			}
			$netRx += intval($iface['rx_bps'] ?? 0);
			$netTx += intval($iface['tx_bps'] ?? 0);
			foreach (($iface['addrs'] ?? []) as $addr) {
				$addr = trim(strval($addr));
				if ($addr === '' || str_starts_with($addr, '127.') || str_starts_with($addr, '::1')) {
					continue;
				}
				// strip CIDR
				if (str_contains($addr, '/')) {
					$addr = explode('/', $addr, 2)[0];
				}
				$ips[$addr] = true;
			}
		}
		$ipsList = array_keys($ips);
		sort($ipsList);

		$now = time();
		$collectedAt = intval($data['collected_at'] ?? $now);
		if ($collectedAt < 1) {
			$collectedAt = $now;
		}

		$patch = [
			'id' => $serverId,
			'hostname' => mb_substr(strval($host['hostname'] ?? ''), 0, 255),
			'os' => mb_substr(trim(strval($host['platform'] ?? $host['os'] ?? '') . ' ' . strval($host['platform_version'] ?? '')), 0, 64),
			'arch' => mb_substr(strval($host['arch'] ?? ''), 0, 32),
			'agent_version' => mb_substr(strval($data['agent_version'] ?? ''), 0, 64),
			'last_seen_time' => $now,
			'last_success_time' => $now,
			'cpu_usage_pct' => round(floatval($cpu['usage_pct'] ?? 0), 2),
			'mem_used_pct' => round(floatval($mem['used_pct'] ?? 0), 2),
			'disk_used_pct_max' => round($diskMax, 2),
			'load1' => round(floatval($cpu['load1'] ?? 0), 2),
			'ips' => json_encode($ipsList, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
			'last_payload' => $encoded,
			'update_time' => $now,
			'_mode' => \DB\Common::CSMODE_UPDATE,
		];
		\Servers::save($patch);

		\ServerMetrics::save([
			'server_id' => $serverId,
			'collected_at' => $collectedAt,
			'cpu_usage_pct' => $patch['cpu_usage_pct'],
			'mem_used_pct' => $patch['mem_used_pct'],
			'disk_used_pct_max' => $patch['disk_used_pct_max'],
			'load1' => $patch['load1'],
			'net_rx_bps' => max(0, $netRx),
			'net_tx_bps' => max(0, $netTx),
			'_mode' => \DB\Common::CSMODE_INSERT,
		]);

		$authEvents = is_array($data['auth_events'] ?? null) ? $data['auth_events'] : [];
		foreach ($authEvents as $ev) {
			if (!is_array($ev)) {
				continue;
			}
			\ServerAuthEvents::save([
				'server_id' => $serverId,
				'event_time' => intval($ev['time'] ?? $now),
				'user' => mb_substr(strval($ev['user'] ?? ''), 0, 128),
				'from_host' => mb_substr(strval($ev['from'] ?? ''), 0, 255),
				'method' => mb_substr(strval($ev['method'] ?? ''), 0, 64),
				'success' => !empty($ev['success']) ? 1 : 0,
				'raw' => mb_substr(strval($ev['raw'] ?? ''), 0, 512),
				'create_time' => $now,
				'_mode' => \DB\Common::CSMODE_INSERT,
			]);
		}

		static::evaluateMonitors($serverId, array_merge($server, $patch), $data);
		return ['server_id' => $serverId];
	}

	/**
	 * @param array<string,mixed> $server
	 * @param array<string,mixed> $payload
	 */
	static public function evaluateMonitors(int $serverId, array $server, array $payload): void {
		$monitors = \Monitors::data([
			'server' => $serverId,
			'status' => \Monitors::STATUS_ENABLED,
		]) ?: [];
		$now = time();
		$notificationCheck = [];
		foreach ($monitors as $monitor) {
			$type = intval($monitor['type'] ?? -1);
			if (!in_array($type, [
				\Monitors::TYPE_SERVER_OFFLINE,
				\Monitors::TYPE_SERVER_CPU,
				\Monitors::TYPE_SERVER_MEMORY,
				\Monitors::TYPE_SERVER_DISK,
			], true)) {
				continue;
			}
			[$status, $statusCode, $error, $result] = static::evalOne($type, $monitor, $server, $payload, true);
			$log = static::writeLog(intval($monitor['id']), $status, $statusCode, $error, $result, $now);
			if ($log) {
				\MonitorsEvents::updateEvent($log);
				$notificationCheck[intval($monitor['id'])] = true;
			}
		}
		if ($notificationCheck) {
			\MonitorsEvents::checkMonitors($notificationCheck);
		}
	}

	/**
	 * Cron path: mark offline monitors for servers that stopped reporting.
	 */
	static public function evaluateOfflineAll(): void {
		$monitors = \Monitors::data([
			'type' => \Monitors::TYPE_SERVER_OFFLINE,
			'status' => \Monitors::STATUS_ENABLED,
		]) ?: [];
		$now = time();
		$notificationCheck = [];
		foreach ($monitors as $monitor) {
			$serverId = intval($monitor['server'] ?? 0);
			if ($serverId < 1) {
				continue;
			}
			$server = \Servers::get(['id' => $serverId]);
			if (!$server || intval($server['status'] ?? 0) !== \Servers::STATUS_ENABLED) {
				continue;
			}
			[$status, $statusCode, $error, $result] = static::evalOne(
				\Monitors::TYPE_SERVER_OFFLINE,
				$monitor,
				$server,
				[],
				false
			);
			$log = static::writeLog(intval($monitor['id']), $status, $statusCode, $error, $result, $now);
			if ($log) {
				\MonitorsEvents::updateEvent($log);
				$notificationCheck[intval($monitor['id'])] = true;
			}
		}
		if ($notificationCheck) {
			\MonitorsEvents::checkMonitors($notificationCheck);
		}
	}

	/**
	 * @param array<string,mixed> $monitor
	 * @param array<string,mixed> $server
	 * @param array<string,mixed> $payload
	 * @return array{0:int,1:int,2:string,3:string}
	 */
	static private function evalOne(int $type, array $monitor, array $server, array $payload, bool $justReported): array {
		$threshold = floatval($monitor['value'] ?? 0);
		if ($type === \Monitors::TYPE_SERVER_OFFLINE) {
			$minutes = $threshold > 0 ? $threshold : 3;
			$last = intval($server['last_seen_time'] ?? 0);
			$age = $justReported ? 0 : ($last > 0 ? (time() - $last) / 60.0 : 9999);
			$ok = $age <= $minutes;
			return [
				$ok ? \MonitoringLogs::STATUS_GOOD : \MonitoringLogs::STATUS_FAIL,
				$ok ? 200 : 503,
				$ok ? '' : 'server offline',
				strval(round($age, 2)),
			];
		}
		$cpu = floatval($server['cpu_usage_pct'] ?? 0);
		$mem = floatval($server['mem_used_pct'] ?? 0);
		$disk = floatval($server['disk_used_pct_max'] ?? 0);
		if ($payload) {
			if (isset($payload['cpu']['usage_pct'])) {
				$cpu = floatval($payload['cpu']['usage_pct']);
			}
			if (isset($payload['memory']['used_pct'])) {
				$mem = floatval($payload['memory']['used_pct']);
			}
		}
		$value = match ($type) {
			\Monitors::TYPE_SERVER_CPU => $cpu,
			\Monitors::TYPE_SERVER_MEMORY => $mem,
			\Monitors::TYPE_SERVER_DISK => $disk,
			default => 0.0,
		};
		$limit = $threshold > 0 ? $threshold : 90.0;
		$ok = $value < $limit;
		return [
			$ok ? \MonitoringLogs::STATUS_GOOD : \MonitoringLogs::STATUS_FAIL,
			$ok ? 200 : 500,
			$ok ? '' : 'threshold exceeded',
			strval(round($value, 2)),
		];
	}

	/** @return array<string,mixed>|null */
	static private function writeLog(int $monitorId, int $status, int $statusCode, string $error, string $result, int $now): ?array {
		if ($monitorId < 1) {
			return null;
		}
		$monitorLog = [
			'monitor_id' => $monitorId,
			'worker_id' => \Servers::SERVER_WORKER_ID,
			'status' => $status,
			'status_code' => $statusCode,
			'response_error' => mb_substr($error !== '' ? $error : ($result !== '' ? 'value='.$result : ''), 0, 255),
			'response_error_num' => $status === \MonitoringLogs::STATUS_GOOD ? 0 : 1,
			'total_time_us' => 0,
			'create_time' => $now,
			'update_time' => $now,
			'_mode' => \DB\Common::CSMODE_INSERT,
		];
		$id = \MonitoringLogs::save($monitorLog);
		$monitorLog['id'] = $id;
		$job = \MonitoringJobs::get([
			'monitor_id' => $monitorId,
			'worker_id' => \Servers::SERVER_WORKER_ID,
		]);
		if (!$job) {
			\MonitoringJobs::save([
				'monitor_id' => $monitorId,
				'worker_id' => \Servers::SERVER_WORKER_ID,
				'create_time' => $now,
				'update_time' => $now,
				'_mode' => \DB\Common::CSMODE_INSERT,
			]);
		} else {
			\MonitoringJobs::save([
				'id' => intval($job['id']),
				'update_time' => $now,
				'_mode' => \DB\Common::CSMODE_UPDATE,
			]);
		}
		return is_array($id) ? $monitorLog : (\MonitoringLogs::get(['id' => $id]) ?: $monitorLog);
	}

	static public function ensureVirtualJob(int $monitorId): void {
		if ($monitorId < 1) {
			return;
		}
		$job = \MonitoringJobs::get([
			'monitor_id' => $monitorId,
			'worker_id' => \Servers::SERVER_WORKER_ID,
		]);
		if ($job) {
			return;
		}
		\MonitoringJobs::save([
			'monitor_id' => $monitorId,
			'worker_id' => \Servers::SERVER_WORKER_ID,
			'create_time' => time(),
			'update_time' => time(),
			'_mode' => \DB\Common::CSMODE_INSERT,
		]);
	}

	static public function clearRealJobs(int $monitorId): void {
		if ($monitorId < 1) {
			return;
		}
		foreach (\MonitoringJobs::data(['monitor_id' => $monitorId]) ?: [] as $job) {
			if (intval($job['worker_id'] ?? 0) === \Servers::SERVER_WORKER_ID) {
				continue;
			}
			\MonitoringJobs::delete(['id' => intval($job['id'])]);
		}
	}
}

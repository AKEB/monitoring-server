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
		$temperatures = is_array($data['temperatures'] ?? null) ? $data['temperatures'] : [];
		$docker = is_array($data['docker'] ?? null) ? $data['docker'] : [];

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

		$tempMax = null;
		foreach ($temperatures as $t) {
			if (!is_array($t)) {
				continue;
			}
			$tc = floatval($t['temp_c'] ?? 0);
			if ($tempMax === null || $tc > $tempMax) {
				$tempMax = $tc;
			}
		}
		$uptimeSec = max(0, intval($host['uptime_sec'] ?? 0));
		$details = static::buildMetricDetails($temperatures, $docker);

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

		$agentVersion = mb_substr(strval($data['agent_version'] ?? ''), 0, 64);
		$updateError = mb_substr(trim(strval($data['update_error'] ?? '')), 0, 512);
		$patch = [
			'id' => $serverId,
			'hostname' => mb_substr(strval($host['hostname'] ?? ''), 0, 255),
			'os' => mb_substr(trim(strval($host['platform'] ?? $host['os'] ?? '') . ' ' . strval($host['platform_version'] ?? '')), 0, 64),
			'arch' => mb_substr(strval($host['arch'] ?? ''), 0, 32),
			'agent_version' => $agentVersion,
			'last_seen_time' => $now,
			'last_success_time' => $now,
			'cpu_usage_pct' => round(floatval($cpu['usage_pct'] ?? 0), 2),
			'mem_used_pct' => round(floatval($mem['used_pct'] ?? 0), 2),
			'disk_used_pct_max' => round($diskMax, 2),
			'load1' => round(floatval($cpu['load1'] ?? 0), 2),
			'temp_c_max' => $tempMax === null ? null : round($tempMax, 2),
			'uptime_sec' => $uptimeSec,
			'ips' => json_encode($ipsList, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
			'last_payload' => $encoded,
			'update_time' => $now,
			'_mode' => \DB\Common::CSMODE_UPDATE,
		];
		if ($updateError !== '') {
			$patch['update_error'] = $updateError;
		} else {
			$desired = \AgentUpdate::desiredForServer($server);
			if ($desired !== '' && \AgentUpdate::versionsEqual($agentVersion, $desired)) {
				$patch['update_error'] = '';
				$patch['update_requested_at'] = 0;
			}
		}
		\Servers::save($patch);
		$server = array_merge($server, $patch);

		\ServerMetrics::save([
			'server_id' => $serverId,
			'collected_at' => $collectedAt,
			'cpu_usage_pct' => $patch['cpu_usage_pct'],
			'mem_used_pct' => $patch['mem_used_pct'],
			'disk_used_pct_max' => $patch['disk_used_pct_max'],
			'load1' => $patch['load1'],
			'net_rx_bps' => max(0, $netRx),
			'net_tx_bps' => max(0, $netTx),
			'temp_c_max' => $patch['temp_c_max'],
			'uptime_sec' => $uptimeSec,
			'details' => $details,
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

		static::evaluateMonitors($serverId, $server, $data);

		$response = ['server_id' => $serverId];
		$updateCmd = \AgentUpdate::buildCommand($server, $data, false);
		if ($updateCmd) {
			\AgentUpdate::markRequested($server);
			$response['update'] = $updateCmd;
		}
		return $response;
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
			if (!\Monitors::is_server_type($type)) {
				continue;
			}
			$monitorId = intval($monitor['id'] ?? 0);
			$repeat = max(1, intval($monitor['repeat_seconds'] ?? 60));
			$job = \MonitoringJobs::get([
				'monitor_id' => $monitorId,
				'worker_id' => \Servers::SERVER_WORKER_ID,
			]);
			// Agents push every ~15s; ovals are 1 slot per repeat_seconds.
			// Throttle by calendar bucket (not elapsed seconds): a write at :50
			// plus "wait 60s" used to land the next log in :00 of minute+2 and
			// leave minute+1 empty (grey / No Data) even though reports arrived.
			$bucket = static::monitorLogBucket($now, $repeat);
			if (!static::shouldWriteMonitorLog($job ?: null, $now, $repeat)) {
				continue;
			}
			[$status, $statusCode, $error, $result] = static::evalOne($type, $monitor, $server, $payload, true);
			$log = static::writeLog($monitorId, $status, $statusCode, $error, $result, $bucket);
			if ($log) {
				\MonitorsEvents::updateEvent($log);
				$notificationCheck[$monitorId] = true;
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
		$src = $payload;
		if (!$src) {
			$decoded = @json_decode(strval($server['last_payload'] ?? ''), true);
			$src = is_array($decoded) ? $decoded : [];
		}
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
		if ($type === \Monitors::TYPE_SERVER_CONTAINER) {
			return static::evalContainer(trim(strval($monitor['container'] ?? '')), $src);
		}
		if ($type === \Monitors::TYPE_SERVER_SYSTEMD) {
			return static::evalSystemd(trim(strval($monitor['container'] ?? '')), $src);
		}
		if ($type === \Monitors::TYPE_SERVER_PORT) {
			$port = intval($monitor['port'] ?? 0);
			$protocol = \Monitors::normalize_port_protocol($monitor['port_protocol'] ?? 'tcp');
			return static::evalPortListening($port, $protocol, $src);
		}
		if (\Monitors::is_server_net_type($type)) {
			$bps = static::netBpsFromPayload($src, $type === \Monitors::TYPE_SERVER_NET_RX ? 'rx' : 'tx');
			$mbps = $bps * 8.0 / 1000000.0;
			$limit = $threshold > 0 ? $threshold : 100.0;
			$ok = $mbps < $limit;
			$dir = $type === \Monitors::TYPE_SERVER_NET_RX ? 'download' : 'upload';
			return [
				$ok ? \MonitoringLogs::STATUS_GOOD : \MonitoringLogs::STATUS_FAIL,
				$ok ? 200 : 500,
				$ok ? '' : 'network '.$dir.' threshold exceeded',
				strval(round($mbps, 2)),
			];
		}
		if ($type === \Monitors::TYPE_SERVER_TEMP) {
			$sensor = trim(strval($monitor['container'] ?? ''));
			$value = static::tempCFromPayload($src, $sensor, floatval($server['temp_c_max'] ?? 0));
			$limit = $threshold > 0 ? $threshold : 80.0;
			$ok = $value < $limit;
			$failMsg = $sensor !== '' ? 'temperature threshold exceeded ('.$sensor.')' : 'temperature threshold exceeded';
			return [
				$ok ? \MonitoringLogs::STATUS_GOOD : \MonitoringLogs::STATUS_FAIL,
				$ok ? 200 : 500,
				$ok ? '' : $failMsg,
				strval(round($value, 2)),
			];
		}
		if ($type === \Monitors::TYPE_SERVER_UPTIME) {
			$host = is_array($src['host'] ?? null) ? $src['host'] : [];
			$uptimeSec = intval($host['uptime_sec'] ?? ($server['uptime_sec'] ?? 0));
			$uptimeMin = $uptimeSec / 60.0;
			$limit = $threshold > 0 ? $threshold : 5.0;
			$ok = $uptimeMin >= $limit;
			return [
				$ok ? \MonitoringLogs::STATUS_GOOD : \MonitoringLogs::STATUS_FAIL,
				$ok ? 200 : 500,
				$ok ? '' : 'recent reboot',
				strval(round($uptimeMin, 2)),
			];
		}
		if (\Monitors::is_server_container_metric_type($type)) {
			$name = trim(strval($monitor['container'] ?? ''));
			[$okFound, $value, $err] = static::containerMetricFromPayload($src, $name, $type);
			if (!$okFound) {
				return [\MonitoringLogs::STATUS_FAIL, 404, $err, '0'];
			}
			$limit = $threshold > 0 ? $threshold : 90.0;
			$ok = $value < $limit;
			return [
				$ok ? \MonitoringLogs::STATUS_GOOD : \MonitoringLogs::STATUS_FAIL,
				$ok ? 200 : 500,
				$ok ? '' : 'threshold exceeded ('.$name.')',
				strval(round($value, 2)),
			];
		}
		if (\Monitors::is_server_systemd_metric_type($type)) {
			$name = trim(strval($monitor['container'] ?? ''));
			[$okFound, $value, $err] = static::systemdMetricFromPayload($src, $name, $type);
			if (!$okFound) {
				return [\MonitoringLogs::STATUS_FAIL, 404, $err, '0'];
			}
			if ($type === \Monitors::TYPE_SERVER_SYSTEMD_MEMORY) {
				// Chart/value in MB; threshold is MB.
				$valueMb = $value / (1024 * 1024);
				$limit = $threshold > 0 ? $threshold : 512.0;
				$ok = $valueMb < $limit;
				return [
					$ok ? \MonitoringLogs::STATUS_GOOD : \MonitoringLogs::STATUS_FAIL,
					$ok ? 200 : 500,
					$ok ? '' : 'threshold exceeded ('.$name.')',
					strval(round($valueMb, 2)),
				];
			}
			$limit = $threshold > 0 ? $threshold : 90.0;
			$ok = $value < $limit;
			return [
				$ok ? \MonitoringLogs::STATUS_GOOD : \MonitoringLogs::STATUS_FAIL,
				$ok ? 200 : 500,
				$ok ? '' : 'threshold exceeded ('.$name.')',
				strval(round($value, 2)),
			];
		}
		$cpu = floatval($server['cpu_usage_pct'] ?? 0);
		$mem = floatval($server['mem_used_pct'] ?? 0);
		$disk = floatval($server['disk_used_pct_max'] ?? 0);
		$mount = trim(strval($monitor['container'] ?? ''));
		if ($src) {
			if (isset($src['cpu']['usage_pct'])) {
				$cpu = floatval($src['cpu']['usage_pct']);
			}
			if (isset($src['memory']['used_pct'])) {
				$mem = floatval($src['memory']['used_pct']);
			}
			if ($type === \Monitors::TYPE_SERVER_DISK) {
				$disk = static::diskUsedPctFromPayload($src, $mount, $disk);
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
		$failMsg = 'threshold exceeded';
		if ($type === \Monitors::TYPE_SERVER_DISK && $mount !== '') {
			$failMsg = 'threshold exceeded ('.$mount.')';
		}
		return [
			$ok ? \MonitoringLogs::STATUS_GOOD : \MonitoringLogs::STATUS_FAIL,
			$ok ? 200 : 500,
			$ok ? '' : $failMsg,
			strval(round($value, 2)),
		];
	}

	/**
	 * Compact JSON for server_metrics charts (temps + docker cpu/mem).
	 *
	 * @param list<mixed> $temperatures
	 * @param array<string,mixed> $docker
	 */
	static public function buildMetricDetails(array $temperatures, array $docker): ?string {
		$temps = [];
		foreach ($temperatures as $t) {
			if (!is_array($t)) {
				continue;
			}
			$sensor = trim(strval($t['sensor'] ?? ''));
			if ($sensor === '') {
				continue;
			}
			$temps[$sensor] = round(floatval($t['temp_c'] ?? 0), 2);
		}
		$dockerCpu = [];
		$dockerMem = [];
		$containers = is_array($docker['containers'] ?? null) ? $docker['containers'] : [];
		foreach ($containers as $c) {
			if (!is_array($c)) {
				continue;
			}
			if (strtolower(trim(strval($c['state'] ?? ''))) !== 'running') {
				continue;
			}
			$name = trim(strval($c['name'] ?? ''));
			if ($name === '') {
				continue;
			}
			$dockerCpu[$name] = round(floatval($c['cpu_pct'] ?? 0), 2);
			$dockerMem[$name] = intval($c['mem_bytes'] ?? 0);
		}
		$payload = [];
		if ($temps) {
			$payload['temps'] = $temps;
		}
		if ($dockerCpu) {
			$payload['docker_cpu'] = $dockerCpu;
		}
		if ($dockerMem) {
			$payload['docker_mem'] = $dockerMem;
		}
		if (!$payload) {
			return null;
		}
		$encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		return $encoded === false ? null : $encoded;
	}

	/**
	 * @param array<string,mixed> $payload
	 */
	static public function tempCFromPayload(array $payload, string $sensor, float $fallback): float {
		$temps = is_array($payload['temperatures'] ?? null) ? $payload['temperatures'] : [];
		$max = null;
		foreach ($temps as $t) {
			if (!is_array($t)) {
				continue;
			}
			$name = trim(strval($t['sensor'] ?? ''));
			$tc = floatval($t['temp_c'] ?? 0);
			if ($sensor !== '' && $name === $sensor) {
				return $tc;
			}
			if ($max === null || $tc > $max) {
				$max = $tc;
			}
		}
		if ($sensor !== '') {
			return 999.0; // missing sensor → fail-friendly
		}
		return $max === null ? $fallback : $max;
	}

	/**
	 * @param array<string,mixed> $payload
	 * @return array{0:bool,1:float,2:string}
	 */
	static public function containerMetricFromPayload(array $payload, string $name, int $type): array {
		if ($name === '') {
			return [false, 0.0, 'container not set'];
		}
		$docker = is_array($payload['docker'] ?? null) ? $payload['docker'] : [];
		if (empty($docker['available'])) {
			return [false, 0.0, 'docker unavailable'];
		}
		foreach ((is_array($docker['containers'] ?? null) ? $docker['containers'] : []) as $c) {
			if (!is_array($c) || strval($c['name'] ?? '') !== $name) {
				continue;
			}
			if ($type === \Monitors::TYPE_SERVER_CONTAINER_CPU) {
				return [true, floatval($c['cpu_pct'] ?? 0), ''];
			}
			return [true, floatval($c['mem_pct'] ?? 0), ''];
		}
		return [false, 0.0, 'container not found: '.$name];
	}

	/**
	 * @param array<string,mixed> $payload
	 * @return array{0:bool,1:float,2:string}
	 */
	static public function systemdMetricFromPayload(array $payload, string $name, int $type): array {
		if ($name === '') {
			return [false, 0.0, 'unit not set'];
		}
		$systemd = is_array($payload['systemd'] ?? null) ? $payload['systemd'] : [];
		if (empty($systemd['available'])) {
			return [false, 0.0, 'systemd unavailable'];
		}
		foreach ((is_array($systemd['units'] ?? null) ? $systemd['units'] : []) as $u) {
			if (!is_array($u) || strval($u['name'] ?? '') !== $name) {
				continue;
			}
			if ($type === \Monitors::TYPE_SERVER_SYSTEMD_CPU) {
				return [true, floatval($u['cpu_pct'] ?? 0), ''];
			}
			return [true, floatval($u['mem_bytes'] ?? 0), ''];
		}
		return [false, 0.0, 'unit not found: '.$name];
	}

	/**
	 * @param array<string,mixed> $payload
	 * @return array{0:int,1:int,2:string,3:string}
	 */
	static private function evalSystemd(string $name, array $payload): array {
		if ($name === '') {
			return [\MonitoringLogs::STATUS_FAIL, 404, 'unit not set', '0'];
		}
		$systemd = is_array($payload['systemd'] ?? null) ? $payload['systemd'] : [];
		if (empty($systemd['available'])) {
			return [\MonitoringLogs::STATUS_FAIL, 503, 'systemd unavailable', '0'];
		}
		$found = null;
		foreach ((is_array($systemd['units'] ?? null) ? $systemd['units'] : []) as $u) {
			if (!is_array($u)) {
				continue;
			}
			if (strval($u['name'] ?? '') === $name) {
				$found = $u;
				break;
			}
		}
		if (!$found) {
			return [\MonitoringLogs::STATUS_FAIL, 404, 'unit not found: '.$name, '0'];
		}
		$active = strtolower(trim(strval($found['active_state'] ?? '')));
		$sub = strtolower(trim(strval($found['sub_state'] ?? '')));
		// active+running for daemons; active+exited OK for oneshot units.
		$ok = $active === 'active' && ($sub === 'running' || $sub === 'exited');
		$statusText = trim($active.($sub !== '' ? '/'.$sub : ''));
		return [
			$ok ? \MonitoringLogs::STATUS_GOOD : \MonitoringLogs::STATUS_FAIL,
			$ok ? 200 : 500,
			$ok ? '' : 'unit '.$name.' is '.$statusText,
			$statusText !== '' ? $statusText : 'unknown',
		];
	}

	/**
	 * Sum interface throughput from agent payload (bytes/sec).
	 *
	 * @param array<string,mixed> $payload
	 */
	static private function netBpsFromPayload(array $payload, string $direction): float {
		$network = is_array($payload['network'] ?? null) ? $payload['network'] : [];
		$ifaces = is_array($network['interfaces'] ?? null) ? $network['interfaces'] : [];
		$key = $direction === 'tx' ? 'tx_bps' : 'rx_bps';
		$sum = 0.0;
		foreach ($ifaces as $iface) {
			if (!is_array($iface)) {
				continue;
			}
			$sum += floatval($iface[$key] ?? 0);
		}
		return max(0.0, $sum);
	}

	/**
	 * @param array<string,mixed> $payload
	 * @return array{0:int,1:int,2:string,3:string}
	 */
	static private function evalContainer(string $name, array $payload): array {
		if ($name === '') {
			return [\MonitoringLogs::STATUS_FAIL, 404, 'container not set', '0'];
		}
		$docker = is_array($payload['docker'] ?? null) ? $payload['docker'] : [];
		if (empty($docker['available'])) {
			return [\MonitoringLogs::STATUS_FAIL, 503, 'docker unavailable', '0'];
		}
		$containers = is_array($docker['containers'] ?? null) ? $docker['containers'] : [];
		$found = null;
		foreach ($containers as $c) {
			if (!is_array($c)) {
				continue;
			}
			if (strval($c['name'] ?? '') === $name) {
				$found = $c;
				break;
			}
		}
		if (!$found) {
			return [\MonitoringLogs::STATUS_FAIL, 404, 'container not found: '.$name, '0'];
		}
		$state = strtolower(trim(strval($found['state'] ?? '')));
		$ok = $state === 'running';
		// Use stable Docker state (running/exited/…), not Status text like
		// "Exited (0) 5 minutes ago" — that churned every minute and spammed events.
		$failMsg = $state !== ''
			? 'container '.$name.' is not running ('.$state.')'
			: 'container '.$name.' is not running';
		return [
			$ok ? \MonitoringLogs::STATUS_GOOD : \MonitoringLogs::STATUS_FAIL,
			$ok ? 200 : 500,
			$ok ? '' : $failMsg,
			$state !== '' ? $state : 'unknown',
		];
	}

	/**
	 * @param array<string,mixed> $payload
	 * @return array{0:int,1:int,2:string,3:string}
	 */
	static public function evalPortListening(int $port, string $protocol, array $payload): array {
		if ($port < 1 || $port > 65535) {
			return [\MonitoringLogs::STATUS_FAIL, 404, 'port not set', '0'];
		}
		$protocol = \Monitors::normalize_port_protocol($protocol);
		$listeners = is_array($payload['listeners'] ?? null) ? $payload['listeners'] : [];
		foreach ($listeners as $l) {
			if (!is_array($l)) {
				continue;
			}
			$lPort = intval($l['port'] ?? 0);
			$lProto = \Monitors::normalize_port_protocol($l['protocol'] ?? '');
			if ($lPort === $port && $lProto === $protocol) {
				$ip = trim(strval($l['ip'] ?? ''));
				$result = $protocol.'/'.$port.($ip !== '' ? '@'.$ip : '');
				return [\MonitoringLogs::STATUS_GOOD, 200, '', $result];
			}
		}
		return [
			\MonitoringLogs::STATUS_FAIL,
			503,
			'port not listening',
			$protocol.'/'.$port,
		];
	}

	/**
	 * @param array<string,mixed> $payload
	 */
	static private function diskUsedPctFromPayload(array $payload, string $mount, float $fallback): float {
		$disks = is_array($payload['disks'] ?? null) ? $payload['disks'] : [];
		if ($mount !== '') {
			foreach ($disks as $d) {
				if (!is_array($d)) {
					continue;
				}
				if (strval($d['mount'] ?? '') === $mount) {
					return floatval($d['used_pct'] ?? 0);
				}
			}
			// selected mount missing from report — treat as full / fail-friendly
			return 100.0;
		}
		$max = 0.0;
		foreach ($disks as $d) {
			if (!is_array($d)) {
				continue;
			}
			$pct = floatval($d['used_pct'] ?? 0);
			if ($pct > $max) {
				$max = $pct;
			}
		}
		return $disks ? $max : $fallback;
	}

	/** Floor unix time into a monitor log slot start. */
	static public function monitorLogBucket(int $unixTime, int $repeatSeconds): int {
		$repeat = max(1, $repeatSeconds);
		return intdiv($unixTime, $repeat) * $repeat;
	}

	/**
	 * True when this agent report should insert a MonitoringLogs row for the
	 * current calendar bucket (at most one oval per repeat_seconds).
	 *
	 * @param array<string,mixed>|null $job
	 */
	static public function shouldWriteMonitorLog(?array $job, int $now, int $repeatSeconds): bool {
		$bucket = static::monitorLogBucket($now, $repeatSeconds);
		if (!$job) {
			return true;
		}
		$lastBucket = static::monitorLogBucket(intval($job['update_time'] ?? 0), $repeatSeconds);
		return $lastBucket < $bucket;
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
			'response_error' => \MonitoringLogs::formatMetricLogMessage($result, $error),
			'response_error_num' => $status === \MonitoringLogs::STATUS_GOOD ? 0 : 1,
			'total_time_us' => 0,
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

	static public function removeVirtualJob(int $monitorId): void {
		if ($monitorId < 1) {
			return;
		}
		$job = \MonitoringJobs::get([
			'monitor_id' => $monitorId,
			'worker_id' => \Servers::SERVER_WORKER_ID,
		]);
		if ($job && isset($job['id']) && $job['id']) {
			\MonitoringJobs::delete($job['id']);
		}
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

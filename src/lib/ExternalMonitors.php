<?php

/**
 * Push-based External monitors: status flips via public GET links and is
 * heartbeat-written into monitoring_logs every minute.
 */
class ExternalMonitors {
	const STATUS_UNSET = -1;

	const PARAM_OK = 'ok';
	const PARAM_FAIL = 'fail';

	static public function generateHash(): string {
		do {
			$hash = bin2hex(random_bytes(32));
			$exists = \Monitors::get(['external_hash' => $hash]);
		} while ($exists);
		return $hash;
	}

	static public function ensureVirtualJob(int $monitor_id): void {
		if ($monitor_id < 1) {
			return;
		}
		$job = \MonitoringJobs::get([
			'monitor_id' => $monitor_id,
			'worker_id' => \Monitors::EXTERNAL_WORKER_ID,
		]);
		if ($job) {
			return;
		}
		\MonitoringJobs::save([
			'monitor_id' => $monitor_id,
			'worker_id' => \Monitors::EXTERNAL_WORKER_ID,
			'create_time' => time(),
			'update_time' => time(),
			'_mode' => \DB\Common::CSMODE_INSERT,
		]);
	}

	static public function removeVirtualJob(int $monitor_id): void {
		if ($monitor_id < 1) {
			return;
		}
		$job = \MonitoringJobs::get([
			'monitor_id' => $monitor_id,
			'worker_id' => \Monitors::EXTERNAL_WORKER_ID,
		]);
		if ($job && isset($job['id']) && $job['id']) {
			\MonitoringJobs::delete($job['id']);
		}
	}

	static public function clearRealJobs(int $monitor_id): void {
		if ($monitor_id < 1) {
			return;
		}
		$jobs = \MonitoringJobs::data(['monitor_id' => $monitor_id]);
		if (!$jobs) {
			return;
		}
		foreach ($jobs as $job) {
			if (intval($job['worker_id']) === \Monitors::EXTERNAL_WORKER_ID) {
				continue;
			}
			if (isset($job['id']) && $job['id']) {
				\MonitoringJobs::delete($job['id']);
			}
		}
	}

	static public function parseStatusParam(?string $raw): ?int {
		if ($raw === null) {
			return null;
		}
		$value = strtolower(trim($raw));
		if ($value === self::PARAM_OK || $value === 'good' || $value === 'up' || $value === '1') {
			return \MonitoringLogs::STATUS_GOOD;
		}
		if ($value === self::PARAM_FAIL || $value === 'error' || $value === 'down' || $value === '0') {
			return \MonitoringLogs::STATUS_FAIL;
		}
		return null;
	}

	static public function baseUrl(): string {
		$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
			|| (isset($_SERVER['SERVER_PORT']) && intval($_SERVER['SERVER_PORT']) === 443)
			|| (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
		$scheme = $https ? 'https' : 'http';
		$host = strval($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost');
		return $scheme.'://'.$host;
	}

	static public function statusUrl(string $hash, string $status_param): string {
		$hash = trim($hash);
		return static::baseUrl().'/api/external/'.rawurlencode($hash).'/?status='.rawurlencode($status_param);
	}

	static public function urlsForMonitor(array $monitor): array {
		$hash = trim(strval($monitor['external_hash'] ?? ''));
		if ($hash === '') {
			return ['ok' => '', 'fail' => ''];
		}
		return [
			'ok' => static::statusUrl($hash, self::PARAM_OK),
			'fail' => static::statusUrl($hash, self::PARAM_FAIL),
		];
	}

	/**
	 * Flip desired status from a public callback and persist a log row immediately.
	 */
	static public function applyCallback(string $hash, int $status): array|false {
		$hash = trim($hash);
		if ($hash === '' || strlen($hash) !== 64 || !ctype_xdigit($hash)) {
			return false;
		}
		if ($status !== \MonitoringLogs::STATUS_GOOD && $status !== \MonitoringLogs::STATUS_FAIL) {
			return false;
		}
		$monitor = \Monitors::get([
			'external_hash' => $hash,
			'type' => \Monitors::TYPE_EXTERNAL,
		]);
		if (!$monitor || !isset($monitor['id'])) {
			return false;
		}
		if (intval($monitor['status']) !== \Monitors::STATUS_ENABLED) {
			return false;
		}
		if (!\Monitors::is_enable(intval($monitor['id']))) {
			return false;
		}

		$save = [
			'id' => intval($monitor['id']),
			'external_status' => $status,
			'update_time' => time(),
			'_mode' => \DB\Common::CSMODE_UPDATE,
		];
		\Monitors::save($save);
		$monitor['external_status'] = $status;

		static::ensureVirtualJob(intval($monitor['id']));
		$result = static::writeStatusLog($monitor, $status, true);
		if ($result) {
			\MonitorsEvents::updateEvent($result);
			\MonitorsEvents::checkMonitors([intval($monitor['id']) => true]);
		}
		return [
			'monitor_id' => intval($monitor['id']),
			'status' => $status,
		];
	}

	/**
	 * Write one monitoring_logs row for the virtual external worker.
	 */
	static public function writeStatusLog(array $monitor, int $status, bool $force = false): array|false {
		$monitor_id = intval($monitor['id'] ?? 0);
		if ($monitor_id < 1) {
			return false;
		}
		if ($status !== \MonitoringLogs::STATUS_GOOD && $status !== \MonitoringLogs::STATUS_FAIL) {
			return false;
		}
		if (intval($monitor['status'] ?? 0) !== \Monitors::STATUS_ENABLED) {
			return false;
		}
		if (!\Monitors::is_enable($monitor_id)) {
			return false;
		}
		$maintenances = \MonitoringMaintenances::getMaintenancesForMonitor($monitor_id);
		if ($maintenances) {
			return false;
		}

		$job = \MonitoringJobs::get([
			'monitor_id' => $monitor_id,
			'worker_id' => \Monitors::EXTERNAL_WORKER_ID,
		]);
		if (!$job) {
			static::ensureVirtualJob($monitor_id);
			$job = \MonitoringJobs::get([
				'monitor_id' => $monitor_id,
				'worker_id' => \Monitors::EXTERNAL_WORKER_ID,
			]);
		}
		if (!$job) {
			return false;
		}

		$now = time();
		if (!$force && intval($job['update_time'] ?? 0) + 30 >= $now) {
			// Avoid duplicate minute ticks if cron overlaps.
			return false;
		}

		\MonitoringJobs::save([
			'id' => $job['id'],
			'update_time' => $now,
			'_mode' => \DB\Common::CSMODE_UPDATE,
		]);

		$error = '';
		$status_code = 200;
		if ($status === \MonitoringLogs::STATUS_FAIL) {
			$error = 'External status: fail';
			$status_code = 500;
		}

		$params = [
			'monitor_id' => $monitor_id,
			'worker_id' => \Monitors::EXTERNAL_WORKER_ID,
			'job_id' => intval($job['id']),
			'status' => $status,
			'update_time' => $now,
			'status_code' => $status_code,
			'response_error_num' => 0,
			'response_error' => $error,
			'total_time_us' => 0,
			'cert_expire' => 0,
		];
		$params['id'] = \MonitoringLogs::save($params);
		if (!$params['id']) {
			return false;
		}
		return $params;
	}

	/**
	 * Heartbeat: persist current external_status for all enabled External monitors.
	 */
	static public function heartbeatAll(): int {
		$written = 0;
		$monitors = \Monitors::data(
			false,
			sql_pholder(
				' AND `type` = ? AND `status` = ? AND `external_status` IN (?@)',
				\Monitors::TYPE_EXTERNAL,
				\Monitors::STATUS_ENABLED,
				[\MonitoringLogs::STATUS_GOOD, \MonitoringLogs::STATUS_FAIL]
			),
			'`id`, `status`, `type`, `external_status`, `parent_id`'
		);
		if (!$monitors) {
			return 0;
		}
		$notification_check = [];
		foreach ($monitors as $monitor) {
			if (!\Monitors::is_enable(intval($monitor['id']))) {
				continue;
			}
			$result = static::writeStatusLog($monitor, intval($monitor['external_status']), false);
			if (!$result) {
				continue;
			}
			$written++;
			\MonitorsEvents::updateEvent($result);
			$notification_check[intval($monitor['id'])] = true;
		}
		if ($notification_check) {
			\MonitorsEvents::checkMonitors($notification_check);
		}
		return $written;
	}

	static public function workerTitle(): string {
		return \T::Monitor_Type_External();
	}
}

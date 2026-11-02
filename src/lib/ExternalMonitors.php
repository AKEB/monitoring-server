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

	/**
	 * Optional alert text from HTTP header Log or query ?log= / ?message=.
	 * Returns null when the caller did not send any of these.
	 */
	static public function extractLogFromRequest(): ?string {
		$headers = [];
		if (function_exists('getallheaders')) {
			$headers = getallheaders() ?: [];
		}
		foreach ($headers as $key => $value) {
			if (strcasecmp(strval($key), 'Log') === 0) {
				return static::sanitizeLog(strval($value));
			}
		}
		if (isset($_SERVER['HTTP_LOG'])) {
			return static::sanitizeLog(strval($_SERVER['HTTP_LOG']));
		}
		if (array_key_exists('log', $_GET)) {
			return static::sanitizeLog(strval($_GET['log']));
		}
		if (array_key_exists('message', $_GET)) {
			return static::sanitizeLog(strval($_GET['message']));
		}
		return null;
	}

	static public function sanitizeLog(string $raw): string {
		$log = trim($raw);
		if ($log === '') {
			return '';
		}
		// Public callback input ends up in HTML notifications (Telegram HTML, etc.).
		$log = strip_tags($log);
		$log = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $log) ?? $log;
		$log = preg_replace("/\r\n?/", "\n", $log) ?? $log;
		if (mb_strlen($log) > 2000) {
			$log = mb_substr($log, 0, 2000);
		}
		return $log;
	}

	/**
	 * Fail description for monitoring_logs.response_error (CHAR 255) and alerts.
	 * Custom text is prefixed with "Log:" (same style as "Alert:" in notifications).
	 */
	static public function formatFailError(string $log): string {
		$log = trim($log);
		if ($log === '') {
			return 'External status: fail';
		}
		// Keep plain text short so HTML-escaped form fits CHAR(255).
		if (mb_strlen($log) > 120) {
			$log = mb_substr($log, 0, 117).'...';
		}
		// Escape for HTML notifications (Telegram/MAX parse_mode HTML).
		return 'Log: '.htmlspecialchars($log, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
	 *
	 * @param string|null $log null = leave previous external_log; on OK always cleared
	 */
	static public function applyCallback(string $hash, int $status, ?string $log = null): array|false {
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
		if ($status === \MonitoringLogs::STATUS_GOOD) {
			$save['external_log'] = '';
			$monitor['external_log'] = '';
		} elseif ($log !== null) {
			$save['external_log'] = $log;
			$monitor['external_log'] = $log;
		}
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
			'log' => strval($monitor['external_log'] ?? ''),
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
			$error = static::formatFailError(strval($monitor['external_log'] ?? ''));
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
			'`id`, `status`, `type`, `external_status`, `external_log`, `parent_id`'
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

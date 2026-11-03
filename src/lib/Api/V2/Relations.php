<?php

namespace Api\V2;

/**
 * Sync join-table links (workers/notify/dockers) like admin Edit controllers.
 */
class Relations {

	/**
	 * @param list<int|string> $desiredIds
	 * @param callable(int):bool $canRead
	 * @return array<int,int> id => 1 add, -1 remove
	 */
	static public function diffLinks(array $existingIds, array $desiredIds, callable $canRead): array {
		$existing = [];
		foreach ($existingIds as $id) {
			$id = intval($id);
			if ($id > 0) {
				$existing[$id] = true;
			}
		}
		$desired = [];
		foreach ($desiredIds as $id) {
			$id = intval($id);
			if ($id > 0 && $canRead($id)) {
				$desired[$id] = true;
			}
		}
		$diff = [];
		foreach ($existing as $id => $_) {
			if (!$canRead($id)) {
				continue;
			}
			if (!isset($desired[$id])) {
				$diff[$id] = -1;
			}
		}
		foreach ($desired as $id => $_) {
			if (!isset($existing[$id])) {
				$diff[$id] = 1;
			}
		}
		return $diff;
	}

	/**
	 * @param array<int,int> $diff
	 */
	static public function applyMonitorWorkers(int $monitorId, array $diff): void {
		foreach ($diff as $workerId => $status) {
			$workerId = intval($workerId);
			if ($workerId === \Monitors::EXTERNAL_WORKER_ID) {
				continue;
			}
			if ($status < 0) {
				$old = \MonitoringJobs::get(['monitor_id' => $monitorId, 'worker_id' => $workerId]);
				if (!empty($old['id'])) {
					\MonitoringJobs::delete(intval($old['id']));
					$logId = \Logs::delete_log(\MonitoringJobs::LOGS_OBJECT, intval($old['id']), $old);
					\Logs::add_tag($logId, \Monitors::LOGS_OBJECT, $monitorId);
					\Logs::add_tag($logId, \Workers::LOGS_OBJECT, $workerId);
				}
				continue;
			}
			$job = [
				'monitor_id' => $monitorId,
				'worker_id' => $workerId,
				'create_time' => time(),
				'update_time' => time(),
			];
			$job['id'] = \MonitoringJobs::save($job);
			$logId = \Logs::create_log(\MonitoringJobs::LOGS_OBJECT, intval($job['id']), $job);
			\Logs::add_tag($logId, \Monitors::LOGS_OBJECT, $monitorId);
			\Logs::add_tag($logId, \Workers::LOGS_OBJECT, $workerId);
		}
	}

	/**
	 * @param array<int,int> $diff
	 */
	static public function applyMonitorNotify(int $monitorId, array $diff): void {
		foreach ($diff as $notifyId => $status) {
			$notifyId = intval($notifyId);
			if ($status < 0) {
				$old = \MonitoringNotify::get(['monitor_id' => $monitorId, 'notify_id' => $notifyId]);
				if (!empty($old['id'])) {
					\MonitoringNotify::delete(intval($old['id']));
					$logId = \Logs::delete_log(\MonitoringNotify::LOGS_OBJECT, intval($old['id']), $old);
					\Logs::add_tag($logId, \Monitors::LOGS_OBJECT, $monitorId);
					\Logs::add_tag($logId, \Notify::LOGS_OBJECT, $notifyId);
				}
				continue;
			}
			$row = [
				'monitor_id' => $monitorId,
				'notify_id' => $notifyId,
				'create_time' => time(),
				'update_time' => time(),
			];
			$row['id'] = \MonitoringNotify::save($row);
			$logId = \Logs::create_log(\MonitoringNotify::LOGS_OBJECT, intval($row['id']), $row);
			\Logs::add_tag($logId, \Monitors::LOGS_OBJECT, $monitorId);
			\Logs::add_tag($logId, \Notify::LOGS_OBJECT, $notifyId);
		}
	}

	/**
	 * @param array<int,int> $diff worker_id => +/-1
	 */
	static public function applyDockerWorkers(int $dockerId, array $diff): void {
		foreach ($diff as $workerId => $status) {
			static::saveDockerWorkerLink($dockerId, intval($workerId), intval($status));
		}
	}

	static private function saveDockerWorkerLink(int $dockerId, int $workerId, int $status): void {
		if ($dockerId < 1 || $workerId < 1) {
			return;
		}
		if ($status < 0) {
			$old = \MonitoringDockers::get(['docker_id' => $dockerId, 'worker_id' => $workerId]);
			if (!empty($old['id'])) {
				\MonitoringDockers::delete(intval($old['id']));
				$logId = \Logs::delete_log(\MonitoringDockers::LOGS_OBJECT, intval($old['id']), $old);
				\Logs::add_tag($logId, \Dockers::LOGS_OBJECT, $dockerId);
				\Logs::add_tag($logId, \Workers::LOGS_OBJECT, $workerId);
			}
			return;
		}
		$row = [
			'docker_id' => $dockerId,
			'worker_id' => $workerId,
			'create_time' => time(),
			'update_time' => time(),
		];
		$row['id'] = \MonitoringDockers::save($row);
		$logId = \Logs::create_log(\MonitoringDockers::LOGS_OBJECT, intval($row['id']), $row);
		\Logs::add_tag($logId, \Dockers::LOGS_OBJECT, $dockerId);
		\Logs::add_tag($logId, \Workers::LOGS_OBJECT, $workerId);
	}

	/**
	 * Normalize PEM / base64 TLS blob from API JSON.
	 * Accepts raw PEM or already-base64 content; stores base64 like UI uploads.
	 */
	static public function normalizeTlsBlob(mixed $raw): string {
		$value = trim(strval($raw ?? ''));
		if ($value === '') {
			return '';
		}
		if (str_contains($value, '-----BEGIN')) {
			return base64_encode($value);
		}
		$decoded = base64_decode($value, true);
		if ($decoded !== false && str_contains($decoded, '-----BEGIN')) {
			return $value;
		}
		return base64_encode($value);
	}

	/**
	 * @param mixed $raw
	 * @return list<int>
	 */
	static public function intIdList(mixed $raw): array {
		if (!is_array($raw)) {
			return [];
		}
		$out = [];
		foreach ($raw as $id) {
			$id = intval($id);
			if ($id > 0) {
				$out[] = $id;
			}
		}
		return array_values(array_unique($out));
	}
}

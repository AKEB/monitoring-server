<?php

class MonitoringJobs extends \DB\MySQLObject {
	static public $table = 'monitoring_jobs';

	const LOGS_OBJECT = 'MonitorJobs';

	/**
	 * Distinct checkable leaf monitors per worker, including folder inheritance.
	 *
	 * @param list<int> $workerIds
	 * @return array<int,int> worker_id => count
	 */
	static public function countMonitorsByWorkerIds(array $workerIds = []): array {
		$sql = '';
		if ($workerIds) {
			$sql .= sql_pholder(' AND t.`worker_id` IN (?@)', $workerIds);
		}
		$data = static::data(
			false,
			$sql,
			't.`worker_id`, t.`monitor_id`, M.`type`',
			false,
			false,
			[
				'_join' => ' INNER JOIN `'.\Monitors::getTable().'` M ON t.`monitor_id` = M.`id`',
			]
		);
		$return = [];
		if ($workerIds) {
			foreach ($workerIds as $wid) {
				$return[intval($wid)] = 0;
			}
		}
		if (!$data) {
			return $return;
		}

		/** @var array<int, array<int, true>> $directByWorker monitor sets before expansion */
		$directByWorker = [];
		foreach ($data as $item) {
			$wid = intval($item['worker_id'] ?? 0);
			$mid = intval($item['monitor_id'] ?? 0);
			if ($wid < 1 || $mid < 1) {
				continue;
			}
			$directByWorker[$wid][$mid] = true;
		}

		$monitorsHash = \Monitors::get_hash();
		$excludeTypes = [
			\Monitors::TYPE_FOLDER => true,
			\Monitors::TYPE_DOCKER => true,
			\Monitors::TYPE_EXTERNAL => true,
		];
		foreach (\Monitors::server_types() as $serverType) {
			$excludeTypes[intval($serverType)] = true;
		}
		foreach ($directByWorker as $wid => $monitorIds) {
			$leaves = [];
			foreach ($monitorIds as $mid => $_) {
				$type = intval($monitorsHash[$mid]['type'] ?? -1);
				if ($type === \Monitors::TYPE_FOLDER) {
					foreach (\Monitors::get_leaf_monitor_ids_under($mid, $monitorsHash) as $leafId) {
						$leafType = intval($monitorsHash[$leafId]['type'] ?? -1);
						if (isset($excludeTypes[$leafType]) || !static::monitorInheritsFolderWorkers($leafType)) {
							continue;
						}
						$leaves[intval($leafId)] = true;
					}
					continue;
				}
				if (isset($excludeTypes[$type]) || !static::monitorInheritsFolderWorkers($type)) {
					continue;
				}
				$leaves[intval($mid)] = true;
			}
			$return[intval($wid)] = count($leaves);
		}
		return $return;
	}

	/**
	 * Whether a monitor of this type receives workers from ancestor folders.
	 * Server-agent monitors (and docker/external) use their own assignment paths —
	 * never inherit regular workers from folders.
	 */
	static public function monitorInheritsFolderWorkers(int $type): bool {
		if (\Monitors::is_server_type($type)) {
			return false;
		}
		if ($type === \Monitors::TYPE_DOCKER || $type === \Monitors::TYPE_EXTERNAL) {
			return false;
		}
		return true;
	}

	/**
	 * Worker IDs attached to ancestor folders starting from $parent_id (inclusive) upward.
	 * Does not include workers linked directly to a child monitor.
	 *
	 * @return array<int,true> worker_id => true
	 */
	static public function getInheritedWorkerIds(int $parent_id): array {
		$parent_id = intval($parent_id);
		if ($parent_id < 1) {
			return [];
		}
		$monitors_hash = \Monitors::get_hash();
		$folder_ids = [];
		$current_id = $parent_id;
		$guard = 0;
		while ($current_id > 0 && $guard < 100) {
			$guard++;
			if (!isset($monitors_hash[$current_id])) {
				break;
			}
			$folder_ids[] = $current_id;
			$current_id = intval($monitors_hash[$current_id]['parent_id'] ?? 0);
		}
		if (!$folder_ids) {
			return [];
		}
		$rows = static::data(false, sql_pholder(' AND `monitor_id` IN (?@)', $folder_ids), '`worker_id`');
		$result = [];
		if ($rows) {
			foreach ($rows as $row) {
				$wid = intval($row['worker_id'] ?? 0);
				if ($wid > 0) {
					$result[$wid] = true;
				}
			}
		}
		return $result;
	}

	/**
	 * Direct workers on $monitor_id plus inherited from parent folders.
	 *
	 * @return array<int,true> worker_id => true
	 */
	static public function getEffectiveWorkerIdsForMonitor(int $monitor_id): array {
		$monitor_id = intval($monitor_id);
		if ($monitor_id < 1) {
			return [];
		}
		$monitors_hash = \Monitors::get_hash();
		if (!isset($monitors_hash[$monitor_id])) {
			return [];
		}
		$result = [];
		$rows = static::data(['monitor_id' => $monitor_id], false, '`worker_id`');
		if ($rows) {
			foreach ($rows as $row) {
				$wid = intval($row['worker_id'] ?? 0);
				// Include virtual server/external worker ids (-1, -2).
				if ($wid !== 0) {
					$result[$wid] = true;
				}
			}
		}
		$type = intval($monitors_hash[$monitor_id]['type'] ?? 0);
		if (!static::monitorInheritsFolderWorkers($type)) {
			return $result;
		}
		$parent_id = intval($monitors_hash[$monitor_id]['parent_id'] ?? 0);
		foreach (static::getInheritedWorkerIds($parent_id) as $wid => $_) {
			$result[intval($wid)] = true;
		}
		return $result;
	}

	static public function isWorkerAssignedToMonitor(int $worker_id, int $monitor_id): bool {
		$worker_id = intval($worker_id);
		if ($worker_id < 1) {
			return false;
		}
		return isset(static::getEffectiveWorkerIdsForMonitor($monitor_id)[$worker_id]);
	}

	/**
	 * Expand folder-level worker assignments onto leaf monitors.
	 * Direct leaf assignments are kept; folder workers are unioned onto descendants.
	 * When both exist for the same leaf+worker, the existing leaf value is preserved.
	 *
	 * @param array<int, array<int, mixed>> $directByMonitor monitor_id => [worker_id => value]
	 * @return array<int, array<int, mixed>> leaf (and non-folder) monitor_id => [worker_id => value]
	 */
	static public function expandAssignmentsToLeaves(array $directByMonitor): array {
		if (!$directByMonitor) {
			return [];
		}
		$monitors_hash = \Monitors::get_hash();
		$result = [];

		foreach ($directByMonitor as $monitorId => $workers) {
			$monitorId = intval($monitorId);
			if ($monitorId < 1 || !is_array($workers)) {
				continue;
			}
			$type = intval($monitors_hash[$monitorId]['type'] ?? -1);
			if ($type === \Monitors::TYPE_FOLDER) {
				continue;
			}
			foreach ($workers as $workerId => $value) {
				$wid = intval($workerId);
				// Keep real workers and virtual agent/external ids (-1, -2).
				if ($wid === 0) {
					continue;
				}
				$result[$monitorId][$wid] = $value;
			}
		}

		foreach ($directByMonitor as $monitorId => $workers) {
			$monitorId = intval($monitorId);
			if ($monitorId < 1 || !is_array($workers)) {
				continue;
			}
			$type = intval($monitors_hash[$monitorId]['type'] ?? -1);
			if ($type !== \Monitors::TYPE_FOLDER) {
				continue;
			}
			$leaves = \Monitors::get_leaf_monitor_ids_under($monitorId, $monitors_hash);
			if (!$leaves) {
				continue;
			}
			foreach ($leaves as $leafId) {
				$leafId = intval($leafId);
				$leafType = intval($monitors_hash[$leafId]['type'] ?? -1);
				if (!static::monitorInheritsFolderWorkers($leafType)) {
					continue;
				}
				foreach ($workers as $workerId => $value) {
					$wid = intval($workerId);
					// Folder inheritance is only for real workers.
					if ($wid < 1) {
						continue;
					}
					if (!isset($result[$leafId][$wid])) {
						$result[$leafId][$wid] = $value;
					}
				}
			}
		}

		return $result;
	}

}

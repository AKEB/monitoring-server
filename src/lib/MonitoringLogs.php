<?php

class MonitoringLogs extends \DB\MySQLObject {
	static public $table = 'monitoring_logs';

	const STATUS_FAIL = 0;
	const STATUS_GOOD = 1;

	const STATE_UNKNOWN = 0;
	const STATE_SUCCESS = 1;
	const STATE_ERROR = 2;



	static public function getMonitorsWithLogs(int $lastCounts=10, int $min_time=0, int $max_time=0): array {
		$monitors = \Monitors::data(false, sql_pholder(' AND `type` <> ? AND `status` <> ?', \Monitors::TYPE_FOLDER, \Monitors::STATUS_DISABLED));
		if (!$max_time) $max_time= time();
		$monitor_workers_hash = [];
		$monitor_workers_data = \Jobs::data(false, ' ORDER BY `monitor_id` ASC, `worker_id` ASC', '`monitor_id`, `worker_id`');
		if ($monitor_workers_data) {
			foreach($monitor_workers_data as $item) {
				if (!isset($monitor_workers_hash[$item['monitor_id']])) $monitor_workers_hash[$item['monitor_id']] = [];
				$monitor_workers_hash[$item['monitor_id']][$item['worker_id']] = true;
			}
		}
		$monitors_data = [];
		foreach($monitors as $monitor) {
			// TODO: Make Permission constant
			if (!\Sessions::checkPermission('monitors', $monitor['id'], READ)) continue;
			$repeat = intval($monitor['repeat_seconds']??30);
			
			$monitor_max_time = $max_time;
			$monitor_min_time = $monitor_max_time - ( $lastCounts * $repeat );

			if (!$min_time) $min_time = $monitor_min_time;
			$min_time = min($monitor_min_time, $min_time);
		
			if (!$monitor_workers_hash[$monitor['id']]) continue;
			$workers = $monitor_workers_hash[$monitor['id']];
			$item = [
				'monitor_id' => $monitor['id'],
				'monitor_title' => $monitor['title'],
				'monitor_min_time' => $monitor_min_time,
				'monitor_max_time' => $monitor_max_time,
				'workers' => $workers,
				'states' => [],
			];
			$workers_array = [];
			foreach($workers as $worker_id=>$_) {
				$workers_array[$worker_id] = static::STATE_UNKNOWN;
			}
			for($i=0; $i<$lastCounts; $i++) {
				$t = $item['monitor_min_time'] + ($i * $repeat);
				$t -= ($t % $repeat);
				$item['states'][$t] = [
					'min_time' => $t,
					'max_time' => $t+$repeat,
					'workers' => $workers_array,
				];
			}
			$monitors_data[$monitor['id']] = $item;
		}
		$logs = static::getLastLogs($lastCounts+2, $min_time - (2 * $repeat), $max_time + (2 * $repeat));
		foreach($logs as $log) {
			$monitor_id = $log['monitor_id']??0;
			$worker_id = $log['worker_id']??0;
			$time = $log['update_time']??0;
			if (!$monitor_id || !$worker_id || !$time) continue;
			$status = $log['status']??null;
			if (!isset($monitors_data[$monitor_id]) || !$monitors_data[$monitor_id]) continue;
			if (
				!isset($monitors_data[$monitor_id]['workers']) || 
				!isset($monitors_data[$monitor_id]['workers'][$worker_id]) || 
				!$monitors_data[$monitor_id]['workers'][$worker_id]
			) continue;
			
			if (!isset($monitors_data[$monitor_id]['states']) || !$monitors_data[$monitor_id]['states'] || !is_array($monitors_data[$monitor_id]['states'])) continue;
			foreach($monitors_data[$monitor_id]['states'] as $k=>$v) {
				if ($time > $v['min_time'] && $time <= $v['max_time']) {
					$state = static::STATE_UNKNOWN;
					if (isset($status) && $status == static::STATUS_GOOD) {
						$state = static::STATE_SUCCESS;
					} elseif (isset($status) && $status == static::STATUS_FAIL) {
						$state = static::STATE_ERROR;
					}
					$monitors_data[$monitor_id]['states'][$k]['workers'][$worker_id] = $state;
					break;
				}
			}
		}
		foreach($monitors as $monitor) {
			$monitor_id = $monitor['id'];
			foreach($monitors_data[$monitor_id]['states'] as &$state) {
				$array_unique = array_unique($state['workers'], SORT_NUMERIC);
				$class = 'bg-secondary';
				if (in_array(\MonitoringLogs::STATE_ERROR, $array_unique) && in_array(\MonitoringLogs::STATE_SUCCESS, $array_unique)) {
					$class = 'bg-warning';
				} elseif (in_array(\MonitoringLogs::STATE_ERROR, $array_unique)) {
					$class = 'bg-danger';
				} elseif (in_array(\MonitoringLogs::STATE_SUCCESS, $array_unique)) {
					$class = 'bg-success';
				}
				$state['class'] = $class;
				$state['title'] = date("Y-m-d H:i:s", $state['min_time']);
			}
			unset($state);
		}
		return $monitors_data;
	}

	static private function getLastLogs(int $count=6, int $min_time=0, int $max_time=0): array {
		if (!$max_time) $max_time = time();
		if (!$min_time) $min_time = $max_time - 3600;
		$sql = "
			SELECT `monitor_id`, `worker_id`, `status`, `update_time`
				FROM (
					SELECT 
						`monitor_id`,
						`worker_id`, 
						`status`,
						`update_time`,
						ROW_NUMBER() OVER (PARTITION BY `monitor_id`,`worker_id` ORDER BY `update_time` DESC) as rn
					FROM `monitoring_logs`
					WHERE `update_time` >= ".intval($min_time)." AND `update_time` <= ".intval($max_time)."
				) AS ranked
			WHERE rn <= ".intval($count)."
			ORDER BY `monitor_id`, `worker_id`, `update_time` DESC;
		";
		$data = [];
		static::getDatabase()->db_GetQueryArray($sql, $data);
		return $data;
	}

}
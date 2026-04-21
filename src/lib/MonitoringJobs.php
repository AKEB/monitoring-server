<?php

class MonitoringJobs extends \DB\MySQLObject {
	static public $table = 'monitoring_jobs';

	const LOGS_OBJECT = 'MonitorJobs';

	static public function countMonitorsByWorkerIds(array $workerIds = []): array {
		$sql = '';
		if ($workerIds) {
			$sql .= sql_pholder(' AND t.`worker_id` IN (?@)', $workerIds);
		}
		$sql .= ' GROUP BY t.`worker_id`';

		$data = static::data(false, $sql, 't.`worker_id`, COUNT(DISTINCT t.`monitor_id`) AS `count`');
		$return = [];
		foreach ($data as $item) {
			$return[intval($item['worker_id'])] = intval($item['count']);
		}
		return $return;
	}

}
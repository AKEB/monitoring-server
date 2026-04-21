<?php

class MonitoringJobs extends \DB\MySQLObject {
	static public $table = 'monitoring_jobs';

	const LOGS_OBJECT = 'MonitorJobs';

	static public function countMonitorsByWorkerIds(array $workerIds = []): array {
		$sql = '';
		if ($workerIds) {
			$sql .= sql_pholder(' AND t.`worker_id` IN (?@)', $workerIds);
		}
		$sql .= sql_pholder(' AND M.`type` NOT IN (?@)', [\Monitors::TYPE_FOLDER, \Monitors::TYPE_DOCKER]);
		$sql .= ' GROUP BY t.`worker_id`';

		$data = static::data(
			false,
			$sql,
			't.`worker_id`, COUNT(DISTINCT t.`monitor_id`) AS `count`',
			false,
			false,
			[
				'_join' => ' INNER JOIN `'.\Monitors::getTable().'` M ON t.`monitor_id` = M.`id`',
			]
		);
		$return = [];
		foreach ($data as $item) {
			$return[intval($item['worker_id'])] = intval($item['count']);
		}
		return $return;
	}

}
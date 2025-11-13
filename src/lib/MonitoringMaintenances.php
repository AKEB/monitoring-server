<?php

class MonitoringMaintenances extends \DB\MySQLObject {
	static public $table = 'monitoring_maintenances';

	const LOGS_OBJECT = 'MonitorMaintenance';


	static public function getMaintenancesForMonitor(int $monitor_id): array {
		$cache = new \Cache('MonitoringMaintenances_'.md5($monitor_id));
		if ($cache->isValid()) {
			$maintenances = $cache->get();
		} else {
			$data = \Monitors::get_hash();
			$folders_hash = [];
			foreach($data as $id=>$monitor) {
				if ($id == $monitor_id) {
					$folders_hash[$id] = $monitor;
				}
				if ($monitor['type'] == \Monitors::TYPE_FOLDER) {
					$folders_hash[$id] = $monitor;
				}
			}
			$monitor_ids = $folders_hash ? array_keys($folders_hash) : [];
			if (!isset($monitor_ids[$monitor_id]) || !$monitor_ids[$monitor_id]) {
				$monitor_ids[] = $monitor_id;
			}
			$sql = '';
			if ($monitor_ids) {
				$sql = sql_pholder(' AND monitor_id IN (?@)', $monitor_ids);
			}
			$maintenances = static::data(false, $sql, '`monitor_id`, `maintenance_id`');
			$maintenances_hash = [];
			if ($maintenances) {
				foreach($maintenances as $v) {
					$maintenances_hash[$v['monitor_id']][$v['maintenance_id']] = true;
				}
			}
			$maintenances = [];
			if ($maintenances_hash) {
				do {
					$monitor = $folders_hash[$monitor_id];
					$maintenances_hash[$monitor['id']] = $maintenances_hash[$monitor['id']] ?? [];
					foreach($maintenances_hash[$monitor['id']] as $notify_id => $_) {
						$maintenances[$notify_id] = $notify_id;
					}
					$monitor_id = $monitor['parent_id'];
					if (!$monitor_id) break;
				} while($monitor['parent_id'] > 0);
			}
			$maintenances = array_keys($maintenances);
			if ($maintenances) {
				$sql = sql_pholder(' AND `id` IN (?@)', $maintenances);
				$sql .= sql_pholder(' AND `start_time` <= ? ', time());
				$sql .= sql_pholder(' AND `finish_time` >= ? ', time());
				$maintenances = \Maintenances::data(false, $sql);
			} else {
				$maintenances = [];
			}
			$cache->update($maintenances, 30);
		}
		return $maintenances;
	}

}
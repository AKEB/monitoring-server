<?php

class MonitoringSubscribe extends \DB\MySQLObject {
	static public $table = 'monitoring_subscribe';

	const LOGS_OBJECT = 'MonitorSubscribe';

	static public function init() {
		$user = \Sessions::currentUser();
		if (!$user) {
			return false;
		}
		$monitors = \Monitors::get_hash();
		$subscribe_data = [];
		foreach($monitors as $monitor_id=>$monitor) {
			if ($monitor['type'] == \Monitors::TYPE_FOLDER) continue;
			if (!\Sessions::checkPermission(\Monitors::PERMISSION_ACCESS, $monitor_id, READ)) continue;
			$subscribe_data[] = [
				'user_id' => $user['id'],
				'monitor_id' => $monitor_id,
				'create_time' => time(),
				'update_time' => time(),
				'expire_time' => time() + 2*60,
			];
		}
		if ($subscribe_data && count($subscribe_data) > 0) {
			static::multi_update($subscribe_data, ['update_time', 'expire_time']);
		}
	}

}
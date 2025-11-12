<?php
class Migration_0021 {
	static public $previous = [
	];

	static function install() {
		global $db;
		$db->execSQL("
			CREATE TABLE IF NOT EXISTS `monitoring_subscribe` (
				`monitor_id` bigint DEFAULT 0,
				`user_id` bigint DEFAULT 0,
				`create_time` int DEFAULT 0,
				`update_time` int DEFAULT 0,
				`expire_time` int DEFAULT 0,
				PRIMARY KEY (`monitor_id`, `user_id`),
				KEY `user_id` (`user_id`),
				KEY `expire_time` (`expire_time`)
			) ENGINE=InnoDB
		;");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("DROP TABLE IF EXISTS `monitoring_subscribe`;");
	}
}

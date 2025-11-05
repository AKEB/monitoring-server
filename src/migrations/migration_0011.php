<?php
class Migration_0011 {
	static public $previous = [

	];

	static function install() {
		global $db;
		$db->execSQL("
			CREATE TABLE IF NOT EXISTS `monitoring_notify` (
				`id` bigint NOT NULL AUTO_INCREMENT,
				`monitor_id` int DEFAULT 0,
				`notify_id` int DEFAULT 0,
				`create_time` int DEFAULT 0,
				`update_time` int DEFAULT 0,
				PRIMARY KEY (`id`),
				UNIQUE (`monitor_id`, `notify_id`)
			) ENGINE=InnoDB
		");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("DROP TABLE IF EXISTS `monitoring_notify`;");
	}
}

<?php
class Migration_0024 {
	static public $previous = [
	];

	static function install() {
		global $db;
		$db->execSQL("
			CREATE TABLE IF NOT EXISTS `monitoring_maintenances` (
				`id` bigint NOT NULL AUTO_INCREMENT,
				`monitor_id` bigint DEFAULT 0,
				`maintenance_id` bigint DEFAULT 0,
				`create_time` int DEFAULT 0,
				`update_time` int DEFAULT 0,
				PRIMARY KEY (`id`),
				INDEX (`monitor_id`),
				INDEX (`maintenance_id`)
			) ENGINE=InnoDB
		;");
		$db->execSQL("
			CREATE TABLE IF NOT EXISTS `maintenances` (
				`id` bigint NOT NULL AUTO_INCREMENT,
				`title` varchar(255) DEFAULT '',
				`start_time` int DEFAULT 0,
				`finish_time` int DEFAULT 0,
				`create_time` int DEFAULT 0,
				`update_time` int DEFAULT 0,
				PRIMARY KEY (`id`),
				INDEX (`start_time`, `finish_time`)
			) ENGINE=InnoDB
		;");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("DROP TABLE IF EXISTS `monitoring_maintenances`;");
		$db->execSQL("DROP TABLE IF EXISTS `maintenances`;");
	}
}

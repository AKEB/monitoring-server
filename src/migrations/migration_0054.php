<?php
class Migration_0054 {
	static public $previous = [
		'migration_0053',
	];

	static function install() {
		global $db;
		$db->execSQL("
			CREATE TABLE IF NOT EXISTS `status_pages` (
				`id` bigint NOT NULL AUTO_INCREMENT,
				`title` VARCHAR(255) NOT NULL DEFAULT '',
				`slug` VARCHAR(64) NOT NULL DEFAULT '',
				`status` tinyint NOT NULL DEFAULT 1,
				`create_time` int NOT NULL DEFAULT 0,
				`update_time` int NOT NULL DEFAULT 0,
				PRIMARY KEY (`id`),
				UNIQUE KEY `uniq_status_pages_slug` (`slug`)
			) ENGINE=InnoDB
		");
		$db->execSQL("
			CREATE TABLE IF NOT EXISTS `status_page_folders` (
				`id` bigint NOT NULL AUTO_INCREMENT,
				`status_page_id` int NOT NULL DEFAULT 0,
				`folder_id` int NOT NULL DEFAULT 0,
				`create_time` int NOT NULL DEFAULT 0,
				`update_time` int NOT NULL DEFAULT 0,
				PRIMARY KEY (`id`),
				UNIQUE KEY `uniq_status_page_folder` (`status_page_id`, `folder_id`)
			) ENGINE=InnoDB
		");
		$db->execSQL("
			CREATE TABLE IF NOT EXISTS `status_page_monitors` (
				`id` bigint NOT NULL AUTO_INCREMENT,
				`status_page_id` int NOT NULL DEFAULT 0,
				`monitor_id` int NOT NULL DEFAULT 0,
				`create_time` int NOT NULL DEFAULT 0,
				`update_time` int NOT NULL DEFAULT 0,
				PRIMARY KEY (`id`),
				UNIQUE KEY `uniq_status_page_monitor` (`status_page_id`, `monitor_id`)
			) ENGINE=InnoDB
		");
		$db->execSQL("
			CREATE TABLE IF NOT EXISTS `status_page_incidents` (
				`id` bigint NOT NULL AUTO_INCREMENT,
				`status_page_id` int NOT NULL DEFAULT 0,
				`title` VARCHAR(255) NOT NULL DEFAULT '',
				`message` TEXT NOT NULL,
				`state` VARCHAR(32) NOT NULL DEFAULT 'investigating',
				`active` tinyint NOT NULL DEFAULT 1,
				`create_time` int NOT NULL DEFAULT 0,
				`update_time` int NOT NULL DEFAULT 0,
				PRIMARY KEY (`id`),
				KEY `idx_status_page_incidents_page` (`status_page_id`, `active`)
			) ENGINE=InnoDB
		");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("DROP TABLE IF EXISTS `status_page_incidents`;");
		$db->execSQL("DROP TABLE IF EXISTS `status_page_monitors`;");
		$db->execSQL("DROP TABLE IF EXISTS `status_page_folders`;");
		$db->execSQL("DROP TABLE IF EXISTS `status_pages`;");
	}
}

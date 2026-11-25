<?php
class Migration_0055 {
	static public $previous = [
		'migration_0054',
	];

	static function install() {
		global $db;
		$db->execSQL("
			CREATE TABLE IF NOT EXISTS `monitor_tags` (
				`id` bigint NOT NULL AUTO_INCREMENT,
				`title` VARCHAR(64) NOT NULL DEFAULT '',
				`color` VARCHAR(16) NOT NULL DEFAULT '#0d6efd',
				`create_time` int NOT NULL DEFAULT 0,
				`update_time` int NOT NULL DEFAULT 0,
				PRIMARY KEY (`id`),
				UNIQUE KEY `uniq_monitor_tags_title` (`title`)
			) ENGINE=InnoDB
		");
		$db->execSQL("
			CREATE TABLE IF NOT EXISTS `monitoring_monitor_tags` (
				`id` bigint NOT NULL AUTO_INCREMENT,
				`monitor_id` int NOT NULL DEFAULT 0,
				`tag_id` int NOT NULL DEFAULT 0,
				`create_time` int NOT NULL DEFAULT 0,
				`update_time` int NOT NULL DEFAULT 0,
				PRIMARY KEY (`id`),
				UNIQUE KEY `uniq_monitor_tag` (`monitor_id`, `tag_id`),
				KEY `idx_monitor_tags_tag` (`tag_id`)
			) ENGINE=InnoDB
		");
		$db->execSQL("
			ALTER TABLE `monitoring_monitor`
			ADD COLUMN `badge_enabled` tinyint NOT NULL DEFAULT 0 AFTER `api_enabled`,
			ADD COLUMN `badge_hash` CHAR(64) NULL DEFAULT NULL AFTER `badge_enabled`
		");
		$db->execSQL("
			ALTER TABLE `monitoring_monitor`
			ADD UNIQUE KEY `uniq_monitoring_monitor_badge_hash` (`badge_hash`)
		");
	}

	static function uninstall() {
		global $db;
		@$db->execSQL("ALTER TABLE `monitoring_monitor` DROP INDEX `uniq_monitoring_monitor_badge_hash`");
		@$db->execSQL("ALTER TABLE `monitoring_monitor` DROP COLUMN `badge_hash`");
		@$db->execSQL("ALTER TABLE `monitoring_monitor` DROP COLUMN `badge_enabled`");
		$db->execSQL("DROP TABLE IF EXISTS `monitoring_monitor_tags`;");
		$db->execSQL("DROP TABLE IF EXISTS `monitor_tags`;");
	}
}

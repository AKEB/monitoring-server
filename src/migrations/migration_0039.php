<?php
class Migration_0039 {
	static public $previous = [
		'migration_0038',
	];

	static function install() {
		global $db;
		$db->execSQL("
			ALTER TABLE `monitoring_monitor`
				ADD `alert_muted` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `alert_fail_mode`
		;");
		$db->execSQL("
			CREATE TABLE IF NOT EXISTS `monitoring_alert_actions` (
				`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				`monitor_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
				`alert_start_time` INT UNSIGNED NOT NULL DEFAULT 0,
				`action` VARCHAR(32) NOT NULL DEFAULT '',
				`author` VARCHAR(255) NOT NULL DEFAULT '',
				`action_time` INT UNSIGNED NOT NULL DEFAULT 0,
				`create_time` INT UNSIGNED NOT NULL DEFAULT 0,
				PRIMARY KEY (`id`),
				INDEX `idx_monitor_alert` (`monitor_id`, `alert_start_time`)
			) ENGINE = InnoDB
		;");
		$db->execSQL("
			CREATE TABLE IF NOT EXISTS `monitoring_alert_messages` (
				`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				`monitor_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
				`notify_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
				`channel` VARCHAR(32) NOT NULL DEFAULT '',
				`chat_id` VARCHAR(64) NOT NULL DEFAULT '',
				`message_id` VARCHAR(128) NOT NULL DEFAULT '',
				`message_text` MEDIUMTEXT,
				`update_time` INT UNSIGNED NOT NULL DEFAULT 0,
				PRIMARY KEY (`id`),
				UNIQUE KEY `uniq_monitor_notify` (`monitor_id`, `notify_id`),
				INDEX `idx_notify` (`notify_id`)
			) ENGINE = InnoDB
		;");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("DROP TABLE IF EXISTS `monitoring_alert_messages`;");
		$db->execSQL("DROP TABLE IF EXISTS `monitoring_alert_actions`;");
		$db->execSQL("
			ALTER TABLE `monitoring_monitor`
				DROP `alert_muted`
		;");
	}
}

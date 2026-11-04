<?php
class Migration_0045 {
	static public $previous = [
		'migration_0044',
	];

	static function install() {
		global $db;
		$db->execSQL("
			CREATE TABLE IF NOT EXISTS `servers` (
				`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				`title` CHAR(255) NOT NULL DEFAULT '',
				`status` INT NOT NULL DEFAULT 1,
				`agent_key_hash` CHAR(128) NOT NULL DEFAULT '',
				`hostname` CHAR(255) NOT NULL DEFAULT '',
				`os` CHAR(64) NOT NULL DEFAULT '',
				`arch` CHAR(32) NOT NULL DEFAULT '',
				`agent_version` CHAR(64) NOT NULL DEFAULT '',
				`last_seen_time` INT UNSIGNED NOT NULL DEFAULT 0,
				`last_success_time` INT UNSIGNED NOT NULL DEFAULT 0,
				`cpu_usage_pct` DECIMAL(6,2) NOT NULL DEFAULT 0,
				`mem_used_pct` DECIMAL(6,2) NOT NULL DEFAULT 0,
				`disk_used_pct_max` DECIMAL(6,2) NOT NULL DEFAULT 0,
				`load1` DECIMAL(10,2) NOT NULL DEFAULT 0,
				`ips` TEXT NULL,
				`last_payload` MEDIUMTEXT NULL,
				`create_time` INT UNSIGNED NOT NULL DEFAULT 0,
				`update_time` INT UNSIGNED NOT NULL DEFAULT 0,
				PRIMARY KEY (`id`),
				UNIQUE KEY `agent_key_hash` (`agent_key_hash`),
				INDEX (`status`),
				INDEX (`last_seen_time`)
			) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4;
		");
		$db->execSQL("
			ALTER TABLE `monitoring_monitor`
				ADD `server` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `container`
		;");
		$db->execSQL("
			CREATE TABLE IF NOT EXISTS `server_metrics` (
				`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				`server_id` BIGINT UNSIGNED NOT NULL,
				`collected_at` INT UNSIGNED NOT NULL,
				`cpu_usage_pct` DECIMAL(6,2) NOT NULL DEFAULT 0,
				`mem_used_pct` DECIMAL(6,2) NOT NULL DEFAULT 0,
				`disk_used_pct_max` DECIMAL(6,2) NOT NULL DEFAULT 0,
				`load1` DECIMAL(10,2) NOT NULL DEFAULT 0,
				`net_rx_bps` BIGINT UNSIGNED NOT NULL DEFAULT 0,
				`net_tx_bps` BIGINT UNSIGNED NOT NULL DEFAULT 0,
				PRIMARY KEY (`id`),
				INDEX (`server_id`, `collected_at`),
				INDEX (`collected_at`)
			) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4;
		");
		$db->execSQL("
			CREATE TABLE IF NOT EXISTS `server_auth_events` (
				`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				`server_id` BIGINT UNSIGNED NOT NULL,
				`event_time` INT UNSIGNED NOT NULL DEFAULT 0,
				`user` CHAR(128) NOT NULL DEFAULT '',
				`from_host` CHAR(255) NOT NULL DEFAULT '',
				`method` CHAR(64) NOT NULL DEFAULT '',
				`success` TINYINT NOT NULL DEFAULT 1,
				`raw` VARCHAR(512) NOT NULL DEFAULT '',
				`create_time` INT UNSIGNED NOT NULL DEFAULT 0,
				PRIMARY KEY (`id`),
				INDEX (`server_id`, `event_time`),
				INDEX (`event_time`)
			) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4;
		");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("DROP TABLE IF EXISTS `server_auth_events`;");
		$db->execSQL("DROP TABLE IF EXISTS `server_metrics`;");
		$db->execSQL("ALTER TABLE `monitoring_monitor` DROP `server`;");
		$db->execSQL("DROP TABLE IF EXISTS `servers`;");
	}
}

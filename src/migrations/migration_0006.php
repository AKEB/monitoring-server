<?php
class Migration_0006 {
	static public $previous = [

	];

	static function install() {
		global $db;
		$db->execSQL("
			CREATE TABLE IF NOT EXISTS `monitoring_jobs` (
				`id` bigint NOT NULL AUTO_INCREMENT,
				`worker_id` int DEFAULT 0,
				`monitor_id` int DEFAULT 0,
				`create_time` int DEFAULT 0,
				`update_time` int DEFAULT 0,
				PRIMARY KEY (`id`),
				UNIQUE (`worker_id`, `monitor_id`)
			) ENGINE=InnoDB
		");
		$db->execSQL("
			CREATE TABLE IF NOT EXISTS `monitoring_monitor` (
				`id` bigint NOT NULL AUTO_INCREMENT,
				`title` varchar(255) DEFAULT '',
				`type` int DEFAULT 0,
				`status` int DEFAULT 0,
				`parent_id` bigint DEFAULT 0,
				`url` varchar(512) DEFAULT '',
				`method` char(32) DEFAULT '',
				`timeout` INT NOT NULL DEFAULT '24',
				`repeat_seconds` int DEFAULT 30,
				`ssl_verify` TINYINT NOT NULL DEFAULT '1',
				`proxy_host` CHAR(32) NOT NULL DEFAULT '',
				`proxy_port` MEDIUMINT NOT NULL DEFAULT '0',
				PRIMARY KEY (`id`),
				INDEX (`type`, `status`)
			) ENGINE=InnoDB
		");
		$db->execSQL("
			CREATE TABLE IF NOT EXISTS `monitoring_logs` (
				`id` bigint NOT NULL AUTO_INCREMENT,
				`monitor_id` int DEFAULT 0,
				`worker_id` int DEFAULT 0,
				`job_id` int DEFAULT 0,
				`status` int DEFAULT 0,
				`update_time` int DEFAULT 0,
				`status_code` int DEFAULT 0,
				`status_text` char(16) DEFAULT '',

				`redirect_count` INT DEFAULT 0,
				`redirect_time` DECIMAL(16,6) DEFAULT 0.0,
				`total_time` DECIMAL(16,6) DEFAULT 0.0,
				`namelookup_time` DECIMAL(16,6) DEFAULT 0.0,
				`connect_time` DECIMAL(16,6) DEFAULT 0.0,
				`pretransfer_time` DECIMAL(16,6) DEFAULT 0.0,
				`starttransfer_time` DECIMAL(16,6) DEFAULT 0.0,

				`redirect_time_us` bigint DEFAULT 0.0,
				`total_time_us` bigint DEFAULT 0.0,
				`namelookup_time_us` bigint DEFAULT 0.0,
				`connect_time_us` bigint DEFAULT 0.0,
				`pretransfer_time_us` bigint DEFAULT 0.0,
				`starttransfer_time_us` bigint DEFAULT 0.0,
				`appconnect_time_us` bigint DEFAULT 0.0,
				`posttransfer_time_us` bigint DEFAULT 0.0,

				`scheme` CHAR(16) DEFAULT '',
				`effective_method` CHAR(16) DEFAULT '',
				`primary_ip` CHAR(16) DEFAULT '',
				`primary_port` INT DEFAULT 0,
				`http_version` INT DEFAULT 0,
				`protocol` INT DEFAULT 0,
				`ssl_verifyresult` INT DEFAULT 0,
				`cert_expire` INT DEFAULT 0,
				PRIMARY KEY (`id`),
				INDEX (`monitor_id`, `worker_id`, `update_time` DESC),
				INDEX (`update_time`, `monitor_id`)
			) ENGINE=InnoDB
		");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("DROP TABLE IF EXISTS `monitoring_jobs`");
		$db->execSQL("DROP TABLE IF EXISTS `monitoring_monitor`");
		$db->execSQL("DROP TABLE IF EXISTS `monitoring_logs`");
	}
}


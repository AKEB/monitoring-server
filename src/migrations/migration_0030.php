<?php
class Migration_0030 {
	static public $previous = [
		'migration_0029',
	];

	static function install() {
		global $db;
		$db->execSQL("
			ALTER TABLE `workers`
				ADD `restart_requested` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER `worker_version`
		;");
		$db->execSQL("
			ALTER TABLE `monitoring_logs`
				DROP `redirect_count`,
				DROP `scheme`,
				DROP `effective_method`,
				DROP `primary_ip`,
				DROP `primary_port`,
				DROP `http_version`,
				DROP `protocol`,
				DROP `ssl_verifyresult`
		;");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("ALTER TABLE `workers` DROP `restart_requested`;");
		$db->execSQL("
			ALTER TABLE `monitoring_logs`
				ADD `redirect_count` INT DEFAULT 0 AFTER `response_error`,
				ADD `scheme` CHAR(16) DEFAULT '' AFTER `total_time_us`,
				ADD `effective_method` CHAR(16) DEFAULT '' AFTER `scheme`,
				ADD `primary_ip` CHAR(16) DEFAULT '' AFTER `effective_method`,
				ADD `primary_port` INT DEFAULT 0 AFTER `primary_ip`,
				ADD `http_version` INT DEFAULT 0 AFTER `primary_port`,
				ADD `protocol` INT DEFAULT 0 AFTER `http_version`,
				ADD `ssl_verifyresult` INT DEFAULT 0 AFTER `protocol`
		;");
	}
}

<?php
class Migration_0046 {
	static public $previous = [
		'migration_0045',
	];

	static function install() {
		global $db;
		$db->execSQL("
			CREATE TABLE IF NOT EXISTS `app_options` (
				`opt_key` CHAR(64) NOT NULL,
				`opt_value` TEXT NULL,
				`update_time` INT UNSIGNED NOT NULL DEFAULT 0,
				PRIMARY KEY (`opt_key`)
			) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4;
		");
		$db->execSQL("
			CREATE TABLE IF NOT EXISTS `github_release_meta` (
				`product` CHAR(32) NOT NULL,
				`version` CHAR(64) NOT NULL,
				`arch` CHAR(32) NOT NULL,
				`url` VARCHAR(512) NOT NULL DEFAULT '',
				`sha256` CHAR(64) NOT NULL DEFAULT '',
				`fetched_at` INT UNSIGNED NOT NULL DEFAULT 0,
				PRIMARY KEY (`product`, `version`, `arch`)
			) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4;
		");
		$db->execSQL("
			ALTER TABLE `servers`
				ADD `desired_agent_version` CHAR(64) NOT NULL DEFAULT '' AFTER `agent_version`,
				ADD `update_requested_at` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `desired_agent_version`,
				ADD `update_error` VARCHAR(512) NOT NULL DEFAULT '' AFTER `update_requested_at`
		;");
		$db->execSQL("
			ALTER TABLE `workers`
				MODIFY `worker_version` CHAR(64) NOT NULL DEFAULT '',
				ADD `arch` CHAR(32) NOT NULL DEFAULT '' AFTER `worker_version`,
				ADD `desired_worker_version` CHAR(64) NOT NULL DEFAULT '' AFTER `arch`,
				ADD `update_requested_at` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `desired_worker_version`,
				ADD `update_error` VARCHAR(512) NOT NULL DEFAULT '' AFTER `update_requested_at`
		;");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("
			ALTER TABLE `workers`
				DROP `desired_worker_version`,
				DROP `update_requested_at`,
				DROP `update_error`,
				DROP `arch`,
				MODIFY `worker_version` CHAR(16) NOT NULL DEFAULT ''
		;");
		$db->execSQL("
			ALTER TABLE `servers`
				DROP `desired_agent_version`,
				DROP `update_requested_at`,
				DROP `update_error`
		;");
		$db->execSQL("DROP TABLE IF EXISTS `github_release_meta`;");
		$db->execSQL("DROP TABLE IF EXISTS `agent_release_meta`;");
		$db->execSQL("DROP TABLE IF EXISTS `app_options`;");
	}
}

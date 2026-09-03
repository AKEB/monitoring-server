<?php
class Migration_0032 {
	static public $previous = [
		'migration_0031',
	];

	static function install() {
		global $db;
		$db->execSQL("
			CREATE TABLE `user_api_tokens` (
				`id` INT NOT NULL AUTO_INCREMENT,
				`user_id` INT NOT NULL DEFAULT 0,
				`title` VARCHAR(255) NOT NULL DEFAULT '',
				`token_hash` CHAR(64) NOT NULL DEFAULT '',
				`token_prefix` CHAR(12) NOT NULL DEFAULT '',
				`last_used_time` INT NOT NULL DEFAULT 0,
				`revoke_time` INT NOT NULL DEFAULT 0,
				`create_time` INT NOT NULL DEFAULT 0,
				`update_time` INT NOT NULL DEFAULT 0,
				PRIMARY KEY (`id`),
				UNIQUE (`token_hash`),
				INDEX (`user_id`),
				INDEX (`revoke_time`)
			) ENGINE = InnoDB
		;");
		$db->execSQL("
			ALTER TABLE `monitoring_monitor`
				ADD `api_enabled` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER `status`
		;");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("ALTER TABLE `monitoring_monitor` DROP `api_enabled`;");
		$db->execSQL("DROP TABLE IF EXISTS `user_api_tokens`");
	}
}

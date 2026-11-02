<?php
class Migration_0042 {
	static public $previous = [
		'migration_0041',
	];

	static function install() {
		global $db;
		$db->execSQL("
			ALTER TABLE `dockers`
				ADD `last_success_time` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `container_states`,
				ADD `last_success_worker_id` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `last_success_time`,
				ADD `engine_info` JSON NULL AFTER `last_success_worker_id`
		;");
		$db->execSQL("
			ALTER TABLE `monitoring_dockers`
				ADD `last_status` TINYINT NOT NULL DEFAULT 0 AFTER `update_time`,
				ADD `last_status_code` INT NOT NULL DEFAULT 0 AFTER `last_status`,
				ADD `last_error` VARCHAR(512) NOT NULL DEFAULT '' AFTER `last_status_code`,
				ADD `last_success_time` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `last_error`,
				ADD `engine_version` VARCHAR(64) NOT NULL DEFAULT '' AFTER `last_success_time`,
				ADD `containers_count` INT NOT NULL DEFAULT 0 AFTER `engine_version`
		;");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("
			ALTER TABLE `dockers`
				DROP `last_success_time`,
				DROP `last_success_worker_id`,
				DROP `engine_info`
		;");
		$db->execSQL("
			ALTER TABLE `monitoring_dockers`
				DROP `last_status`,
				DROP `last_status_code`,
				DROP `last_error`,
				DROP `last_success_time`,
				DROP `engine_version`,
				DROP `containers_count`
		;");
	}
}

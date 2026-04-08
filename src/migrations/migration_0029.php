<?php
class Migration_0029 {
	static public $previous = [
		'migration_0027',
		'migration_0028',
	];

	static function install() {
		global $db;
		$db->execSQL("
			ALTER TABLE `monitoring_monitor`
				ADD `request_headers` TEXT NOT NULL AFTER `method`,
				ADD `json_path` CHAR(255) NOT NULL DEFAULT '' AFTER `request_headers`,
				ADD `json_expected_value` CHAR(255) NOT NULL DEFAULT '' AFTER `json_path`,
				ADD `json_expected_type` CHAR(16) NOT NULL DEFAULT 'string' AFTER `json_expected_value`
		;");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("ALTER TABLE `monitoring_monitor` DROP `request_headers`, DROP `json_path`, DROP `json_expected_value`, DROP `json_expected_type`;");
	}
}

<?php
class Migration_0025 {
	static public $previous = [
		'migration_0023',
	];

	static function install() {
		global $db;
		$db->execSQL("
			ALTER TABLE `monitoring_monitor`
			 ADD `additional_text` TEXT NOT NULL AFTER `method`
		;");

	}

	static function uninstall() {
		global $db;
		$db->execSQL("ALTER TABLE `monitoring_monitor` DROP `additional_text`;");
	}
}

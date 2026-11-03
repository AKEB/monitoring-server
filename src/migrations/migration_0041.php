<?php
class Migration_0041 {
	static public $previous = [
		'migration_0040',
	];

	static function install() {
		global $db;
		$db->execSQL("
			ALTER TABLE `monitoring_monitor`
				ADD `external_log` TEXT NOT NULL AFTER `external_status`
		;");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("
			ALTER TABLE `monitoring_monitor`
				DROP `external_log`
		;");
	}
}

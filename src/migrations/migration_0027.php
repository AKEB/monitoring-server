<?php
class Migration_0027 {
	static public $previous = [
		'migration_0025',
	];

	static function install() {
		global $db;
		$db->execSQL("
			ALTER TABLE `monitoring_monitor`
			 ADD `value` INT NOT NULL DEFAULT 0 AFTER `container`
		;");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("ALTER TABLE `monitoring_monitor` DROP `value`;");
	}
}

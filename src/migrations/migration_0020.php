<?php
class Migration_0020 {
	static public $previous = [
		'migration_0013',
	];

	static function install() {
		global $db;
		$db->execSQL("ALTER TABLE `monitoring_monitor`
			CHANGE `host` `host` CHAR(128) NOT NULL DEFAULT ''
		;");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("ALTER TABLE `monitoring_monitor` CHANGE `host` `host` CHAR(15) NOT NULL DEFAULT '';");
	}
}

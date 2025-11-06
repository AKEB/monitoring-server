<?php
class Migration_0013 {
	static public $previous = [
		'migration_0012',
	];

	static function install() {
		global $db;
		$db->execSQL("ALTER TABLE `monitoring_monitor`
			ADD `host` CHAR(15) NOT NULL DEFAULT '' AFTER `url`,
			ADD `port` INT UNSIGNED NOT NULL DEFAULT '0' AFTER `host`
		;");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("ALTER TABLE `monitoring_monitor` DROP `host`, DROP `port`;");
	}
}

<?php
class Migration_0019 {
	static public $previous = [
		'migration_0013',
	];

	static function install() {
		global $db;
		$db->execSQL("ALTER TABLE `monitoring_monitor`
			ADD `docker` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `port`,
			ADD `container` CHAR(128) NOT NULL DEFAULT '' AFTER `docker`
		;");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("ALTER TABLE `monitoring_monitor` DROP `docker`, DROP `container`;");
	}
}

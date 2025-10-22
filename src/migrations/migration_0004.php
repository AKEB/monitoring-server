<?php
class Migration_0004 {
	static public $previous = [
		'migration_0002',
	];

	static function install() {
		global $db;
		$db->execSQL("ALTER TABLE `workers` CHANGE `status` `last_active_time` INT NOT NULL DEFAULT '0';");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("ALTER TABLE `workers` CHANGE `last_active_time` `status` INT NOT NULL DEFAULT '0';");
	}
}


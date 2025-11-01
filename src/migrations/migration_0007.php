<?php
class Migration_0007 {
	static public $previous = [
		'migration_0003',
	];

	static function install() {
		global $db;
		$db->execSQL("ALTER TABLE `monitoring_monitor` ADD `max_redirects` INT NOT NULL DEFAULT '10' AFTER `repeat_seconds`;");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("ALTER TABLE `monitoring_monitor` DROP `max_redirects`;");
	}
}

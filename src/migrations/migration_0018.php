<?php
class Migration_0018 {
	static public $previous = [
		'migration_0017',
	];

	static function install() {
		global $db;
		$db->execSQL("
			ALTER TABLE `dockers`
				ADD `timeout` INT NOT NULL DEFAULT 15 AFTER `port`,
				ADD `repeat_seconds` int DEFAULT 60 AFTER `timeout`
		;");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("ALTER TABLE `dockers` DROP `timeout`, DROP `repeat_seconds`;");
	}
}

<?php
class Migration_0004 {
	static public $previous = [
		'migration_0003',
	];

	static function install() {
		global $db;
		$db->execSQL("
			ALTER TABLE `monitoring_monitor`
				ADD `create_time` INT NOT NULL DEFAULT 0 AFTER `proxy_port`,
				ADD `update_time` INT NOT NULL DEFAULT 0
			;
		");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("
			ALTER TABLE `monitoring_monitor`
				DROP `create_time`,
				DROP `update_time`
			;
		");
	}
}


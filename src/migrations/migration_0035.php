<?php
class Migration_0035 {
	static public $previous = [
		'migration_0034',
	];

	static function install() {
		global $db;
		$db->execSQL("
			ALTER TABLE `monitoring_monitor`
				ADD `alert_start_time` INT NOT NULL DEFAULT 0 AFTER `last_send_time`
		;");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("
			ALTER TABLE `monitoring_monitor`
				DROP `alert_start_time`
		;");
	}
}

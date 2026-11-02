<?php
class Migration_0038 {
	static public $previous = [
		'migration_0037',
	];

	static function install() {
		global $db;
		$db->execSQL("
			ALTER TABLE `monitoring_monitor`
				ADD `alert_fail_mode` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `send_repeat_minute`
		;");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("
			ALTER TABLE `monitoring_monitor`
				DROP `alert_fail_mode`
		;");
	}
}

<?php
class Migration_0040 {
	static public $previous = [
		'migration_0039',
	];

	static function install() {
		global $db;
		$db->execSQL("
			ALTER TABLE `monitoring_monitor`
				MODIFY `alert_fail_mode` TINYINT UNSIGNED NOT NULL DEFAULT 1
		;");
		// migration_0038 defaulted to ALL (0); product default is ≥50% (1)
		$db->execSQL("
			UPDATE `monitoring_monitor`
				SET `alert_fail_mode` = 1
				WHERE `alert_fail_mode` = 0
		;");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("
			ALTER TABLE `monitoring_monitor`
				MODIFY `alert_fail_mode` TINYINT UNSIGNED NOT NULL DEFAULT 0
		;");
	}
}

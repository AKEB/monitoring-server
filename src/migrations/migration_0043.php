<?php
class Migration_0043 {
	static public $previous = [
		'migration_0042',
	];

	static function install() {
		global $db;
		$db->execSQL("
			ALTER TABLE `monitoring_monitor`
				MODIFY `external_log` TEXT NOT NULL DEFAULT ''
		;");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("
			ALTER TABLE `monitoring_monitor`
				MODIFY `external_log` TEXT NOT NULL
		;");
	}
}

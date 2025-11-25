<?php
class Migration_0028 {
	static public $previous = [
		'migration_0006',
	];

	static function install() {
		global $db;
		$db->execSQL("
			ALTER TABLE `monitoring_logs` ADD INDEX `idx_optimized`
			(`update_time`, `monitor_id`, `status`, `total_time_us`)
		;");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("ALTER TABLE `monitoring_logs` DROP INDEX `idx_optimized`;");
	}
}

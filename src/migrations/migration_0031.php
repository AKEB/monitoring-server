<?php
class Migration_0031 {
	static public $previous = [
		'migration_0030',
	];

	static function install() {
		global $db;
		// Redundant with idx_agg_day prefix and (monitor_id, worker_id, update_time).
		$db->execSQL("ALTER TABLE `monitoring_logs` DROP INDEX `idx_optimized`;");
		$db->execSQL("ALTER TABLE `monitoring_logs` DROP INDEX `update_time`;");
		// Covering index for mngr_aggregate_today / refreshTodayFromRaw (GROUP BY monitor_id, worker_id).
		$db->execSQL("
			ALTER TABLE `monitoring_logs`
				ADD INDEX `idx_agg_day` (`update_time`, `monitor_id`, `worker_id`, `status`, `total_time_us`)
		;");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("ALTER TABLE `monitoring_logs` DROP INDEX `idx_agg_day`;");
		$db->execSQL("
			ALTER TABLE `monitoring_logs`
				ADD INDEX `idx_optimized` (`update_time`, `monitor_id`, `status`, `total_time_us`)
		;");
		$db->execSQL("
			ALTER TABLE `monitoring_logs`
				ADD INDEX `update_time` (`update_time`, `monitor_id`)
		;");
	}
}

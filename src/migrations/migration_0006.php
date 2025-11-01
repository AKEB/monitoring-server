<?php
class Migration_0006 {
	static public $previous = [
		'migration_0003',
	];

	static function install() {
		global $db;
		$db->execSQL("ALTER TABLE `monitoring_logs`
			DROP `redirect_time`,
			DROP `total_time`,
			DROP `namelookup_time`,
			DROP `connect_time`,
			DROP `pretransfer_time`,
			DROP `starttransfer_time`,
			DROP `namelookup_time_us`,
			DROP `connect_time_us`,
			DROP `pretransfer_time_us`,
			DROP `starttransfer_time_us`,
			DROP `redirect_time_us`,
			DROP `appconnect_time_us`,
			DROP `posttransfer_time_us`
			;
		");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("ALTER TABLE `monitoring_logs`
				ADD `redirect_time` DECIMAL(16,6) DEFAULT 0.0 AFTER `redirect_count`,
				ADD `total_time` DECIMAL(16,6) DEFAULT 0.0,
				ADD `namelookup_time` DECIMAL(16,6) DEFAULT 0.0,
				ADD `connect_time` DECIMAL(16,6) DEFAULT 0.0,
				ADD `pretransfer_time` DECIMAL(16,6) DEFAULT 0.0,
				ADD `starttransfer_time` DECIMAL(16,6) DEFAULT 0.0,
				ADD `redirect_time_us` bigint DEFAULT 0.0,
				ADD `namelookup_time_us` bigint DEFAULT 0.0,
				ADD `connect_time_us` bigint DEFAULT 0.0,
				ADD `pretransfer_time_us` bigint DEFAULT 0.0,
				ADD `starttransfer_time_us` bigint DEFAULT 0.0,
				ADD `appconnect_time_us` bigint DEFAULT 0.0,
				ADD `posttransfer_time_us` bigint DEFAULT 0.0
		;");
	}
}


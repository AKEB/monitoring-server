<?php
class Migration_0048 {
	static public $previous = [
		'migration_0047',
	];

	static function install() {
		global $db;
		$db->execSQL("
			ALTER TABLE `server_metrics`
				ADD `temp_c_max` DECIMAL(6,2) NULL DEFAULT NULL AFTER `net_tx_bps`,
				ADD `uptime_sec` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `temp_c_max`,
				ADD `details` MEDIUMTEXT NULL AFTER `uptime_sec`
		;");
		$db->execSQL("
			ALTER TABLE `servers`
				ADD `temp_c_max` DECIMAL(6,2) NULL DEFAULT NULL AFTER `load1`,
				ADD `uptime_sec` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `temp_c_max`
		;");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("
			ALTER TABLE `server_metrics`
				DROP `temp_c_max`,
				DROP `uptime_sec`,
				DROP `details`
		;");
		$db->execSQL("
			ALTER TABLE `servers`
				DROP `temp_c_max`,
				DROP `uptime_sec`
		;");
	}
}

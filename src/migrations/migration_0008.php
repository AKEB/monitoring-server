<?php
class Migration_0008 {
	static public $previous = [

	];

	static function install() {
		global $db;
		$db->execSQL("
			CREATE TABLE IF NOT EXISTS `monitoring_logs_aggregate` (
				`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT ,
				`monitor_id` INT UNSIGNED NOT NULL ,
				`worker_id` INT UNSIGNED NOT NULL ,
				`time` INT UNSIGNED NOT NULL ,
				`total_count` INT UNSIGNED NOT NULL ,
				`good_count` INT UNSIGNED NOT NULL ,
				`error_count` INT UNSIGNED NOT NULL ,
				`sum_total_time_ms` BIGINT UNSIGNED NOT NULL ,
				`max_total_time_ms` INT UNSIGNED NOT NULL ,
				`min_total_time_ms` INT UNSIGNED NOT NULL ,
				`availability_percent` DECIMAL(15,2) UNSIGNED NOT NULL ,
				`avg_total_time_ms` DECIMAL(15,2) UNSIGNED NOT NULL ,
				`create_time` INT UNSIGNED NOT NULL ,
				`update_time` INT UNSIGNED NOT NULL ,
				PRIMARY KEY (`id`),
				UNIQUE `mon_work_time` (`monitor_id`, `worker_id`, `time`)
			) ENGINE = InnoDB;
		");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("DROP TABLE IF EXISTS `monitoring_logs_aggregate`;");
	}
}

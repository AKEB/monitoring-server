<?php
class Migration_0009 {
	static public $previous = [

	];

	static function install() {
		global $db;
		$db->execSQL("
			CREATE TABLE IF NOT EXISTS `monitoring_events` (
				`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT ,
				`monitor_id` INT NOT NULL DEFAULT '0' ,
				`worker_id` INT NOT NULL DEFAULT '0' ,
				`status` TINYINT NOT NULL DEFAULT '0' ,
				`event_time` INT UNSIGNED NOT NULL ,
				`status_code` INT NOT NULL DEFAULT '0' ,
				`response_error_num` INT NOT NULL DEFAULT '0' ,
				`response_error` CHAR(255) NOT NULL DEFAULT '' ,
				`hash` CHAR(32) NOT NULL DEFAULT '' ,
				`create_time` INT UNSIGNED NOT NULL ,
				PRIMARY KEY (`id`),
				INDEX (`monitor_id`, `worker_id`, `event_time`)
			) ENGINE = InnoDB;
		");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("DROP TABLE IF EXISTS `monitoring_events`;");
	}
}

<?php
class Migration_0000 {
	static public $previous = [

	];

	static function install() {
		global $db;
		$db->execSQL("
			CREATE TABLE `workers` (
				`id` INT NOT NULL AUTO_INCREMENT ,
				`worker_key_hash` CHAR(128) NOT NULL DEFAULT '' ,
				`title` VARCHAR(255) NOT NULL DEFAULT '' ,
				`worker_threads` INT NOT NULL DEFAULT '0' ,
				`jobs_get_timeout` INT NOT NULL DEFAULT '0' ,
				`loop_timeout` INT NOT NULL DEFAULT '0' ,
				`response_send_timeout` INT NOT NULL DEFAULT '0' ,
				`logs_write_timeout` INT NOT NULL DEFAULT '0' ,
				`status` INT NOT NULL DEFAULT '0' ,
				`create_time` INT NOT NULL DEFAULT '0' ,
				`update_time` INT NOT NULL DEFAULT '0' ,
				PRIMARY KEY (`id`),
				INDEX (`update_time`),
				UNIQUE (`worker_key_hash`)
			) ENGINE = InnoDB;");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("DROP TABLE IF EXISTS `workers`");

	}
}


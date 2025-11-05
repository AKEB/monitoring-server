<?php
class Migration_0010 {
	static public $previous = [

	];

	static function install() {
		global $db;
		$db->execSQL("
			CREATE TABLE IF NOT EXISTS `notify` (
				`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				`type` INT NOT NULL DEFAULT 0,
				`title` CHAR(255) NOT NULL DEFAULT '',
				`receiver` CHAR(255) NOT NULL DEFAULT '',
				`json_data` JSON,
				`status` INT NOT NULL DEFAULT 1,
				`create_time` INT UNSIGNED NOT NULL,
				`update_time` INT UNSIGNED NOT NULL,
				PRIMARY KEY (`id`),
				INDEX (`status`, `type`)
			) ENGINE = InnoDB;
		");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("DROP TABLE IF EXISTS `notify`;");
	}
}

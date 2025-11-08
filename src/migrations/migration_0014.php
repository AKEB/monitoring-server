<?php
class Migration_0014 {
	static public $previous = [

	];

	static function install() {
		global $db;
		$db->execSQL("
			CREATE TABLE IF NOT EXISTS `dockers` (
				`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				`title` CHAR(255) NOT NULL DEFAULT '',
				`status` INT NOT NULL DEFAULT 1,
				`create_time` INT UNSIGNED NOT NULL,
				`update_time` INT UNSIGNED NOT NULL,
				PRIMARY KEY (`id`),
				INDEX (`status`)
			) ENGINE = InnoDB;
		");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("DROP TABLE IF EXISTS `dockers`;");
	}
}

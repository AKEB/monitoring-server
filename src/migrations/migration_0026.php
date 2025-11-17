<?php
class Migration_0026 {
	static public $previous = [
		'migration_0018',
	];

	static function install() {
		global $db;
		$db->execSQL("
			ALTER TABLE `dockers`
				ADD `proxy_host` CHAR(32) NOT NULL DEFAULT '' AFTER `repeat_seconds`,
				ADD `proxy_type` MEDIUMINT NOT NULL DEFAULT 0 AFTER `proxy_host`
		;");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("ALTER TABLE `dockers` DROP `proxy_host`, DROP `proxy_type`;");
	}
}

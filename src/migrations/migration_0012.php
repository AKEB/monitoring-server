<?php
class Migration_0012 {
	static public $previous = [
		'migration_0007',
	];

	static function install() {
		global $db;
		$db->execSQL("ALTER TABLE `monitoring_monitor`
			ADD `last_send_status` INT UNSIGNED NOT NULL DEFAULT '1' AFTER `proxy_type`,
			ADD `last_send_time` INT UNSIGNED NOT NULL DEFAULT '0' AFTER `last_send_status`,
			ADD `send_repeat_minute` INT UNSIGNED NOT NULL DEFAULT '0' AFTER `last_send_time`
		;");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("ALTER TABLE `monitoring_monitor` DROP `last_send_status`, DROP `last_send_time`, DROP `send_repeat_minute`;");
	}
}

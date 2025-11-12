<?php
class Migration_0022 {
	static public $previous = [
		'migration_0008',
		'migration_0009',
	];

	static function install() {
		global $db;
		$db->execSQL("ALTER TABLE `monitoring_events` ADD INDEX(`event_time`);");
		$db->execSQL("ALTER TABLE `monitoring_logs_aggregate` ADD INDEX(`time`);");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("ALTER TABLE `monitoring_events`  DROP INDEX `event_time`;");
		$db->execSQL("ALTER TABLE `monitoring_logs_aggregate`  DROP INDEX `time`;");
	}
}

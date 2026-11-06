<?php
class Migration_0047 {
	static public $previous = [
		'migration_0046',
	];

	static function install() {
		global $db;
		$db->execSQL("
			ALTER TABLE `monitoring_monitor`
				ADD `port_protocol` CHAR(3) NOT NULL DEFAULT 'tcp' AFTER `port`
		;");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("
			ALTER TABLE `monitoring_monitor`
				DROP `port_protocol`
		;");
	}
}

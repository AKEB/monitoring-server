<?php
class Migration_0005 {
	static public $previous = [
		'migration_0002',
	];

	static function install() {
		global $db;
		$db->execSQL("
			ALTER TABLE `workers`
				ADD `protocol_version` CHAR(16) NOT NULL DEFAULT '' AFTER `logs_write_timeout`,
				ADD `worker_version` CHAR(16) NOT NULL DEFAULT '' AFTER `protocol_version`
			;
		");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("
			ALTER TABLE `workers`
				DROP `protocol_version`,
				DROP `worker_version`
			;
		");
	}
}


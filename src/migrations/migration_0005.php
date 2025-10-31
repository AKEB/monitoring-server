<?php
class Migration_0005 {
	static public $previous = [
		'migration_0003',
	];

	static function install() {
		global $db;
		$db->execSQL("ALTER TABLE `monitoring_logs` DROP `status_text`, ADD `response_error_num` INT DEFAULT 0 AFTER `status_code`, ADD `response_error` CHAR(255) NOT NULL DEFAULT '' AFTER `response_error_num`;");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("ALTER TABLE `monitoring_logs` DROP `response_error_num`, DROP `response_error`, ADD `status_text` char(16) DEFAULT '' AFTER `status_code`;");
	}
}


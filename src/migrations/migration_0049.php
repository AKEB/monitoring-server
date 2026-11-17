<?php
class Migration_0049 {
	static public $previous = [
		'migration_0048',
	];

	static function install() {
		global $db;
		$db->execSQL("
			ALTER TABLE `monitoring_monitor`
				ADD `notify_success_text` TEXT NOT NULL AFTER `signature_id`,
				ADD `notify_fail_text` TEXT NOT NULL AFTER `notify_success_text`
		;");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("
			ALTER TABLE `monitoring_monitor`
				DROP `notify_success_text`,
				DROP `notify_fail_text`
		;");
	}
}

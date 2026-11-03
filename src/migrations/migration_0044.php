<?php
class Migration_0044 {
	static public $previous = [
		'migration_0043',
	];

	/**
	 * External monitors use worker_id = -1; aggregate table was UNSIGNED and rejected inserts.
	 */
	static function install() {
		global $db;
		$db->execSQL("
			ALTER TABLE `monitoring_logs_aggregate`
				MODIFY `worker_id` INT NOT NULL
		;");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("
			ALTER TABLE `monitoring_logs_aggregate`
				MODIFY `worker_id` INT UNSIGNED NOT NULL
		;");
	}
}

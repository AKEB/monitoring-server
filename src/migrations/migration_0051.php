<?php
class Migration_0051 {
	static public $previous = [
		'migration_0050',
	];

	static function install() {
		global $db;
		// TEXT NOT NULL without DEFAULT breaks INSERT under strict SQL when the app
		// omits the column (e.g. creating a Domain monitor without DNS fields).
		$db->execSQL("
			ALTER TABLE `monitoring_monitor`
				MODIFY `dns_expected` TEXT NOT NULL DEFAULT ('')
		;");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("
			ALTER TABLE `monitoring_monitor`
				MODIFY `dns_expected` TEXT NOT NULL
		;");
	}
}

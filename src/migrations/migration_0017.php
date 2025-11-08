<?php
class Migration_0017 {
	static public $previous = [
		'migration_0015',
	];

	static function install() {
		global $db;
		$db->execSQL("
			ALTER TABLE `dockers`
				ADD `container_states` JSON NULL AFTER `tls_key`
		;");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("ALTER TABLE `dockers` DROP `container_states`;");
	}
}

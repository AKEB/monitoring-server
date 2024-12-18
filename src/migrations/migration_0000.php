<?php
class Migration_0000 {
	static public $previous = [
		// 'migration_0000',
	];

	static function install() {
		global $db;
		$db->execSQL("CREATE TABLE `test_table` LIKE `migrations`");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("DROP TABLE IF EXISTS `test_table`");
	}
}


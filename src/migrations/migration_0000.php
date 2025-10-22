<?php
class Migration_0000 {
	static public $previous = [];

	static function install() {
		global $db;
		$db->execSQL("CREATE TABLE IF NOT EXISTS `test_table` LIKE `migrations`");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("DROP TABLE IF EXISTS `test_table`");
	}
}


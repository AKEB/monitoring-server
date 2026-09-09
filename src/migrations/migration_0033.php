<?php
class Migration_0033 {
	static public $previous = [
		'migration_0032',
	];

	static function install() {
		global $db;
		$db->execSQL("
			ALTER TABLE `monitoring_monitor`
				ADD `api_description_ru` TEXT NOT NULL AFTER `api_enabled`,
				ADD `api_description_en` TEXT NOT NULL AFTER `api_description_ru`
		;");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("
			ALTER TABLE `monitoring_monitor`
				DROP `api_description_ru`,
				DROP `api_description_en`
		;");
	}
}

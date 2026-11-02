<?php
class Migration_0034 {
	static public $previous = [
		'migration_0033',
	];

	static function install() {
		global $db;
		$db->execSQL("
			ALTER TABLE `monitoring_monitor`
				ADD `external_hash` CHAR(64) NOT NULL DEFAULT '' AFTER `api_description_en`,
				ADD `external_status` TINYINT NOT NULL DEFAULT '-1' AFTER `external_hash`,
				ADD INDEX `external_hash` (`external_hash`)
		;");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("
			ALTER TABLE `monitoring_monitor`
				DROP INDEX `external_hash`,
				DROP `external_status`,
				DROP `external_hash`
		;");
	}
}

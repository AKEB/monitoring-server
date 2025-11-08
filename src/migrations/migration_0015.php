<?php
class Migration_0015 {
	static public $previous = [
		'migration_0014',
	];

	static function install() {
		global $db;
		$db->execSQL("
			ALTER TABLE `dockers`
				ADD `host` CHAR(64) NOT NULL DEFAULT '' AFTER `status`,
				ADD `port` INT NOT NULL DEFAULT '2376' AFTER `host`,
				ADD `tls` TINYINT NOT NULL DEFAULT '1' AFTER `port`,
				ADD `tls_ca_file` TEXT NULL AFTER `tls`,
				ADD `tls_certificate` TEXT NULL AFTER `tls_ca_file`,
				ADD `tls_key` TEXT NULL AFTER `tls_certificate`
		;");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("ALTER TABLE `dockers` DROP `host`, DROP `port`, DROP `tls`, DROP `tls_ca_file`, DROP `tls_certificate`, DROP `tls_key`;");
	}
}

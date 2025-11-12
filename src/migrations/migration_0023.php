<?php
class Migration_0023 {
	static public $previous = [
		'migration_0020',
	];

	static function install() {
		global $db;
		$db->execSQL("ALTER TABLE `monitoring_monitor`
			 ADD `ssl_cert_expire_alert` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1 AFTER `ssl_verify_host`,
			 ADD `last_send_ssl_cert_expire_alert` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `ssl_cert_expire_alert`
		;");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("ALTER TABLE `monitoring_monitor` DROP `ssl_cert_expire_alert`, DROP `last_send_ssl_cert_expire_alert`;");
	}
}

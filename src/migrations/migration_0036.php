<?php
class Migration_0036 {
	static public $previous = [
		'migration_0035',
	];

	static function install() {
		global $db;
		$db->execSQL("ALTER TABLE `monitoring_monitor`
			 ADD `ssl_cert_expire_alert_days` VARCHAR(64) NOT NULL DEFAULT '15,7,6,5,4,3,2,1' AFTER `ssl_cert_expire_alert`
		;");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("ALTER TABLE `monitoring_monitor` DROP `ssl_cert_expire_alert_days`;");
	}
}

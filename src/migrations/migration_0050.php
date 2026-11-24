<?php
class Migration_0050 {
	static public $previous = [
		'migration_0049',
	];

	static function install() {
		global $db;
		$db->execSQL("
			ALTER TABLE `monitoring_monitor`
				ADD `domain_expire_alert_days` VARCHAR(64) NOT NULL DEFAULT '14,7,6,5,4,3,2,1,0' AFTER `notify_fail_text`,
				ADD `dns_record_type` VARCHAR(16) NOT NULL DEFAULT 'A' AFTER `domain_expire_alert_days`,
				ADD `dns_resolver` VARCHAR(128) NOT NULL DEFAULT '' AFTER `dns_record_type`,
				ADD `dns_transport` VARCHAR(16) NOT NULL DEFAULT 'auto' AFTER `dns_resolver`,
				ADD `dns_doh_url` VARCHAR(512) NOT NULL DEFAULT '' AFTER `dns_transport`,
				ADD `dns_match_mode` VARCHAR(32) NOT NULL DEFAULT 'exact_set' AFTER `dns_doh_url`,
				ADD `dns_expected` TEXT NOT NULL DEFAULT ('') AFTER `dns_match_mode`
		;");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("
			ALTER TABLE `monitoring_monitor`
				DROP `domain_expire_alert_days`,
				DROP `dns_record_type`,
				DROP `dns_resolver`,
				DROP `dns_transport`,
				DROP `dns_doh_url`,
				DROP `dns_match_mode`,
				DROP `dns_expected`
		;");
	}
}

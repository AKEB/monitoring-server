<?php
class Migration_0053 {
	static public $previous = [
		'migration_0052',
	];

	static function install() {
		global $db;
		$db->execSQL("
			ALTER TABLE `monitoring_monitor`
				ADD `db_name` VARCHAR(255) NOT NULL DEFAULT '' AFTER `ntp_max_offset_ms`,
				ADD `db_query` TEXT NOT NULL DEFAULT ('') AFTER `db_name`,
				ADD `db_expected` TEXT NOT NULL DEFAULT ('') AFTER `db_query`,
				ADD `sftp_private_key` TEXT NOT NULL DEFAULT ('') AFTER `db_expected`,
				ADD `sftp_path` VARCHAR(1024) NOT NULL DEFAULT '' AFTER `sftp_private_key`,
				ADD `snmp_community` VARCHAR(255) NOT NULL DEFAULT 'public' AFTER `sftp_path`,
				ADD `snmp_oid` VARCHAR(512) NOT NULL DEFAULT '' AFTER `snmp_community`,
				ADD `snmp_expected` TEXT NOT NULL DEFAULT ('') AFTER `snmp_oid`,
				ADD `radius_secret` VARCHAR(255) NOT NULL DEFAULT '' AFTER `snmp_expected`,
				ADD `kafka_brokers` TEXT NOT NULL DEFAULT ('') AFTER `radius_secret`,
				ADD `kafka_topic` VARCHAR(255) NOT NULL DEFAULT '' AFTER `kafka_brokers`,
				ADD `kafka_message` TEXT NOT NULL DEFAULT ('') AFTER `kafka_topic`
		;");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("
			ALTER TABLE `monitoring_monitor`
				DROP `db_name`,
				DROP `db_query`,
				DROP `db_expected`,
				DROP `sftp_private_key`,
				DROP `sftp_path`,
				DROP `snmp_community`,
				DROP `snmp_oid`,
				DROP `snmp_expected`,
				DROP `radius_secret`,
				DROP `kafka_brokers`,
				DROP `kafka_topic`,
				DROP `kafka_message`
		;");
	}
}

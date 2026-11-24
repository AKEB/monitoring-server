<?php
class Migration_0052 {
	static public $previous = [
		'migration_0051',
	];

	static function install() {
		global $db;
		$db->execSQL("
			ALTER TABLE `monitoring_monitor`
				ADD `request_body` TEXT NOT NULL DEFAULT ('') AFTER `request_headers`,
				ADD `http_body_encoding` CHAR(16) NOT NULL DEFAULT 'json' AFTER `request_body`,
				ADD `auth_method` CHAR(32) NOT NULL DEFAULT 'none' AFTER `http_body_encoding`,
				ADD `basic_auth_user` VARCHAR(255) NOT NULL DEFAULT '' AFTER `auth_method`,
				ADD `basic_auth_pass` VARCHAR(255) NOT NULL DEFAULT '' AFTER `basic_auth_user`,
				ADD `bearer_token` TEXT NOT NULL DEFAULT ('') AFTER `basic_auth_pass`,
				ADD `oauth_client_id` TEXT NOT NULL DEFAULT ('') AFTER `bearer_token`,
				ADD `oauth_client_secret` TEXT NOT NULL DEFAULT ('') AFTER `oauth_client_id`,
				ADD `oauth_token_url` TEXT NOT NULL DEFAULT ('') AFTER `oauth_client_secret`,
				ADD `oauth_scopes` TEXT NOT NULL DEFAULT ('') AFTER `oauth_token_url`,
				ADD `oauth_audience` TEXT NOT NULL DEFAULT ('') AFTER `oauth_scopes`,
				ADD `oauth_auth_method` CHAR(32) NOT NULL DEFAULT 'client_secret_basic' AFTER `oauth_audience`,
				ADD `http_tls_ca` TEXT NOT NULL DEFAULT ('') AFTER `oauth_auth_method`,
				ADD `http_tls_cert` TEXT NOT NULL DEFAULT ('') AFTER `http_tls_ca`,
				ADD `http_tls_key` TEXT NOT NULL DEFAULT ('') AFTER `http_tls_cert`,
				ADD `cache_bust` TINYINT(1) NOT NULL DEFAULT 0 AFTER `http_tls_key`,
				ADD `keyword` TEXT NOT NULL DEFAULT ('') AFTER `cache_bust`,
				ADD `keyword_invert` TINYINT(1) NOT NULL DEFAULT 0 AFTER `keyword`,
				ADD `smtp_security` CHAR(16) NOT NULL DEFAULT 'none' AFTER `keyword_invert`,
				ADD `ntp_max_offset_ms` INT NOT NULL DEFAULT 5000 AFTER `smtp_security`
		;");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("
			ALTER TABLE `monitoring_monitor`
				DROP `request_body`,
				DROP `http_body_encoding`,
				DROP `auth_method`,
				DROP `basic_auth_user`,
				DROP `basic_auth_pass`,
				DROP `bearer_token`,
				DROP `oauth_client_id`,
				DROP `oauth_client_secret`,
				DROP `oauth_token_url`,
				DROP `oauth_scopes`,
				DROP `oauth_audience`,
				DROP `oauth_auth_method`,
				DROP `http_tls_ca`,
				DROP `http_tls_cert`,
				DROP `http_tls_key`,
				DROP `cache_bust`,
				DROP `keyword`,
				DROP `keyword_invert`,
				DROP `smtp_security`,
				DROP `ntp_max_offset_ms`
		;");
	}
}

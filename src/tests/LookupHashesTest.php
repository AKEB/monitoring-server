<?php

error_reporting(E_ALL);

class LookupHashesTest extends PHPUnit\Framework\TestCase {

	function test_notify_constants_and_hashes() {
		$this->assertSame(0, \Notify::TYPE_TELEGRAM);
		$this->assertSame(1, \Notify::TYPE_MATTERMOST);
		$this->assertSame(2, \Notify::TYPE_GOTIFY);
		$this->assertSame(3, \Notify::TYPE_MAX);
		$this->assertSame(4, \Notify::TYPE_WEBHOOK);
		$this->assertSame([0, 1, 2, 3, 4], array_keys(\Notify::type_hash()));
		$this->assertSame([0, 1], array_keys(\Notify::status_hash()));
	}

	function test_dockers_signatures_status() {
		$this->assertSame(0, \Dockers::STATUS_DISABLED);
		$this->assertSame(1, \Dockers::STATUS_ENABLED);
		$this->assertSame([0, 1], array_keys(\Dockers::status_hash()));
		$this->assertSame(0, \Signatures::STATUS_DISABLED);
		$this->assertSame(1, \Signatures::STATUS_ENABLED);
		$this->assertSame([0, 1], array_keys(\Signatures::status_hash()));
	}

	function test_monitoring_logs_constants() {
		$this->assertSame(0, \MonitoringLogs::STATUS_FAIL);
		$this->assertSame(1, \MonitoringLogs::STATUS_GOOD);
		$this->assertSame(0, \MonitoringLogs::STATE_UNKNOWN);
		$this->assertSame(1, \MonitoringLogs::STATE_SUCCESS);
		$this->assertSame(2, \MonitoringLogs::STATE_ERROR);
		$this->assertSame(600, \MonitoringLogsAggregate::TODAY_MAX_AGE);
	}

	function test_user_api_token_helpers() {
		$plain = \UserApiTokens::generatePlaintext();
		$this->assertStringStartsWith('mnt_', $plain);
		$this->assertSame(4 + 48, strlen($plain));
		$this->assertSame(hash('sha256', 'mnt_fixed'), \UserApiTokens::hashToken('mnt_fixed'));
		$this->assertSame('mnt_fixedtok', \UserApiTokens::prefixFromPlaintext('mnt_fixedtoken_extra'));
	}
}

<?php

error_reporting(E_ALL);

class MonitorsTest extends PHPUnit\Framework\TestCase {

	function test_type_constants() {
		$this->assertSame(0, \Monitors::TYPE_HTTPS);
		$this->assertSame(1, \Monitors::TYPE_FOLDER);
		$this->assertSame(2, \Monitors::TYPE_TCP);
		$this->assertSame(3, \Monitors::TYPE_PING);
		$this->assertSame(4, \Monitors::TYPE_DOCKER);
		$this->assertSame(5, \Monitors::TYPE_EXPORTER_DISK);
		$this->assertSame(6, \Monitors::TYPE_EXPORTER_MEMORY);
		$this->assertSame(7, \Monitors::TYPE_EXPORTER_CPU);
		$this->assertSame(8, \Monitors::TYPE_HTTP_JSON);
		$this->assertSame(9, \Monitors::TYPE_EXTERNAL);
		$this->assertSame(10, \Monitors::TYPE_SERVER_OFFLINE);
		$this->assertSame(11, \Monitors::TYPE_SERVER_CPU);
		$this->assertSame(12, \Monitors::TYPE_SERVER_MEMORY);
		$this->assertSame(13, \Monitors::TYPE_SERVER_DISK);
		$this->assertSame(-1, \Monitors::EXTERNAL_WORKER_ID);
		$this->assertSame(-2, \Servers::SERVER_WORKER_ID);
	}

	function test_type_hash_excludes_exporter_cpu() {
		$keys = array_keys(\Monitors::type_hash());
		sort($keys);
		$this->assertSame([0, 1, 2, 3, 4, 5, 6, 8, 9, 10, 11, 12, 13], $keys);
		$this->assertArrayNotHasKey(\Monitors::TYPE_EXPORTER_CPU, \Monitors::type_hash());
	}

	function test_alert_fail_threshold() {
		$this->assertFalse(\Monitors::is_alert_fail_threshold(\Monitors::ALERT_FAIL_MODE_ANY, 0, 10));
		$this->assertFalse(\Monitors::is_alert_fail_threshold(\Monitors::ALERT_FAIL_MODE_ANY, 1, 0));
		$this->assertTrue(\Monitors::is_alert_fail_threshold(\Monitors::ALERT_FAIL_MODE_ANY, 2, 10));
		$this->assertTrue(\Monitors::is_alert_fail_threshold(\Monitors::ALERT_FAIL_MODE_MAJORITY, 1, 2));
		$this->assertFalse(\Monitors::is_alert_fail_threshold(\Monitors::ALERT_FAIL_MODE_MAJORITY, 1, 3));
		$this->assertTrue(\Monitors::is_alert_fail_threshold(\Monitors::ALERT_FAIL_MODE_MAJORITY, 2, 3));
		$this->assertTrue(\Monitors::is_alert_fail_threshold(\Monitors::ALERT_FAIL_MODE_ALL, 2, 2));
		$this->assertFalse(\Monitors::is_alert_fail_threshold(\Monitors::ALERT_FAIL_MODE_ALL, 1, 2));
	}

	function test_response_code_hash_shape() {
		$hash = \Monitors::response_code_hash();
		$this->assertSame('100-199', $hash[1]);
		$this->assertSame('200-299', $hash[2]);
		$this->assertSame(200, $hash[200]);
		$this->assertSame(599, $hash[599]);
		$this->assertCount(505, $hash);
	}

	function test_ssl_cert_expire_days() {
		$this->assertSame([15, 7, 6, 5, 4, 3, 2, 1], \Monitors::ssl_cert_expire_alert_days_list());
		$this->assertSame('15,7,6,5,4,3,2,1', \Monitors::ssl_cert_expire_alert_days_default());
		$this->assertSame([15, 7, 6, 5, 4, 3, 2, 1], \Monitors::parse_ssl_cert_expire_alert_days(null));
		$this->assertSame([15, 7, 6, 5, 4, 3, 2, 1], \Monitors::parse_ssl_cert_expire_alert_days(''));
		$this->assertSame([15, 3], \Monitors::parse_ssl_cert_expire_alert_days('15, 3, 99'));
		$this->assertSame([15, 7, 6, 5, 4, 3, 2, 1], \Monitors::parse_ssl_cert_expire_alert_days('99'));
	}

	function test_http_methods() {
		$this->assertSame([
			'GET' => 'GET',
			'HEAD' => 'HEAD',
			'POST' => 'POST',
			'PUT' => 'PUT',
			'DELETE' => 'DELETE',
			'CONNECT' => 'CONNECT',
			'OPTIONS' => 'OPTIONS',
			'TRACE' => 'TRACE',
			'PATCH' => 'PATCH',
		], \Monitors::HTTP_methods());
	}
}

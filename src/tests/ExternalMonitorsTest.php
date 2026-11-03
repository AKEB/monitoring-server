<?php

error_reporting(E_ALL);

class ExternalMonitorsTest extends PHPUnit\Framework\TestCase {

	protected function tearDown(): void {
		$_SERVER = [];
		$_GET = [];
	}

	function test_parse_status_param() {
		$this->assertNull(\ExternalMonitors::parseStatusParam(null));
		$this->assertNull(\ExternalMonitors::parseStatusParam('maybe'));
		$this->assertSame(\MonitoringLogs::STATUS_GOOD, \ExternalMonitors::parseStatusParam('ok'));
		$this->assertSame(\MonitoringLogs::STATUS_GOOD, \ExternalMonitors::parseStatusParam('GOOD'));
		$this->assertSame(\MonitoringLogs::STATUS_GOOD, \ExternalMonitors::parseStatusParam('  up  '));
		$this->assertSame(\MonitoringLogs::STATUS_GOOD, \ExternalMonitors::parseStatusParam('1'));
		$this->assertSame(\MonitoringLogs::STATUS_FAIL, \ExternalMonitors::parseStatusParam('fail'));
		$this->assertSame(\MonitoringLogs::STATUS_FAIL, \ExternalMonitors::parseStatusParam('error'));
		$this->assertSame(\MonitoringLogs::STATUS_FAIL, \ExternalMonitors::parseStatusParam('down'));
		$this->assertSame(\MonitoringLogs::STATUS_FAIL, \ExternalMonitors::parseStatusParam('0'));
	}

	function test_sanitize_log() {
		$this->assertSame('', \ExternalMonitors::sanitizeLog('   '));
		$this->assertSame('x', \ExternalMonitors::sanitizeLog('<b>x</b>'));
		$this->assertSame("a\nb", \ExternalMonitors::sanitizeLog("a\r\nb"));
		$this->assertSame(2000, mb_strlen(\ExternalMonitors::sanitizeLog(str_repeat('я', 2500))));
	}

	function test_format_fail_error() {
		$this->assertSame('External status: fail', \ExternalMonitors::formatFailError(''));
		$this->assertSame('Log: disk full', \ExternalMonitors::formatFailError('disk full'));
		$this->assertSame('Log: &lt;b&gt;x&lt;/b&gt;', \ExternalMonitors::formatFailError('<b>x</b>'));
		$long = str_repeat('a', 130);
		$formatted = \ExternalMonitors::formatFailError($long);
		$this->assertStringStartsWith('Log: ', $formatted);
		$this->assertStringEndsWith('...', $formatted);
		$this->assertSame(5 + 120, strlen($formatted));
	}

	function test_parse_log_body_from_response_error() {
		$this->assertNull(\ExternalMonitors::parseLogBodyFromResponseError(''));
		$this->assertNull(\ExternalMonitors::parseLogBodyFromResponseError('Alert: boom'));
		$this->assertSame('disk full', \ExternalMonitors::parseLogBodyFromResponseError('Log: disk full'));
	}

	function test_is_valid_hash() {
		$this->assertFalse(\ExternalMonitors::isValidHash(''));
		$this->assertFalse(\ExternalMonitors::isValidHash(str_repeat('g', 64)));
		$this->assertFalse(\ExternalMonitors::isValidHash(str_repeat('a', 63)));
		$this->assertTrue(\ExternalMonitors::isValidHash(str_repeat('ab', 32)));
	}

	function test_base_url_https_and_host_fallback() {
		$_SERVER = ['HTTP_HOST' => 'mon.example', 'HTTPS' => 'on'];
		$this->assertSame('https://mon.example', \ExternalMonitors::baseUrl());

		$_SERVER = ['SERVER_NAME' => 'only-name', 'SERVER_PORT' => '443'];
		$this->assertSame('https://only-name', \ExternalMonitors::baseUrl());

		$_SERVER = ['HTTP_HOST' => 'fwd.example', 'HTTP_X_FORWARDED_PROTO' => 'https'];
		$this->assertSame('https://fwd.example', \ExternalMonitors::baseUrl());

		$_SERVER = [];
		$this->assertSame('http://localhost', \ExternalMonitors::baseUrl());
	}

	function test_urls_for_monitor() {
		$_SERVER = ['HTTP_HOST' => 'mon.example', 'HTTPS' => 'on'];
		$this->assertSame(['ok' => '', 'fail' => ''], \ExternalMonitors::urlsForMonitor(['external_hash' => '']));

		$hash = str_repeat('ab', 32);
		$urls = \ExternalMonitors::urlsForMonitor(['external_hash' => $hash]);
		$this->assertSame(
			'https://mon.example/api/external/'.$hash.'/?status=ok',
			$urls['ok']
		);
		$this->assertSame(
			'https://mon.example/api/external/'.$hash.'/?status=fail',
			$urls['fail']
		);
	}
}

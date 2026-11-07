<?php

error_reporting(E_ALL);

class ServerAgentPortTest extends PHPUnit\Framework\TestCase {

	function test_eval_port_listening_match_any_ip() {
		$payload = [
			'listeners' => [
				['protocol' => 'tcp', 'ip' => '127.0.0.1', 'port' => 22],
				['protocol' => 'udp', 'ip' => '0.0.0.0', 'port' => 53],
			],
		];
		[$status, $code, $error, $result] = \ServerAgent::evalPortListening(22, 'tcp', $payload);
		$this->assertSame(\MonitoringLogs::STATUS_GOOD, $status);
		$this->assertSame(200, $code);
		$this->assertSame('', $error);
		$this->assertSame('tcp/22@127.0.0.1', $result);

		[$status, $code, $error, $result] = \ServerAgent::evalPortListening(53, 'UDP', $payload);
		$this->assertSame(\MonitoringLogs::STATUS_GOOD, $status);
		$this->assertSame(200, $code);
		$this->assertSame('', $error);
		$this->assertSame('udp/53@0.0.0.0', $result);
	}

	function test_eval_port_listening_missing() {
		$payload = [
			'listeners' => [
				['protocol' => 'tcp', 'ip' => '0.0.0.0', 'port' => 80],
			],
		];
		[$status, $code, $error, $result] = \ServerAgent::evalPortListening(443, 'tcp', $payload);
		$this->assertSame(\MonitoringLogs::STATUS_FAIL, $status);
		$this->assertSame(503, $code);
		$this->assertSame('port not listening', $error);
		$this->assertSame('tcp/443', $result);
	}

	function test_eval_port_listening_invalid_port() {
		[$status, $code, $error] = \ServerAgent::evalPortListening(0, 'tcp', []);
		$this->assertSame(\MonitoringLogs::STATUS_FAIL, $status);
		$this->assertSame(404, $code);
		$this->assertSame('port not set', $error);
	}

	function test_eval_port_protocol_mismatch() {
		$payload = [
			'listeners' => [
				['protocol' => 'tcp', 'ip' => '0.0.0.0', 'port' => 53],
			],
		];
		[$status, $code, $error] = \ServerAgent::evalPortListening(53, 'udp', $payload);
		$this->assertSame(\MonitoringLogs::STATUS_FAIL, $status);
		$this->assertSame(503, $code);
		$this->assertSame('port not listening', $error);
	}

	function test_remove_virtual_job_is_noop_without_job() {
		// Method must exist and tolerate missing rows (no DB fixture in unit tests).
		$this->assertTrue(method_exists(\ServerAgent::class, 'removeVirtualJob'));
		\ServerAgent::removeVirtualJob(0);
		$this->assertTrue(true);
	}

	function test_monitor_log_bucket_throttle_fills_each_minute() {
		$repeat = 60;
		// Old elapsed throttle: write at :50 → next after +60s lands in minute+2.
		$writeAt = strtotime('2026-11-07 04:10:50 UTC');
		$nextMinute = strtotime('2026-11-07 04:11:15 UTC'); // agent report inside 04:11
		$job = ['update_time' => $writeAt];

		$this->assertSame(strtotime('2026-11-07 04:10:00 UTC'), \ServerAgent::monitorLogBucket($writeAt, $repeat));
		$this->assertTrue(\ServerAgent::shouldWriteMonitorLog($job, $nextMinute, $repeat));
		$this->assertFalse(\ServerAgent::shouldWriteMonitorLog(
			['update_time' => $nextMinute],
			strtotime('2026-11-07 04:11:45 UTC'),
			$repeat
		));
		$this->assertTrue(\ServerAgent::shouldWriteMonitorLog(
			['update_time' => $nextMinute],
			strtotime('2026-11-07 04:12:00 UTC'),
			$repeat
		));
	}
}

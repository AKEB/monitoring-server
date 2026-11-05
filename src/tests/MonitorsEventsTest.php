<?php

error_reporting(E_ALL);

class MonitorsEventsTest extends PHPUnit\Framework\TestCase {

	function test_event_hash_ok_ignores_response_error_text() {
		$base = [
			'monitor_id' => 11,
			'worker_id' => -2,
			'status' => \MonitoringLogs::STATUS_GOOD,
			'status_code' => 200,
			'response_error_num' => 0,
		];
		$a = \MonitorsEvents::eventHash($base + ['response_error' => 'value=18.3']);
		$b = \MonitorsEvents::eventHash($base + ['response_error' => 'value=24.91']);
		$this->assertSame($a, $b);
	}

	function test_event_hash_fail_uses_response_error_text() {
		$base = [
			'monitor_id' => 11,
			'worker_id' => -2,
			'status' => \MonitoringLogs::STATUS_FAIL,
			'status_code' => 200,
			'response_error_num' => 1,
		];
		$a = \MonitorsEvents::eventHash($base + ['response_error' => 'value=95.1; threshold exceeded']);
		$b = \MonitorsEvents::eventHash($base + ['response_error' => 'value=96.2; threshold exceeded']);
		$this->assertNotSame($a, $b);

		$same = \MonitorsEvents::eventHash($base + ['response_error' => 'value=95.1; threshold exceeded']);
		$this->assertSame($a, $same);
	}

	function test_event_hash_status_change_differs() {
		$fail = \MonitorsEvents::eventHash([
			'monitor_id' => 11,
			'worker_id' => -2,
			'status' => \MonitoringLogs::STATUS_FAIL,
			'status_code' => 200,
			'response_error_num' => 1,
			'response_error' => 'value=99',
		]);
		$ok = \MonitorsEvents::eventHash([
			'monitor_id' => 11,
			'worker_id' => -2,
			'status' => \MonitoringLogs::STATUS_GOOD,
			'status_code' => 200,
			'response_error_num' => 0,
			'response_error' => 'value=10',
		]);
		$this->assertNotSame($fail, $ok);
	}
}

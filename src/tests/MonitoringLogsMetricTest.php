<?php

error_reporting(E_ALL);

class MonitoringLogsMetricTest extends PHPUnit\Framework\TestCase {

	function test_format_metric_log_message() {
		$this->assertSame('value=12.5', \MonitoringLogs::formatMetricLogMessage('12.5', ''));
		$this->assertSame('value=12.5; threshold exceeded', \MonitoringLogs::formatMetricLogMessage('12.5', 'threshold exceeded'));
		$this->assertSame('threshold exceeded', \MonitoringLogs::formatMetricLogMessage('', 'threshold exceeded'));
		$this->assertSame('', \MonitoringLogs::formatMetricLogMessage('', ''));
		$long = str_repeat('a', 300);
		$this->assertSame(255, mb_strlen(\MonitoringLogs::formatMetricLogMessage($long, '')));
	}

	function test_parse_metric_value() {
		$this->assertNull(\MonitoringLogs::parseMetricValue(null));
		$this->assertNull(\MonitoringLogs::parseMetricValue(''));
		$this->assertNull(\MonitoringLogs::parseMetricValue('threshold exceeded'));
		$this->assertSame(12.5, \MonitoringLogs::parseMetricValue('value=12.5'));
		$this->assertSame(90.0, \MonitoringLogs::parseMetricValue('value=90; threshold exceeded'));
		$this->assertSame(42.0, \MonitoringLogs::parseMetricValue('42'));
		$this->assertSame(7.25, \MonitoringLogs::parseMetricValue('7.25'));
	}

	function test_metric_type_helpers() {
		$this->assertTrue(\Monitors::is_metric_value_type(\Monitors::TYPE_SERVER_DISK));
		$this->assertTrue(\Monitors::is_metric_value_type(\Monitors::TYPE_SERVER_NET_RX));
		$this->assertTrue(\Monitors::is_metric_value_type(\Monitors::TYPE_SERVER_NET_TX));
		$this->assertTrue(\Monitors::is_metric_value_type(\Monitors::TYPE_SERVER_CPU));
		$this->assertTrue(\Monitors::is_metric_value_type(\Monitors::TYPE_SERVER_MEMORY));
		$this->assertTrue(\Monitors::is_metric_value_type(\Monitors::TYPE_SERVER_OFFLINE));
		$this->assertTrue(\Monitors::is_metric_value_type(\Monitors::TYPE_EXPORTER_MEMORY));
		$this->assertFalse(\Monitors::is_metric_value_type(\Monitors::TYPE_SERVER_CONTAINER));
		$this->assertFalse(\Monitors::is_metric_value_type(\Monitors::TYPE_HTTPS));

		foreach ([
			\Monitors::TYPE_SERVER_DISK,
			\Monitors::TYPE_SERVER_NET_RX,
			\Monitors::TYPE_SERVER_NET_TX,
			\Monitors::TYPE_SERVER_CPU,
			\Monitors::TYPE_SERVER_MEMORY,
			\Monitors::TYPE_SERVER_OFFLINE,
			\Monitors::TYPE_SERVER_CONTAINER,
			\Monitors::TYPE_EXTERNAL,
			\Monitors::TYPE_EXPORTER_DISK,
		] as $type) {
			$this->assertTrue(\Monitors::hides_response_time_ui($type), 'type '.$type);
		}
		$this->assertFalse(\Monitors::hides_response_time_ui(\Monitors::TYPE_HTTPS));

		$this->assertSame('%', \Monitors::metric_chart_unit(\Monitors::TYPE_SERVER_DISK));
		$this->assertSame('%', \Monitors::metric_chart_unit(\Monitors::TYPE_SERVER_CPU));
		$this->assertSame('Mbps', \Monitors::metric_chart_unit(\Monitors::TYPE_SERVER_NET_RX));
		$this->assertSame('Mbps', \Monitors::metric_chart_unit(\Monitors::TYPE_SERVER_NET_TX));
		$this->assertSame('min', \Monitors::metric_chart_unit(\Monitors::TYPE_SERVER_OFFLINE));
	}

	function test_server_worker_title_fallback() {
		$this->assertNotSame('', \Servers::workerTitle());
		$this->assertSame(\Servers::workerTitle(), \Servers::workerTitleForMonitor(['server' => 0]));
	}

	function test_trim_trailing_empty_log_slots() {
		$repeat = 60;
		$now = 1000;
		$logs = [
			900 => ['title' => 'a', 1 => ['status' => 1]],
			960 => ['title' => 'b'], // empty open interval (960+60 > 1000)
			1020 => ['title' => 'c'], // empty future-ish
		];
		\MonitoringLogs::trimTrailingEmptyLogSlots($logs, $repeat, $now);
		$this->assertArrayHasKey(900, $logs);
		$this->assertArrayNotHasKey(960, $logs);
		$this->assertArrayNotHasKey(1020, $logs);

		$logs = [
			840 => ['title' => 'old-empty'], // 840+60 <= 1000 → keep real gap
			900 => ['title' => 'ok', 1 => ['status' => 1]],
		];
		\MonitoringLogs::trimTrailingEmptyLogSlots($logs, $repeat, $now);
		$this->assertArrayHasKey(840, $logs);
		$this->assertArrayHasKey(900, $logs);
	}

	function test_is_open_interval() {
		$this->assertTrue(\MonitoringLogs::isOpenInterval(960, 60, 1000));
		$this->assertFalse(\MonitoringLogs::isOpenInterval(900, 60, 1000));
		$this->assertFalse(\MonitoringLogs::isOpenInterval(1020, 60, 1000));
		$this->assertFalse(\MonitoringLogs::isOpenInterval(960, 0, 1000));
	}

	function test_open_unknown_is_optimistic_green() {
		$workers = [1 => 1, 2 => 1, 3 => 1];
		$states = [
			1 => \MonitoringLogs::STATE_SUCCESS,
			// 2 and 3 still unknown in the open minute
		];
		$counts = \MonitoringLogs::countWorkerStatesForSlot($workers, $states, true);
		$this->assertSame(0, $counts['error']);
		$this->assertSame(3, $counts['success']);
		$this->assertSame(0, $counts['unknown']);
		$this->assertSame(
			'bg-success',
			\MonitoringLogs::classForCountedStates($counts['error'], $counts['success'])
		);
	}

	function test_closed_unknown_stays_grey() {
		$workers = [1 => 1, 2 => 1];
		$allUnknown = \MonitoringLogs::countWorkerStatesForSlot($workers, [], false);
		$this->assertSame(0, $allUnknown['error']);
		$this->assertSame(0, $allUnknown['success']);
		$this->assertSame(2, $allUnknown['unknown']);
		$this->assertSame(
			'bg-secondary',
			\MonitoringLogs::classForCountedStates($allUnknown['error'], $allUnknown['success'])
		);

		// Partial report on a closed slot: known success still wins (legacy rule).
		$partial = \MonitoringLogs::countWorkerStatesForSlot(
			$workers,
			[1 => \MonitoringLogs::STATE_SUCCESS],
			false
		);
		$this->assertSame(1, $partial['unknown']);
		$this->assertSame(
			'bg-success',
			\MonitoringLogs::classForCountedStates($partial['error'], $partial['success'])
		);
	}

	function test_real_fail_wins_over_optimistic_unknown() {
		$workers = [1 => 1, 2 => 1, 3 => 1];
		$states = [
			1 => \MonitoringLogs::STATE_ERROR,
			// 2,3 unknown → optimistic success
		];
		$counts = \MonitoringLogs::countWorkerStatesForSlot($workers, $states, true);
		$this->assertSame(1, $counts['error']);
		$this->assertSame(2, $counts['success']);
		$this->assertSame(
			'bg-warning',
			\MonitoringLogs::classForCountedStates($counts['error'], $counts['success'])
		);

		$statesMajorityFail = [
			1 => \MonitoringLogs::STATE_ERROR,
			2 => \MonitoringLogs::STATE_ERROR,
		];
		$countsFail = \MonitoringLogs::countWorkerStatesForSlot($workers, $statesMajorityFail, true);
		$this->assertSame(
			'bg-danger',
			\MonitoringLogs::classForCountedStates($countsFail['error'], $countsFail['success'])
		);
	}

	function test_monitor_data_cache_gen_key() {
		$this->assertSame('monitor_data_gen_58', \MonitoringLogs::monitorDataCacheGenKey(58));
	}
}

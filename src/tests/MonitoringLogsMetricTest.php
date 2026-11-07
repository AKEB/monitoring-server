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
		$horizon = 1000;
		$logs = [
			900 => ['title' => 'a', 1 => ['status' => 1]],
			960 => ['title' => 'b'], // empty, still inside grace (960+60 > 1000)
			1020 => ['title' => 'c'], // empty future-ish
		];
		\MonitoringLogs::trimTrailingEmptyLogSlots($logs, $repeat, $horizon);
		$this->assertArrayHasKey(900, $logs);
		$this->assertArrayNotHasKey(960, $logs);
		$this->assertArrayNotHasKey(1020, $logs);

		$logs = [
			840 => ['title' => 'old-empty'], // 840+60 <= 1000 → keep real gap
			900 => ['title' => 'ok', 1 => ['status' => 1]],
		];
		\MonitoringLogs::trimTrailingEmptyLogSlots($logs, $repeat, $horizon);
		$this->assertArrayHasKey(840, $logs);
		$this->assertArrayHasKey(900, $logs);
	}
}

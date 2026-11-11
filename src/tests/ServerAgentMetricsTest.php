<?php

error_reporting(E_ALL);

class ServerAgentMetricsTest extends PHPUnit\Framework\TestCase {

	function test_new_monitor_types_registered() {
		$types = \Monitors::server_types();
		$this->assertContains(\Monitors::TYPE_SERVER_TEMP, $types);
		$this->assertContains(\Monitors::TYPE_SERVER_UPTIME, $types);
		$this->assertContains(\Monitors::TYPE_SERVER_CONTAINER_CPU, $types);
		$this->assertContains(\Monitors::TYPE_SERVER_CONTAINER_MEMORY, $types);
		$this->assertContains(\Monitors::TYPE_SERVER_SYSTEMD, $types);
		$this->assertContains(\Monitors::TYPE_SERVER_SYSTEMD_CPU, $types);
		$this->assertContains(\Monitors::TYPE_SERVER_SYSTEMD_MEMORY, $types);

		$this->assertTrue(\Monitors::is_metric_value_type(\Monitors::TYPE_SERVER_TEMP));
		$this->assertTrue(\Monitors::is_metric_value_type(\Monitors::TYPE_SERVER_UPTIME));
		$this->assertTrue(\Monitors::is_metric_value_type(\Monitors::TYPE_SERVER_CONTAINER_CPU));
		$this->assertFalse(\Monitors::is_metric_value_type(\Monitors::TYPE_SERVER_SYSTEMD));
		$this->assertSame('°C', \Monitors::metric_chart_unit(\Monitors::TYPE_SERVER_TEMP));
		$this->assertSame('min', \Monitors::metric_chart_unit(\Monitors::TYPE_SERVER_UPTIME));
		$this->assertSame('MB', \Monitors::metric_chart_unit(\Monitors::TYPE_SERVER_SYSTEMD_MEMORY));
	}

	function test_build_metric_details() {
		$details = \ServerAgent::buildMetricDetails(
			[
				['sensor' => 'cpu_thermal', 'temp_c' => 42.5],
				['sensor' => '', 'temp_c' => 10],
			],
			[
				'available' => true,
				'containers' => [
					['name' => 'web', 'state' => 'running', 'cpu_pct' => 12.3, 'mem_bytes' => 1048576],
					['name' => 'stopped', 'state' => 'exited', 'cpu_pct' => 1, 'mem_bytes' => 1],
				],
			]
		);
		$this->assertNotNull($details);
		$decoded = json_decode($details, true);
		$this->assertSame(42.5, $decoded['temps']['cpu_thermal']);
		$this->assertSame(12.3, $decoded['docker_cpu']['web']);
		$this->assertSame(1048576, $decoded['docker_mem']['web']);
		$this->assertArrayNotHasKey('stopped', $decoded['docker_cpu']);
	}

	function test_temp_from_payload() {
		$payload = [
			'temperatures' => [
				['sensor' => 'a', 'temp_c' => 30],
				['sensor' => 'b', 'temp_c' => 55],
			],
		];
		$this->assertSame(55.0, \ServerAgent::tempCFromPayload($payload, '', 0));
		$this->assertSame(30.0, \ServerAgent::tempCFromPayload($payload, 'a', 0));
		$this->assertSame(999.0, \ServerAgent::tempCFromPayload($payload, 'missing', 0));
	}

	function test_container_metric_from_payload() {
		$payload = [
			'docker' => [
				'available' => true,
				'containers' => [
					['name' => 'api', 'cpu_pct' => 7.5, 'mem_pct' => 33.1],
				],
			],
		];
		[$ok, $cpu] = \ServerAgent::containerMetricFromPayload($payload, 'api', \Monitors::TYPE_SERVER_CONTAINER_CPU);
		$this->assertTrue($ok);
		$this->assertSame(7.5, $cpu);
		[$okMem, $mem] = \ServerAgent::containerMetricFromPayload($payload, 'api', \Monitors::TYPE_SERVER_CONTAINER_MEMORY);
		$this->assertTrue($okMem);
		$this->assertSame(33.1, $mem);
		[$okMissing] = \ServerAgent::containerMetricFromPayload($payload, 'nope', \Monitors::TYPE_SERVER_CONTAINER_CPU);
		$this->assertFalse($okMissing);
	}

	function test_systemd_metric_from_payload() {
		$payload = [
			'systemd' => [
				'available' => true,
				'units' => [
					['name' => 'nginx.service', 'cpu_pct' => 1.2, 'mem_bytes' => 2097152],
				],
			],
		];
		[$ok, $cpu] = \ServerAgent::systemdMetricFromPayload($payload, 'nginx.service', \Monitors::TYPE_SERVER_SYSTEMD_CPU);
		$this->assertTrue($ok);
		$this->assertSame(1.2, $cpu);
		[$okMem, $mem] = \ServerAgent::systemdMetricFromPayload($payload, 'nginx.service', \Monitors::TYPE_SERVER_SYSTEMD_MEMORY);
		$this->assertTrue($okMem);
		$this->assertSame(2097152.0, $mem);
	}

	function test_eval_port_still_works() {
		$payload = [
			'listeners' => [
				['protocol' => 'tcp', 'port' => 22, 'ip' => '0.0.0.0'],
			],
		];
		[$status] = \ServerAgent::evalPortListening(22, 'tcp', $payload);
		$this->assertSame(\MonitoringLogs::STATUS_GOOD, $status);
	}
}

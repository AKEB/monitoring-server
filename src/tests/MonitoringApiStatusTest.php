<?php

error_reporting(E_ALL);

class MonitoringApiStatusTest extends PHPUnit\Framework\TestCase {

	function test_percent_null_when_no_samples() {
		$this->assertNull(\MonitoringAvailabilityPercent::percent(0, 0));
		$this->assertNull(\MonitoringAvailabilityPercent::percent(5, 0));
	}

	function test_percent_formula() {
		$this->assertSame(50.0, \MonitoringAvailabilityPercent::percent(1, 2));
		$this->assertSame(100.0, \MonitoringAvailabilityPercent::percent(10, 10));
		$this->assertSame(99.99, \MonitoringAvailabilityPercent::percent(9999, 10000));
	}

	function test_empty_windows_keys() {
		$windows = \MonitoringAvailabilityPercent::emptyWindows();
		$this->assertSame(['1m', '5m', '10m', '1h', '4h', '8h'], array_keys($windows));
		foreach ($windows as $value) {
			$this->assertNull($value);
		}
	}

	function test_rollup_sums_checks_not_percents() {
		$rolled = \MonitoringAvailabilityPercent::rollupWindows([
			1 => [
				'good' => ['1m' => 1, '5m' => 1, '10m' => 0, '1h' => 0, '4h' => 0, '8h' => 0],
				'total' => ['1m' => 1, '5m' => 2, '10m' => 0, '1h' => 0, '4h' => 0, '8h' => 0],
				'percent' => [],
			],
			2 => [
				'good' => ['1m' => 0, '5m' => 2, '10m' => 0, '1h' => 0, '4h' => 0, '8h' => 0],
				'total' => ['1m' => 1, '5m' => 2, '10m' => 0, '1h' => 0, '4h' => 0, '8h' => 0],
				'percent' => [],
			],
		]);
		$this->assertSame(50.0, $rolled['1m']);
		$this->assertSame(75.0, $rolled['5m']);
		$this->assertNull($rolled['1h']);
	}

	function test_check_url_by_type() {
		$this->assertSame('https://ex.com/health', \MonitoringApiStatus::checkUrl([
			'type' => \Monitors::TYPE_HTTPS,
			'url' => 'https://ex.com/health',
		]));
		$this->assertSame('https://ex.com/json', \MonitoringApiStatus::checkUrl([
			'type' => \Monitors::TYPE_HTTP_JSON,
			'url' => 'https://ex.com/json',
		]));
		$this->assertSame('tcp://db.local:3306', \MonitoringApiStatus::checkUrl([
			'type' => \Monitors::TYPE_TCP,
			'host' => 'db.local',
			'port' => 3306,
		]));
		$this->assertSame('icmp://8.8.8.8', \MonitoringApiStatus::checkUrl([
			'type' => \Monitors::TYPE_PING,
			'host' => '8.8.8.8',
		]));
		$this->assertSame('docker://prod/nginx', \MonitoringApiStatus::checkUrl(
			['type' => \Monitors::TYPE_DOCKER, 'docker' => 4, 'container' => 'nginx'],
			[4 => ['title' => 'prod']]
		));
		$this->assertSame('docker://prod', \MonitoringApiStatus::checkUrl(
			['type' => \Monitors::TYPE_DOCKER, 'docker' => 4, 'container' => ''],
			[4 => ['title' => 'prod']]
		));
		$this->assertSame('node:9100', \MonitoringApiStatus::checkUrl([
			'type' => \Monitors::TYPE_EXPORTER_DISK,
			'host' => 'node',
			'port' => 9100,
		]));
		$this->assertSame('node', \MonitoringApiStatus::checkUrl([
			'type' => \Monitors::TYPE_EXPORTER_MEMORY,
			'host' => 'node',
			'port' => 0,
		]));
		$this->assertSame('external://abcd', \MonitoringApiStatus::checkUrl([
			'type' => \Monitors::TYPE_EXTERNAL,
			'external_hash' => 'abcd',
		]));
		$this->assertSame('external://', \MonitoringApiStatus::checkUrl([
			'type' => \Monitors::TYPE_EXTERNAL,
			'external_hash' => '',
		]));
	}

	function test_availability_windows_and_percent_cap() {
		$this->assertSame([
			'1m' => 60,
			'5m' => 300,
			'10m' => 600,
			'1h' => 3600,
			'4h' => 14400,
			'8h' => 28800,
		], \MonitoringAvailabilityPercent::WINDOWS);
		$this->assertSame(100.0, \MonitoringAvailabilityPercent::percent(3, 2));
		$empty = \MonitoringAvailabilityPercent::rollupWindows([]);
		$this->assertSame(['1m', '5m', '10m', '1h', '4h', '8h'], array_keys($empty));
		foreach ($empty as $value) {
			$this->assertNull($value);
		}
	}

	function test_aggregate_status() {
		$this->assertSame(\MonitoringApiStatus::STATUS_MAINTENANCE, \MonitoringApiStatus::aggregateResourceStatus(true, [\MonitoringApiStatus::STATUS_UP]));
		$this->assertSame(\MonitoringApiStatus::STATUS_DOWN, \MonitoringApiStatus::aggregateResourceStatus(false, [\MonitoringApiStatus::STATUS_UP, \MonitoringApiStatus::STATUS_DOWN]));
		$this->assertSame(\MonitoringApiStatus::STATUS_UP, \MonitoringApiStatus::aggregateResourceStatus(false, [\MonitoringApiStatus::STATUS_UP, \MonitoringApiStatus::STATUS_UNKNOWN]));
		$this->assertSame(\MonitoringApiStatus::STATUS_UNKNOWN, \MonitoringApiStatus::aggregateResourceStatus(false, [\MonitoringApiStatus::STATUS_UNKNOWN]));
		$this->assertSame(\MonitoringApiStatus::STATUS_UNKNOWN, \MonitoringApiStatus::aggregateResourceStatus(false, []));
	}

	function test_worker_status_from_log() {
		$now = 1_700_000_000;
		$this->assertSame(\MonitoringApiStatus::STATUS_MAINTENANCE, \MonitoringApiStatus::workerStatusFromLog(true, ['status' => 1, 'update_time' => $now], $now, 60));
		$this->assertSame(\MonitoringApiStatus::STATUS_UP, \MonitoringApiStatus::workerStatusFromLog(false, ['status' => \MonitoringLogs::STATUS_GOOD, 'update_time' => $now - 10], $now, 60));
		$this->assertSame(\MonitoringApiStatus::STATUS_DOWN, \MonitoringApiStatus::workerStatusFromLog(false, ['status' => \MonitoringLogs::STATUS_FAIL, 'update_time' => $now - 10], $now, 60));
		$this->assertSame(\MonitoringApiStatus::STATUS_UNKNOWN, \MonitoringApiStatus::workerStatusFromLog(false, ['status' => \MonitoringLogs::STATUS_GOOD, 'update_time' => $now - 121], $now, 60));
		$this->assertSame(\MonitoringApiStatus::STATUS_UNKNOWN, \MonitoringApiStatus::workerStatusFromLog(false, null, $now, 60));
	}

	function test_ssl_expire_fields() {
		$this->assertSame(
			['ssl_expire_time' => null, 'ssl_expire_at' => null],
			\MonitoringApiStatus::sslExpireFields(null)
		);
		$this->assertSame(
			['ssl_expire_time' => null, 'ssl_expire_at' => null],
			\MonitoringApiStatus::sslExpireFields(0)
		);
		$ts = mktime(12, 0, 0, 12, 31, 2026);
		$fields = \MonitoringApiStatus::sslExpireFields($ts);
		$this->assertSame($ts, $fields['ssl_expire_time']);
		$this->assertSame('2026-12-31', $fields['ssl_expire_at']);
	}

	function test_monitor_is_under_targets_walks_parents() {
		$hash = [
			1 => ['id' => 1, 'parent_id' => 0],
			2 => ['id' => 2, 'parent_id' => 1],
			3 => ['id' => 3, 'parent_id' => 2],
			4 => ['id' => 4, 'parent_id' => 0],
		];
		$this->assertTrue(\MonitoringApiStatus::monitorIsUnderTargets(3, [1 => true], $hash));
		$this->assertTrue(\MonitoringApiStatus::monitorIsUnderTargets(3, [3 => true], $hash));
		$this->assertFalse(\MonitoringApiStatus::monitorIsUnderTargets(3, [4 => true], $hash));
		$this->assertFalse(\MonitoringApiStatus::monitorIsUnderTargets(3, [], $hash));
	}

	function test_token_hash_and_prefix() {
		$plain = \UserApiTokens::generatePlaintext();
		$this->assertStringStartsWith('mnt_', $plain);
		$this->assertSame(4 + 48, strlen($plain));
		$this->assertSame(64, strlen(\UserApiTokens::hashToken($plain)));
		$this->assertSame(substr($plain, 0, 12), \UserApiTokens::prefixFromPlaintext($plain));
		$this->assertNotSame($plain, \UserApiTokens::hashToken($plain));
	}
}

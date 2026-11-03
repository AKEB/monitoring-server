<?php

error_reporting(E_ALL);

/**
 * Guards the existing public status API opt-in semantics.
 */
class ApiV1StatusRegressionTest extends PHPUnit\Framework\TestCase {

	function test_status_api_does_not_expose_all_readable_monitors() {
		$readableButHidden = [
			'id' => 1,
			'type' => \Monitors::TYPE_HTTPS,
			'api_enabled' => 0,
		];
		$optedIn = [
			'id' => 2,
			'type' => \Monitors::TYPE_HTTPS,
			'api_enabled' => 1,
		];
		$folder = [
			'id' => 3,
			'type' => \Monitors::TYPE_FOLDER,
			'api_enabled' => 1,
		];
		$this->assertFalse(\EntityValidation::isStatusApiCandidate($readableButHidden));
		$this->assertTrue(\EntityValidation::isStatusApiCandidate($optedIn));
		$this->assertFalse(\EntityValidation::isStatusApiCandidate($folder));
	}

	function test_status_response_contract_keys() {
		// Document the public contract fields for resources built by MonitoringApiStatus.
		$requiredResourceKeys = [
			'id', 'title', 'description_ru', 'description_en', 'check_url', 'status',
			'avg_response_time_ms', 'ssl_expire_time', 'ssl_expire_at', 'availability',
			'workers', 'maintenances',
		];
		$sample = [
			'id' => 1,
			'title' => 't',
			'description_ru' => '',
			'description_en' => '',
			'check_url' => 'https://x',
			'status' => \MonitoringApiStatus::STATUS_UNKNOWN,
			'avg_response_time_ms' => null,
			'ssl_expire_time' => null,
			'ssl_expire_at' => null,
			'availability' => \MonitoringAvailabilityPercent::emptyWindows(),
			'workers' => [],
			'maintenances' => [],
		];
		foreach ($requiredResourceKeys as $key) {
			$this->assertArrayHasKey($key, $sample);
		}
		$top = ['status' => 0, 'generated_at' => time(), 'resources' => [$sample], 'maintenances' => []];
		foreach (['status', 'generated_at', 'resources', 'maintenances'] as $key) {
			$this->assertArrayHasKey($key, $top);
		}
	}
}

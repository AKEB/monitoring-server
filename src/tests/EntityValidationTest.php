<?php

error_reporting(E_ALL);

class EntityValidationTest extends PHPUnit\Framework\TestCase {

	function test_title_length() {
		$this->assertFalse(\EntityValidation::isTitleLengthValid('a'));
		$this->assertTrue(\EntityValidation::isTitleLengthValid('ab'));
		$this->assertTrue(\EntityValidation::isTitleLengthValid(str_repeat('x', 255)));
		$this->assertFalse(\EntityValidation::isTitleLengthValid(str_repeat('x', 256)));
	}

	function test_signature_title_allows_single_char() {
		$this->assertTrue(\EntityValidation::isSignatureTitleValid('a'));
		$this->assertFalse(\EntityValidation::isSignatureTitleValid(''));
	}

	function test_project_length() {
		$this->assertTrue(\EntityValidation::isProjectLengthValid(''));
		$this->assertTrue(\EntityValidation::isProjectLengthValid(str_repeat('p', 255)));
		$this->assertFalse(\EntityValidation::isProjectLengthValid(str_repeat('p', 256)));
	}

	function test_folder_never_api_enabled() {
		$this->assertSame(0, \EntityValidation::normalizeApiEnabled(\Monitors::TYPE_FOLDER, 'true'));
		$this->assertSame(0, \EntityValidation::normalizeApiEnabled(\Monitors::TYPE_FOLDER, 1));
	}

	function test_api_enabled_for_regular_monitor() {
		$this->assertSame(1, \EntityValidation::normalizeApiEnabled(\Monitors::TYPE_HTTPS, 'true'));
		$this->assertSame(1, \EntityValidation::normalizeApiEnabled(\Monitors::TYPE_HTTPS, true));
		$this->assertSame(0, \EntityValidation::normalizeApiEnabled(\Monitors::TYPE_HTTPS, 'false'));
		$this->assertSame(0, \EntityValidation::normalizeApiEnabled(\Monitors::TYPE_HTTPS, null));
	}

	function test_status_api_candidate_requires_opt_in_and_not_folder() {
		$this->assertFalse(\EntityValidation::isStatusApiCandidate([
			'type' => \Monitors::TYPE_HTTPS,
			'api_enabled' => 0,
		]));
		$this->assertTrue(\EntityValidation::isStatusApiCandidate([
			'type' => \Monitors::TYPE_HTTPS,
			'api_enabled' => 1,
		]));
		$this->assertFalse(\EntityValidation::isStatusApiCandidate([
			'type' => \Monitors::TYPE_FOLDER,
			'api_enabled' => 1,
		]));
	}

	function test_clamp_repeat_seconds() {
		$this->assertSame(60, \EntityValidation::clampRepeatSeconds(10));
		$this->assertSame(60, \EntityValidation::clampRepeatSeconds(60));
		$this->assertSame(120, \EntityValidation::clampRepeatSeconds(120));
	}

	function test_maintenance_time_rules() {
		$this->assertSame('required', \EntityValidation::maintenanceTimeError(null, 100));
		$this->assertSame('required', \EntityValidation::maintenanceTimeError(100, 0));
		$this->assertSame('order', \EntityValidation::maintenanceTimeError(200, 100));
		$this->assertNull(\EntityValidation::maintenanceTimeError(100, 100));
		$this->assertNull(\EntityValidation::maintenanceTimeError(100, 200));
	}

	function test_merge_json_preserves_missing_secrets() {
		$merged = \EntityValidation::mergeJsonData(
			['telegram_bot_token' => 'old-secret', 'keep' => '1'],
			['keep' => '2']
		);
		$this->assertSame('old-secret', $merged['telegram_bot_token']);
		$this->assertSame('2', $merged['keep']);
	}

	function test_tls_mime() {
		$this->assertTrue(\EntityValidation::isTlsCertMimeValid('application/x-x509-ca-cert'));
		$this->assertFalse(\EntityValidation::isTlsCertMimeValid('text/plain'));
	}
}

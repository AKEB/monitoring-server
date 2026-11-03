<?php

error_reporting(E_ALL);

class ApiV2EnvelopeTest extends PHPUnit\Framework\TestCase {

	function test_ok_envelope() {
		$out = \Api\V2\Envelope::ok(['id' => 1], ['total' => 1]);
		$this->assertSame(0, $out['status']);
		$this->assertSame(['id' => 1], $out['data']);
		$this->assertSame(['total' => 1], $out['meta']);
	}

	function test_error_envelope() {
		$out = \Api\V2\Envelope::error('Nope');
		$this->assertSame(1, $out['status']);
		$this->assertSame('Nope', $out['error']);
		$this->assertArrayNotHasKey('data', $out);
	}

	function test_rate_limit_key_differs_from_status_api() {
		$this->assertSame('api_v2_rl_5', \Api\V2\RateLimit::cacheKey(5));
		$this->assertStringNotContainsString('status', \Api\V2\RateLimit::cacheKey(5));
		$this->assertSame(60, \Api\V2\RateLimit::WINDOW_SECONDS);
		$this->assertSame(120, \Api\V2\RateLimit::MAX_REQUESTS);
	}
}

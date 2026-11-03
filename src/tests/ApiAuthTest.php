<?php

error_reporting(E_ALL);

class ApiAuthTest extends PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		$_SERVER = [];
	}

	protected function tearDown(): void {
		$_SERVER = [];
	}

	function test_extract_token_empty() {
		$this->assertSame('', \ApiAuth::extractToken());
	}

	function test_extract_bearer_from_server() {
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer mnt_abc';
		$this->assertSame('mnt_abc', \ApiAuth::extractToken());
	}

	function test_extract_bearer_case_insensitive_and_spaces() {
		$_SERVER['HTTP_AUTHORIZATION'] = 'bearer   mnt_xyz';
		$this->assertSame('mnt_xyz', \ApiAuth::extractToken());
	}

	function test_extract_redirect_authorization() {
		$_SERVER['REDIRECT_HTTP_AUTHORIZATION'] = 'Bearer mnt_redir';
		$this->assertSame('mnt_redir', \ApiAuth::extractToken());
	}

	function test_extract_x_api_token_fallback() {
		$_SERVER['HTTP_X_API_TOKEN'] = 'mnt_header';
		$this->assertSame('mnt_header', \ApiAuth::extractToken());
	}

	function test_bearer_wins_over_x_api_token() {
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer mnt_bearer';
		$_SERVER['HTTP_X_API_TOKEN'] = 'mnt_x';
		$this->assertSame('mnt_bearer', \ApiAuth::extractToken());
	}

	function test_basic_auth_ignored() {
		$_SERVER['HTTP_AUTHORIZATION'] = 'Basic dXNlcjpwYXNz';
		$_SERVER['HTTP_X_API_TOKEN'] = 'mnt_x';
		$this->assertSame('mnt_x', \ApiAuth::extractToken());
	}

	function test_cache_key_helpers() {
		$rl = new ReflectionMethod(\ApiAuth::class, 'rateLimitCacheKey');
		$rl->setAccessible(true);
		$this->assertSame('api_v1_status_rl_7', $rl->invoke(null, 7));

		$status = new ReflectionMethod(\ApiAuth::class, 'statusCacheKey');
		$status->setAccessible(true);
		$this->assertSame('api_v1_status_9', $status->invoke(null, 9));
	}
}

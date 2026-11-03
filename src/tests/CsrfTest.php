<?php

error_reporting(E_ALL);

require_once dirname(__DIR__) . '/lib/Csrf.php';

class CsrfTest extends PHPUnit\Framework\TestCase {

	function test_field_constant() {
		$this->assertSame('_csrf', Csrf::FIELD);
	}

	function test_js_post_to_url_contains_token_field() {
		$_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
		$_SERVER['HTTP_USER_AGENT'] = $_SERVER['HTTP_USER_AGENT'] ?? 'phpunit';
		$js = Csrf::jsPostToUrl("'/admin/test/' + id + '/'");
		$this->assertStringContainsString('method="POST"', $js);
		$this->assertStringContainsString('_csrf', $js);
		$this->assertStringContainsString("'/admin/test/' + id + '/'", $js);
		$this->assertStringContainsString('submit()', $js);
	}
}

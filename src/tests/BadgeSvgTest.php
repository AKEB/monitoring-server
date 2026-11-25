<?php

error_reporting(E_ALL);

class BadgeSvgTest extends \PHPUnit\Framework\TestCase {

	function test_hash_validation() {
		$this->assertTrue(\BadgeSvg::isValidHash(str_repeat('a', 64)));
		$this->assertTrue(\BadgeSvg::isValidHash(str_repeat('AB', 32)));
		$this->assertFalse(\BadgeSvg::isValidHash(str_repeat('a', 63)));
		$this->assertFalse(\BadgeSvg::isValidHash('zzzz'));
		$this->assertFalse(\BadgeSvg::isValidHash(''));
	}

	function test_status_colors_and_labels() {
		$this->assertSame('Up', \BadgeSvg::statusLabel(\BadgeSvg::STATUS_UP));
		$this->assertSame('Down', \BadgeSvg::statusLabel(\BadgeSvg::STATUS_DOWN));
		$this->assertSame('Maintenance', \BadgeSvg::statusLabel(\BadgeSvg::STATUS_MAINTENANCE));
		$this->assertSame('Unknown', \BadgeSvg::statusLabel(\BadgeSvg::STATUS_UNKNOWN));

		$this->assertSame('#198754', \BadgeSvg::statusColor(\BadgeSvg::STATUS_UP));
		$this->assertSame('#dc3545', \BadgeSvg::statusColor(\BadgeSvg::STATUS_DOWN));
		$this->assertSame('#ffc107', \BadgeSvg::statusColor(\BadgeSvg::STATUS_MAINTENANCE));
		$this->assertSame('#6c757d', \BadgeSvg::statusColor(\BadgeSvg::STATUS_UNKNOWN));
	}

	function test_normalize_status() {
		$this->assertSame(\BadgeSvg::STATUS_UP, \BadgeSvg::normalizeStatus('up'));
		$this->assertSame(\BadgeSvg::STATUS_DOWN, \BadgeSvg::normalizeStatus('DOWN'));
		$this->assertSame(\BadgeSvg::STATUS_MAINTENANCE, \BadgeSvg::normalizeStatus('maintenance'));
		$this->assertSame(\BadgeSvg::STATUS_UNKNOWN, \BadgeSvg::normalizeStatus('pending'));
		$this->assertSame(\BadgeSvg::STATUS_UNKNOWN, \BadgeSvg::normalizeStatus(''));
	}

	function test_render_svg_shields_shape() {
		$svg = \BadgeSvg::render('API', \BadgeSvg::STATUS_UP);
		$this->assertStringContainsString('<svg', $svg);
		$this->assertStringContainsString('API', $svg);
		$this->assertStringContainsString('Up', $svg);
		$this->assertStringContainsString('#198754', $svg);
		$this->assertStringContainsString('#555', $svg);

		$down = \BadgeSvg::render('Site', \BadgeSvg::STATUS_DOWN);
		$this->assertStringContainsString('Down', $down);
		$this->assertStringContainsString('#dc3545', $down);
	}

	function test_badge_url_format() {
		$hash = str_repeat('ab', 32);
		$url = \BadgeSvg::badgeUrl($hash);
		$this->assertStringContainsString('/badge/'.$hash.'.svg', $url);
		$this->assertSame('', \BadgeSvg::badgeUrl('short'));
	}

	function test_render_for_hash_rejects_invalid_format() {
		$bad = \BadgeSvg::renderForHash('not-a-hash');
		$this->assertSame(404, $bad['http']);
		$this->assertSame('', $bad['svg']);

		$short = \BadgeSvg::renderForHash(str_repeat('a', 32));
		$this->assertSame(404, $short['http']);
	}
}

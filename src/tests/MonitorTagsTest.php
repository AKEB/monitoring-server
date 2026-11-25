<?php

error_reporting(E_ALL);

class MonitorTagsTest extends \PHPUnit\Framework\TestCase {

	function test_normalize_title_and_color() {
		$this->assertTrue(\MonitorTags::isTitleValid('prod'));
		$this->assertTrue(\MonitorTags::isTitleValid('a'));
		$this->assertFalse(\MonitorTags::isTitleValid(''));
		$this->assertFalse(\MonitorTags::isTitleValid(str_repeat('x', 65)));

		$this->assertSame('hello world', \MonitorTags::normalizeTitle('  hello   world  '));
		$this->assertSame('#0d6efd', \MonitorTags::normalizeColor('#0D6EFD'));
		$this->assertSame('#198754', \MonitorTags::normalizeColor('198754'));
		$this->assertSame(\MonitorTags::DEFAULT_COLOR, \MonitorTags::normalizeColor('red'));
		$this->assertSame(\MonitorTags::DEFAULT_COLOR, \MonitorTags::normalizeColor('#xyz'));
	}

	function test_color_presets_are_bootstrap_hex() {
		$presets = \MonitorTags::color_presets();
		$this->assertArrayHasKey('#0d6efd', $presets);
		$this->assertArrayHasKey('#198754', $presets);
		$this->assertArrayHasKey('#dc3545', $presets);
		foreach (array_keys($presets) as $hex) {
			$this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $hex);
		}
	}
}

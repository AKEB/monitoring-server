<?php

use PHPUnit\Framework\TestCase;

class AgentUpdateTest extends TestCase {

	public function testNormalizeVersion(): void {
		$this->assertSame('', \AppOptions::normalizeVersion(''));
		$this->assertSame('v1.0.2', \AppOptions::normalizeVersion('v1.0.2'));
		$this->assertSame('v1.0.2', \AppOptions::normalizeVersion('1.0.2'));
	}

	public function testNormalizeArch(): void {
		$this->assertSame('amd64', \GithubReleaseMeta::normalizeArch('amd64'));
		$this->assertSame('amd64', \GithubReleaseMeta::normalizeArch('x86_64'));
		$this->assertSame('arm64', \GithubReleaseMeta::normalizeArch('aarch64'));
		$this->assertSame('', \GithubReleaseMeta::normalizeArch('ppc64'));
	}

	public function testVersionsEqual(): void {
		$this->assertTrue(\AgentUpdate::versionsEqual('1.0.1', 'v1.0.1'));
		$this->assertFalse(\AgentUpdate::versionsEqual('v1.0.1', 'v1.0.2'));
	}
}

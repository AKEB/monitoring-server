<?php

use PHPUnit\Framework\TestCase;

class WorkerUpdateTest extends TestCase {

	public function testVersionsEqual(): void {
		$this->assertTrue(\WorkerUpdate::versionsEqual('2.2.9', 'v2.2.9'));
		$this->assertFalse(\WorkerUpdate::versionsEqual('v2.2.8', 'v2.2.9'));
	}

	public function testDesiredOverrideWins(): void {
		$worker = ['desired_worker_version' => 'v9.9.9'];
		$this->assertSame('v9.9.9', \WorkerUpdate::desiredForWorker($worker));
	}
}

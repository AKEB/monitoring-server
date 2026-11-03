<?php

error_reporting(E_ALL);

/**
 * Base for Api\V2 / MCP tests that use in-memory store.
 */
abstract class ApiV2TestCase extends PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		\Api\V2\Runtime::installMemory();
	}

	protected function tearDown(): void {
		\Api\V2\Runtime::reset();
	}
}

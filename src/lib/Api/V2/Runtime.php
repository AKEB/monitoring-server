<?php

namespace Api\V2;

/**
 * Test/production switch for Api\V2 services.
 * In PHPUnit, tests install in-memory stores via Runtime::installMemory().
 * In production, stores are null and services use DB models.
 */
class Runtime {

	static public ?MemoryStore $store = null;

	/** When true, ACL checks are skipped (unit tests). */
	static public bool $bypassAcl = false;

	static public function installMemory(): MemoryStore {
		static::$store = new MemoryStore();
		static::$bypassAcl = true;
		return static::$store;
	}

	static public function reset(): void {
		static::$store = null;
		static::$bypassAcl = false;
	}

	static public function store(): ?MemoryStore {
		return static::$store;
	}
}

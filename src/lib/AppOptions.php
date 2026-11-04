<?php

class AppOptions extends \DB\MySQLObject {

	static public $table = 'app_options';

	const KEY_DESIRED_AGENT_VERSION = 'desired_agent_version';
	const KEY_DESIRED_WORKER_VERSION = 'desired_worker_version';

	static public function getValue(string $key, string $default = ''): string {
		$row = static::get(['opt_key' => $key]);
		if (!$row) {
			return $default;
		}
		return strval($row['opt_value'] ?? $default);
	}

	static public function setValue(string $key, string $value): void {
		$existing = static::get(['opt_key' => $key]);
		$now = time();
		if ($existing) {
			static::save([
				'opt_key' => $key,
				'opt_value' => $value,
				'update_time' => $now,
				'_mode' => \DB\Common::CSMODE_UPDATE,
			], '', 'opt_key');
			return;
		}
		static::save([
			'opt_key' => $key,
			'opt_value' => $value,
			'update_time' => $now,
			'_mode' => \DB\Common::CSMODE_INSERT,
		], '', 'opt_key');
	}

	static public function getDesiredAgentVersion(): string {
		$env = trim(strval(getenv('DESIRED_AGENT_VERSION') ?: ''));
		$db = trim(static::getValue(static::KEY_DESIRED_AGENT_VERSION, ''));
		// DB wins when set (UI); else env fallback.
		if ($db !== '') {
			return $db;
		}
		return $env;
	}

	static public function setDesiredAgentVersion(string $version): void {
		$version = static::normalizeVersion($version);
		static::setValue(static::KEY_DESIRED_AGENT_VERSION, $version);
	}

	static public function getDesiredWorkerVersion(): string {
		$env = trim(strval(getenv('DESIRED_WORKER_VERSION') ?: ''));
		$db = trim(static::getValue(static::KEY_DESIRED_WORKER_VERSION, ''));
		if ($db !== '') {
			return $db;
		}
		return $env;
	}

	static public function setDesiredWorkerVersion(string $version): void {
		$version = static::normalizeVersion($version);
		static::setValue(static::KEY_DESIRED_WORKER_VERSION, $version);
	}

	static public function normalizeVersion(string $version): string {
		$version = trim($version);
		if ($version === '') {
			return '';
		}
		if ($version[0] !== 'v' && preg_match('/^\d/', $version)) {
			$version = 'v'.$version;
		}
		return mb_substr($version, 0, 64);
	}
}

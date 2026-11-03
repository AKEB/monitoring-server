<?php

/**
 * Shared validation/normalization rules used by admin Edit forms
 * (and later by /api/v2). Pure helpers — no DB / sessions.
 */
class EntityValidation {

	static public function isTitleLengthValid(string $title, int $min = 2, int $max = 255): bool {
		$len = mb_strlen($title);
		return $len >= $min && $len <= $max;
	}

	static public function isSignatureTitleValid(string $title): bool {
		return static::isTitleLengthValid($title, 1, 255);
	}

	static public function isProjectLengthValid(string $project): bool {
		return mb_strlen($project) <= 255;
	}

	/**
	 * Folders never expose to /api/v1/status/; other types follow the checkbox.
	 *
	 * @param mixed $rawValue form value ('true'/'false') or bool/int
	 */
	static public function normalizeApiEnabled(int $monitorType, mixed $rawValue): int {
		if ($monitorType === \Monitors::TYPE_FOLDER) {
			return 0;
		}
		return static::normalizeSwitchStatus($rawValue);
	}

	/**
	 * @param mixed $rawValue
	 */
	static public function normalizeSwitchStatus(mixed $rawValue): int {
		if ($rawValue === true || $rawValue === 1 || $rawValue === '1' || $rawValue === 'true') {
			return 1;
		}
		return 0;
	}

	static public function clampRepeatSeconds(int $seconds, int $min = 60): int {
		return $seconds < $min ? $min : $seconds;
	}

	/**
	 * @return null|'required'|'order'
	 */
	static public function maintenanceTimeError(?int $start, ?int $finish): ?string {
		if (!$start || !$finish) {
			return 'required';
		}
		if ($finish < $start) {
			return 'order';
		}
		return null;
	}

	/**
	 * Partial secret/json updates: new keys overlay old, missing secrets stay.
	 *
	 * @param array<string,mixed> $old
	 * @param array<string,mixed> $new
	 * @return array<string,mixed>
	 */
	static public function mergeJsonData(array $old, array $new): array {
		return array_merge($old, $new);
	}

	static public function isTlsCertMimeValid(string $mime): bool {
		return $mime === 'application/x-x509-ca-cert';
	}

	/**
	 * Opt-in rule for GET /api/v1/status/ (folders never qualify).
	 *
	 * @param array<string,mixed> $monitor
	 */
	static public function isStatusApiCandidate(array $monitor): bool {
		if (intval($monitor['type'] ?? -1) === \Monitors::TYPE_FOLDER) {
			return false;
		}
		return intval($monitor['api_enabled'] ?? 0) === 1;
	}
}

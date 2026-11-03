<?php

namespace Api\V2;

class Acl {

	/**
	 * @param array<string,mixed> $user
	 * @param string $action permission action code (READ/WRITE/DELETE/ACCESS_*), string constants from framework
	 */
	static public function can(array $user, string $subject, int $subjectId, string $action): bool {
		if (Runtime::$bypassAcl) {
			return true;
		}
		return \Sessions::checkPermission($subject, $subjectId, $action, $user);
	}

	/**
	 * @param array<string,mixed> $user
	 */
	static public function canCreate(array $user, string $createSubject): bool {
		return static::can($user, $createSubject, 0, WRITE);
	}
}

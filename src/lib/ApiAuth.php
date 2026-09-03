<?php

class ApiAuth {

	private const RATE_LIMIT_SECONDS = 5;
	private const CACHE_TTL_SECONDS = 20;

	static public function extractToken(): string {
		$headers = [];
		if (function_exists('getallheaders')) {
			$headers = getallheaders() ?: [];
		}
		$auth = static::headerValue($headers, 'Authorization')
			?: strval($_SERVER['HTTP_AUTHORIZATION'] ?? '')
			?: strval($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
		if (preg_match('/^Bearer\s+(\S+)/i', $auth, $m)) {
			return trim($m[1]);
		}
		$x = static::headerValue($headers, 'X-Api-Token')
			?: strval($_SERVER['HTTP_X_API_TOKEN'] ?? '');
		return trim($x);
	}

	static private function headerValue(array $headers, string $name): string {
		foreach ($headers as $key => $value) {
			if (strcasecmp(strval($key), $name) === 0) {
				return trim(strval($value));
			}
		}
		return '';
	}

	/**
	 * @return array{token: array, user: array}|null
	 */
	static public function authenticate(): ?array {
		$plaintext = static::extractToken();
		if ($plaintext === '') {
			return null;
		}
		$token = \UserApiTokens::findActiveByPlaintext($plaintext);
		if (!$token) {
			return null;
		}
		$user = \Users::get(['id' => intval($token['user_id'])]);
		if (!$user || !is_array($user) || intval($user['status']) !== \Users::STATUS_ACTIVE) {
			return null;
		}
		$user = static::hydrateUser($user);
		\UserApiTokens::touchLastUsed($token);
		return [
			'token' => $token,
			'user' => $user,
		];
	}

	static public function isRateLimited(int $token_id): bool {
		$cache = new \Cache(static::rateLimitCacheKey($token_id));
		if ($cache->isValid()) {
			return true;
		}
		$cache->update('1', static::RATE_LIMIT_SECONDS);
		return false;
	}

	static public function cachedStatusPayload(int $user_id): ?string {
		$cache = new \Cache(static::statusCacheKey($user_id));
		if (!$cache->isValid()) {
			return null;
		}
		$payload = $cache->get();
		return is_string($payload) && $payload !== '' ? $payload : null;
	}

	static public function storeStatusPayload(int $user_id, string $payload): void {
		$cache = new \Cache(static::statusCacheKey($user_id));
		$cache->update($payload, static::CACHE_TTL_SECONDS);
	}

	static public function hydrateUser(array $user): array {
		$user['groups'] = \Sessions::getUserGroups(intval($user['id']));
		$groupIds = [];
		if (isset($user['groups']) && is_array($user['groups']) && $user['groups']) {
			$groupIds = array_keys($user['groups']);
		}
		$groupPermissions = \Sessions::getGroupsPermissions($groupIds);
		$userPermissions = static::loadUserObjectPermissions(intval($user['id']));
		$user['permissions'] = [];
		static::mergePermissionSet($user['permissions'], $groupPermissions, true);
		static::mergePermissionSet($user['permissions'], $userPermissions, false);

		foreach (\Permissions::get_subject_classes() as $subjectClass) {
			$permissions = $subjectClass::getUserPermissions($user);
			if (!$permissions || !is_array($permissions)) {
				continue;
			}
			foreach ($permissions as $subjectType => $permissions_hash) {
				if (!$permissions_hash || !is_array($permissions_hash)) {
					continue;
				}
				if (!isset($user['permissions'][$subjectType])) {
					$user['permissions'][$subjectType] = [];
				}
				foreach ($permissions_hash as $permissionSubjectId => $permission) {
					if (!isset($user['permissions'][$subjectType][$permissionSubjectId])) {
						$user['permissions'][$subjectType][$permissionSubjectId] = static::emptyAccess();
					}
					$user['permissions'][$subjectType][$permissionSubjectId] = [
						READ => ($permission[READ] ?? 0) == 1 ? 1 : $user['permissions'][$subjectType][$permissionSubjectId][READ],
						WRITE => ($permission[WRITE] ?? 0) == 1 ? 1 : $user['permissions'][$subjectType][$permissionSubjectId][WRITE],
						DELETE => ($permission[DELETE] ?? 0) == 1 ? 1 : $user['permissions'][$subjectType][$permissionSubjectId][DELETE],
						ACCESS_READ => ($permission[ACCESS_READ] ?? 0) == 1 ? 1 : $user['permissions'][$subjectType][$permissionSubjectId][ACCESS_READ],
						ACCESS_WRITE => ($permission[ACCESS_WRITE] ?? 0) == 1 ? 1 : $user['permissions'][$subjectType][$permissionSubjectId][ACCESS_WRITE],
						ACCESS_CHANGE => ($permission[ACCESS_CHANGE] ?? 0) == 1 ? 1 : $user['permissions'][$subjectType][$permissionSubjectId][ACCESS_CHANGE],
					];
					if (array_sum($user['permissions'][$subjectType][$permissionSubjectId]) == 0) {
						unset($user['permissions'][$subjectType][$permissionSubjectId]);
					}
				}
			}
		}
		return $user;
	}

	static private function loadUserObjectPermissions(int $user_id): array {
		$permissions = [];
		$rows = \ObjectPermissions::data(['object' => 'user', 'object_id' => [$user_id]]);
		if (!$rows || !is_array($rows)) {
			return $permissions;
		}
		foreach ($rows as $row) {
			if (!isset($row['subject']) || !$row['subject']) {
				continue;
			}
			if (!isset($permissions[$row['subject']])) {
				$permissions[$row['subject']] = [];
			}
			$subjectId = $row['subject_id'];
			$permissions[$row['subject']][$subjectId] = [
				READ => max($permissions[$row['subject']][$subjectId][READ] ?? 0, intval($row[READ])),
				WRITE => max($permissions[$row['subject']][$subjectId][WRITE] ?? 0, intval($row[WRITE])),
				DELETE => max($permissions[$row['subject']][$subjectId][DELETE] ?? 0, intval($row[DELETE])),
				ACCESS_READ => max($permissions[$row['subject']][$subjectId][ACCESS_READ] ?? 0, intval($row[ACCESS_READ])),
				ACCESS_WRITE => max($permissions[$row['subject']][$subjectId][ACCESS_WRITE] ?? 0, intval($row[ACCESS_WRITE])),
				ACCESS_CHANGE => max($permissions[$row['subject']][$subjectId][ACCESS_CHANGE] ?? 0, intval($row[ACCESS_CHANGE])),
			];
		}
		return $permissions;
	}

	static private function mergePermissionSet(array &$target, array $source, bool $useMax): void {
		foreach ($source as $subject => $permissions) {
			if (!isset($target[$subject])) {
				$target[$subject] = [];
			}
			foreach ($permissions as $subjectId => $permission) {
				if (!isset($target[$subject][$subjectId])) {
					$target[$subject][$subjectId] = static::emptyAccess();
				}
				if ($useMax) {
					$target[$subject][$subjectId] = [
						READ => max($target[$subject][$subjectId][READ], intval($permission[READ] ?? 0)),
						WRITE => max($target[$subject][$subjectId][WRITE], intval($permission[WRITE] ?? 0)),
						DELETE => max($target[$subject][$subjectId][DELETE], intval($permission[DELETE] ?? 0)),
						ACCESS_READ => max($target[$subject][$subjectId][ACCESS_READ], intval($permission[ACCESS_READ] ?? 0)),
						ACCESS_WRITE => max($target[$subject][$subjectId][ACCESS_WRITE], intval($permission[ACCESS_WRITE] ?? 0)),
						ACCESS_CHANGE => max($target[$subject][$subjectId][ACCESS_CHANGE], intval($permission[ACCESS_CHANGE] ?? 0)),
					];
				} else {
					$target[$subject][$subjectId] = [
						READ => intval($permission[READ] ?? 0) == 0 ? $target[$subject][$subjectId][READ] : intval($permission[READ]),
						WRITE => intval($permission[WRITE] ?? 0) == 0 ? $target[$subject][$subjectId][WRITE] : intval($permission[WRITE]),
						DELETE => intval($permission[DELETE] ?? 0) == 0 ? $target[$subject][$subjectId][DELETE] : intval($permission[DELETE]),
						ACCESS_READ => intval($permission[ACCESS_READ] ?? 0) == 0 ? $target[$subject][$subjectId][ACCESS_READ] : intval($permission[ACCESS_READ]),
						ACCESS_WRITE => intval($permission[ACCESS_WRITE] ?? 0) == 0 ? $target[$subject][$subjectId][ACCESS_WRITE] : intval($permission[ACCESS_WRITE]),
						ACCESS_CHANGE => intval($permission[ACCESS_CHANGE] ?? 0) == 0 ? $target[$subject][$subjectId][ACCESS_CHANGE] : intval($permission[ACCESS_CHANGE]),
					];
				}
			}
		}
	}

	static private function emptyAccess(): array {
		return [
			READ => 0,
			WRITE => 0,
			DELETE => 0,
			ACCESS_READ => 0,
			ACCESS_WRITE => 0,
			ACCESS_CHANGE => 0,
		];
	}

	static private function rateLimitCacheKey(int $token_id): string {
		return 'api_v1_status_rl_'.$token_id;
	}

	static private function statusCacheKey(int $user_id): string {
		return 'api_v1_status_'.$user_id;
	}
}

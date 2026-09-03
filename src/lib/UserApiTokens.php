<?php

class UserApiTokens extends \DB\MySQLObject {
	static public $table = 'user_api_tokens';

	const LOGS_OBJECT = 'UserApiTokens';

	const TOKEN_PREFIX = 'mnt_';
	const TOKEN_BYTES = 24;
	const PREFIX_LENGTH = 12;

	static public function generatePlaintext(): string {
		return static::TOKEN_PREFIX.bin2hex(random_bytes(static::TOKEN_BYTES));
	}

	static public function hashToken(string $plaintext): string {
		return hash('sha256', $plaintext);
	}

	static public function prefixFromPlaintext(string $plaintext): string {
		return substr($plaintext, 0, static::PREFIX_LENGTH);
	}

	static public function createForUser(int $user_id, string $title): array|false {
		$plaintext = static::generatePlaintext();
		$now = time();
		$row = [
			'user_id' => $user_id,
			'title' => $title,
			'token_hash' => static::hashToken($plaintext),
			'token_prefix' => static::prefixFromPlaintext($plaintext),
			'last_used_time' => 0,
			'revoke_time' => 0,
			'create_time' => $now,
			'update_time' => $now,
			'_mode' => \DB\Common::CSMODE_INSERT,
		];
		$id = static::save($row);
		if (!$id) {
			return false;
		}
		$row['id'] = $id;
		$log_row = $row;
		unset($log_row['token_hash'], $log_row['_mode']);
		\Logs::create_log(static::LOGS_OBJECT, intval($id), $log_row);
		$row['plaintext'] = $plaintext;
		unset($row['token_hash'], $row['_mode']);
		return $row;
	}

	static public function findActiveByPlaintext(string $plaintext): ?array {
		$plaintext = trim($plaintext);
		if ($plaintext === '') {
			return null;
		}
		$token = static::get(['token_hash' => static::hashToken($plaintext)]);
		if (!$token || !is_array($token)) {
			return null;
		}
		if (intval($token['revoke_time'] ?? 0) > 0) {
			return null;
		}
		return $token;
	}

	static public function listActiveForUser(int $user_id): array {
		$data = static::data(
			['user_id' => $user_id, 'revoke_time' => 0],
			' ORDER BY `id` DESC'
		);
		return $data ?: [];
	}

	static public function revokeForUser(int $user_id, int $token_id): bool {
		$token = static::get(['id' => $token_id, 'user_id' => $user_id]);
		if (!$token || !is_array($token)) {
			return false;
		}
		if (intval($token['revoke_time'] ?? 0) > 0) {
			return false;
		}
		$now = time();
		$ok = static::save([
			'id' => $token_id,
			'revoke_time' => $now,
			'update_time' => $now,
			'_mode' => \DB\Common::CSMODE_UPDATE,
		]);
		if (!$ok) {
			return false;
		}
		$new = $token;
		$new['revoke_time'] = $now;
		$new['update_time'] = $now;
		unset($token['token_hash'], $new['token_hash']);
		\Logs::update_log(static::LOGS_OBJECT, $token_id, $token, $new, [
			'_save_fields' => ['id', 'title'],
		]);
		return true;
	}

	static public function touchLastUsed(array $token): void {
		$id = intval($token['id'] ?? 0);
		if ($id < 1) {
			return;
		}
		$last = intval($token['last_used_time'] ?? 0);
		$now = time();
		if ($last + 60 >= $now) {
			return;
		}
		static::save([
			'id' => $id,
			'last_used_time' => $now,
			'update_time' => $now,
			'_mode' => \DB\Common::CSMODE_UPDATE,
		]);
	}
}

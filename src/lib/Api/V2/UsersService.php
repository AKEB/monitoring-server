<?php

namespace Api\V2;

class UsersService {

	/** @param array<string,mixed> $user */
	static public function list(array $user): array {
		$store = Runtime::store();
		if ($store) {
			$rows = array_map([static::class, 'publicFields'], $store->all('users'));
			return Envelope::ok($rows, ['total' => count($rows)]);
		}
		$rows = \Users::data(['status' => \Users::STATUS_ACTIVE]) ?: [];
		$out = [];
		foreach ($rows as $row) {
			$out[] = static::publicFields($row);
		}
		return Envelope::ok($out, ['total' => count($out)]);
	}

	/** @param array<string,mixed> $user */
	static public function get(array $user, int $id): array {
		$store = Runtime::store();
		if ($store) {
			$row = $store->get('users', $id);
			return $row ? Envelope::ok(static::publicFields($row)) : Envelope::error('Not found') + ['http' => 404];
		}
		$row = \Users::get(['id' => $id]);
		if (!$row) {
			return Envelope::error('Not found') + ['http' => 404];
		}
		return Envelope::ok(static::publicFields($row));
	}

	/** @param array<string,mixed> $row @return array<string,mixed> */
	static private function publicFields(array $row): array {
		return [
			'id' => intval($row['id'] ?? 0),
			'login' => strval($row['login'] ?? $row['email'] ?? ''),
			'name' => strval($row['name'] ?? ''),
			'status' => intval($row['status'] ?? 0),
		];
	}
}

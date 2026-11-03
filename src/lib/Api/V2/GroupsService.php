<?php

namespace Api\V2;

class GroupsService {

	/** @param array<string,mixed> $user */
	static public function list(array $user): array {
		$store = Runtime::store();
		if ($store) {
			$rows = [];
			foreach ($store->all('groups') as $row) {
				$rows[] = [
					'id' => intval($row['id'] ?? 0),
					'title' => strval($row['title'] ?? $row['name'] ?? ''),
				];
			}
			return Envelope::ok($rows, ['total' => count($rows)]);
		}
		$rows = \Groups::data(false) ?: [];
		$out = [];
		foreach ($rows as $row) {
			$out[] = [
				'id' => intval($row['id'] ?? 0),
				'title' => strval($row['title'] ?? $row['name'] ?? ''),
			];
		}
		return Envelope::ok($out, ['total' => count($out)]);
	}

	/** @param array<string,mixed> $user */
	static public function get(array $user, int $id): array {
		$store = Runtime::store();
		if ($store) {
			$row = $store->get('groups', $id);
			if (!$row) {
				return Envelope::error('Not found') + ['http' => 404];
			}
			return Envelope::ok([
				'id' => intval($row['id'] ?? 0),
				'title' => strval($row['title'] ?? $row['name'] ?? ''),
			]);
		}
		$row = \Groups::get(['id' => $id]);
		if (!$row) {
			return Envelope::error('Not found') + ['http' => 404];
		}
		return Envelope::ok([
			'id' => intval($row['id'] ?? 0),
			'title' => strval($row['title'] ?? $row['name'] ?? ''),
		]);
	}
}

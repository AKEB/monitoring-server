<?php

namespace Api\V2;

/**
 * In-memory persistence for Api\V2 unit tests.
 */
class MemoryStore {

	/** @var array<string, array<int, array<string,mixed>>> */
	public array $tables = [
		'monitors' => [],
		'notify' => [],
		'signatures' => [],
		'workers' => [],
		'maintenances' => [],
		'dockers' => [],
		'servers' => [],
		'users' => [],
		'groups' => [],
		'logs' => [],
		'permissions' => [],
	];

	/** @var array<string, int> */
	private array $seq = [];

	/**
	 * @param array<string,mixed> $row
	 * @return array<string,mixed>
	 */
	public function insert(string $table, array $row): array {
		$id = intval($row['id'] ?? 0);
		if ($id < 1) {
			$this->seq[$table] = intval($this->seq[$table] ?? 0) + 1;
			$id = $this->seq[$table];
			$row['id'] = $id;
		} else {
			$this->seq[$table] = max(intval($this->seq[$table] ?? 0), $id);
		}
		$this->tables[$table][$id] = $row;
		return $row;
	}

	/**
	 * @param array<string,mixed> $row
	 * @return array<string,mixed>|null
	 */
	public function update(string $table, int $id, array $row): ?array {
		if (!isset($this->tables[$table][$id])) {
			return null;
		}
		$this->tables[$table][$id] = array_merge($this->tables[$table][$id], $row, ['id' => $id]);
		return $this->tables[$table][$id];
	}

	/** @return array<string,mixed>|null */
	public function get(string $table, int $id): ?array {
		return $this->tables[$table][$id] ?? null;
	}

	/** @return list<array<string,mixed>> */
	public function all(string $table): array {
		return array_values($this->tables[$table]);
	}

	public function delete(string $table, int $id): bool {
		if (!isset($this->tables[$table][$id])) {
			return false;
		}
		unset($this->tables[$table][$id]);
		return true;
	}
}

<?php

/**
 * Pure helpers for public status-page membership.
 * Intentionally independent of api_enabled (Status API opt-in).
 */
class StatusPageMembership {

	const INCIDENT_STATES = ['investigating', 'identified', 'monitoring', 'resolved'];

	const OTHER_GROUP_TITLE = 'Other';

	static public function normalizeSlug(string $slug): string {
		$slug = strtolower(trim($slug));
		$slug = preg_replace('/\s+/', '-', $slug) ?? '';
		$slug = preg_replace('/[^a-z0-9_\-]/', '', $slug) ?? '';
		$slug = preg_replace('/\-+/', '-', $slug) ?? '';
		$slug = preg_replace('/_+/', '_', $slug) ?? '';
		return trim($slug, '-_');
	}

	static public function isSlugValid(string $slug): bool {
		if ($slug === '' || strlen($slug) > 64) {
			return false;
		}
		return (bool)preg_match('/^[a-z0-9]+(?:[_-][a-z0-9]+)*$/', $slug);
	}

	/**
	 * Accept human input: letters/digits/_/-/spaces only; then normalize.
	 */
	static public function isSlugInputAcceptable(string $raw): bool {
		$raw = strtolower(trim($raw));
		if ($raw === '' || strlen($raw) > 64) {
			return false;
		}
		if (preg_match('/[^a-z0-9_\-\s]/', $raw)) {
			return false;
		}
		return static::isSlugValid(static::normalizeSlug($raw));
	}

	static public function isIncidentStateValid(string $state): bool {
		return in_array($state, self::INCIDENT_STATES, true);
	}

	/**
	 * Leaf monitor suitable for a status page (not a folder; enabled).
	 * Does NOT look at api_enabled.
	 *
	 * @param array<string,mixed> $monitor
	 */
	static public function isStatusPageLeafCandidate(array $monitor): bool {
		if (intval($monitor['type'] ?? -1) === \Monitors::TYPE_FOLDER) {
			return false;
		}
		return intval($monitor['status'] ?? 0) === 1;
	}

	/**
	 * Walk parent_id chain; every ancestor (and self) must be status=enabled.
	 *
	 * @param array<int,array<string,mixed>> $monitorsHash
	 */
	static public function isEnabledInTree(int $monitorId, array $monitorsHash): bool {
		$seen = [];
		$id = $monitorId;
		while ($id > 0) {
			if (isset($seen[$id])) {
				return false;
			}
			$seen[$id] = true;
			if (!isset($monitorsHash[$id])) {
				return false;
			}
			if (intval($monitorsHash[$id]['status'] ?? 0) !== 1) {
				return false;
			}
			$id = intval($monitorsHash[$id]['parent_id'] ?? 0);
		}
		return true;
	}

	/**
	 * Expand selected folders + explicit monitors into groups and unique leaf ids.
	 * api_enabled is ignored on purpose.
	 *
	 * @param list<int> $folderIds
	 * @param list<int> $monitorIds
	 * @param array<int,array<string,mixed>> $monitorsHash
	 * @return array{groups: list<array{id:int,title:string,monitor_ids:list<int>}>, monitor_ids: list<int>}
	 */
	static public function resolveGroups(array $folderIds, array $monitorIds, array $monitorsHash): array {
		$children = [];
		foreach ($monitorsHash as $id => $row) {
			$parent = intval($row['parent_id'] ?? 0);
			$children[$parent][] = intval($id);
		}

		$groups = [];
		$allLeafIds = [];

		foreach ($folderIds as $folderId) {
			$folderId = intval($folderId);
			if ($folderId < 1 || !isset($monitorsHash[$folderId])) {
				continue;
			}
			if (intval($monitorsHash[$folderId]['type'] ?? -1) !== \Monitors::TYPE_FOLDER) {
				continue;
			}
			if (!static::isEnabledInTree($folderId, $monitorsHash)) {
				continue;
			}
			$leaves = static::collectLeavesUnder($folderId, $children, $monitorsHash);
			$groups[] = [
				'id' => $folderId,
				'title' => strval($monitorsHash[$folderId]['title'] ?? ('#'.$folderId)),
				'monitor_ids' => $leaves,
			];
			foreach ($leaves as $leafId) {
				$allLeafIds[$leafId] = true;
			}
		}

		$other = [];
		foreach ($monitorIds as $monitorId) {
			$monitorId = intval($monitorId);
			if ($monitorId < 1 || !isset($monitorsHash[$monitorId])) {
				continue;
			}
			$row = $monitorsHash[$monitorId];
			if (!static::isStatusPageLeafCandidate($row)) {
				continue;
			}
			if (!static::isEnabledInTree($monitorId, $monitorsHash)) {
				continue;
			}
			if (isset($allLeafIds[$monitorId])) {
				continue;
			}
			$other[] = $monitorId;
			$allLeafIds[$monitorId] = true;
		}
		if ($other) {
			$groups[] = [
				'id' => 0,
				'title' => self::OTHER_GROUP_TITLE,
				'monitor_ids' => $other,
			];
		}

		$monitorIdList = array_map('intval', array_keys($allLeafIds));
		sort($monitorIdList);

		return [
			'groups' => $groups,
			'monitor_ids' => $monitorIdList,
		];
	}

	/**
	 * @param array<int,list<int>> $children
	 * @param array<int,array<string,mixed>> $monitorsHash
	 * @return list<int>
	 */
	static private function collectLeavesUnder(int $rootId, array $children, array $monitorsHash): array {
		$ids = [];
		$stack = [$rootId];
		$seen = [];
		while ($stack) {
			$id = array_pop($stack);
			if (isset($seen[$id])) {
				continue;
			}
			$seen[$id] = true;
			foreach ($children[$id] ?? [] as $childId) {
				if (!isset($monitorsHash[$childId])) {
					continue;
				}
				$row = $monitorsHash[$childId];
				$type = intval($row['type'] ?? -1);
				if ($type === \Monitors::TYPE_FOLDER) {
					if (static::isEnabledInTree($childId, $monitorsHash)) {
						$stack[] = $childId;
					}
					continue;
				}
				if (!static::isStatusPageLeafCandidate($row)) {
					continue;
				}
				if (!static::isEnabledInTree($childId, $monitorsHash)) {
					continue;
				}
				$ids[] = $childId;
			}
		}
		sort($ids);
		return $ids;
	}
}

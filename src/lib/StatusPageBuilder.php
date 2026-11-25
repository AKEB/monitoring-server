<?php

/**
 * Builds anonymous public status-page payloads.
 * Membership is independent of api_enabled / user ACL.
 */
class StatusPageBuilder {

	const CACHE_TTL_SEC = 45;
	const HEARTBEAT_LIMIT = 40;

	/**
	 * @return array<string,mixed>|null
	 */
	static public function buildBySlug(string $slug): ?array {
		$page = \StatusPages::getBySlug($slug);
		if (!$page || intval($page['status'] ?? 0) !== \StatusPages::STATUS_ENABLED) {
			return null;
		}
		return static::buildForPage($page);
	}

	/**
	 * @param array<string,mixed> $page
	 * @return array<string,mixed>
	 */
	static public function buildForPage(array $page): array {
		$pageId = intval($page['id'] ?? 0);
		$folderIds = [];
		foreach (\StatusPageFolders::data(['status_page_id' => $pageId]) ?: [] as $row) {
			$folderIds[] = intval($row['folder_id'] ?? 0);
		}
		$monitorIds = [];
		foreach (\StatusPageMonitors::data(['status_page_id' => $pageId]) ?: [] as $row) {
			$monitorIds[] = intval($row['monitor_id'] ?? 0);
		}

		$monitorsHash = [];
		foreach (\Monitors::data(false, '', '`id`, `title`, `type`, `url`, `host`, `port`, `docker`, `container`, `status`, `api_enabled`, `parent_id`, `repeat_seconds`, `api_description_ru`, `api_description_en`, `external_hash`') ?: [] as $row) {
			$monitorsHash[intval($row['id'])] = $row;
		}

		$resolved = \StatusPageMembership::resolveGroups($folderIds, $monitorIds, $monitorsHash);
		$leafMonitors = [];
		foreach ($resolved['monitor_ids'] as $mid) {
			if (isset($monitorsHash[$mid])) {
				$leafMonitors[] = $monitorsHash[$mid];
			}
		}

		$payload = \MonitoringApiStatus::buildPayloadForMonitors($leafMonitors);
		$beatsByMonitor = static::recentBeats(array_map(static fn($m) => intval($m['id']), $leafMonitors));
		$resourcesById = [];
		$allStatuses = [];
		foreach ($payload['resources'] as $resource) {
			$id = intval($resource['id']);
			$resource['beats'] = $beatsByMonitor[$id] ?? [];
			$resourcesById[$id] = $resource;
			$allStatuses[] = strval($resource['status'] ?? \MonitoringApiStatus::STATUS_UNKNOWN);
		}

		$groups = [];
		foreach ($resolved['groups'] as $group) {
			$items = [];
			foreach ($group['monitor_ids'] as $mid) {
				if (isset($resourcesById[$mid])) {
					$items[] = $resourcesById[$mid];
				}
			}
			$groups[] = [
				'id' => intval($group['id']),
				'title' => strval($group['title']),
				'resources' => $items,
			];
		}

		$incidents = [];
		foreach (\StatusPageIncidents::data(
			['status_page_id' => $pageId, 'active' => 1],
			' ORDER BY `id` DESC'
		) ?: [] as $inc) {
			$incidents[] = [
				'id' => intval($inc['id']),
				'title' => strval($inc['title'] ?? ''),
				'message' => strval($inc['message'] ?? ''),
				'state' => strval($inc['state'] ?? ''),
				'active' => 1,
				'create_time' => intval($inc['create_time'] ?? 0),
				'update_time' => intval($inc['update_time'] ?? 0),
			];
		}

		return [
			'id' => $pageId,
			'title' => strval($page['title'] ?? ''),
			'slug' => strval($page['slug'] ?? ''),
			'generated_at' => time(),
			'overall' => static::overallFromStatuses($allStatuses),
			'groups' => $groups,
			'maintenances' => $payload['maintenances'],
			'incidents' => $incidents,
		];
	}

	/**
	 * @param list<string> $statuses
	 * @return array{code:string,label:string}
	 */
	static public function overallFromStatuses(array $statuses): array {
		if (!$statuses) {
			return ['code' => 'empty', 'label' => 'No Services'];
		}
		$up = 0;
		$down = 0;
		$maint = 0;
		$unknown = 0;
		foreach ($statuses as $st) {
			switch ($st) {
				case \MonitoringApiStatus::STATUS_UP:
					$up++;
					break;
				case \MonitoringApiStatus::STATUS_DOWN:
					$down++;
					break;
				case \MonitoringApiStatus::STATUS_MAINTENANCE:
					$maint++;
					break;
				default:
					$unknown++;
					break;
			}
		}
		$total = count($statuses);
		if ($down === 0 && $unknown === 0 && $maint === 0 && $up === $total) {
			return ['code' => 'ok', 'label' => 'All Systems Operational'];
		}
		if ($down === $total) {
			return ['code' => 'down', 'label' => 'Degraded Service'];
		}
		if ($down > 0) {
			return ['code' => 'partial', 'label' => 'Partially Degraded Service'];
		}
		if ($maint > 0 && $up + $maint === $total) {
			return ['code' => 'maint', 'label' => 'Under Maintenance'];
		}
		if ($unknown === $total) {
			return ['code' => 'unknown', 'label' => 'Status Unknown'];
		}
		if ($unknown > 0 && $down === 0) {
			return ['code' => 'partial', 'label' => 'Partially Degraded Service'];
		}
		return ['code' => 'partial', 'label' => 'Partially Degraded Service'];
	}

	/**
	 * Recent heartbeat statuses (oldest → newest), capped per monitor.
	 *
	 * @param list<int> $monitorIds
	 * @return array<int, list<string>>
	 */
	static public function recentBeats(array $monitorIds): array {
		$monitorIds = array_values(array_unique(array_filter(array_map('intval', $monitorIds))));
		$out = [];
		foreach ($monitorIds as $id) {
			$out[$id] = [];
		}
		if (!$monitorIds) {
			return $out;
		}
		$since = time() - 86400;
		$limit = max(count($monitorIds) * self::HEARTBEAT_LIMIT, self::HEARTBEAT_LIMIT);
		$rows = \MonitoringLogs::data(
			false,
			sql_pholder(
				' AND `monitor_id` IN (?@) AND `update_time` >= ? ORDER BY `update_time` DESC, `id` DESC LIMIT '.$limit,
				$monitorIds,
				$since
			),
			'`monitor_id`, `status`, `update_time`'
		) ?: [];
		$counts = [];
		foreach ($rows as $row) {
			$mid = intval($row['monitor_id'] ?? 0);
			if ($mid < 1 || !isset($out[$mid])) {
				continue;
			}
			$counts[$mid] = ($counts[$mid] ?? 0) + 1;
			if ($counts[$mid] > self::HEARTBEAT_LIMIT) {
				continue;
			}
			$status = intval($row['status'] ?? -1);
			$beat = match ($status) {
				\MonitoringLogs::STATUS_GOOD => 'up',
				\MonitoringLogs::STATUS_FAIL => 'down',
				default => 'pending',
			};
			$out[$mid][] = $beat;
		}
		foreach ($out as $mid => $beats) {
			$out[$mid] = array_reverse($beats);
		}
		return $out;
	}

	static public function invalidateSlugCache(string $slug): void {
		$slug = \StatusPageMembership::normalizeSlug($slug);
		if ($slug === '') {
			return;
		}
		try {
			$cache = new \Cache('status_page_public_'.$slug);
			$cache->update('', 1);
		} catch (\Throwable $e) {
			// ignore
		}
	}

	/**
	 * @return array<string,mixed>|null
	 */
	static public function buildBySlugCached(string $slug): ?array {
		$slug = \StatusPageMembership::normalizeSlug($slug);
		if ($slug === '') {
			return null;
		}
		$cacheKey = 'status_page_public_'.$slug;
		try {
			$cache = new \Cache($cacheKey);
			if ($cache->isValid()) {
				$cached = $cache->get();
				if (is_string($cached) && $cached !== '') {
					$decoded = json_decode($cached, true);
					if (is_array($decoded)) {
						return $decoded;
					}
				}
			}
		} catch (\Throwable $e) {
			// fall through
		}
		$data = static::buildBySlug($slug);
		if ($data) {
			try {
				$payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
				if (is_string($payload) && $payload !== '') {
					$cache = new \Cache($cacheKey);
					$cache->update($payload, self::CACHE_TTL_SEC);
				}
			} catch (\Throwable $e) {
				// ignore
			}
		}
		return $data;
	}
}

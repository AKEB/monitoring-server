<?php

/**
 * Builds anonymous public status-page payloads.
 * Membership is independent of api_enabled / user ACL.
 */
class StatusPageBuilder {

	const CACHE_TTL_SEC = 45;

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
		$resourcesById = [];
		foreach ($payload['resources'] as $resource) {
			$resourcesById[intval($resource['id'])] = $resource;
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
			'groups' => $groups,
			'maintenances' => $payload['maintenances'],
			'incidents' => $incidents,
		];
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

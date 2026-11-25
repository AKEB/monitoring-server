<?php

namespace Api\V2;

class StatusPagesService {

	/** @param array<string,mixed> $user */
	static public function list(array $user, array $query = []): array {
		$store = Runtime::store();
		if ($store) {
			$rows = $store->all('status_pages');
			return Envelope::ok($rows, ['total' => count($rows)]);
		}
		$rows = \StatusPages::data(false, ' ORDER BY `title` ASC') ?: [];
		$out = [];
		foreach ($rows as $row) {
			$id = intval($row['id'] ?? 0);
			if ($id > 0 && Acl::can($user, \StatusPages::PERMISSION_ACCESS, $id, READ)) {
				$out[] = static::publicRow($row);
			}
		}
		return Envelope::ok($out, ['total' => count($out)]);
	}

	/** @param array<string,mixed> $user */
	static public function get(array $user, int $id): array {
		$store = Runtime::store();
		if ($store) {
			$row = $store->get('status_pages', $id);
			return $row ? Envelope::ok($row) : Envelope::error('Not found') + ['http' => 404];
		}
		if ($id < 1 || !Acl::can($user, \StatusPages::PERMISSION_ACCESS, $id, READ)) {
			return Envelope::error($id < 1 ? 'Not found' : 'Forbidden') + ['http' => $id < 1 ? 404 : 403];
		}
		$row = \StatusPages::get(['id' => $id]);
		return $row ? Envelope::ok(static::publicRow($row)) : Envelope::error('Not found') + ['http' => 404];
	}

	/** @param array<string,mixed> $user @param array<string,mixed> $payload */
	static public function create(array $user, array $payload): array {
		$title = trim(strval($payload['title'] ?? ''));
		if (!\EntityValidation::isTitleLengthValid($title)) {
			return Envelope::error('Invalid title length') + ['http' => 422];
		}
		$slugRaw = strval($payload['slug'] ?? '');
		if (trim($slugRaw) === '' && $title !== '') {
			$slugRaw = $title;
		}
		if (!\StatusPageMembership::isSlugInputAcceptable($slugRaw)) {
			return Envelope::error('Invalid slug') + ['http' => 422];
		}
		$slug = \StatusPageMembership::normalizeSlug($slugRaw);
		$folderIds = static::intIdList($payload['folder_ids'] ?? []);
		$monitorIds = static::intIdList($payload['monitor_ids'] ?? []);
		$row = [
			'title' => $title,
			'slug' => $slug,
			'status' => \EntityValidation::normalizeSwitchStatus($payload['status'] ?? 1),
			'folder_ids' => $folderIds,
			'monitor_ids' => $monitorIds,
			'create_time' => time(),
			'update_time' => time(),
		];
		$store = Runtime::store();
		if ($store) {
			if (static::memorySlugTaken($store, $slug, 0)) {
				return Envelope::error('Slug already exists') + ['http' => 422];
			}
			return Envelope::ok($store->insert('status_pages', $row));
		}
		if (!Acl::canCreate($user, \StatusPages::PERMISSION_CREATE)) {
			return Envelope::error('Forbidden') + ['http' => 403];
		}
		if (\StatusPages::get(['slug' => $slug])) {
			return Envelope::error('Slug already exists') + ['http' => 422];
		}
		$save = [
			'title' => $title,
			'slug' => $slug,
			'status' => $row['status'],
			'create_time' => time(),
			'update_time' => time(),
			'_mode' => \DB\Common::CSMODE_INSERT,
		];
		$id = \StatusPages::save($save);
		if (!$id) {
			return Envelope::error('Save failed') + ['http' => 500];
		}
		static::syncMembership(intval($id), $folderIds, $monitorIds, $user);
		\StatusPages::grantCreatePermissions(intval($id), intval($user['id'] ?? 0));
		\Logs::create_log(\StatusPages::LOGS_OBJECT, intval($id), $save);
		$saved = \StatusPages::get(['id' => intval($id)]);
		return Envelope::ok(static::publicRow($saved ?: $save + ['id' => intval($id)]));
	}

	/** @param array<string,mixed> $user @param array<string,mixed> $payload */
	static public function update(array $user, int $id, array $payload): array {
		$store = Runtime::store();
		if ($store) {
			$existing = $store->get('status_pages', $id);
			if (!$existing) {
				return Envelope::error('Not found') + ['http' => 404];
			}
			$patch = ['update_time' => time()];
			if (array_key_exists('title', $payload)) {
				$title = trim(strval($payload['title']));
				if (!\EntityValidation::isTitleLengthValid($title)) {
					return Envelope::error('Invalid title length') + ['http' => 422];
				}
				$patch['title'] = $title;
			}
			if (array_key_exists('slug', $payload)) {
				$slugRaw = strval($payload['slug']);
				if (!\StatusPageMembership::isSlugInputAcceptable($slugRaw)) {
					return Envelope::error('Invalid slug') + ['http' => 422];
				}
				$slug = \StatusPageMembership::normalizeSlug($slugRaw);
				if (static::memorySlugTaken($store, $slug, $id)) {
					return Envelope::error('Slug already exists') + ['http' => 422];
				}
				$patch['slug'] = $slug;
			}
			if (array_key_exists('status', $payload)) {
				$patch['status'] = \EntityValidation::normalizeSwitchStatus($payload['status']);
			}
			if (array_key_exists('folder_ids', $payload)) {
				$patch['folder_ids'] = static::intIdList($payload['folder_ids']);
			}
			if (array_key_exists('monitor_ids', $payload)) {
				$patch['monitor_ids'] = static::intIdList($payload['monitor_ids']);
			}
			return Envelope::ok($store->update('status_pages', $id, $patch));
		}
		if ($id < 1 || !Acl::can($user, \StatusPages::PERMISSION_ACCESS, $id, WRITE)) {
			return Envelope::error($id < 1 ? 'Not found' : 'Forbidden') + ['http' => $id < 1 ? 404 : 403];
		}
		$existing = \StatusPages::get(['id' => $id]);
		if (!$existing) {
			return Envelope::error('Not found') + ['http' => 404];
		}
		$patch = ['id' => $id, 'update_time' => time(), '_mode' => \DB\Common::CSMODE_UPDATE];
		if (array_key_exists('title', $payload)) {
			$title = trim(strval($payload['title']));
			if (!\EntityValidation::isTitleLengthValid($title)) {
				return Envelope::error('Invalid title length') + ['http' => 422];
			}
			$patch['title'] = $title;
		}
		if (array_key_exists('slug', $payload)) {
			$slugRaw = strval($payload['slug']);
			if (!\StatusPageMembership::isSlugInputAcceptable($slugRaw)) {
				return Envelope::error('Invalid slug') + ['http' => 422];
			}
			$slug = \StatusPageMembership::normalizeSlug($slugRaw);
			$other = \StatusPages::get(['slug' => $slug]);
			if ($other && intval($other['id']) !== $id) {
				return Envelope::error('Slug already exists') + ['http' => 422];
			}
			$patch['slug'] = $slug;
		}
		if (array_key_exists('status', $payload)) {
			$patch['status'] = \EntityValidation::normalizeSwitchStatus($payload['status']);
		}
		\StatusPages::save($patch);
		if (array_key_exists('folder_ids', $payload) || array_key_exists('monitor_ids', $payload)) {
			$folderIds = array_key_exists('folder_ids', $payload)
				? static::intIdList($payload['folder_ids'])
				: static::folderIdsForPage($id);
			$monitorIds = array_key_exists('monitor_ids', $payload)
				? static::intIdList($payload['monitor_ids'])
				: static::monitorIdsForPage($id);
			static::syncMembership($id, $folderIds, $monitorIds, $user);
		}
		\Logs::update_log(\StatusPages::LOGS_OBJECT, $id, $existing, $patch);
		$saved = \StatusPages::get(['id' => $id]);
		return Envelope::ok(static::publicRow($saved ?: $existing));
	}

	/** @param array<string,mixed> $user */
	static public function delete(array $user, int $id): array {
		$store = Runtime::store();
		if ($store) {
			if (!$store->get('status_pages', $id)) {
				return Envelope::error('Not found') + ['http' => 404];
			}
			$store->delete('status_pages', $id);
			foreach ($store->all('status_page_incidents') as $inc) {
				if (intval($inc['status_page_id'] ?? 0) === $id) {
					$store->delete('status_page_incidents', intval($inc['id']));
				}
			}
			return Envelope::ok(['id' => $id, 'deleted' => true]);
		}
		if ($id < 1 || !Acl::can($user, \StatusPages::PERMISSION_ACCESS, $id, DELETE)) {
			return Envelope::error($id < 1 ? 'Not found' : 'Forbidden') + ['http' => $id < 1 ? 404 : 403];
		}
		$row = \StatusPages::get(['id' => $id]);
		if (!$row) {
			return Envelope::error('Not found') + ['http' => 404];
		}
		foreach (\StatusPageFolders::data(['status_page_id' => $id]) ?: [] as $link) {
			\StatusPageFolders::delete(['id' => intval($link['id'])]);
		}
		foreach (\StatusPageMonitors::data(['status_page_id' => $id]) ?: [] as $link) {
			\StatusPageMonitors::delete(['id' => intval($link['id'])]);
		}
		foreach (\StatusPageIncidents::data(['status_page_id' => $id]) ?: [] as $inc) {
			\StatusPageIncidents::delete(['id' => intval($inc['id'])]);
		}
		\StatusPages::delete(['id' => $id]);
		\Logs::delete_log(\StatusPages::LOGS_OBJECT, $id, $row);
		return Envelope::ok(['id' => $id, 'deleted' => true]);
	}

	/** @param array<string,mixed> $user @param array<string,mixed> $payload */
	static public function createIncident(array $user, int $pageId, array $payload): array {
		$title = trim(strval($payload['title'] ?? ''));
		if (!\EntityValidation::isTitleLengthValid($title)) {
			return Envelope::error('Invalid title length') + ['http' => 422];
		}
		$state = strval($payload['state'] ?? 'investigating');
		if (!\StatusPageMembership::isIncidentStateValid($state)) {
			return Envelope::error('Invalid incident state') + ['http' => 422];
		}
		$row = [
			'status_page_id' => $pageId,
			'title' => $title,
			'message' => strval($payload['message'] ?? ''),
			'state' => $state,
			'active' => \EntityValidation::normalizeSwitchStatus($payload['active'] ?? 1),
			'create_time' => time(),
			'update_time' => time(),
		];
		$store = Runtime::store();
		if ($store) {
			$page = $store->get('status_pages', $pageId);
			if (!$page) {
				return Envelope::error('Not found') + ['http' => 404];
			}
			return Envelope::ok($store->insert('status_page_incidents', $row));
		}
		if ($pageId < 1 || !Acl::can($user, \StatusPages::PERMISSION_ACCESS, $pageId, WRITE)) {
			return Envelope::error($pageId < 1 ? 'Not found' : 'Forbidden') + ['http' => $pageId < 1 ? 404 : 403];
		}
		if (!\StatusPages::get(['id' => $pageId])) {
			return Envelope::error('Not found') + ['http' => 404];
		}
		$row['_mode'] = \DB\Common::CSMODE_INSERT;
		$id = \StatusPageIncidents::save($row);
		if (!$id) {
			return Envelope::error('Save failed') + ['http' => 500];
		}
		return Envelope::ok(\StatusPageIncidents::get(['id' => intval($id)]) ?: $row + ['id' => intval($id)]);
	}

	/** @param array<string,mixed> $user */
	static public function listIncidents(array $user, int $pageId): array {
		$store = Runtime::store();
		if ($store) {
			$page = $store->get('status_pages', $pageId);
			if (!$page) {
				return Envelope::error('Not found') + ['http' => 404];
			}
			$out = [];
			foreach ($store->all('status_page_incidents') as $inc) {
				if (intval($inc['status_page_id'] ?? 0) === $pageId) {
					$out[] = $inc;
				}
			}
			return Envelope::ok($out, ['total' => count($out)]);
		}
		if ($pageId < 1 || !Acl::can($user, \StatusPages::PERMISSION_ACCESS, $pageId, READ)) {
			return Envelope::error($pageId < 1 ? 'Not found' : 'Forbidden') + ['http' => $pageId < 1 ? 404 : 403];
		}
		$rows = \StatusPageIncidents::data(
			['status_page_id' => $pageId],
			' ORDER BY `id` DESC'
		) ?: [];
		return Envelope::ok(array_values($rows), ['total' => count($rows)]);
	}

	/**
	 * @param array<string,mixed> $row
	 * @return array<string,mixed>
	 */
	static public function publicRow(array $row): array {
		$id = intval($row['id'] ?? 0);
		$out = [
			'id' => $id,
			'title' => strval($row['title'] ?? ''),
			'slug' => strval($row['slug'] ?? ''),
			'status' => intval($row['status'] ?? 0),
			'create_time' => intval($row['create_time'] ?? 0),
			'update_time' => intval($row['update_time'] ?? 0),
			'folder_ids' => array_key_exists('folder_ids', $row)
				? static::intIdList($row['folder_ids'])
				: static::folderIdsForPage($id),
			'monitor_ids' => array_key_exists('monitor_ids', $row)
				? static::intIdList($row['monitor_ids'])
				: static::monitorIdsForPage($id),
		];
		return $out;
	}

	/** @param mixed $raw @return list<int> */
	static private function intIdList(mixed $raw): array {
		if (!is_array($raw)) {
			return [];
		}
		$ids = [];
		foreach ($raw as $v) {
			$id = intval($v);
			if ($id > 0) {
				$ids[$id] = true;
			}
		}
		$out = array_map('intval', array_keys($ids));
		sort($out);
		return $out;
	}

	/** @return list<int> */
	static private function folderIdsForPage(int $pageId): array {
		if ($pageId < 1) {
			return [];
		}
		$ids = [];
		foreach (\StatusPageFolders::data(['status_page_id' => $pageId]) ?: [] as $row) {
			$id = intval($row['folder_id'] ?? 0);
			if ($id > 0) {
				$ids[] = $id;
			}
		}
		sort($ids);
		return $ids;
	}

	/** @return list<int> */
	static private function monitorIdsForPage(int $pageId): array {
		if ($pageId < 1) {
			return [];
		}
		$ids = [];
		foreach (\StatusPageMonitors::data(['status_page_id' => $pageId]) ?: [] as $row) {
			$id = intval($row['monitor_id'] ?? 0);
			if ($id > 0) {
				$ids[] = $id;
			}
		}
		sort($ids);
		return $ids;
	}

	/**
	 * @param list<int> $folderIds
	 * @param list<int> $monitorIds
	 * @param array<string,mixed> $user
	 */
	static private function syncMembership(int $pageId, array $folderIds, array $monitorIds, array $user): void {
		foreach (\StatusPageFolders::data(['status_page_id' => $pageId]) ?: [] as $link) {
			\StatusPageFolders::delete(['id' => intval($link['id'])]);
		}
		foreach (\StatusPageMonitors::data(['status_page_id' => $pageId]) ?: [] as $link) {
			\StatusPageMonitors::delete(['id' => intval($link['id'])]);
		}
		$now = time();
		foreach ($folderIds as $folderId) {
			if ($folderId < 1) {
				continue;
			}
			if (!Acl::can($user, \Monitors::PERMISSION_ACCESS, $folderId, READ)) {
				continue;
			}
			\StatusPageFolders::save([
				'status_page_id' => $pageId,
				'folder_id' => $folderId,
				'create_time' => $now,
				'update_time' => $now,
				'_mode' => \DB\Common::CSMODE_INSERT,
			]);
		}
		foreach ($monitorIds as $monitorId) {
			if ($monitorId < 1) {
				continue;
			}
			if (!Acl::can($user, \Monitors::PERMISSION_ACCESS, $monitorId, READ)) {
				continue;
			}
			\StatusPageMonitors::save([
				'status_page_id' => $pageId,
				'monitor_id' => $monitorId,
				'create_time' => $now,
				'update_time' => $now,
				'_mode' => \DB\Common::CSMODE_INSERT,
			]);
		}
	}

	static private function memorySlugTaken(MemoryStore $store, string $slug, int $exceptId): bool {
		foreach ($store->all('status_pages') as $row) {
			if (strval($row['slug'] ?? '') === $slug && intval($row['id'] ?? 0) !== $exceptId) {
				return true;
			}
		}
		return false;
	}
}

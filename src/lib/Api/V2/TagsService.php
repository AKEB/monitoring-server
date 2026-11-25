<?php

namespace Api\V2;

class TagsService {

	/** @param array<string,mixed> $user */
	static public function list(array $user, array $query = []): array {
		$store = Runtime::store();
		if ($store) {
			$rows = $store->all('monitor_tags');
			return Envelope::ok($rows, ['total' => count($rows)]);
		}
		$rows = \MonitorTags::data(false, ' ORDER BY `title` ASC') ?: [];
		$out = [];
		foreach ($rows as $row) {
			$id = intval($row['id'] ?? 0);
			if ($id > 0 && Acl::can($user, \MonitorTags::PERMISSION_ACCESS, $id, READ)) {
				$out[] = static::publicRow($row);
			}
		}
		return Envelope::ok($out, ['total' => count($out)]);
	}

	/** @param array<string,mixed> $user */
	static public function get(array $user, int $id): array {
		$store = Runtime::store();
		if ($store) {
			$row = $store->get('monitor_tags', $id);
			return $row ? Envelope::ok($row) : Envelope::error('Not found') + ['http' => 404];
		}
		if ($id < 1 || !Acl::can($user, \MonitorTags::PERMISSION_ACCESS, $id, READ)) {
			return Envelope::error($id < 1 ? 'Not found' : 'Forbidden') + ['http' => $id < 1 ? 404 : 403];
		}
		$row = \MonitorTags::get(['id' => $id]);
		return $row ? Envelope::ok(static::publicRow($row)) : Envelope::error('Not found') + ['http' => 404];
	}

	/** @param array<string,mixed> $user @param array<string,mixed> $payload */
	static public function create(array $user, array $payload): array {
		$title = \MonitorTags::normalizeTitle(strval($payload['title'] ?? ''));
		if (!\MonitorTags::isTitleValid($title)) {
			return Envelope::error('Invalid title length') + ['http' => 422];
		}
		$color = \MonitorTags::normalizeColor(strval($payload['color'] ?? \MonitorTags::DEFAULT_COLOR));
		$row = [
			'title' => $title,
			'color' => $color,
			'create_time' => time(),
			'update_time' => time(),
		];
		$store = Runtime::store();
		if ($store) {
			foreach ($store->all('monitor_tags') as $existing) {
				if (strcasecmp(strval($existing['title'] ?? ''), $title) === 0) {
					return Envelope::error('Title already exists') + ['http' => 422];
				}
			}
			return Envelope::ok($store->insert('monitor_tags', $row));
		}
		if (!Acl::canCreate($user, \MonitorTags::PERMISSION_CREATE)) {
			return Envelope::error('Forbidden') + ['http' => 403];
		}
		if (\MonitorTags::get(['title' => $title])) {
			return Envelope::error('Title already exists') + ['http' => 422];
		}
		$save = $row;
		$save['_mode'] = \DB\Common::CSMODE_INSERT;
		$id = \MonitorTags::save($save);
		if (!$id) {
			return Envelope::error('Save failed') + ['http' => 500];
		}
		\MonitorTags::grantCreatePermissions(intval($id), intval($user['id'] ?? 0));
		\Logs::create_log(\MonitorTags::LOGS_OBJECT, intval($id), $save);
		$saved = \MonitorTags::get(['id' => intval($id)]);
		return Envelope::ok(static::publicRow($saved ?: $row + ['id' => intval($id)]));
	}

	/** @param array<string,mixed> $user @param array<string,mixed> $payload */
	static public function update(array $user, int $id, array $payload): array {
		$store = Runtime::store();
		if ($store) {
			$existing = $store->get('monitor_tags', $id);
			if (!$existing) {
				return Envelope::error('Not found') + ['http' => 404];
			}
			$patch = ['update_time' => time()];
			if (array_key_exists('title', $payload)) {
				$title = \MonitorTags::normalizeTitle(strval($payload['title']));
				if (!\MonitorTags::isTitleValid($title)) {
					return Envelope::error('Invalid title length') + ['http' => 422];
				}
				foreach ($store->all('monitor_tags') as $other) {
					if (intval($other['id'] ?? 0) !== $id && strcasecmp(strval($other['title'] ?? ''), $title) === 0) {
						return Envelope::error('Title already exists') + ['http' => 422];
					}
				}
				$patch['title'] = $title;
			}
			if (array_key_exists('color', $payload)) {
				$patch['color'] = \MonitorTags::normalizeColor(strval($payload['color']));
			}
			return Envelope::ok($store->update('monitor_tags', $id, $patch) ?? []);
		}
		if ($id < 1 || !Acl::can($user, \MonitorTags::PERMISSION_ACCESS, $id, WRITE)) {
			return Envelope::error($id < 1 ? 'Not found' : 'Forbidden') + ['http' => $id < 1 ? 404 : 403];
		}
		$existing = \MonitorTags::get(['id' => $id]);
		if (!$existing) {
			return Envelope::error('Not found') + ['http' => 404];
		}
		$patch = ['id' => $id, 'update_time' => time(), '_mode' => \DB\Common::CSMODE_UPDATE];
		if (array_key_exists('title', $payload)) {
			$title = \MonitorTags::normalizeTitle(strval($payload['title']));
			if (!\MonitorTags::isTitleValid($title)) {
				return Envelope::error('Invalid title length') + ['http' => 422];
			}
			$other = \MonitorTags::get(['title' => $title]);
			if ($other && intval($other['id']) !== $id) {
				return Envelope::error('Title already exists') + ['http' => 422];
			}
			$patch['title'] = $title;
		}
		if (array_key_exists('color', $payload)) {
			$patch['color'] = \MonitorTags::normalizeColor(strval($payload['color']));
		}
		\MonitorTags::save($patch);
		\Logs::update_log(\MonitorTags::LOGS_OBJECT, $id, $existing, $patch);
		$saved = \MonitorTags::get(['id' => $id]);
		return Envelope::ok(static::publicRow($saved ?: $existing));
	}

	/** @param array<string,mixed> $user */
	static public function delete(array $user, int $id): array {
		$store = Runtime::store();
		if ($store) {
			if (!$store->delete('monitor_tags', $id)) {
				return Envelope::error('Not found') + ['http' => 404];
			}
			return Envelope::ok(['id' => $id, 'deleted' => true]);
		}
		if ($id < 1 || !Acl::can($user, \MonitorTags::PERMISSION_ACCESS, $id, DELETE)) {
			return Envelope::error($id < 1 ? 'Not found' : 'Forbidden') + ['http' => $id < 1 ? 404 : 403];
		}
		$existing = \MonitorTags::get(['id' => $id]);
		if (!$existing) {
			return Envelope::error('Not found') + ['http' => 404];
		}
		$links = \MonitoringMonitorTags::data(['tag_id' => $id]) ?: [];
		foreach ($links as $link) {
			\MonitoringMonitorTags::delete(intval($link['id']));
		}
		\MonitorTags::delete($id);
		\Logs::delete_log(\MonitorTags::LOGS_OBJECT, $id, $existing);
		return Envelope::ok(['id' => $id, 'deleted' => true]);
	}

	/**
	 * @param array<string,mixed> $row
	 * @return array<string,mixed>
	 */
	static public function publicRow(array $row): array {
		return [
			'id' => intval($row['id'] ?? 0),
			'title' => strval($row['title'] ?? ''),
			'color' => \MonitorTags::normalizeColor(strval($row['color'] ?? \MonitorTags::DEFAULT_COLOR)),
			'create_time' => intval($row['create_time'] ?? 0),
			'update_time' => intval($row['update_time'] ?? 0),
		];
	}
}

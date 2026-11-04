<?php

namespace Api\V2;

class ServersService {

	/** @param array<string,mixed> $user */
	static public function list(array $user, array $query = []): array {
		$store = Runtime::store();
		if ($store) {
			$rows = [];
			foreach ($store->all('servers') as $row) {
				$rows[] = static::serialize($row, $user);
			}
			return Envelope::ok($rows, ['total' => count($rows)]);
		}
		$rows = \Servers::data(false, ' ORDER BY `title` ASC') ?: [];
		$out = [];
		foreach ($rows as $row) {
			$id = intval($row['id'] ?? 0);
			if ($id > 0 && Acl::can($user, \Servers::PERMISSION_ACCESS, $id, READ)) {
				$out[] = static::serialize($row, $user);
			}
		}
		return Envelope::ok($out, ['total' => count($out)]);
	}

	/** @param array<string,mixed> $user */
	static public function get(array $user, int $id): array {
		$store = Runtime::store();
		if ($store) {
			$row = $store->get('servers', $id);
			return $row ? Envelope::ok(static::serialize($row, $user)) : Envelope::error('Not found') + ['http' => 404];
		}
		if ($id < 1 || !Acl::can($user, \Servers::PERMISSION_ACCESS, $id, READ)) {
			return Envelope::error($id < 1 ? 'Not found' : 'Forbidden') + ['http' => $id < 1 ? 404 : 403];
		}
		$row = \Servers::get(['id' => $id]);
		return $row ? Envelope::ok(static::serialize($row, $user)) : Envelope::error('Not found') + ['http' => 404];
	}

	/** @param array<string,mixed> $user @param array<string,mixed> $payload */
	static public function create(array $user, array $payload): array {
		$title = trim(strval($payload['title'] ?? ''));
		if (!\EntityValidation::isTitleLengthValid($title)) {
			return Envelope::error('Invalid title length') + ['http' => 422];
		}
		$row = static::normalizeFields($payload, true);
		$row['title'] = $title;
		$row['agent_key_hash'] = \Servers::generateAgentKeyHash();
		$row['create_time'] = time();
		$row['update_time'] = time();

		$store = Runtime::store();
		if ($store) {
			return Envelope::ok(static::serialize($store->insert('servers', $row), $user));
		}
		if (!Acl::canCreate($user, \Servers::PERMISSION_CREATE)) {
			return Envelope::error('Forbidden') + ['http' => 403];
		}
		$row['_mode'] = \DB\Common::CSMODE_INSERT;
		$id = \Servers::save($row);
		if (!$id) {
			return Envelope::error('Save failed') + ['http' => 500];
		}
		$id = intval($id);
		\Logs::create_log(\Servers::LOGS_OBJECT, $id, $row);
		static::grantCreatorAccess($user, $id);
		return Envelope::ok(static::serialize(\Servers::get(['id' => $id]) ?: $row + ['id' => $id], $user));
	}

	/** @param array<string,mixed> $user @param array<string,mixed> $payload */
	static public function update(array $user, int $id, array $payload): array {
		$store = Runtime::store();
		if ($store) {
			$existing = $store->get('servers', $id);
			if (!$existing) {
				return Envelope::error('Not found') + ['http' => 404];
			}
			$patch = static::normalizeFields($payload, false);
			if (array_key_exists('title', $payload)) {
				$title = trim(strval($payload['title']));
				if (!\EntityValidation::isTitleLengthValid($title)) {
					return Envelope::error('Invalid title length') + ['http' => 422];
				}
				$patch['title'] = $title;
			}
			if (!empty($payload['regenerate_key'])) {
				$patch['agent_key_hash'] = \Servers::generateAgentKeyHash();
			}
			$patch['update_time'] = time();
			return Envelope::ok(static::serialize($store->update('servers', $id, $patch) ?? [], $user));
		}
		if ($id < 1 || !Acl::can($user, \Servers::PERMISSION_ACCESS, $id, WRITE)) {
			return Envelope::error($id < 1 ? 'Not found' : 'Forbidden') + ['http' => $id < 1 ? 404 : 403];
		}
		$existing = \Servers::get(['id' => $id]);
		if (!$existing) {
			return Envelope::error('Not found') + ['http' => 404];
		}
		$patch = static::normalizeFields($payload, false);
		if (array_key_exists('title', $payload)) {
			$title = trim(strval($payload['title']));
			if (!\EntityValidation::isTitleLengthValid($title)) {
				return Envelope::error('Invalid title length') + ['http' => 422];
			}
			$patch['title'] = $title;
		}
		if (!empty($payload['regenerate_key'])) {
			$patch['agent_key_hash'] = \Servers::generateAgentKeyHash();
		}
		$patch['id'] = $id;
		$patch['update_time'] = time();
		$patch['_mode'] = \DB\Common::CSMODE_UPDATE;
		\Servers::save($patch);
		\Logs::update_log(\Servers::LOGS_OBJECT, $id, $existing, $patch);
		return Envelope::ok(static::serialize(\Servers::get(['id' => $id]) ?: $existing, $user));
	}

	/** @param array<string,mixed> $user */
	static public function delete(array $user, int $id): array {
		$store = Runtime::store();
		if ($store) {
			return $store->delete('servers', $id)
				? Envelope::ok(['id' => $id, 'deleted' => true])
				: Envelope::error('Not found') + ['http' => 404];
		}
		if ($id < 1 || !Acl::can($user, \Servers::PERMISSION_ACCESS, $id, DELETE)) {
			return Envelope::error($id < 1 ? 'Not found' : 'Forbidden') + ['http' => $id < 1 ? 404 : 403];
		}
		$existing = \Servers::get(['id' => $id]);
		if (!$existing) {
			return Envelope::error('Not found') + ['http' => 404];
		}
		foreach (\Monitors::data(['server' => $id]) ?: [] as $monitor) {
			$mid = intval($monitor['id']);
			foreach (\MonitoringJobs::data(['monitor_id' => $mid]) ?: [] as $job) {
				\MonitoringJobs::delete(['id' => intval($job['id'])]);
			}
			\MonitorsEvents::delete(['monitor_id' => $mid]);
			\MonitoringLogs::delete(['monitor_id' => $mid]);
			\Monitors::delete(['id' => $mid]);
		}
		\ServerMetrics::delete(['server_id' => $id]);
		\ServerAuthEvents::delete(['server_id' => $id]);
		\Servers::delete($id);
		\Logs::delete_log(\Servers::LOGS_OBJECT, $id, $existing);
		return Envelope::ok(['id' => $id, 'deleted' => true]);
	}

	/**
	 * @param array<string,mixed> $payload
	 * @return array<string,mixed>
	 */
	static private function normalizeFields(array $payload, bool $creating): array {
		$row = [];
		if ($creating || array_key_exists('status', $payload)) {
			$row['status'] = \EntityValidation::normalizeSwitchStatus($payload['status'] ?? 1);
		}
		return $row;
	}

	/**
	 * @param array<string,mixed> $user
	 * @param array<string,mixed> $row
	 * @return array<string,mixed>
	 */
	static public function serialize(array $row, array $user = []): array {
		unset($row['_mode']);
		$id = intval($row['id'] ?? 0);
		$row['online'] = \Servers::isOnline($row);
		$canKey = !empty($user['__agent_key_hash'])
			|| Runtime::$bypassAcl
			|| ($id > 0 && Acl::can($user, \Servers::PERMISSION_SECRETS, $id, READ));
		if (!$canKey && array_key_exists('agent_key_hash', $row)) {
			$row['agent_key_hash'] = '***';
		}
		if (!empty($row['last_payload']) && is_string($row['last_payload'])) {
			$decoded = @json_decode($row['last_payload'], true);
			$row['payload'] = is_array($decoded) ? $decoded : null;
		}
		unset($row['last_payload']);
		return $row;
	}

	/** @param array<string,mixed> $user */
	static private function grantCreatorAccess(array $user, int $id): void {
		$userId = intval($user['id'] ?? 0);
		if ($userId < 1 || Runtime::$bypassAcl) {
			return;
		}
		if (!empty($user['is_admin'])) {
			return;
		}
		foreach (\Servers::permissions_hash() as $permission => $_) {
			\ObjectPermissions::save([
				'object' => 'user',
				'object_id' => $userId,
				'subject' => $permission,
				'subject_id' => $id,
				READ => 1,
				WRITE => 1,
				DELETE => 1,
				ACCESS_READ => 1,
				ACCESS_WRITE => 1,
				ACCESS_CHANGE => 1,
				'create_time' => time(),
				'update_time' => time(),
				'_mode' => \DB\Common::CSMODE_INSERT,
			]);
		}
	}
}

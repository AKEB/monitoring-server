<?php

namespace Api\V2;

class DockersService {

	/** @param array<string,mixed> $user */
	static public function list(array $user, array $query = []): array {
		$store = Runtime::store();
		if ($store) {
			$rows = [];
			foreach ($store->all('dockers') as $row) {
				$rows[] = static::serialize($row, $store);
			}
			return Envelope::ok($rows, ['total' => count($rows)]);
		}
		$rows = \Dockers::data(false, ' ORDER BY `title` ASC') ?: [];
		$out = [];
		foreach ($rows as $row) {
			$id = intval($row['id'] ?? 0);
			if ($id > 0 && Acl::can($user, \Dockers::PERMISSION_ACCESS, $id, READ)) {
				$out[] = static::serialize($row);
			}
		}
		return Envelope::ok($out, ['total' => count($out)]);
	}

	/** @param array<string,mixed> $user */
	static public function get(array $user, int $id): array {
		$store = Runtime::store();
		if ($store) {
			$row = $store->get('dockers', $id);
			return $row ? Envelope::ok(static::serialize($row, $store)) : Envelope::error('Not found') + ['http' => 404];
		}
		if ($id < 1 || !Acl::can($user, \Dockers::PERMISSION_ACCESS, $id, READ)) {
			return Envelope::error($id < 1 ? 'Not found' : 'Forbidden') + ['http' => $id < 1 ? 404 : 403];
		}
		$row = \Dockers::get(['id' => $id]);
		return $row ? Envelope::ok(static::serialize($row)) : Envelope::error('Not found') + ['http' => 404];
	}

	/** @param array<string,mixed> $user @param array<string,mixed> $payload */
	static public function create(array $user, array $payload): array {
		$title = trim(strval($payload['title'] ?? ''));
		if (!\EntityValidation::isTitleLengthValid($title)) {
			return Envelope::error('Invalid title length') + ['http' => 422];
		}
		$row = static::normalizeFields($payload, true, null);
		$row['title'] = $title;
		$row['create_time'] = time();
		$row['update_time'] = time();
		$workers = Relations::intIdList($payload['workers'] ?? []);

		$store = Runtime::store();
		if ($store) {
			$row['workers'] = $workers;
			return Envelope::ok(static::serialize($store->insert('dockers', $row), $store));
		}
		if (!Acl::canCreate($user, \Dockers::PERMISSION_CREATE)) {
			return Envelope::error('Forbidden') + ['http' => 403];
		}
		$row['_mode'] = \DB\Common::CSMODE_INSERT;
		$id = \Dockers::save($row);
		if (!$id) {
			return Envelope::error('Save failed') + ['http' => 500];
		}
		$id = intval($id);
		\Logs::create_log(\Dockers::LOGS_OBJECT, $id, $row);
		if (!Runtime::$bypassAcl) {
			$workers = array_values(array_filter($workers, fn($wid) => Acl::can($user, \Workers::PERMISSION_ACCESS, $wid, READ)));
		}
		$diff = Relations::diffLinks([], $workers, fn(int $wid) => true);
		Relations::applyDockerWorkers($id, $diff);
		return Envelope::ok(static::serialize(\Dockers::get(['id' => $id]) ?: $row + ['id' => $id]));
	}

	/** @param array<string,mixed> $user @param array<string,mixed> $payload */
	static public function update(array $user, int $id, array $payload): array {
		$store = Runtime::store();
		if ($store) {
			$existing = $store->get('dockers', $id);
			if (!$existing) {
				return Envelope::error('Not found') + ['http' => 404];
			}
			if (array_key_exists('title', $payload)) {
				$title = trim(strval($payload['title']));
				if (!\EntityValidation::isTitleLengthValid($title)) {
					return Envelope::error('Invalid title length') + ['http' => 422];
				}
				$payload['title'] = $title;
			}
			$patch = static::normalizeFields($payload, false, $existing);
			if (array_key_exists('title', $payload)) {
				$patch['title'] = $payload['title'];
			}
			if (array_key_exists('workers', $payload)) {
				$patch['workers'] = Relations::intIdList($payload['workers']);
			}
			$patch['update_time'] = time();
			return Envelope::ok(static::serialize($store->update('dockers', $id, $patch) ?? [], $store));
		}
		if ($id < 1 || !Acl::can($user, \Dockers::PERMISSION_ACCESS, $id, WRITE)) {
			return Envelope::error($id < 1 ? 'Not found' : 'Forbidden') + ['http' => $id < 1 ? 404 : 403];
		}
		$existing = \Dockers::get(['id' => $id]);
		if (!$existing) {
			return Envelope::error('Not found') + ['http' => 404];
		}
		if (array_key_exists('title', $payload)) {
			$title = trim(strval($payload['title']));
			if (!\EntityValidation::isTitleLengthValid($title)) {
				return Envelope::error('Invalid title length') + ['http' => 422];
			}
			$payload['title'] = $title;
		}
		$patch = static::normalizeFields($payload, false, $existing);
		if (array_key_exists('title', $payload)) {
			$patch['title'] = $payload['title'];
		}
		$patch['id'] = $id;
		$patch['update_time'] = time();
		$patch['_mode'] = \DB\Common::CSMODE_UPDATE;
		\Dockers::save($patch);
		\Logs::update_log(\Dockers::LOGS_OBJECT, $id, $existing, $patch);

		if (array_key_exists('workers', $payload)) {
			$old = [];
			foreach (\MonitoringDockers::data(['docker_id' => $id]) ?: [] as $link) {
				$old[] = intval($link['worker_id'] ?? 0);
			}
			$desired = Relations::intIdList($payload['workers']);
			if (!Runtime::$bypassAcl) {
				$desired = array_values(array_filter($desired, fn($wid) => Acl::can($user, \Workers::PERMISSION_ACCESS, $wid, READ)));
			}
			$diff = Relations::diffLinks($old, $desired, fn(int $wid) => Acl::can($user, \Workers::PERMISSION_ACCESS, $wid, READ) || Runtime::$bypassAcl);
			Relations::applyDockerWorkers($id, $diff);
		}
		return Envelope::ok(static::serialize(\Dockers::get(['id' => $id]) ?: $existing));
	}

	/** @param array<string,mixed> $user */
	static public function delete(array $user, int $id): array {
		$store = Runtime::store();
		if ($store) {
			return $store->delete('dockers', $id)
				? Envelope::ok(['id' => $id, 'deleted' => true])
				: Envelope::error('Not found') + ['http' => 404];
		}
		if ($id < 1 || !Acl::can($user, \Dockers::PERMISSION_ACCESS, $id, DELETE)) {
			return Envelope::error($id < 1 ? 'Not found' : 'Forbidden') + ['http' => $id < 1 ? 404 : 403];
		}
		$existing = \Dockers::get(['id' => $id]);
		if (!$existing) {
			return Envelope::error('Not found') + ['http' => 404];
		}
		\Dockers::delete($id);
		\Logs::delete_log(\Dockers::LOGS_OBJECT, $id, $existing);
		return Envelope::ok(['id' => $id, 'deleted' => true]);
	}

	/**
	 * @param array<string,mixed> $payload
	 * @param array<string,mixed>|null $existing
	 * @return array<string,mixed>
	 */
	static private function normalizeFields(array $payload, bool $creating, ?array $existing): array {
		$row = [];
		if ($creating || array_key_exists('status', $payload)) {
			$row['status'] = \EntityValidation::normalizeSwitchStatus($payload['status'] ?? 0);
		}
		if ($creating || array_key_exists('host', $payload)) {
			$row['host'] = trim(strval($payload['host'] ?? ''));
		}
		if ($creating || array_key_exists('port', $payload)) {
			$row['port'] = intval($payload['port'] ?? 0);
		}
		if ($creating || array_key_exists('repeat_seconds', $payload)) {
			$row['repeat_seconds'] = \EntityValidation::clampRepeatSeconds(intval($payload['repeat_seconds'] ?? 60));
		}
		if ($creating || array_key_exists('timeout', $payload)) {
			$row['timeout'] = intval($payload['timeout'] ?? 0);
		}
		if ($creating || array_key_exists('proxy_host', $payload)) {
			$row['proxy_host'] = trim(strval($payload['proxy_host'] ?? ''));
		}
		if ($creating || array_key_exists('proxy_type', $payload)) {
			$row['proxy_type'] = intval($payload['proxy_type'] ?? 0);
		}
		if ($creating || array_key_exists('tls', $payload)) {
			$row['tls'] = \EntityValidation::normalizeSwitchStatus($payload['tls'] ?? 0);
		}
		$tlsOn = intval($row['tls'] ?? ($existing['tls'] ?? 0)) === 1;
		foreach (['tls_ca_file', 'tls_certificate', 'tls_key'] as $field) {
			if (array_key_exists($field, $payload)) {
				$row[$field] = Relations::normalizeTlsBlob($payload[$field]);
			} elseif ($creating) {
				$row[$field] = '';
			} elseif ($tlsOn && $existing && !empty($existing[$field])) {
				// preserve existing blobs when TLS stays on and field omitted
				$row[$field] = $existing[$field];
			}
		}
		return $row;
	}

	/**
	 * @param array<string,mixed> $row
	 * @return array<string,mixed>
	 */
	static public function serialize(array $row, ?MemoryStore $store = null): array {
		$hasTls = [
			'tls_ca_file' => !empty($row['tls_ca_file']),
			'tls_certificate' => !empty($row['tls_certificate']),
			'tls_key' => !empty($row['tls_key']),
		];
		unset($row['tls_ca_file'], $row['tls_certificate'], $row['tls_key'], $row['_mode']);
		$row['tls_present'] = $hasTls;
		$id = intval($row['id'] ?? 0);
		if ($store) {
			$row['workers'] = Relations::intIdList($row['workers'] ?? []);
			return $row;
		}
		if ($id > 0 && !Runtime::store()) {
			$workers = [];
			foreach (\MonitoringDockers::data(['docker_id' => $id]) ?: [] as $link) {
				$wid = intval($link['worker_id'] ?? 0);
				if ($wid > 0) {
					$workers[] = $wid;
				}
			}
			$row['workers'] = array_values(array_unique($workers));
		}
		return $row;
	}
}

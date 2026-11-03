<?php

namespace Api\V2;

class WorkersService {

	/** @param array<string,mixed> $user */
	static public function list(array $user, array $query = []): array {
		$store = Runtime::store();
		if ($store) {
			$rows = [];
			foreach ($store->all('workers') as $row) {
				$rows[] = static::serialize($user, $row, $store);
			}
			return Envelope::ok($rows, ['total' => count($rows)]);
		}
		$rows = \Workers::data(false, ' ORDER BY `title` ASC') ?: [];
		$out = [];
		foreach ($rows as $row) {
			$id = intval($row['id'] ?? 0);
			if ($id > 0 && Acl::can($user, \Workers::PERMISSION_ACCESS, $id, READ)) {
				$out[] = static::serialize($user, $row);
			}
		}
		return Envelope::ok($out, ['total' => count($out)]);
	}

	/** @param array<string,mixed> $user */
	static public function get(array $user, int $id): array {
		$store = Runtime::store();
		if ($store) {
			$row = $store->get('workers', $id);
			return $row ? Envelope::ok(static::serialize($user, $row, $store)) : Envelope::error('Not found') + ['http' => 404];
		}
		if ($id < 1 || !Acl::can($user, \Workers::PERMISSION_ACCESS, $id, READ)) {
			return Envelope::error($id < 1 ? 'Not found' : 'Forbidden') + ['http' => $id < 1 ? 404 : 403];
		}
		$row = \Workers::get(['id' => $id]);
		return $row ? Envelope::ok(static::serialize($user, $row)) : Envelope::error('Not found') + ['http' => 404];
	}

	/** @param array<string,mixed> $user @param array<string,mixed> $payload */
	static public function create(array $user, array $payload): array {
		$title = trim(strval($payload['title'] ?? ''));
		if (!\EntityValidation::isTitleLengthValid($title)) {
			return Envelope::error('Invalid title length') + ['http' => 422];
		}
		$row = static::normalizeFields($payload, true);
		$row['title'] = $title;
		$row['worker_key_hash'] = hash('sha512', md5($title.microtime().random_int(100000000, 999999999)));
		$row['create_time'] = time();
		$row['update_time'] = time();
		$monitors = Relations::intIdList($payload['monitors'] ?? []);
		$dockers = Relations::intIdList($payload['dockers'] ?? []);

		$store = Runtime::store();
		if ($store) {
			$row['monitors'] = $monitors;
			$row['dockers'] = $dockers;
			return Envelope::ok(static::serialize($user, $store->insert('workers', $row), $store));
		}
		if (!Acl::canCreate($user, \Workers::PERMISSION_CREATE)) {
			return Envelope::error('Forbidden') + ['http' => 403];
		}
		$row['_mode'] = \DB\Common::CSMODE_INSERT;
		$id = \Workers::save($row);
		if (!$id) {
			return Envelope::error('Save failed') + ['http' => 500];
		}
		$id = intval($id);
		\Logs::create_log(\Workers::LOGS_OBJECT, $id, $row);
		static::syncAssignments($user, $id, $monitors, $dockers, true);
		$saved = \Workers::get(['id' => $id]);
		return Envelope::ok(static::serialize($user, $saved ?: $row + ['id' => $id]));
	}

	/** @param array<string,mixed> $user @param array<string,mixed> $payload */
	static public function update(array $user, int $id, array $payload): array {
		$store = Runtime::store();
		if ($store) {
			$existing = $store->get('workers', $id);
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
			if (array_key_exists('monitors', $payload)) {
				$patch['monitors'] = Relations::intIdList($payload['monitors']);
			}
			if (array_key_exists('dockers', $payload)) {
				$patch['dockers'] = Relations::intIdList($payload['dockers']);
			}
			if (!empty($payload['restart_requested']) || !empty($payload['restart_worker'])) {
				$patch['restart_requested'] = 1;
			}
			$patch['update_time'] = time();
			return Envelope::ok(static::serialize($user, $store->update('workers', $id, $patch) ?? [], $store));
		}
		if ($id < 1 || !Acl::can($user, \Workers::PERMISSION_ACCESS, $id, WRITE)) {
			return Envelope::error($id < 1 ? 'Not found' : 'Forbidden') + ['http' => $id < 1 ? 404 : 403];
		}
		$existing = \Workers::get(['id' => $id]);
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
		if (!empty($payload['restart_requested']) || !empty($payload['restart_worker'])) {
			$patch['restart_requested'] = 1;
		}
		$patch['id'] = $id;
		$patch['update_time'] = time();
		$patch['_mode'] = \DB\Common::CSMODE_UPDATE;
		\Workers::save($patch);
		\Logs::update_log(\Workers::LOGS_OBJECT, $id, $existing, $patch);

		$syncMonitors = array_key_exists('monitors', $payload);
		$syncDockers = array_key_exists('dockers', $payload);
		if ($syncMonitors || $syncDockers) {
			static::syncAssignments(
				$user,
				$id,
				$syncMonitors ? Relations::intIdList($payload['monitors']) : null,
				$syncDockers ? Relations::intIdList($payload['dockers']) : null,
				false
			);
		}
		return Envelope::ok(static::serialize($user, \Workers::get(['id' => $id]) ?: $existing));
	}

	/** @param array<string,mixed> $user */
	static public function delete(array $user, int $id): array {
		$store = Runtime::store();
		if ($store) {
			return $store->delete('workers', $id)
				? Envelope::ok(['id' => $id, 'deleted' => true])
				: Envelope::error('Not found') + ['http' => 404];
		}
		if ($id < 1 || !Acl::can($user, \Workers::PERMISSION_ACCESS, $id, DELETE)) {
			return Envelope::error($id < 1 ? 'Not found' : 'Forbidden') + ['http' => $id < 1 ? 404 : 403];
		}
		$existing = \Workers::get(['id' => $id]);
		if (!$existing) {
			return Envelope::error('Not found') + ['http' => 404];
		}
		\Workers::delete($id);
		\Logs::delete_log(\Workers::LOGS_OBJECT, $id, $existing);
		return Envelope::ok(['id' => $id, 'deleted' => true]);
	}

	/**
	 * @param array<string,mixed> $user
	 * @param array<string,mixed> $worker
	 * @return array<string,mixed>
	 */
	static public function serialize(array $user, array $worker, ?MemoryStore $store = null): array {
		$out = $worker;
		$canKey = !empty($user['__worker_key_hash']);
		if (!$canKey && !Runtime::$bypassAcl) {
			$id = intval($out['id'] ?? 0);
			$canKey = $id > 0 && Acl::can($user, \Workers::PERMISSION_SECRETS, $id, READ);
		}
		if (Runtime::$bypassAcl && empty($user['__worker_key_hash'])) {
			$canKey = false;
		}
		if (!$canKey && array_key_exists('worker_key_hash', $out)) {
			$out['worker_key_hash'] = '***';
		}
		$id = intval($out['id'] ?? 0);
		if ($store) {
			$out['monitors'] = Relations::intIdList($out['monitors'] ?? []);
			$out['dockers'] = Relations::intIdList($out['dockers'] ?? []);
			return $out;
		}
		if ($id > 0 && !Runtime::store()) {
			$monitors = [];
			foreach (\MonitoringJobs::data(['worker_id' => $id]) ?: [] as $job) {
				$mid = intval($job['monitor_id'] ?? 0);
				if ($mid > 0) {
					$monitors[] = $mid;
				}
			}
			$dockers = [];
			foreach (\MonitoringDockers::data(['worker_id' => $id]) ?: [] as $link) {
				$did = intval($link['docker_id'] ?? 0);
				if ($did > 0) {
					$dockers[] = $did;
				}
			}
			$out['monitors'] = array_values(array_unique($monitors));
			$out['dockers'] = array_values(array_unique($dockers));
		}
		unset($out['_mode']);
		return $out;
	}

	/**
	 * @param array<string,mixed> $payload
	 * @return array<string,mixed>
	 */
	static private function normalizeFields(array $payload, bool $creating): array {
		$row = [];
		foreach (['worker_threads', 'jobs_get_timeout', 'loop_timeout', 'response_send_timeout', 'logs_write_timeout'] as $field) {
			if ($creating || array_key_exists($field, $payload)) {
				$row[$field] = intval($payload[$field] ?? 0);
			}
		}
		return $row;
	}

	/**
	 * @param array<string,mixed> $user
	 * @param list<int>|null $monitors
	 * @param list<int>|null $dockers
	 */
	static private function syncAssignments(array $user, int $workerId, ?array $monitors, ?array $dockers, bool $creating): void {
		if ($monitors !== null) {
			if (!Runtime::$bypassAcl) {
				$monitors = array_values(array_filter($monitors, fn($mid) => Acl::can($user, \Monitors::PERMISSION_ACCESS, $mid, READ)));
			}
			$old = [];
			if (!$creating) {
				foreach (\MonitoringJobs::data(['worker_id' => $workerId]) ?: [] as $job) {
					$old[] = intval($job['monitor_id'] ?? 0);
				}
			}
			$diff = Relations::diffLinks($old, $monitors, fn(int $mid) => true);
			foreach ($diff as $monitorId => $status) {
				if ($status < 0) {
					$oldJob = \MonitoringJobs::get(['monitor_id' => $monitorId, 'worker_id' => $workerId]);
					if (!empty($oldJob['id'])) {
						\MonitoringJobs::delete(intval($oldJob['id']));
					}
				} else {
					\MonitoringJobs::save([
						'monitor_id' => $monitorId,
						'worker_id' => $workerId,
						'create_time' => time(),
						'update_time' => time(),
					]);
				}
			}
		}
		if ($dockers !== null) {
			if (!Runtime::$bypassAcl) {
				$dockers = array_values(array_filter($dockers, fn($did) => Acl::can($user, \Dockers::PERMISSION_ACCESS, $did, READ)));
			}
			$old = [];
			if (!$creating) {
				foreach (\MonitoringDockers::data(['worker_id' => $workerId]) ?: [] as $link) {
					$old[] = intval($link['docker_id'] ?? 0);
				}
			}
			$diff = Relations::diffLinks($old, $dockers, fn(int $did) => true);
			foreach ($diff as $dockerId => $status) {
				Relations::applyDockerWorkers(intval($dockerId), [$workerId => $status]);
			}
		}
	}
}

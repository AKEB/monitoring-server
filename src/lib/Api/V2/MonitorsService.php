<?php

namespace Api\V2;

class MonitorsService {

	/** @param array<string,mixed> $user @param array<string,mixed> $query */
	static public function list(array $user, array $query = []): array {
		$store = Runtime::store();
		if ($store) {
			$rows = [];
			foreach ($store->all('monitors') as $row) {
				$rows[] = static::publicRow($row, $store);
			}
			return Envelope::ok($rows, ['total' => count($rows)]);
		}
		$rows = \Monitors::data(false, ' ORDER BY `title` ASC') ?: [];
		$out = [];
		foreach ($rows as $row) {
			$id = intval($row['id'] ?? 0);
			if ($id < 1 || !Acl::can($user, \Monitors::PERMISSION_ACCESS, $id, READ)) {
				continue;
			}
			$out[] = static::publicRow($row);
		}
		return Envelope::ok($out, ['total' => count($out)]);
	}

	/** @param array<string,mixed> $user */
	static public function get(array $user, int $id): array {
		$store = Runtime::store();
		if ($store) {
			$row = $store->get('monitors', $id);
			if (!$row) {
				return Envelope::error('Not found') + ['http' => 404];
			}
			return Envelope::ok(static::publicRow($row, $store));
		}
		if ($id < 1 || !Acl::can($user, \Monitors::PERMISSION_ACCESS, $id, READ)) {
			return Envelope::error($id < 1 ? 'Not found' : 'Forbidden') + ['http' => $id < 1 ? 404 : 403];
		}
		$row = \Monitors::get(['id' => $id]);
		if (!$row) {
			return Envelope::error('Not found') + ['http' => 404];
		}
		return Envelope::ok(static::publicRow($row));
	}

	/** @param array<string,mixed> $user @param array<string,mixed> $payload */
	static public function create(array $user, array $payload): array {
		$title = trim(strval($payload['title'] ?? ''));
		if (!\EntityValidation::isTitleLengthValid($title)) {
			return Envelope::error('Invalid title length') + ['http' => 422];
		}
		$type = intval($payload['type'] ?? \Monitors::TYPE_HTTPS);
		$built = static::buildRow($user, $payload, $type, null);
		if (isset($built['error'])) {
			return Envelope::error($built['error']) + ['http' => intval($built['http'] ?? 422)];
		}
		/** @var array<string,mixed> $row */
		$row = $built['row'];
		$row['title'] = $title;
		$row['type'] = $type;
		$row['create_time'] = time();
		$row['update_time'] = time();
		$workers = $built['workers'];
		$notify = $built['notify'];

		$store = Runtime::store();
		if ($store) {
			$row['workers'] = $workers;
			$row['notify'] = $notify;
			return Envelope::ok(static::publicRow($store->insert('monitors', $row), $store));
		}
		if (!Acl::canCreate($user, \Monitors::PERMISSION_CREATE)) {
			return Envelope::error('Forbidden') + ['http' => 403];
		}
		$row['response_code'] = $row['response_code'] ?? '';
		$row['request_headers'] = $row['request_headers'] ?? '';
		$row['external_log'] = $row['external_log'] ?? '';
		$row['_mode'] = \DB\Common::CSMODE_INSERT;
		$id = \Monitors::save($row);
		if (!$id) {
			return Envelope::error('Save failed') + ['http' => 500];
		}
		$id = intval($id);
		static::grantCreatorAccess($user, $id);
		$monitor = \Monitors::get(['id' => $id]);
		\Logs::create_log(\Monitors::LOGS_OBJECT, $id, $monitor ?: $row);

		$workerDiff = Relations::diffLinks([], $workers, fn(int $wid) => Acl::can($user, \Workers::PERMISSION_ACCESS, $wid, READ));
		Relations::applyMonitorWorkers($id, $workerDiff);
		$notifyDiff = Relations::diffLinks([], $notify, fn(int $nid) => Acl::can($user, \Notify::PERMISSION_ACCESS, $nid, READ));
		Relations::applyMonitorNotify($id, $notifyDiff);

		if ($type === \Monitors::TYPE_EXTERNAL) {
			\ExternalMonitors::ensureVirtualJob($id);
			\ExternalMonitors::clearRealJobs($id);
		}
		if (\Monitors::is_server_type($type)) {
			\ServerAgent::ensureVirtualJob($id);
			\ServerAgent::clearRealJobs($id);
		}

		return Envelope::ok(static::publicRow($monitor ?: $row + ['id' => $id]));
	}

	/** @param array<string,mixed> $user @param array<string,mixed> $payload */
	static public function update(array $user, int $id, array $payload): array {
		$store = Runtime::store();
		if ($store) {
			$existing = $store->get('monitors', $id);
			if (!$existing) {
				return Envelope::error('Not found') + ['http' => 404];
			}
			$type = intval($payload['type'] ?? $existing['type']);
			if (array_key_exists('title', $payload)) {
				$title = trim(strval($payload['title']));
				if (!\EntityValidation::isTitleLengthValid($title)) {
					return Envelope::error('Invalid title length') + ['http' => 422];
				}
				$payload['title'] = $title;
			}
			$built = static::buildRow($user, $payload, $type, $existing);
			if (isset($built['error'])) {
				return Envelope::error($built['error']) + ['http' => intval($built['http'] ?? 422)];
			}
			$patch = $built['row'];
			if (array_key_exists('title', $payload)) {
				$patch['title'] = $payload['title'];
			}
			if (array_key_exists('type', $payload)) {
				$patch['type'] = $type;
			}
			if (array_key_exists('workers', $payload) || $type === \Monitors::TYPE_EXTERNAL) {
				$patch['workers'] = $built['workers'];
			}
			if (array_key_exists('notify', $payload)) {
				$patch['notify'] = $built['notify'];
			}
			$patch['update_time'] = time();
			return Envelope::ok(static::publicRow($store->update('monitors', $id, $patch) ?? [], $store));
		}

		if ($id < 1 || !Acl::can($user, \Monitors::PERMISSION_ACCESS, $id, WRITE)) {
			return Envelope::error($id < 1 ? 'Not found' : 'Forbidden') + ['http' => $id < 1 ? 404 : 403];
		}
		$existing = \Monitors::get(['id' => $id]);
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
		$type = intval($payload['type'] ?? $existing['type']);
		$built = static::buildRow($user, $payload, $type, $existing);
		if (isset($built['error'])) {
			return Envelope::error($built['error']) + ['http' => intval($built['http'] ?? 422)];
		}
		$patch = $built['row'];
		if (array_key_exists('title', $payload)) {
			$patch['title'] = $payload['title'];
		}
		if (array_key_exists('type', $payload)) {
			$patch['type'] = $type;
		}
		$patch['id'] = $id;
		$patch['update_time'] = time();
		$patch['_mode'] = \DB\Common::CSMODE_UPDATE;
		\Monitors::save($patch);
		\Logs::update_log(\Monitors::LOGS_OBJECT, $id, $existing, $patch);

		if (array_key_exists('workers', $payload) || $type === \Monitors::TYPE_EXTERNAL || intval($existing['type']) === \Monitors::TYPE_EXTERNAL) {
			$oldWorkers = [];
			foreach (\MonitoringJobs::data(['monitor_id' => $id]) ?: [] as $job) {
				$wid = intval($job['worker_id'] ?? 0);
				if ($wid !== \Monitors::EXTERNAL_WORKER_ID) {
					$oldWorkers[] = $wid;
				}
			}
			$diff = Relations::diffLinks(
				$oldWorkers,
				$built['workers'],
				fn(int $wid) => Acl::can($user, \Workers::PERMISSION_ACCESS, $wid, READ)
			);
			Relations::applyMonitorWorkers($id, $diff);
		}
		if (array_key_exists('notify', $payload)) {
			$oldNotify = [];
			foreach (\MonitoringNotify::data(['monitor_id' => $id]) ?: [] as $link) {
				$oldNotify[] = intval($link['notify_id'] ?? 0);
			}
			$diff = Relations::diffLinks(
				$oldNotify,
				$built['notify'],
				fn(int $nid) => Acl::can($user, \Notify::PERMISSION_ACCESS, $nid, READ)
			);
			Relations::applyMonitorNotify($id, $diff);
		}

		if ($type === \Monitors::TYPE_EXTERNAL) {
			\ExternalMonitors::ensureVirtualJob($id);
			\ExternalMonitors::clearRealJobs($id);
		} elseif (intval($existing['type']) === \Monitors::TYPE_EXTERNAL) {
			\ExternalMonitors::removeVirtualJob($id);
		}
		if (\Monitors::is_server_type($type)) {
			\ServerAgent::ensureVirtualJob($id);
			\ServerAgent::clearRealJobs($id);
		}

		$saved = \Monitors::get(['id' => $id]);
		return Envelope::ok(static::publicRow($saved ?: $existing));
	}

	/** @param array<string,mixed> $user */
	static public function delete(array $user, int $id): array {
		$store = Runtime::store();
		if ($store) {
			if (!$store->delete('monitors', $id)) {
				return Envelope::error('Not found') + ['http' => 404];
			}
			return Envelope::ok(['id' => $id, 'deleted' => true]);
		}
		if ($id < 1 || !Acl::can($user, \Monitors::PERMISSION_ACCESS, $id, DELETE)) {
			return Envelope::error($id < 1 ? 'Not found' : 'Forbidden') + ['http' => $id < 1 ? 404 : 403];
		}
		$existing = \Monitors::get(['id' => $id]);
		if (!$existing) {
			return Envelope::error('Not found') + ['http' => 404];
		}
		\Monitors::delete($id);
		\Logs::delete_log(\Monitors::LOGS_OBJECT, $id, $existing);
		return Envelope::ok(['id' => $id, 'deleted' => true]);
	}

	/**
	 * @param array<string,mixed> $user
	 * @param array<string,mixed> $payload
	 * @param array<string,mixed>|null $existing
	 * @return array{row?:array<string,mixed>,workers?:list<int>,notify?:list<int>,error?:string,http?:int}
	 */
	static private function buildRow(array $user, array $payload, int $type, ?array $existing): array {
		$creating = $existing === null;
		$row = [];

		if ($creating || array_key_exists('status', $payload)) {
			$row['status'] = \EntityValidation::normalizeSwitchStatus($payload['status'] ?? 0);
		}
		if ($creating || array_key_exists('api_enabled', $payload) || array_key_exists('type', $payload)) {
			$row['api_enabled'] = \EntityValidation::normalizeApiEnabled($type, $payload['api_enabled'] ?? ($existing['api_enabled'] ?? 0));
		}
		foreach (['api_description_ru', 'api_description_en'] as $field) {
			if ($creating || array_key_exists($field, $payload)) {
				$row[$field] = trim(strval($payload[$field] ?? ''));
			}
		}
		if ($creating || array_key_exists('send_repeat_minute', $payload)) {
			$row['send_repeat_minute'] = intval($payload['send_repeat_minute'] ?? 0);
		}
		if ($creating || array_key_exists('alert_fail_mode', $payload)) {
			$mode = intval($payload['alert_fail_mode'] ?? \Monitors::ALERT_FAIL_MODE_MAJORITY);
			$row['alert_fail_mode'] = isset(\Monitors::alert_fail_mode_hash()[$mode])
				? $mode
				: \Monitors::ALERT_FAIL_MODE_MAJORITY;
		}
		if ($creating || array_key_exists('repeat_seconds', $payload)) {
			$row['repeat_seconds'] = \EntityValidation::clampRepeatSeconds(intval($payload['repeat_seconds'] ?? 60));
		}
		if ($creating || array_key_exists('timeout', $payload)) {
			$row['timeout'] = intval($payload['timeout'] ?? 0);
		}
		if ($creating || array_key_exists('parent_id', $payload)) {
			$row['parent_id'] = intval($payload['parent_id'] ?? 0);
		}

		if ($creating || array_key_exists('signature_id', $payload)) {
			$sigId = intval($payload['signature_id'] ?? 0);
			if ($sigId > 0) {
				if (!Acl::can($user, \Signatures::PERMISSION_ACCESS, $sigId, READ) && !Runtime::$bypassAcl) {
					return ['error' => 'Signature permission denied', 'http' => 403];
				}
				if (!Runtime::store()) {
					$signature = \Signatures::get(['id' => $sigId]);
					if (!$signature) {
						$sigId = 0;
					}
				}
			}
			$row['signature_id'] = $sigId;
		}

		if ($type === \Monitors::TYPE_HTTPS || $type === \Monitors::TYPE_HTTP_JSON) {
			foreach (['url', 'method', 'proxy_host', 'request_headers', 'json_path', 'json_expected_value', 'json_expected_type'] as $field) {
				if ($creating || array_key_exists($field, $payload)) {
					$row[$field] = trim(strval($payload[$field] ?? ''));
				}
			}
			foreach (['max_redirects', 'proxy_type'] as $field) {
				if ($creating || array_key_exists($field, $payload)) {
					$row[$field] = intval($payload[$field] ?? 0);
				}
			}
			foreach (['ssl_verify', 'ssl_verify_host', 'ssl_cert_expire_alert'] as $field) {
				if ($creating || array_key_exists($field, $payload)) {
					$row[$field] = \EntityValidation::normalizeSwitchStatus($payload[$field] ?? 0);
				}
			}
			if ($creating || array_key_exists('ssl_cert_expire_alert_days', $payload)) {
				$row['ssl_cert_expire_alert_days'] = static::normalizeSslDays($payload['ssl_cert_expire_alert_days'] ?? null);
			}
			if ($creating || array_key_exists('response_code', $payload)) {
				$row['response_code'] = static::normalizeResponseCodes($payload['response_code'] ?? null);
			}
		} elseif (in_array($type, [\Monitors::TYPE_EXPORTER_DISK, \Monitors::TYPE_EXPORTER_MEMORY, \Monitors::TYPE_EXPORTER_CPU], true)) {
			if ($creating || array_key_exists('host', $payload)) {
				$row['host'] = trim(strval($payload['host'] ?? ''));
			}
			if ($creating || array_key_exists('port', $payload)) {
				$row['port'] = intval($payload['port'] ?? 0);
			}
			if ($creating || array_key_exists('value', $payload)) {
				$row['value'] = intval($payload['value'] ?? 0);
			}
			if ($creating || array_key_exists('proxy_host', $payload)) {
				$row['proxy_host'] = trim(strval($payload['proxy_host'] ?? ''));
			}
			if ($creating || array_key_exists('proxy_type', $payload)) {
				$row['proxy_type'] = intval($payload['proxy_type'] ?? 0);
			}
		} elseif ($type === \Monitors::TYPE_TCP) {
			if ($creating || array_key_exists('host', $payload)) {
				$row['host'] = trim(strval($payload['host'] ?? ''));
			}
			if ($creating || array_key_exists('port', $payload)) {
				$row['port'] = intval($payload['port'] ?? 0);
			}
		} elseif ($type === \Monitors::TYPE_PING) {
			if ($creating || array_key_exists('host', $payload)) {
				$row['host'] = trim(strval($payload['host'] ?? ''));
			}
		} elseif ($type === \Monitors::TYPE_DOCKER) {
			if ($creating || array_key_exists('docker', $payload)) {
				$dockerId = intval($payload['docker'] ?? 0);
				if ($dockerId > 0 && !Acl::can($user, \Dockers::PERMISSION_ACCESS, $dockerId, READ) && !Runtime::$bypassAcl) {
					return ['error' => 'Docker permission denied', 'http' => 403];
				}
				if ($dockerId > 0 && !Runtime::store()) {
					$docker = \Dockers::get(['id' => $dockerId]);
					if (!$docker) {
						return ['error' => 'Docker not found', 'http' => 404];
					}
					$row['repeat_seconds'] = intval($docker['repeat_seconds']);
					$row['timeout'] = intval($docker['timeout']);
				}
				$row['docker'] = $dockerId;
			}
			if ($creating || array_key_exists('container', $payload)) {
				$row['container'] = trim(strval($payload['container'] ?? ''));
			}
		} elseif ($type === \Monitors::TYPE_EXTERNAL) {
			$row['repeat_seconds'] = 60;
			$row['timeout'] = 60;
			if ($creating) {
				$row['external_hash'] = Runtime::store()
					? bin2hex(random_bytes(32))
					: \ExternalMonitors::generateHash();
				$row['external_status'] = \ExternalMonitors::STATUS_UNSET;
				$row['external_log'] = '';
			} elseif ($existing && intval($existing['type'] ?? 0) !== \Monitors::TYPE_EXTERNAL) {
				$row['external_hash'] = Runtime::store()
					? bin2hex(random_bytes(32))
					: \ExternalMonitors::generateHash();
				$row['external_status'] = \ExternalMonitors::STATUS_UNSET;
				$row['external_log'] = '';
			}
		} elseif (\Monitors::is_server_type($type)) {
			$row['repeat_seconds'] = 60;
			$row['timeout'] = 60;
			if ($creating || array_key_exists('server', $payload)) {
				$serverId = intval($payload['server'] ?? 0);
				if ($serverId > 0 && !Acl::can($user, \Servers::PERMISSION_ACCESS, $serverId, READ) && !Runtime::$bypassAcl) {
					return ['error' => 'Server permission denied', 'http' => 403];
				}
				if ($serverId > 0 && !Runtime::store()) {
					$server = \Servers::get(['id' => $serverId]);
					if (!$server) {
						return ['error' => 'Server not found', 'http' => 404];
					}
				}
				$row['server'] = $serverId;
			}
			if ($creating || array_key_exists('value', $payload)) {
				$default = match (true) {
					$type === \Monitors::TYPE_SERVER_OFFLINE => 3,
					\Monitors::is_server_net_type($type) => 100,
					default => 90,
				};
				$row['value'] = intval($payload['value'] ?? $default);
			}
			if ($type === \Monitors::TYPE_SERVER_DISK && ($creating || array_key_exists('disk_mount', $payload) || array_key_exists('container', $payload))) {
				$row['container'] = trim(strval($payload['disk_mount'] ?? $payload['container'] ?? ''));
			} elseif ($type === \Monitors::TYPE_SERVER_CONTAINER && ($creating || array_key_exists('agent_container', $payload) || array_key_exists('container', $payload))) {
				$row['container'] = trim(strval($payload['agent_container'] ?? $payload['container'] ?? ''));
				if ($row['container'] === '') {
					return ['error' => 'Container required', 'http' => 422];
				}
			} elseif ($creating || !in_array($type, [\Monitors::TYPE_SERVER_DISK, \Monitors::TYPE_SERVER_CONTAINER], true)) {
				$row['container'] = '';
			}
			if ($type === \Monitors::TYPE_SERVER_PORT) {
				if ($creating || array_key_exists('port', $payload)) {
					$port = intval($payload['port'] ?? 0);
					if ($port < 1 || $port > 65535) {
						return ['error' => 'Port must be between 1 and 65535', 'http' => 422];
					}
					$row['port'] = $port;
				}
				if ($creating || array_key_exists('port_protocol', $payload)) {
					$row['port_protocol'] = \Monitors::normalize_port_protocol($payload['port_protocol'] ?? 'tcp');
				}
			} elseif ($creating || array_key_exists('port_protocol', $payload)) {
				$row['port_protocol'] = 'tcp';
			}
		}

		if ($existing && intval($existing['type'] ?? 0) === \Monitors::TYPE_EXTERNAL && $type !== \Monitors::TYPE_EXTERNAL) {
			$row['external_hash'] = '';
			$row['external_status'] = \ExternalMonitors::STATUS_UNSET;
			$row['external_log'] = '';
		}

		$workers = [];
		if ($type === \Monitors::TYPE_EXTERNAL || \Monitors::is_server_type($type)) {
			$workers = [];
		} elseif ($creating || array_key_exists('workers', $payload)) {
			$workers = Relations::intIdList($payload['workers'] ?? []);
			if (!Runtime::$bypassAcl) {
				$workers = array_values(array_filter($workers, fn($wid) => Acl::can($user, \Workers::PERMISSION_ACCESS, $wid, READ)));
			}
		} elseif ($existing) {
			$workers = Relations::intIdList($existing['workers'] ?? []);
		}

		$notify = [];
		if ($creating || array_key_exists('notify', $payload)) {
			$notify = Relations::intIdList($payload['notify'] ?? []);
			if (!Runtime::$bypassAcl) {
				$notify = array_values(array_filter($notify, fn($nid) => Acl::can($user, \Notify::PERMISSION_ACCESS, $nid, READ)));
			}
		} elseif ($existing) {
			$notify = Relations::intIdList($existing['notify'] ?? []);
		}

		return ['row' => $row, 'workers' => $workers, 'notify' => $notify];
	}

	static private function normalizeSslDays(mixed $raw): string {
		$allowed = \Monitors::ssl_cert_expire_alert_days_list();
		if (is_array($raw)) {
			$days = array_map('intval', $raw);
		} elseif (is_string($raw) && trim($raw) !== '') {
			$days = array_map('intval', array_map('trim', explode(',', $raw)));
		} else {
			return \Monitors::ssl_cert_expire_alert_days_default();
		}
		$days = array_values(array_intersect($days, $allowed));
		return $days ? implode(',', $days) : \Monitors::ssl_cert_expire_alert_days_default();
	}

	static private function normalizeResponseCodes(mixed $raw): string {
		if (is_array($raw)) {
			$codes = array_map('intval', $raw);
		} elseif (is_string($raw) && trim($raw) !== '') {
			$codes = array_map('intval', array_map('trim', explode(',', $raw)));
		} else {
			return '';
		}
		$codes = array_values(array_filter($codes, fn($c) => $c > 0));
		return implode(',', $codes);
	}

	/**
	 * @param array<string,mixed> $row
	 * @return array<string,mixed>
	 */
	static private function publicRow(array $row, ?MemoryStore $store = null): array {
		unset($row['_mode'], $row['_save_fields']);
		$id = intval($row['id'] ?? 0);
		if ($store) {
			$row['workers'] = Relations::intIdList($row['workers'] ?? []);
			$row['notify'] = Relations::intIdList($row['notify'] ?? []);
			return $row;
		}
		if ($id > 0 && !Runtime::store()) {
			$workers = [];
			foreach (\MonitoringJobs::data(['monitor_id' => $id]) ?: [] as $job) {
				$wid = intval($job['worker_id'] ?? 0);
				if ($wid > 0 && $wid !== \Monitors::EXTERNAL_WORKER_ID) {
					$workers[] = $wid;
				}
			}
			$notify = [];
			foreach (\MonitoringNotify::data(['monitor_id' => $id]) ?: [] as $link) {
				$nid = intval($link['notify_id'] ?? 0);
				if ($nid > 0) {
					$notify[] = $nid;
				}
			}
			$row['workers'] = array_values(array_unique($workers));
			$row['notify'] = array_values(array_unique($notify));
		}
		return $row;
	}

	/** @param array<string,mixed> $user */
	static private function grantCreatorAccess(array $user, int $monitorId): void {
		$userId = intval($user['id'] ?? 0);
		if ($userId < 1 || $monitorId < 1) {
			return;
		}
		if (isset($user['groups'][\Groups::ADMIN_GROUP_ID]) || \Sessions::in_group(\Groups::ADMIN_GROUP_ID, $userId)) {
			return;
		}
		foreach (\Monitors::permissions_hash() as $permission => $_) {
			\ObjectPermissions::save([
				'user_id' => $userId,
				'group_id' => 0,
				'object' => 'user',
				'object_id' => $userId,
				'subject' => $permission,
				'subject_id' => $monitorId,
				READ => 1,
				WRITE => 1,
				DELETE => 1,
				ACCESS_READ => 1,
				ACCESS_WRITE => 1,
				ACCESS_CHANGE => 1,
			]);
		}
	}
}

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
		$tags = $built['tags'] ?? [];

		$store = Runtime::store();
		if ($store) {
			$row['workers'] = $workers;
			$row['notify'] = $notify;
			$row['tags'] = $tags;
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
		$tagDiff = Relations::diffLinks([], $tags, fn(int $tid) => Acl::can($user, \MonitorTags::PERMISSION_ACCESS, $tid, READ));
		Relations::applyMonitorTags($id, $tagDiff);

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
			if (array_key_exists('tags', $payload) || array_key_exists('tag_ids', $payload)) {
				$patch['tags'] = $built['tags'] ?? [];
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
		if (array_key_exists('tags', $payload) || array_key_exists('tag_ids', $payload)) {
			$oldTags = \MonitoringMonitorTags::tagIdsForMonitor($id);
			$diff = Relations::diffLinks(
				$oldTags,
				$built['tags'] ?? [],
				fn(int $tid) => Acl::can($user, \MonitorTags::PERMISSION_ACCESS, $tid, READ)
			);
			Relations::applyMonitorTags($id, $diff);
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
		} else {
			\ServerAgent::removeVirtualJob($id);
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
		if ($creating || array_key_exists('badge_enabled', $payload)) {
			$badgeOn = \EntityValidation::normalizeSwitchStatus($payload['badge_enabled'] ?? 0);
			$row['badge_enabled'] = $badgeOn;
			$existingHash = trim(strval($existing['badge_hash'] ?? ''));
			if ($badgeOn) {
				if ($existingHash === '' || !\BadgeSvg::isValidHash($existingHash)) {
					$row['badge_hash'] = Runtime::store()
						? bin2hex(random_bytes(32))
						: \BadgeSvg::generateHash();
				}
			} else {
				$row['badge_hash'] = null;
			}
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
		foreach (['notify_success_text', 'notify_fail_text'] as $notifyTextField) {
			if ($creating || array_key_exists($notifyTextField, $payload)) {
				$row[$notifyTextField] = mb_substr(trim(strval($payload[$notifyTextField] ?? '')), 0, 65000);
			}
		}

		if (\Monitors::is_http_request_type($type) || $type === \Monitors::TYPE_HTTP_JSON) {
			foreach (['url', 'method', 'proxy_host', 'request_headers', 'request_body', 'json_path', 'json_expected_value', 'json_expected_type', 'keyword', 'basic_auth_user', 'basic_auth_pass', 'bearer_token', 'oauth_client_id', 'oauth_client_secret', 'oauth_token_url', 'oauth_scopes', 'oauth_audience', 'http_tls_ca', 'http_tls_cert', 'http_tls_key'] as $field) {
				if ($creating || array_key_exists($field, $payload)) {
					$row[$field] = strval($payload[$field] ?? '');
					if ($field !== 'request_body' && $field !== 'http_tls_ca' && $field !== 'http_tls_cert' && $field !== 'http_tls_key' && $field !== 'bearer_token' && $field !== 'oauth_client_secret') {
						$row[$field] = trim($row[$field]);
					}
				}
			}
			if ($creating || array_key_exists('http_body_encoding', $payload)) {
				$row['http_body_encoding'] = \Monitors::normalize_http_body_encoding($payload['http_body_encoding'] ?? 'json');
			}
			if ($creating || array_key_exists('auth_method', $payload)) {
				$row['auth_method'] = \Monitors::normalize_auth_method($payload['auth_method'] ?? 'none');
			}
			if ($creating || array_key_exists('oauth_auth_method', $payload)) {
				$row['oauth_auth_method'] = \Monitors::normalize_oauth_auth_method($payload['oauth_auth_method'] ?? 'client_secret_basic');
			}
			foreach (['max_redirects', 'proxy_type'] as $field) {
				if ($creating || array_key_exists($field, $payload)) {
					$row[$field] = intval($payload[$field] ?? 0);
				}
			}
			foreach (['ssl_verify', 'ssl_verify_host', 'ssl_cert_expire_alert', 'cache_bust', 'keyword_invert'] as $field) {
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
			if ($type === \Monitors::TYPE_HTTP_KEYWORD && ($creating || array_key_exists('keyword', $payload))) {
				if (trim(strval($row['keyword'] ?? '')) === '') {
					return ['error' => 'keyword is required for HTTP Keyword monitors', 'http' => 422];
				}
			}
			$auth = $row['auth_method'] ?? ($existing['auth_method'] ?? 'none');
			if ($auth === 'oauth2_cc') {
				$tokenUrl = trim(strval($row['oauth_token_url'] ?? ($existing['oauth_token_url'] ?? '')));
				$clientId = trim(strval($row['oauth_client_id'] ?? ($existing['oauth_client_id'] ?? '')));
				if ($tokenUrl === '' || $clientId === '') {
					return ['error' => 'oauth2_cc requires oauth_token_url and oauth_client_id', 'http' => 422];
				}
			}
			if ($auth === 'mtls') {
				$cert = trim(strval($row['http_tls_cert'] ?? ($existing['http_tls_cert'] ?? '')));
				$key = trim(strval($row['http_tls_key'] ?? ($existing['http_tls_key'] ?? '')));
				if ($cert === '' || $key === '') {
					return ['error' => 'mtls requires http_tls_cert and http_tls_key', 'http' => 422];
				}
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
		} elseif (in_array($type, [
			\Monitors::TYPE_TCP,
			\Monitors::TYPE_UDP,
			\Monitors::TYPE_SMTP,
			\Monitors::TYPE_NTP,
			\Monitors::TYPE_MYSQL,
			\Monitors::TYPE_POSTGRES,
			\Monitors::TYPE_REDIS,
			\Monitors::TYPE_MONGODB,
			\Monitors::TYPE_SFTP,
			\Monitors::TYPE_SNMP,
			\Monitors::TYPE_RADIUS,
			\Monitors::TYPE_MQTT,
			\Monitors::TYPE_RABBITMQ,
		], true)) {
			if ($creating || array_key_exists('host', $payload)) {
				$row['host'] = trim(strval($payload['host'] ?? ''));
			}
			if ($creating || array_key_exists('port', $payload)) {
				$row['port'] = intval($payload['port'] ?? 0);
			}
			if ($type === \Monitors::TYPE_SMTP && ($creating || array_key_exists('smtp_security', $payload))) {
				$row['smtp_security'] = \Monitors::normalize_smtp_security($payload['smtp_security'] ?? 'none');
			}
			if ($type === \Monitors::TYPE_NTP && ($creating || array_key_exists('ntp_max_offset_ms', $payload))) {
				$row['ntp_max_offset_ms'] = intval($payload['ntp_max_offset_ms'] ?? 5000);
			}
			if (in_array($type, [\Monitors::TYPE_MYSQL, \Monitors::TYPE_POSTGRES, \Monitors::TYPE_REDIS, \Monitors::TYPE_MONGODB], true)) {
				foreach (['db_name', 'db_query', 'db_expected', 'basic_auth_user', 'basic_auth_pass'] as $field) {
					if ($creating || array_key_exists($field, $payload)) {
						$row[$field] = strval($payload[$field] ?? '');
						if ($field !== 'basic_auth_pass' && $field !== 'db_query' && $field !== 'db_expected') {
							$row[$field] = trim($row[$field]);
						}
					}
				}
			}
			if ($type === \Monitors::TYPE_SFTP) {
				foreach (['basic_auth_user', 'basic_auth_pass', 'sftp_private_key', 'sftp_path'] as $field) {
					if ($creating || array_key_exists($field, $payload)) {
						$row[$field] = strval($payload[$field] ?? '');
					}
				}
			}
			if ($type === \Monitors::TYPE_SNMP) {
				foreach (['snmp_community', 'snmp_oid', 'snmp_expected'] as $field) {
					if ($creating || array_key_exists($field, $payload)) {
						$row[$field] = trim(strval($payload[$field] ?? ''));
					}
				}
			}
			if ($type === \Monitors::TYPE_RADIUS) {
				foreach (['basic_auth_user', 'basic_auth_pass', 'radius_secret'] as $field) {
					if ($creating || array_key_exists($field, $payload)) {
						$row[$field] = strval($payload[$field] ?? '');
					}
				}
			}
		} elseif ($type === \Monitors::TYPE_KAFKA_PRODUCER) {
			foreach (['kafka_brokers', 'kafka_topic', 'kafka_message'] as $field) {
				if ($creating || array_key_exists($field, $payload)) {
					$row[$field] = strval($payload[$field] ?? '');
				}
			}
		} elseif ($type === \Monitors::TYPE_GRPC_KEYWORD) {
			if ($creating || array_key_exists('url', $payload)) {
				$row['url'] = trim(strval($payload['url'] ?? ''));
			}
			if ($creating || array_key_exists('keyword', $payload)) {
				$row['keyword'] = strval($payload['keyword'] ?? '');
			}
		} elseif ($type === \Monitors::TYPE_PING) {
			if ($creating || array_key_exists('host', $payload)) {
				$row['host'] = trim(strval($payload['host'] ?? ''));
			}
		} elseif ($type === \Monitors::TYPE_DOMAIN) {
			if ($creating || array_key_exists('host', $payload)) {
				$row['host'] = strtolower(trim(strval($payload['host'] ?? '')));
			}
			if ($creating || array_key_exists('domain_expire_alert_days', $payload)) {
				$row['domain_expire_alert_days'] = static::normalizeDomainDays($payload['domain_expire_alert_days'] ?? null);
			}
			if ($creating && !array_key_exists('repeat_seconds', $payload)) {
				$row['repeat_seconds'] = 3600;
			}
			if ($creating && !array_key_exists('send_repeat_minute', $payload)) {
				$row['send_repeat_minute'] = 1440;
			}
			if ($creating && !array_key_exists('timeout', $payload)) {
				$row['timeout'] = 30;
			}
		} elseif ($type === \Monitors::TYPE_DNS) {
			if ($creating || array_key_exists('host', $payload)) {
				$row['host'] = trim(strval($payload['host'] ?? ''));
			}
			if ($creating || array_key_exists('dns_record_type', $payload)) {
				$row['dns_record_type'] = \Monitors::normalize_dns_record_type($payload['dns_record_type'] ?? 'A');
			}
			if ($creating || array_key_exists('dns_resolver', $payload)) {
				$row['dns_resolver'] = trim(strval($payload['dns_resolver'] ?? ''));
			}
			if ($creating || array_key_exists('dns_transport', $payload)) {
				$row['dns_transport'] = \Monitors::normalize_dns_transport($payload['dns_transport'] ?? 'auto');
			}
			if ($creating || array_key_exists('dns_doh_url', $payload)) {
				$row['dns_doh_url'] = trim(strval($payload['dns_doh_url'] ?? ''));
			}
			if ($creating || array_key_exists('dns_match_mode', $payload)) {
				$row['dns_match_mode'] = \Monitors::normalize_dns_match_mode($payload['dns_match_mode'] ?? 'exact_set');
			}
			if ($creating || array_key_exists('dns_expected', $payload)) {
				$row['dns_expected'] = \Monitors::normalize_dns_expected($payload['dns_expected'] ?? '');
			}
			if ($creating || array_key_exists('port', $payload)) {
				$row['port'] = intval($payload['port'] ?? 0);
			} elseif ($creating) {
				$transport = $row['dns_transport'] ?? 'auto';
				$row['port'] = $transport === 'dot' ? 853 : ($transport === 'doh' ? 0 : 53);
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
					$type === \Monitors::TYPE_SERVER_UPTIME => 5,
					$type === \Monitors::TYPE_SERVER_TEMP => 80,
					$type === \Monitors::TYPE_SERVER_SYSTEMD_MEMORY => 512,
					\Monitors::is_server_net_type($type) => 100,
					default => 90,
				};
				$row['value'] = intval($payload['value'] ?? $default);
			}
			if ($type === \Monitors::TYPE_SERVER_DISK && ($creating || array_key_exists('disk_mount', $payload) || array_key_exists('container', $payload))) {
				$row['container'] = trim(strval($payload['disk_mount'] ?? $payload['container'] ?? ''));
			} elseif ($type === \Monitors::TYPE_SERVER_TEMP && ($creating || array_key_exists('temp_sensor', $payload) || array_key_exists('container', $payload))) {
				$row['container'] = trim(strval($payload['temp_sensor'] ?? $payload['container'] ?? ''));
			} elseif ((\Monitors::uses_container_name_field($type)) && ($creating || array_key_exists('agent_container', $payload) || array_key_exists('systemd_unit', $payload) || array_key_exists('container', $payload))) {
				$row['container'] = trim(strval($payload['agent_container'] ?? $payload['systemd_unit'] ?? $payload['container'] ?? ''));
				if ($row['container'] === '') {
					$msg = ($type === \Monitors::TYPE_SERVER_SYSTEMD || \Monitors::is_server_systemd_metric_type($type))
						? 'Systemd unit required'
						: 'Container required';
					return ['error' => $msg, 'http' => 422];
				}
			} elseif ($creating || !in_array($type, array_merge([\Monitors::TYPE_SERVER_DISK, \Monitors::TYPE_SERVER_TEMP], [
				\Monitors::TYPE_SERVER_CONTAINER,
				\Monitors::TYPE_SERVER_CONTAINER_CPU,
				\Monitors::TYPE_SERVER_CONTAINER_MEMORY,
				\Monitors::TYPE_SERVER_SYSTEMD,
				\Monitors::TYPE_SERVER_SYSTEMD_CPU,
				\Monitors::TYPE_SERVER_SYSTEMD_MEMORY,
			]), true)) {
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
		if ($existing && \Monitors::is_server_type(intval($existing['type'] ?? 0)) && !\Monitors::is_server_type($type)) {
			$row['server'] = 0;
		}
		if (!\Monitors::is_server_type($type) && intval(($existing['server'] ?? $row['server'] ?? 0)) > 0 && !array_key_exists('server', $row)) {
			// Non-server monitors should not keep a leftover server link (e.g. after type change).
			$row['server'] = 0;
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

		// Folder-inherited workers are runtime-only; never persist on the child.
		// Server-agent / docker / external never inherit folder workers.
		if ($workers && !Runtime::store() && \MonitoringJobs::monitorInheritsFolderWorkers($type)) {
			$parentId = intval($row['parent_id'] ?? ($existing['parent_id'] ?? 0));
			$inheritedWorkers = \MonitoringJobs::getInheritedWorkerIds($parentId);
			if ($inheritedWorkers) {
				$workers = array_values(array_filter(
					$workers,
					static fn(int $wid): bool => $wid > 0 && !isset($inheritedWorkers[$wid])
				));
			}
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

		if ($notify && !Runtime::store()) {
			$parentId = intval($row['parent_id'] ?? ($existing['parent_id'] ?? 0));
			$inheritedNotify = \MonitoringNotify::getInheritedNotifyIds($parentId);
			if ($inheritedNotify) {
				$notify = array_values(array_filter(
					$notify,
					static fn(int $nid): bool => $nid > 0 && !isset($inheritedNotify[$nid])
				));
			}
		}

		$tags = [];
		if ($creating || array_key_exists('tags', $payload) || array_key_exists('tag_ids', $payload)) {
			$rawTags = $payload['tags'] ?? ($payload['tag_ids'] ?? []);
			$tags = Relations::intIdList($rawTags);
			if (!Runtime::$bypassAcl) {
				$tags = array_values(array_filter($tags, fn($tid) => Acl::can($user, \MonitorTags::PERMISSION_ACCESS, $tid, READ)));
			}
		} elseif ($existing) {
			$tags = Relations::intIdList($existing['tags'] ?? ($existing['tag_ids'] ?? []));
		}

		return ['row' => $row, 'workers' => $workers, 'notify' => $notify, 'tags' => $tags];
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

	static private function normalizeDomainDays(mixed $raw): string {
		$allowed = \Monitors::domain_expire_alert_days_list();
		if (is_array($raw)) {
			$days = array_map('intval', $raw);
		} elseif (is_string($raw) && trim($raw) !== '') {
			$days = array_map('intval', array_map('trim', explode(',', $raw)));
		} else {
			return \Monitors::domain_expire_alert_days_default();
		}
		$days = array_values(array_intersect($days, $allowed));
		return $days ? implode(',', $days) : \Monitors::domain_expire_alert_days_default();
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
			$row['tags'] = Relations::intIdList($row['tags'] ?? ($row['tag_ids'] ?? []));
			$row['tag_ids'] = $row['tags'];
			$row['badge_enabled'] = intval($row['badge_enabled'] ?? 0);
			$hash = trim(strval($row['badge_hash'] ?? ''));
			$row['badge_url'] = (intval($row['badge_enabled']) === 1 && $hash !== '')
				? \BadgeSvg::badgeUrl($hash)
				: '';
			return $row;
		}
		if ($id > 0 && !Runtime::store()) {
			$workers = [];
			foreach (\MonitoringJobs::getEffectiveWorkerIdsForMonitor($id) as $wid => $_) {
				$wid = intval($wid);
				if ($wid > 0 && $wid !== \Monitors::EXTERNAL_WORKER_ID) {
					$workers[] = $wid;
				}
			}
			$notify = [];
			foreach (\MonitoringNotify::data(['monitor_id' => $id]) ?: [] as $link) {
				$nid = intval($link['notify_id'] ?? 0);
				if ($nid > 0) {
					$notify[$nid] = $nid;
				}
			}
			$parentId = intval($row['parent_id'] ?? 0);
			foreach (\MonitoringNotify::getInheritedNotifyIds($parentId) as $nid => $_) {
				$nid = intval($nid);
				if ($nid > 0) {
					$notify[$nid] = $nid;
				}
			}
			$row['workers'] = array_values(array_unique($workers));
			$row['notify'] = array_values($notify);
			$row['tags'] = \MonitoringMonitorTags::tagIdsForMonitor($id);
			$row['tag_ids'] = $row['tags'];
		} else {
			$row['tags'] = Relations::intIdList($row['tags'] ?? ($row['tag_ids'] ?? []));
			$row['tag_ids'] = $row['tags'];
		}
		$row['badge_enabled'] = intval($row['badge_enabled'] ?? 0);
		$hash = trim(strval($row['badge_hash'] ?? ''));
		$row['badge_url'] = (intval($row['badge_enabled']) === 1 && $hash !== '')
			? \BadgeSvg::badgeUrl($hash)
			: '';
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

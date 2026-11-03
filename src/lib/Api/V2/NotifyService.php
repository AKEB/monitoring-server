<?php

namespace Api\V2;

class NotifyService {

	/** @param array<string,mixed> $user */
	static public function list(array $user, array $query = []): array {
		$store = Runtime::store();
		if ($store) {
			$rows = [];
			foreach ($store->all('notify') as $row) {
				$rows[] = static::serialize($user, $row);
			}
			return Envelope::ok($rows, ['total' => count($rows)]);
		}
		$rows = \Notify::data(false, ' ORDER BY `title` ASC') ?: [];
		$out = [];
		foreach ($rows as $row) {
			$id = intval($row['id'] ?? 0);
			if ($id < 1 || !Acl::can($user, \Notify::PERMISSION_ACCESS, $id, READ)) {
				continue;
			}
			$out[] = static::serialize($user, $row);
		}
		return Envelope::ok($out, ['total' => count($out)]);
	}

	/** @param array<string,mixed> $user */
	static public function get(array $user, int $id): array {
		$store = Runtime::store();
		if ($store) {
			$row = $store->get('notify', $id);
			if (!$row) {
				return Envelope::error('Not found') + ['http' => 404];
			}
			return Envelope::ok(static::serialize($user, $row));
		}
		if ($id < 1 || !Acl::can($user, \Notify::PERMISSION_ACCESS, $id, READ)) {
			return Envelope::error($id < 1 ? 'Not found' : 'Forbidden') + ['http' => $id < 1 ? 404 : 403];
		}
		$row = \Notify::get(['id' => $id]);
		if (!$row) {
			return Envelope::error('Not found') + ['http' => 404];
		}
		return Envelope::ok(static::serialize($user, $row));
	}

	/** @param array<string,mixed> $user @param array<string,mixed> $payload */
	static public function create(array $user, array $payload): array {
		$title = trim(strval($payload['title'] ?? ''));
		if (!\EntityValidation::isTitleLengthValid($title)) {
			return Envelope::error('Invalid title length') + ['http' => 422];
		}
		$json = is_array($payload['json_data'] ?? null) ? $payload['json_data'] : [];
		$row = [
			'title' => $title,
			'type' => intval($payload['type'] ?? \Notify::TYPE_TELEGRAM),
			'status' => \EntityValidation::normalizeSwitchStatus($payload['status'] ?? 0),
			'receiver' => strval($payload['receiver'] ?? ''),
			'json_data' => $json,
			'create_time' => time(),
			'update_time' => time(),
		];
		$store = Runtime::store();
		if ($store) {
			return Envelope::ok(static::serialize($user, $store->insert('notify', $row)));
		}
		if (!Acl::canCreate($user, \Notify::PERMISSION_CREATE)) {
			return Envelope::error('Forbidden') + ['http' => 403];
		}
		$save = $row;
		$save['json_data'] = json_encode($json);
		$save['_mode'] = \DB\Common::CSMODE_INSERT;
		$id = \Notify::save($save);
		if (!$id) {
			return Envelope::error('Save failed') + ['http' => 500];
		}
		\Logs::create_log(\Notify::LOGS_OBJECT, intval($id), $save);
		$saved = \Notify::get(['id' => intval($id)]);
		return Envelope::ok(static::serialize($user, $saved ?: $row + ['id' => intval($id)]));
	}

	/** @param array<string,mixed> $user @param array<string,mixed> $payload */
	static public function update(array $user, int $id, array $payload): array {
		$store = Runtime::store();
		if ($store) {
			$existing = $store->get('notify', $id);
			if (!$existing) {
				return Envelope::error('Not found') + ['http' => 404];
			}
			$patch = static::patchFromPayload($payload, $existing);
			if (isset($patch['__error'])) {
				return Envelope::error($patch['__error']) + ['http' => 422];
			}
			$patch['update_time'] = time();
			return Envelope::ok(static::serialize($user, $store->update('notify', $id, $patch) ?? []));
		}
		if ($id < 1 || !Acl::can($user, \Notify::PERMISSION_ACCESS, $id, WRITE)) {
			return Envelope::error($id < 1 ? 'Not found' : 'Forbidden') + ['http' => $id < 1 ? 404 : 403];
		}
		$existing = \Notify::get(['id' => $id]);
		if (!$existing) {
			return Envelope::error('Not found') + ['http' => 404];
		}
		if (is_string($existing['json_data'] ?? null)) {
			$existing['json_data'] = json_decode($existing['json_data'], true) ?: [];
		}
		$patch = static::patchFromPayload($payload, $existing);
		if (isset($patch['__error'])) {
			return Envelope::error($patch['__error']) + ['http' => 422];
		}
		$patch['id'] = $id;
		$patch['update_time'] = time();
		if (isset($patch['json_data']) && is_array($patch['json_data'])) {
			$patch['json_data'] = json_encode($patch['json_data']);
		}
		$patch['_mode'] = \DB\Common::CSMODE_UPDATE;
		\Notify::save($patch);
		\Logs::update_log(\Notify::LOGS_OBJECT, $id, $existing, $patch);
		$saved = \Notify::get(['id' => $id]);
		return Envelope::ok(static::serialize($user, $saved ?: $existing));
	}

	/** @param array<string,mixed> $user */
	static public function delete(array $user, int $id): array {
		$store = Runtime::store();
		if ($store) {
			if (!$store->delete('notify', $id)) {
				return Envelope::error('Not found') + ['http' => 404];
			}
			return Envelope::ok(['id' => $id, 'deleted' => true]);
		}
		if ($id < 1 || !Acl::can($user, \Notify::PERMISSION_ACCESS, $id, DELETE)) {
			return Envelope::error($id < 1 ? 'Not found' : 'Forbidden') + ['http' => $id < 1 ? 404 : 403];
		}
		$existing = \Notify::get(['id' => $id]);
		if (!$existing) {
			return Envelope::error('Not found') + ['http' => 404];
		}
		\Notify::delete($id);
		\Logs::delete_log(\Notify::LOGS_OBJECT, $id, $existing);
		return Envelope::ok(['id' => $id, 'deleted' => true]);
	}

	/**
	 * @param array<string,mixed> $user
	 * @param array<string,mixed> $notify
	 * @return array<string,mixed>
	 */
	static public function serialize(array $user, array $notify): array {
		$out = $notify;
		$json = $out['json_data'] ?? [];
		if (is_string($json)) {
			$json = json_decode($json, true) ?: [];
		}
		if (!is_array($json)) {
			$json = [];
		}
		$canSecrets = !empty($user['__notify_secrets']);
		if (!$canSecrets && !Runtime::$bypassAcl) {
			$id = intval($out['id'] ?? 0);
			$canSecrets = $id > 0 && Acl::can($user, \Notify::PERMISSION_SECRETS, $id, READ);
		}
		if (Runtime::$bypassAcl && empty($user['__notify_secrets'])) {
			$canSecrets = false;
		}
		if (!$canSecrets) {
			foreach (['telegram_bot_token', 'mattermost_token', 'max_token', 'webhook_password', 'gotify_token'] as $secret) {
				if (array_key_exists($secret, $json)) {
					$json[$secret] = '***';
				}
			}
		}
		$out['json_data'] = $json;
		return $out;
	}

	/**
	 * @param array<string,mixed> $payload
	 * @param array<string,mixed> $existing
	 * @return array<string,mixed>
	 */
	static private function patchFromPayload(array $payload, array $existing): array {
		$patch = [];
		if (array_key_exists('title', $payload)) {
			$title = trim(strval($payload['title']));
			if (!\EntityValidation::isTitleLengthValid($title)) {
				return ['__error' => 'Invalid title length'];
			}
			$patch['title'] = $title;
		}
		if (array_key_exists('type', $payload)) {
			$patch['type'] = intval($payload['type']);
		}
		if (array_key_exists('status', $payload)) {
			$patch['status'] = \EntityValidation::normalizeSwitchStatus($payload['status']);
		}
		if (array_key_exists('receiver', $payload)) {
			$patch['receiver'] = strval($payload['receiver']);
		}
		if (array_key_exists('json_data', $payload) && is_array($payload['json_data'])) {
			$old = is_array($existing['json_data'] ?? null) ? $existing['json_data'] : [];
			$patch['json_data'] = \EntityValidation::mergeJsonData($old, $payload['json_data']);
		}
		return $patch;
	}
}

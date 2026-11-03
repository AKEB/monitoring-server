<?php

namespace Api\V2;

class SignaturesService {

	/** @param array<string,mixed> $user */
	static public function list(array $user, array $query = []): array {
		$store = Runtime::store();
		if ($store) {
			$rows = $store->all('signatures');
			return Envelope::ok($rows, ['total' => count($rows)]);
		}
		$rows = \Signatures::data(false, ' ORDER BY `project` ASC, `title` ASC') ?: [];
		$out = [];
		foreach ($rows as $row) {
			$id = intval($row['id'] ?? 0);
			if ($id > 0 && Acl::can($user, \Signatures::PERMISSION_ACCESS, $id, READ)) {
				$out[] = $row;
			}
		}
		return Envelope::ok($out, ['total' => count($out)]);
	}

	/** @param array<string,mixed> $user */
	static public function get(array $user, int $id): array {
		$store = Runtime::store();
		if ($store) {
			$row = $store->get('signatures', $id);
			return $row ? Envelope::ok($row) : Envelope::error('Not found') + ['http' => 404];
		}
		if ($id < 1 || !Acl::can($user, \Signatures::PERMISSION_ACCESS, $id, READ)) {
			return Envelope::error($id < 1 ? 'Not found' : 'Forbidden') + ['http' => $id < 1 ? 404 : 403];
		}
		$row = \Signatures::get(['id' => $id]);
		return $row ? Envelope::ok($row) : Envelope::error('Not found') + ['http' => 404];
	}

	/** @param array<string,mixed> $user @param array<string,mixed> $payload */
	static public function create(array $user, array $payload): array {
		$project = trim(strval($payload['project'] ?? ''));
		$title = trim(strval($payload['title'] ?? ''));
		if (!\EntityValidation::isProjectLengthValid($project)) {
			return Envelope::error('Invalid project length') + ['http' => 422];
		}
		if (!\EntityValidation::isSignatureTitleValid($title)) {
			return Envelope::error('Invalid title length') + ['http' => 422];
		}
		$row = [
			'project' => $project,
			'title' => $title,
			'text' => strval($payload['text'] ?? ''),
			'status' => \EntityValidation::normalizeSwitchStatus($payload['status'] ?? 0),
			'create_time' => time(),
			'update_time' => time(),
		];
		$store = Runtime::store();
		if ($store) {
			return Envelope::ok($store->insert('signatures', $row));
		}
		if (!Acl::canCreate($user, \Signatures::PERMISSION_CREATE)) {
			return Envelope::error('Forbidden') + ['http' => 403];
		}
		$row['_mode'] = \DB\Common::CSMODE_INSERT;
		$id = \Signatures::save($row);
		if (!$id) {
			return Envelope::error('Save failed') + ['http' => 500];
		}
		\Logs::create_log(\Signatures::LOGS_OBJECT, intval($id), $row);
		return Envelope::ok(\Signatures::get(['id' => intval($id)]) ?: $row + ['id' => intval($id)]);
	}

	/** @param array<string,mixed> $user @param array<string,mixed> $payload */
	static public function update(array $user, int $id, array $payload): array {
		$store = Runtime::store();
		if ($store) {
			if (!$store->get('signatures', $id)) {
				return Envelope::error('Not found') + ['http' => 404];
			}
			$patch = ['update_time' => time()];
			foreach (['text' => 'str', 'title' => 'sig_title', 'project' => 'project', 'status' => 'switch'] as $field => $kind) {
				if (!array_key_exists($field, $payload)) {
					continue;
				}
				if ($kind === 'sig_title') {
					$v = trim(strval($payload[$field]));
					if (!\EntityValidation::isSignatureTitleValid($v)) {
						return Envelope::error('Invalid title length') + ['http' => 422];
					}
					$patch[$field] = $v;
				} elseif ($kind === 'project') {
					$v = trim(strval($payload[$field]));
					if (!\EntityValidation::isProjectLengthValid($v)) {
						return Envelope::error('Invalid project length') + ['http' => 422];
					}
					$patch[$field] = $v;
				} elseif ($kind === 'switch') {
					$patch[$field] = \EntityValidation::normalizeSwitchStatus($payload[$field]);
				} else {
					$patch[$field] = strval($payload[$field]);
				}
			}
			return Envelope::ok($store->update('signatures', $id, $patch));
		}
		if ($id < 1 || !Acl::can($user, \Signatures::PERMISSION_ACCESS, $id, WRITE)) {
			return Envelope::error($id < 1 ? 'Not found' : 'Forbidden') + ['http' => $id < 1 ? 404 : 403];
		}
		$existing = \Signatures::get(['id' => $id]);
		if (!$existing) {
			return Envelope::error('Not found') + ['http' => 404];
		}
		$patch = ['id' => $id, 'update_time' => time(), '_mode' => \DB\Common::CSMODE_UPDATE];
		if (array_key_exists('text', $payload)) {
			$patch['text'] = strval($payload['text']);
		}
		if (array_key_exists('title', $payload)) {
			$title = trim(strval($payload['title']));
			if (!\EntityValidation::isSignatureTitleValid($title)) {
				return Envelope::error('Invalid title length') + ['http' => 422];
			}
			$patch['title'] = $title;
		}
		if (array_key_exists('project', $payload)) {
			$project = trim(strval($payload['project']));
			if (!\EntityValidation::isProjectLengthValid($project)) {
				return Envelope::error('Invalid project length') + ['http' => 422];
			}
			$patch['project'] = $project;
		}
		if (array_key_exists('status', $payload)) {
			$patch['status'] = \EntityValidation::normalizeSwitchStatus($payload['status']);
		}
		\Signatures::save($patch);
		\Logs::update_log(\Signatures::LOGS_OBJECT, $id, $existing, $patch);
		return Envelope::ok(\Signatures::get(['id' => $id]) ?: $existing);
	}

	/** @param array<string,mixed> $user */
	static public function delete(array $user, int $id): array {
		$store = Runtime::store();
		if ($store) {
			return $store->delete('signatures', $id)
				? Envelope::ok(['id' => $id, 'deleted' => true])
				: Envelope::error('Not found') + ['http' => 404];
		}
		if ($id < 1 || !Acl::can($user, \Signatures::PERMISSION_ACCESS, $id, DELETE)) {
			return Envelope::error($id < 1 ? 'Not found' : 'Forbidden') + ['http' => $id < 1 ? 404 : 403];
		}
		$existing = \Signatures::get(['id' => $id]);
		if (!$existing) {
			return Envelope::error('Not found') + ['http' => 404];
		}
		\Signatures::delete($id);
		\Logs::delete_log(\Signatures::LOGS_OBJECT, $id, $existing);
		return Envelope::ok(['id' => $id, 'deleted' => true]);
	}
}

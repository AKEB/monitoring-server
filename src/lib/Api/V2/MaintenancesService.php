<?php

namespace Api\V2;

class MaintenancesService {

	/** @param array<string,mixed> $user */
	static public function list(array $user, array $query = []): array {
		$store = Runtime::store();
		if ($store) {
			$rows = $store->all('maintenances');
			return Envelope::ok($rows, ['total' => count($rows)]);
		}
		$rows = \Maintenances::data(false, ' ORDER BY `start_time` DESC') ?: [];
		$out = [];
		foreach ($rows as $row) {
			$id = intval($row['id'] ?? 0);
			if ($id > 0 && Acl::can($user, \Maintenances::PERMISSION_ACCESS, $id, READ)) {
				$out[] = $row;
			}
		}
		return Envelope::ok($out, ['total' => count($out)]);
	}

	/** @param array<string,mixed> $user */
	static public function get(array $user, int $id): array {
		$store = Runtime::store();
		if ($store) {
			$row = $store->get('maintenances', $id);
			return $row ? Envelope::ok($row) : Envelope::error('Not found') + ['http' => 404];
		}
		if ($id < 1 || !Acl::can($user, \Maintenances::PERMISSION_ACCESS, $id, READ)) {
			return Envelope::error($id < 1 ? 'Not found' : 'Forbidden') + ['http' => $id < 1 ? 404 : 403];
		}
		$row = \Maintenances::get(['id' => $id]);
		return $row ? Envelope::ok($row) : Envelope::error('Not found') + ['http' => 404];
	}

	/** @param array<string,mixed> $user @param array<string,mixed> $payload */
	static public function create(array $user, array $payload): array {
		$title = trim(strval($payload['title'] ?? ''));
		if (!\EntityValidation::isTitleLengthValid($title)) {
			return Envelope::error('Invalid title length') + ['http' => 422];
		}
		$start = isset($payload['start_time']) ? intval($payload['start_time']) : null;
		$finish = isset($payload['finish_time']) ? intval($payload['finish_time']) : null;
		if (\EntityValidation::maintenanceTimeError($start, $finish) !== null) {
			return Envelope::error('Invalid maintenance time range') + ['http' => 422];
		}
		$monitorIds = $payload['monitor_ids'] ?? $payload['monitor_id'] ?? [];
		if (!is_array($monitorIds)) {
			$monitorIds = [];
		}
		$row = [
			'title' => $title,
			'start_time' => $start,
			'finish_time' => $finish,
			'monitor_ids' => array_values(array_map('intval', $monitorIds)),
			'create_time' => time(),
			'update_time' => time(),
		];
		$store = Runtime::store();
		if ($store) {
			return Envelope::ok($store->insert('maintenances', $row));
		}
		if (!Acl::canCreate($user, \Maintenances::PERMISSION_CREATE)) {
			return Envelope::error('Forbidden') + ['http' => 403];
		}
		$save = [
			'title' => $title,
			'start_time' => $start,
			'finish_time' => $finish,
			'create_time' => time(),
			'update_time' => time(),
			'_mode' => \DB\Common::CSMODE_INSERT,
		];
		$id = \Maintenances::save($save);
		if (!$id) {
			return Envelope::error('Save failed') + ['http' => 500];
		}
		foreach ($row['monitor_ids'] as $monitorId) {
			if ($monitorId > 0 && Acl::can($user, \Monitors::PERMISSION_ACCESS, $monitorId, READ)) {
				\MonitoringMaintenances::save([
					'maintenance_id' => intval($id),
					'monitor_id' => $monitorId,
					'_mode' => \DB\Common::CSMODE_INSERT,
				]);
			}
		}
		\Logs::create_log(\Maintenances::LOGS_OBJECT, intval($id), $save);
		$saved = \Maintenances::get(['id' => intval($id)]);
		return Envelope::ok($saved ?: $save + ['id' => intval($id)]);
	}

	/** @param array<string,mixed> $user @param array<string,mixed> $payload */
	static public function update(array $user, int $id, array $payload): array {
		$store = Runtime::store();
		if ($store) {
			$existing = $store->get('maintenances', $id);
			if (!$existing) {
				return Envelope::error('Not found') + ['http' => 404];
			}
			$start = array_key_exists('start_time', $payload) ? intval($payload['start_time']) : intval($existing['start_time']);
			$finish = array_key_exists('finish_time', $payload) ? intval($payload['finish_time']) : intval($existing['finish_time']);
			if (\EntityValidation::maintenanceTimeError($start, $finish) !== null) {
				return Envelope::error('Invalid maintenance time range') + ['http' => 422];
			}
			$patch = ['start_time' => $start, 'finish_time' => $finish, 'update_time' => time()];
			if (array_key_exists('title', $payload)) {
				$title = trim(strval($payload['title']));
				if (!\EntityValidation::isTitleLengthValid($title)) {
					return Envelope::error('Invalid title length') + ['http' => 422];
				}
				$patch['title'] = $title;
			}
			if (array_key_exists('monitor_ids', $payload) || array_key_exists('monitor_id', $payload)) {
				$ids = $payload['monitor_ids'] ?? $payload['monitor_id'] ?? [];
				$patch['monitor_ids'] = is_array($ids) ? array_values(array_map('intval', $ids)) : [];
			}
			return Envelope::ok($store->update('maintenances', $id, $patch));
		}
		if ($id < 1 || !Acl::can($user, \Maintenances::PERMISSION_ACCESS, $id, WRITE)) {
			return Envelope::error($id < 1 ? 'Not found' : 'Forbidden') + ['http' => $id < 1 ? 404 : 403];
		}
		$existing = \Maintenances::get(['id' => $id]);
		if (!$existing) {
			return Envelope::error('Not found') + ['http' => 404];
		}
		$start = array_key_exists('start_time', $payload) ? intval($payload['start_time']) : intval($existing['start_time']);
		$finish = array_key_exists('finish_time', $payload) ? intval($payload['finish_time']) : intval($existing['finish_time']);
		if (\EntityValidation::maintenanceTimeError($start, $finish) !== null) {
			return Envelope::error('Invalid maintenance time range') + ['http' => 422];
		}
		$patch = [
			'id' => $id,
			'start_time' => $start,
			'finish_time' => $finish,
			'update_time' => time(),
			'_mode' => \DB\Common::CSMODE_UPDATE,
		];
		if (array_key_exists('title', $payload)) {
			$title = trim(strval($payload['title']));
			if (!\EntityValidation::isTitleLengthValid($title)) {
				return Envelope::error('Invalid title length') + ['http' => 422];
			}
			$patch['title'] = $title;
		}
		\Maintenances::save($patch);
		\Logs::update_log(\Maintenances::LOGS_OBJECT, $id, $existing, $patch);
		return Envelope::ok(\Maintenances::get(['id' => $id]) ?: $existing);
	}

	/** @param array<string,mixed> $user */
	static public function delete(array $user, int $id): array {
		$store = Runtime::store();
		if ($store) {
			return $store->delete('maintenances', $id)
				? Envelope::ok(['id' => $id, 'deleted' => true])
				: Envelope::error('Not found') + ['http' => 404];
		}
		if ($id < 1 || !Acl::can($user, \Maintenances::PERMISSION_ACCESS, $id, DELETE)) {
			return Envelope::error($id < 1 ? 'Not found' : 'Forbidden') + ['http' => $id < 1 ? 404 : 403];
		}
		$existing = \Maintenances::get(['id' => $id]);
		if (!$existing) {
			return Envelope::error('Not found') + ['http' => 404];
		}
		\Maintenances::delete($id);
		\Logs::delete_log(\Maintenances::LOGS_OBJECT, $id, $existing);
		return Envelope::ok(['id' => $id, 'deleted' => true]);
	}
}

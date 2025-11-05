<?php

class MonitorsEvents extends \DB\MySQLObject {
	static public $table = 'monitoring_events';

	static public function getLastEvent(int $monitor_id, int $worker_id) {
		if (!isset($monitor_id) || !$monitor_id) return false;
		if (!isset($worker_id) || !$worker_id) return false;

		$cache = new \Cache('monitoring_events_'.md5($monitor_id.'__'.$worker_id));
		if ($cache->isValid()) {
			$from_cache = json_decode($cache->get(), true);
			if (
				$from_cache &&
				isset($from_cache['monitor_id']) && $monitor_id == $from_cache['monitor_id'] &&
				isset($from_cache['worker_id']) && $worker_id == $from_cache['worker_id']
			) {
				return $from_cache;
			}
		}
		$event = static::get([
			'monitor_id' => $monitor_id,
			'worker_id' => $worker_id,
		], ' ORDER BY event_time DESC');
		if ($event) {
			$cache->update(json_encode($event), 300);
			return $event;
		}
		return false;
	}

	static public function save($param, $table_fields='', $ref_name='id', $add=''): int|bool {
		$event_id = parent::save($param, $table_fields, $ref_name, $add);
		if ($event_id) {
			$event = static::get(['id'=>$event_id]);
			$cache = new \Cache('monitoring_events_'.md5($event['monitor_id'].'__'.$event['worker_id']));
			$cache->remove();
		}
		return $event_id;
	}

	static public function updateEvent($param): bool|int {
		if (!isset($param['monitor_id']) || !$param['monitor_id']) return false;
		if (!isset($param['worker_id']) || !$param['worker_id']) return false;
		$lastEvent = static::getLastEvent($param['monitor_id'], $param['worker_id']);
		$event = [
			'monitor_id' => $param['monitor_id'],
			'worker_id' => $param['worker_id'],
			'status' => $param['status'],
			'event_time' => $param['update_time'],
			'status_code' => $param['status_code'] ?? 0,
			'response_error_num' => $param['response_error_num'] ?? '',
			'response_error' => $param['response_error'] ?? '',
			'create_time' => time(),
		];
		$event['hash'] = md5(json_encode([
			'monitor_id' => $event['monitor_id'],
			'worker_id' => $event['worker_id'],
			'status' => $event['status'],
			'status_code' => $event['status_code'],
			'response_error_num' => $event['response_error_num'],
		]));
		if ($lastEvent) {
			// Есть какое-то событие, нужно проверить такого же оно состояния или нет
			if ($lastEvent['hash'] == $event['hash']) {
				// Состояние не изменилось
				return false;
			} else {
				// Состояние изменилось
				return static::save($event);
			}
		} else {
			// Последнего события нет, нужно его создать, если статус не успех
			if ($param['status'] == \MonitoringLogs::STATUS_FAIL) {
				return static::save($event);
			}
			return false;
		}
	}

	static public function getEventsForMonitor(int $monitor_id): array {
		// if (!isset($monitor_id) || !$monitor_id) return [];
		$sql = '';
		if ($monitor_id > 0) {
			$sql .= sql_pholder(' AND `monitor_id` = ?', $monitor_id);
		}
		$sql .= sql_pholder(' AND `event_time` >= ?', time() - 2 * 24*60*60);
		$sql .= ' ORDER BY event_time DESC';
		$data = static::data(false, $sql);
		$data_hash = [];
		foreach($data as $item) {
			$event = [
				'worker_id' => intval($item['worker_id']),
				'status' => intval($item['status']),
				'event_time' => date("Y-m-d H:i:s", $item['event_time']),
				'status_code' => intval($item['status_code']),
				'response_error_num' => intval($item['response_error_num']),
				'response_error' => strval($item['response_error']),
			];
			if ($monitor_id < 1) {
				$event['monitor_id'] = intval($item['monitor_id']);
				if (!\Sessions::checkPermission(\Monitors::PERMISSION_ACCESS, $event['monitor_id'], READ)) continue;
			}
			$data_hash[] = $event;
		}
		return $data_hash;

	}

}
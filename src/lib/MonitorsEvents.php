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
			'response_error_num' => intval($param['response_error_num'] ?? 0),
			'response_error' => $param['response_error'] ?? '',
			'create_time' => time(),
		];
		$event['hash'] = md5(json_encode([
			'monitor_id' => $event['monitor_id'],
			'worker_id' => $event['worker_id'],
			'status' => $event['status'],
			'status_code' => $event['status_code'],
			'response_error_num' => intval($event['response_error_num']),
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

	static public function checkMonitors(array $monitors): void {
		if (!isset($monitors) || !count($monitors)) return;
		$monitor_ids = array_keys($monitors);
		if (!count($monitor_ids)) return;
		$monitors = \Monitors::data(false, sql_pholder(' AND `id` IN (?@) AND `status` = ?', $monitor_ids, \Monitors::STATUS_ENABLED), '`id`, `title`, `type`, `docker`, `last_send_status`, `last_send_time`, `send_repeat_minute`');
		$monitors_hash = [];
		$dockers_monitor_hash = [];
		foreach($monitors as $monitor) {
			if (!\Monitors::is_enable($monitor['id'])) continue;
			$monitors_hash[$monitor['id']] = $monitor;
			if ($monitor['type'] == \Monitors::TYPE_DOCKER) {
				$dockers_monitor_hash[$monitor['docker']][$monitor['id']] = $monitor['id'];
			}
		}
		if (!$monitors_hash) return;
		$jobs = \MonitoringJobs::data(false, sql_pholder(' AND `monitor_id` IN (?@)', array_keys($monitors_hash)), '`monitor_id`, `worker_id`');
		$jobs_hash = [];
		foreach($jobs as $job) {
			if (!$job['worker_id']) continue;
			$job['worker_id'] = intval($job['worker_id']);
			$jobs_hash[$job['monitor_id']][$job['worker_id']] = $job['worker_id'];
		}
		if ($dockers_monitor_hash) {
			$dockers = \MonitoringDockers::data(false, sql_pholder(' AND `docker_id` IN (?@)', array_keys($dockers_monitor_hash)), '`docker_id`, `worker_id`');
			foreach($dockers as $docker) {
				$monitors_ids = $dockers_monitor_hash[$docker['docker_id']]??[];
				foreach($monitors_ids as $monitor_id) {
					if (!isset($jobs_hash[$monitor_id])) $jobs_hash[$monitor_id] = [];
					$jobs_hash[$monitor_id][$docker['worker_id']] = $docker['worker_id'];
				}
			}
		}
		$fields = '`monitor_id`, `worker_id`, `status`, `update_time`, `status_code`, `response_error_num`, `response_error`';
		$sub_sql = sql_pholder(' AND `monitor_id` IN (?@) ', array_keys($monitors_hash));
		$sub_sql .= sql_pholder(' AND `update_time` >= ? ', time() - 10 * 60);
		$sub_query_sql = "SELECT ".$fields.", ROW_NUMBER() OVER (PARTITION BY `monitor_id`, `worker_id` ORDER BY `update_time` DESC) as rn FROM ".\MonitoringLogs::$table." WHERE 1 $sub_sql";
		$sql_query = "SELECT ".$fields." FROM ($sub_query_sql) as sub WHERE sub.rn = 1 ";
		$logs = [];
		\MonitoringLogs::$database->db_GetQueryArray($sql_query, $logs);
		$logs_hash = [];
		foreach($logs as $log) {
			$logs_hash[$log['monitor_id']][$log['worker_id']] = $log;
		}
		foreach($monitors_hash as $monitor_id=>$monitor) {
			if (!isset($jobs_hash[$monitor_id])) continue;
			$global_good = 0;
			$global_failed = 0;
			$descriptions = [];
			foreach($jobs_hash[$monitor_id] as $worker_id=>$worker_id) {
				if (!isset($logs_hash[$monitor_id])) continue;
				if (!isset($logs_hash[$monitor_id][$worker_id])) continue;
				$log = $logs_hash[$monitor_id][$worker_id];
				if ($log['status'] == \MonitoringLogs::STATUS_GOOD) {
					$global_good++;
				} else {
					$descriptions[$log['response_error_num']] = $log['response_error'];
					$global_failed++;
				}
			}
			$text = '';
			if ($global_good == count($jobs_hash[$monitor_id])) {
				// Все воркеры вернули успех
				if ($monitor['last_send_status'] != \MonitoringLogs::STATUS_GOOD) {
					$text = "✅ ".\Monitors::get_monitor_title_with_folders($monitor['id'])." is up";
					\Monitors::save([
						'id' => $monitor_id,
						'last_send_status' => \MonitoringLogs::STATUS_GOOD,
						'last_send_time' => time(),
						'update_time' => time(),
					]);
				}
			} elseif ($global_failed == count($jobs_hash[$monitor_id])) {
				// Все воркеры вернули ошибки
				if ($monitor['last_send_status'] != \MonitoringLogs::STATUS_FAIL) {
					$text = "🚫 ".\Monitors::get_monitor_title_with_folders($monitor['id'])." is down";
					if ($descriptions) $text .= "\n\n". implode("\n", $descriptions);
					\Monitors::save([
						'id' => $monitor_id,
						'last_send_status' => \MonitoringLogs::STATUS_FAIL,
						'last_send_time' => time(),
						'update_time' => time(),
					]);
				} elseif ($monitor['send_repeat_minute'] > 0) {
					if ($monitor['last_send_time'] + $monitor['send_repeat_minute'] * 60 < time()) {
						$text = "🚫 ".\Monitors::get_monitor_title_with_folders($monitor['id'])." is down";
						if ($descriptions) $text .= "\n\n". implode("\n", $descriptions);
						\Monitors::save([
							'id' => $monitor_id,
							'last_send_status' => \MonitoringLogs::STATUS_FAIL,
							'last_send_time' => time(),
							'update_time' => time(),
						]);
					}
				}
			}
			if ($text) {
				\MonitoringNotify::sendNotification($monitor_id, $text);
			}
		}
	}
}
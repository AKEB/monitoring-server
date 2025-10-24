<?php

class MonitoringLogs extends \DB\MySQLObject {
	static public $table = 'monitoring_logs';

	const STATUS_FAIL = 0;
	const STATUS_GOOD = 1;

	const STATE_UNKNOWN = 0;
	const STATE_SUCCESS = 1;
	const STATE_ERROR = 2;



	static public function getMonitorsWithLogs(int $lastCounts=10, int $min_time=0, int $max_time=0): array {
		$sql = '';
		// $sql .= sql_pholder(' AND `status` <> ?', \Monitors::STATUS_DISABLED);
		$monitors = \Monitors::data(false, $sql);
		if (!$max_time) $max_time= time();
		$monitor_workers_hash = [];
		$monitor_workers_data = \Jobs::data(false, ' ORDER BY `monitor_id` ASC, `worker_id` ASC', '`monitor_id`, `worker_id`, `create_time`');
		if ($monitor_workers_data) {
			foreach($monitor_workers_data as $item) {
				if (!isset($monitor_workers_hash[$item['monitor_id']])) $monitor_workers_hash[$item['monitor_id']] = [];
				$monitor_workers_hash[$item['monitor_id']][$item['worker_id']] = $item['create_time'] ? $item['create_time'] : 1;
			}
		}

		// Получить процент доступности
		$availability = static::getAvailabilityPercent();
		if ($availability) {
			$availability = get_hash($availability, 'monitor_id', 'availability_percent');
		}

		$monitors_data = [];
		foreach($monitors as $monitor) {
			if (!\Sessions::checkPermission(\Monitors::PERMISSION_ACCESS, $monitor['id'], READ)) continue;
			$repeat = intval($monitor['repeat_seconds']??30);

			$monitor_max_time = $max_time;
			$monitor_min_time = $monitor_max_time - ( $lastCounts * $repeat );

			if (!$min_time) $min_time = $monitor_min_time;
			$min_time = min($monitor_min_time, $min_time);

			$workers = $monitor_workers_hash[$monitor['id']]??[];
			$item = [
				'monitor_id' => $monitor['id'],
				'monitor_title' => $monitor['title'],
				'monitor_parent_id' => $monitor['parent_id']??0,
				'monitor_type' => $monitor['type'],
				'monitor_min_time' => $monitor_min_time,
				'monitor_max_time' => $monitor_max_time,
				'workers' => $workers,
				'availability_percent' => round($availability[$monitor['id']]??0, 2),
				'states' => [],
			];
			$workers_array = [];
			foreach($workers as $worker_id=>$_) {
				$workers_array[$worker_id] = static::STATE_UNKNOWN;
			}
			for($i=0; $i<$lastCounts; $i++) {
				$t = $item['monitor_min_time'] + ($i * $repeat);
				$t -= ($t % $repeat);
				$item['states'][$t] = [
					'min_time' => $t,
					'max_time' => $t+$repeat,
					'workers' => $workers_array,
				];
				foreach($item['states'][$t]['workers'] as $worker_id=>$state) {
					if (!$workers[$worker_id] || $workers[$worker_id] > $t) {
						unset($item['states'][$t]['workers'][$worker_id]);
					}
				}
			}
			$monitors_data[$monitor['id']] = $item;
		}
		// Получаем последние логи
		$logs = static::getLastLogs($lastCounts+2, $min_time - (2 * $repeat), $max_time + (2 * $repeat));
		foreach($logs as $log) {
			$monitor_id = $log['monitor_id']??0;
			$worker_id = $log['worker_id']??0;
			$time = $log['update_time']??0;
			if (!$monitor_id || !$worker_id || !$time) continue;
			$status = $log['status']??null;
			if (!isset($monitors_data[$monitor_id]) || !$monitors_data[$monitor_id]) continue;
			if (
				!isset($monitors_data[$monitor_id]['workers']) ||
				!isset($monitors_data[$monitor_id]['workers'][$worker_id]) ||
				!$monitors_data[$monitor_id]['workers'][$worker_id]
			) continue;

			if (!isset($monitors_data[$monitor_id]['states']) || !$monitors_data[$monitor_id]['states'] || !is_array($monitors_data[$monitor_id]['states'])) continue;
			foreach($monitors_data[$monitor_id]['states'] as $k=>$v) {
				if ($time > $v['min_time'] && $time <= $v['max_time']) {
					$state = static::STATE_UNKNOWN;
					if (isset($status) && $status == static::STATUS_GOOD) {
						$state = static::STATE_SUCCESS;
					} elseif (isset($status) && $status == static::STATUS_FAIL) {
						$state = static::STATE_ERROR;
					}
					$monitors_data[$monitor_id]['states'][$k]['workers'][$worker_id] = $state;
					break;
				}
			}
		}

		// Проставляем параметры для мониторов и родителей
		foreach($monitors as $monitor) {
			$monitor_id = $monitor['id'];
			foreach($monitors_data[$monitor_id]['states'] as $min_time=>&$state) {
				if ($monitors_data[$monitor_id]['monitor_type'] != \Monitors::TYPE_FOLDER) {
					$array_unique = array_unique($state['workers'], SORT_NUMERIC);
					if (in_array(\MonitoringLogs::STATE_ERROR, $array_unique) || in_array(\MonitoringLogs::STATE_UNKNOWN, $array_unique)) {
						// Есть ошибка или неизвестный статус
						if (in_array(\MonitoringLogs::STATE_SUCCESS, $array_unique)) {
							// Есть удачные ответы
							// Помечаем WARNING так как есть ошибки и успех
							if ($min_time + 1.5 * $monitor['repeat_seconds'] >= time() && !in_array(\MonitoringLogs::STATE_ERROR, $array_unique)) {
								// Ошибок нет, но статус пока еще не от всех обработчиков пришел
								static::setParamForParents($monitor_id, $monitors_data, $min_time, 'has_success', true);
							} else {
								static::setParamForParents($monitor_id, $monitors_data, $min_time, 'has_warning', true);
							}
						} elseif (in_array(\MonitoringLogs::STATE_ERROR, $array_unique)) {
							// Есть ошибки
							static::setParamForParents($monitor_id, $monitors_data, $min_time, 'has_danger', true);
						} elseif (in_array(\MonitoringLogs::STATE_UNKNOWN, $array_unique)) {
							// неизвестный статус
							static::setParamForParents($monitor_id, $monitors_data, $min_time, 'has_secondary', true);
						}
					} elseif (in_array(\MonitoringLogs::STATE_SUCCESS, $array_unique)) {
						// Нет ошибок
						static::setParamForParents($monitor_id, $monitors_data, $min_time, 'has_success', true);
					} else {
						// неизвестный статус
						static::setParamForParents($monitor_id, $monitors_data, $min_time, 'has_secondary', true);
					}
				}
				$state['title'] = date("Y-m-d H:i:s", $state['min_time']);
			}
			unset($state);
		}
		// Проставляем цвета для состояний
		foreach($monitors as $monitor) {
			$monitor_id = $monitor['id'];
			if (!$monitors_data[$monitor_id]) continue;
			foreach($monitors_data[$monitor_id]['states'] as $min_time=>&$state) {
				if ($monitor['status'] == \Monitors::STATUS_DISABLED) {
					$state['class'] = 'bg-secondary';
				} elseif (isset($state['has_danger'])) {
					$state['class'] = 'bg-danger';
				} elseif (isset($state['has_warning'])) {
					$state['class'] = 'bg-warning';
				} elseif (isset($state['has_secondary'])) {
					$state['class'] = 'bg-secondary';
				} elseif (isset($state['has_success'])) {
					$state['class'] = 'bg-success';
				} else {
					$state['class'] = 'bg-secondary';
				}
				unset($state['has_danger']);
				unset($state['has_warning']);
				unset($state['has_success']);
				unset($state['has_secondary']);
			}
			$last = end($monitors_data[$monitor_id]['states']);
			$monitors_data[$monitor_id]['badge_class'] = $last['class'];
			$parent_id = $monitors_data[$monitor_id]['monitor_parent_id']??0;
			if ($parent_id) {
				static::setParentAvailabilityPercent($parent_id, $monitors_data, $monitors_data[$monitor_id]['availability_percent']);
			}
			$monitors_data[$monitor_id]['class'] = '';
			if ($monitor['status'] == \Monitors::STATUS_DISABLED) {
				$monitors_data[$monitor_id]['class'] = 'disabled';
			}
		}

		return $monitors_data;
	}

	static private function setParentAvailabilityPercent(int $parent_id, array &$monitors_data, float $availability_percent) {
		if (!$parent_id) return;
		if (!isset($monitors_data[$parent_id]) || !$monitors_data[$parent_id]) return;
		if (!$monitors_data[$parent_id]['availability_percent']) {
			$monitors_data[$parent_id]['availability_percent'] = $availability_percent;
		} else {
			$monitors_data[$parent_id]['availability_percent'] = min($monitors_data[$parent_id]['availability_percent'], $availability_percent);
		}
		if ($monitors_data[$parent_id]['monitor_parent_id']) {
			static::setParentAvailabilityPercent($monitors_data[$parent_id]['monitor_parent_id'], $monitors_data, $monitors_data[$parent_id]['availability_percent']);
		}
	}

	static private function setParamForParents(int $monitor_id, array &$monitors_data, int $min_time, string $param, mixed $value) {
		if (!$monitor_id) return;
		if (!isset($monitors_data[$monitor_id]) || !$monitors_data[$monitor_id]) return;
		if (!isset($monitors_data[$monitor_id]['states']) || !$monitors_data[$monitor_id]['states']) return;
		if (!isset($monitors_data[$monitor_id]['states'][$min_time]) || !$monitors_data[$monitor_id]['states'][$min_time]) return;
		if (!isset($monitors_data[$monitor_id]['states'][$min_time][$param]) || !$monitors_data[$monitor_id]['states'][$min_time][$param]) {
			$monitors_data[$monitor_id]['states'][$min_time][$param] = $value;
		}
		if ($monitors_data[$monitor_id]['monitor_parent_id']) {
			static::setParamForParents($monitors_data[$monitor_id]['monitor_parent_id'], $monitors_data, $min_time, $param, $value);
		}
	}

	static private function getLastLogs(int $count=6, int $min_time=0, int $max_time=0): array {
		if (!$max_time) $max_time = time();
		if (!$min_time) $min_time = $max_time - 3600;
		$sql = "
			SELECT `monitor_id`, `worker_id`, `status`, `update_time`
				FROM (
					SELECT
						`monitor_id`,
						`worker_id`,
						`status`,
						`update_time`,
						ROW_NUMBER() OVER (PARTITION BY `monitor_id`,`worker_id` ORDER BY `update_time` DESC) as rn
					FROM `monitoring_logs`
					WHERE `update_time` >= ".intval($min_time)." AND `update_time` <= ".intval($max_time)."
				) AS ranked
			WHERE rn <= ".intval($count)."
			ORDER BY `monitor_id`, `worker_id`, `update_time` DESC;
		";
		$data = [];
		static::getDatabase()->db_GetQueryArray($sql, $data);
		return $data;
	}

	static private function getAvailabilityPercent(int $seconds=86400): array {
		if (!$seconds) $seconds = 86400;
		$sql = "
			SELECT
				monitor_id,
				COUNT(*) as total_checks,
				SUM(status = 1) as successful_checks,
				ROUND((SUM(status = 1) / COUNT(*)) * 100, 2) as availability_percent
			FROM monitoring_logs
			WHERE update_time >= UNIX_TIMESTAMP() - ".intval($seconds)."
			GROUP BY monitor_id
			ORDER BY availability_percent DESC;
		";
		$data = [];
		static::getDatabase()->db_GetQueryArray($sql, $data);
		return $data;
	}

}
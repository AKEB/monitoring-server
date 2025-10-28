<?php

class MonitoringLogs extends \DB\MySQLObject {
	static public $table = 'monitoring_logs';

	const STATUS_FAIL = 0;
	const STATUS_GOOD = 1;

	const STATE_UNKNOWN = 0;
	const STATE_SUCCESS = 1;
	const STATE_ERROR = 2;

	static public function getAvailabilityPercent(int $seconds=86400): array {
		if (!$seconds) $seconds = 86400;
		$fields = '`monitor_id`, ROUND((SUM(`status` = 1) / COUNT(*)) * 100, 2) as `availability_percent`';
		$sql = sql_pholder(' AND update_time >= ?', time() - intval($seconds));
		$sql .= 'GROUP BY monitor_id ORDER BY NULL';
		return static::data(false, $sql, $fields);
	}

	static private function getDataForParents(&$data, &$parent):bool {
		$children_has_error = [];
		$children_has_warning = [];
		if (isset($data['children']) && $data['children']) {
			error_log(sprintf("data %d %s %d", $data['id']??0, $data['title']??'', count($data['children']??[])));
			$data['states'] = [];
			usort($data['children'], function($a, $b){
				$titleA = $a['title'];
				$titleB = $b['title'];

				// Проверяем, начинается ли строка с русской буквы
				$isRussianA = preg_match('/^[а-яё]/iu', $titleA);
				$isRussianB = preg_match('/^[а-яё]/iu', $titleB);

				// Если одна строка начинается с русской буквы, а другая - нет,
				// русская строка должна идти первой
				if ($isRussianA && !$isRussianB) {
					return -1;
				}
				if (!$isRussianA && $isRussianB) {
					return 1;
				}

				// Если обе строки начинаются с русских букв или обе с английских,
				// сортируем регистронезависимо
				return strcasecmp($titleA, $titleB);
			});
			$parent_states = [];
			foreach($data['children'] as $k=>&$monitor) {
				if (!static::getDataForParents($monitor, $data)) {
					$monitor = [];
					unset($monitor);
					unset($data['children'][$k]);
					continue;
				}
				error_log(sprintf("monitor %d %s", $monitor['id']??0, $monitor['title']??''));

				$data['repeat_seconds'] = max($data['repeat_seconds']??0, $monitor['repeat_seconds']??0);
				$data['max_time'] = max($data['max_time']??0, $monitor['max_time']??0);

				if (!isset($data['min_time']) || !$data['min_time']) $data['min_time'] = $monitor['min_time']??0;
				else $data['min_time'] = min($data['min_time'], $monitor['min_time']??0);

				$data['availability_percent'] = min($data['availability_percent']??100, $monitor['availability_percent']??0);

				if (!isset($data['workers']) || !$data['workers']) $data['workers'] = $monitor['workers']??[];
				else {
					foreach($monitor['workers'] as $worker_id=>$start_time) {
						if (!isset($data['workers'][$worker_id]) || !$data['workers'][$worker_id]) $data['workers'][$worker_id] = $start_time;
						else $data['workers'][$worker_id] = min($data['workers'][$worker_id], $start_time);
					}
				}

				$last_class = '';
				for($i=$monitor['min_time']; $i<=$monitor['max_time']; $i+=intval($monitor['repeat_seconds'])) {
					if ($i > time()) continue;
					if ($i+$monitor['repeat_seconds'] > time()) continue;

					if (!isset($parent_states[$i])) {
						$parent_states[$i] = [
							'min_state' => static::STATE_ERROR,
							'max_state' => static::STATE_UNKNOWN,
						];
					}
					$workers = false;
					foreach($monitor['workers'] as $worker_id=>$start_time) {
						if ($start_time > $i) {
							continue;
						}
						$workers = true;
						$monitor['states'][$i]['min_state'] = min($monitor['states'][$i]['min_state'], $monitor['states'][$i]['workers'][$worker_id]);
						$monitor['states'][$i]['max_state'] = max($monitor['states'][$i]['max_state'], $monitor['states'][$i]['workers'][$worker_id]);

						$parent_states[$i]['min_state'] = min($monitor['states'][$i]['min_state'], $parent_states[$i]['min_state']);
						$parent_states[$i]['max_state'] = max($monitor['states'][$i]['max_state'], $parent_states[$i]['max_state']);
					}

					if (!$workers) {
						$monitor['states'][$i]['min_state'] = static::STATE_UNKNOWN;
						$monitor['states'][$i]['max_state'] = static::STATE_UNKNOWN;

						$parent_states[$i]['min_state'] = min($monitor['states'][$i]['min_state'], $parent_states[$i]['min_state']);
						$parent_states[$i]['max_state'] = max($monitor['states'][$i]['max_state'], $parent_states[$i]['max_state']);

						$monitor['states'][$i]['class'] = '';
					} else {
						if (!isset($monitor['states'][$i]['class']) || !$monitor['states'][$i]['class']) {
							if ($monitor['states'][$i]['max_state'] == $monitor['states'][$i]['min_state']) {
								if ($monitor['states'][$i]['max_state'] == static::STATE_ERROR) {
									$parent_states[$i]['min_state'] = static::STATE_ERROR;
									$parent_states[$i]['max_state'] = static::STATE_ERROR;
									$monitor['states'][$i]['class'] = 'bg-danger';
									$monitor['states'][$i]['error'] = true;
									$children_has_error[$i] = true;
								}
								if ($monitor['states'][$i]['max_state'] == static::STATE_SUCCESS) {
									$monitor['states'][$i]['error'] = false;
									$monitor['states'][$i]['warning'] = false;
									$monitor['states'][$i]['class'] = 'bg-success';
								}
								if ($monitor['states'][$i]['max_state'] == static::STATE_UNKNOWN) {
									$monitor['states'][$i]['class'] = 'bg-secondary';
									$monitor['states'][$i]['error'] = false;
									$monitor['states'][$i]['warning'] = false;
								}
							} else {
								if ($monitor['states'][$i]['max_state'] == static::STATE_ERROR) {
									$monitor['states'][$i]['class'] = 'bg-warning';
									$children_has_warning[$i] = true;
									$monitor['states'][$i]['warning'] = true;
								}
								if ($monitor['states'][$i]['max_state'] == static::STATE_SUCCESS) {
									$monitor['states'][$i]['class'] = 'bg-warning';
									$monitor['states'][$i]['warning'] = true;
									$children_has_warning[$i] = true;
								}
							}
						}
					}
					if (isset($monitor['states'][$i]['error']) && $monitor['states'][$i]['error']) {
						$children_has_error[$i] = true;
					}
					if (isset($monitor['states'][$i]['warning']) && $monitor['states'][$i]['warning']) {
						$children_has_warning[$i] = true;
					}

					$last_class = $monitor['states'][$i]['class'];
				}
				$monitor['badge_class'] = $last_class;


				$last_class = '';
				for($i=$data['min_time']; $i<=$data['max_time']; $i+=intval($data['repeat_seconds'])) {
					if ($i > time()) continue;
					if ($i+$data['repeat_seconds'] > time()) continue;
					if (!isset($data['states'][$i])) {
						$data['states'][$i] = [
							'class' => '',
							'title' => date("Y-m-d H:i:s",$i),
							'min_state' => min(static::STATE_ERROR, $parent_states[$i]['min_state']??static::STATE_ERROR),
							'max_state' => max(static::STATE_UNKNOWN, $parent_states[$i]['max_state']??static::STATE_UNKNOWN),
							'min_time' => $i,
							'max_time' => $i+$data['repeat_seconds'],
							'min_date' => date("H:i:s",$i),
							'max_date' => date("H:i:s",$i+$data['repeat_seconds']),
							'workers' => [],
						];
					}
					foreach($data['workers'] as $worker_id=>$start_time) {
						if ($start_time > $i) continue;
						if (!isset($data['states'][$i]['workers'][$worker_id])) {
							$data['states'][$i]['workers'][$worker_id] = static::STATE_UNKNOWN;
						}
						$data['states'][$i]['workers'][$worker_id] = max($data['states'][$i]['workers'][$worker_id], $monitor['states'][$i]['workers'][$worker_id]??static::STATE_UNKNOWN);
						$data['states'][$i]['min_state'] = min($data['states'][$i]['min_state'], $data['states'][$i]['workers'][$worker_id]);
						$data['states'][$i]['max_state'] = max($data['states'][$i]['max_state'], $data['states'][$i]['workers'][$worker_id]);
					}
					if (!isset($data['states'][$i]['class']) || !$data['states'][$i]['class']) {
						if ((isset($children_has_error[$i]) && $children_has_error[$i]) || $data['states'][$i]['max_state'] == static::STATE_ERROR) {
							$children_has_error[$i] = true;
							$data['states'][$i]['error'] = true;
							$data['states'][$i]['class'] = 'bg-danger';
						}
						elseif ((isset($children_has_warning[$i]) && $children_has_warning[$i])) {
							$data['states'][$i]['class'] = 'bg-warning';
							$data['states'][$i]['warning'] = true;
						}
						elseif ($data['states'][$i]['max_state'] == static::STATE_SUCCESS) $data['states'][$i]['class'] = 'bg-success';
						elseif ($data['states'][$i]['max_state'] == static::STATE_UNKNOWN) $data['states'][$i]['class'] = 'bg-secondary';
					}
					$last_class = $data['states'][$i]['class'];
					$data['badge_class'] = $last_class;
				}
			}
		}
		if (isset($data['type']) && $data['type'] == \Monitors::TYPE_FOLDER) {
			if ($children_has_warning) {
				foreach($children_has_warning as $i=>$_) {
					$data['states'][$i]['class'] = 'bg-warning';
					$data['states'][$i]['warning'] = true;
				}
			}
			if ($children_has_error) {
				foreach($children_has_error as $i=>$_) {
					$data['states'][$i]['class'] = 'bg-danger';
					$data['states'][$i]['error'] = true;
				}
			}
			if (!isset($data['children']) || !$data['children']) return false;
		}
		return true;
	}

	static public function getMonitorsWithData(int $count=5, ?int $max_time=null): array {
		if ($count < 1) return [];
		if (!$max_time) $max_time = time();

		$data = \Monitors::data(false,' ORDER BY title ASC ');

		$access_hash = \Monitors::getMonitorsAccess();
		$monitors = [];
		foreach($data as $item) {
			$monitors[$item['id']] = $item;
		}
		if (!$max_time) $max_time= time();
		$monitor_workers_hash = [];

		$monitor_workers_data = \Jobs::data(false, ' ORDER BY `monitor_id` ASC, `worker_id` ASC', '`monitor_id`, `worker_id`, `create_time`');

		if ($monitor_workers_data) {
			foreach($monitor_workers_data as $item) {
				if (!isset($monitor_workers_hash[$item['monitor_id']])) $monitor_workers_hash[$item['monitor_id']] = [];
				$monitor_workers_hash[$item['monitor_id']][$item['worker_id']] = $item['create_time'] ? $item['create_time'] : 1;
			}
		}
		$cache = new \Cache('AvailabilityPercent_86400');
		if ($cache->isValid()) {
			$availability = $cache->get();
		} else {
			$availability = static::getAvailabilityPercent();
			if ($availability) {
				$availability = get_hash($availability, 'monitor_id', 'availability_percent');
			}
			$cache->update($availability, 30*60);
		}


		$repeat_seconds = [];
		$global_min_time = 0;
		$global_max_time = 0;
		$monitor_ids = [];
		// Make monitors structure
		foreach($monitors as $monitor_id=>&$monitor) {
			if (!isset($monitor['type'])) continue;
			if ($monitor['status'] == \Monitors::STATUS_DISABLED) continue;
			if ($monitor['type'] != \Monitors::TYPE_FOLDER) {
				if (!$access_hash[$monitor_id]['access'][READ]) {
					unset($monitors[$monitor_id]);
					continue;
				}
			}
			$monitor_ids[$monitor_id] = $monitor_id;
			if (!$monitor['repeat_seconds']) $monitor['repeat_seconds'] = 30;

			$repeat_seconds[$monitor['repeat_seconds']] = $monitor['repeat_seconds'];

			$monitor_max_time = intval($max_time / intval($monitor['repeat_seconds'])) * intval($monitor['repeat_seconds']);
			$monitor_min_time = $monitor_max_time - ( $count * intval($monitor['repeat_seconds']) );
			$monitor['min_time'] = $monitor_min_time;
			$monitor['max_time'] = $monitor_max_time;

			$global_max_time = $global_max_time ? max($global_max_time, $monitor_max_time) : $monitor_max_time;
			$global_min_time = $global_min_time ? min($global_min_time, $monitor_min_time) : $monitor_min_time;

			if (!isset($monitor['availability_percent'])) $monitor['availability_percent'] = round($availability[$monitor_id]??100, 2);
			if (!isset($monitor['workers'])) $monitor['workers'] = $monitor_workers_hash[$monitor['id']]??[];
			if (!isset($monitor['states'])) $monitor['states'] = [];

			for($i=$monitor['min_time']; $i<=$monitor['max_time']; $i+=intval($monitor['repeat_seconds'])) {
				if ($i > time()) continue;
				if ($i+$monitor['repeat_seconds'] > time()) continue;
				$monitor['states'][$i] = [
					'class' => '',
					'title' => date("Y-m-d H:i:s",$i),
					'min_state' => static::STATE_ERROR,
					'max_state' => static::STATE_UNKNOWN,
					'min_time' => $i,
					'max_time' => $i+$monitor['repeat_seconds'],
					'min_date' => date("H:i:s",$i),
					'max_date' => date("H:i:s",$i+$monitor['repeat_seconds']),
					'workers' => [],
				];
				foreach($monitor['workers'] as $worker_id=>$start_time) {
					if ($start_time > $i) continue;
					$monitor['states'][$i]['workers'][$worker_id] = static::STATE_UNKNOWN;
				}

			}
			if (!isset($monitor['children'])) $monitor['children'] = [];
			$parent = &$monitors[$monitor['parent_id']];
			if (!isset($parent['children'])) $parent['children'] = [];
			$parent['children'][$monitor_id] = &$monitors[$monitor_id];
		}
		unset($parent);
		unset($monitor);

		// Get Data From MonitoringLogs
		$sql = '';
		$fields = '';
		$sql .= sql_pholder(' AND update_time >= ? AND update_time <= ?', $global_min_time, $global_max_time);
		if ($monitor_ids) {
			$sql .= sql_pholder(' AND monitor_id IN (?@)', array_keys($monitor_ids));
		} else {
			$sql .= ' AND false ';
		}
		// $sql .= " ORDER BY monitor_id, worker_id, update_time DESC";
		$sql .= " ORDER BY NULL";
		$fields = ' `monitor_id`, `worker_id`, `status` ,';
		foreach($repeat_seconds as $interval_seconds) {
			$fields .= 'FLOOR(`update_time` / '.intval($interval_seconds).') * '.intval($interval_seconds).' as `interval_time_'.intval($interval_seconds).'`,';
		}
		$fields .= '`update_time`';
		$data = static::data(false, $sql, $fields);
		foreach($data as $item) {
			$monitor_id = $item['monitor_id']??0;
			$worker_id = $item['worker_id']??0;
			$time = $item['update_time']??0;
			if (!$monitor_id || !$worker_id || !$time) continue;
			$status = $item['status']??null;
			if (!isset($monitors[$monitor_id]) || !$monitors[$monitor_id]) continue;
			if (!isset($monitors[$monitor_id]['workers']) || !$monitors[$monitor_id]['workers']) continue;
			if (!isset($monitors[$monitor_id]['workers'][$worker_id]) || !$monitors[$monitor_id]['workers'][$worker_id]) continue;
			if (!isset($monitors[$monitor_id]['states']) || !$monitors[$monitor_id]['states']) continue;
			$repeat_seconds = intval($monitors[$monitor_id]['repeat_seconds']??0);
			if (!$repeat_seconds) continue;
			$key = 'interval_time_'.intval($repeat_seconds);
			if (!isset($item[$key]) || !$item[$key]) continue;
			if (!isset($monitors[$monitor_id]['states'][$item[$key]]) || !$monitors[$monitor_id]['states'][$item[$key]]) continue;
			if (!isset($monitors[$monitor_id]['states'][$item[$key]]['workers'][$worker_id])) {
				$monitors[$monitor_id]['states'][$item[$key]]['workers'][$worker_id] = static::STATE_UNKNOWN;
			}
			$state = static::STATE_UNKNOWN;
			if ($status == static::STATUS_FAIL) {
				$state = static::STATE_ERROR;
			} else if ($status == static::STATUS_GOOD) {
				$state = static::STATE_SUCCESS;
			}
			$monitors[$monitor_id]['states'][$item[$key]]['workers'][$worker_id] = max($monitors[$monitor_id]['states'][$item[$key]]['workers'][$worker_id], $state??static::STATE_UNKNOWN);
		}
		if (!isset($monitors[0])) return [];
		$parent = [];
		static::getDataForParents($monitors[0], $parent);
		unset($parent);
		return $monitors[0]['children']??[];
	}


}
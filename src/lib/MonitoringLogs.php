<?php

/**
 * Агрегатор логов мониторинга.
 *
 * Класс отвечает за получение логов, построение деревьев мониторов и расчёт
 * визуальных метрик (состояния, проценты доступности, таймлайны).
 */
class MonitoringLogs extends \DB\MySQLObject {
	static public $table = 'monitoring_logs';

	const STATUS_FAIL = 0;
	const STATUS_GOOD = 1;

	const STATE_UNKNOWN = 0;
	const STATE_SUCCESS = 1;
	const STATE_ERROR = 2;


	/**
	 * Рекурсивно обходит дерево мониторов и рассчитывает состояние родительских
	 * узлов на основании детей.
	 *
	 * @param array $data  Уже собранные данные по мониторам, где папки содержат
	 *                     массив children.
	 */
	static private function getDataForParents($data):array {

		// Отрисовка в UI ожидает, что названия папок отсортированы с учётом
		// Кириллицы, поэтому сортируем специальной функцией.
		usort($data, function($a, $b){
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

		foreach ($data as $k=>&$monitor) {
			if ($monitor['type'] == \Monitors::TYPE_FOLDER) {
				// Для папки сначала пересобираем состояния всех детей.
				$monitor['children'] = static::getDataForParents($monitor['children']);

				if (!isset($monitor['children']) || !$monitor['children']) {
					// error_log($monitor['title']);
					unset($data[$k]['children']);
					unset($data[$k]);
					continue;
				}

				$monitor['states'] = [];
				foreach($monitor['children'] as $child) {
					$monitor['repeat_seconds'] = max($monitor['repeat_seconds']??0, $child['repeat_seconds']??0);
					$monitor['max_time'] = max($monitor['max_time']??0, $child['max_time']??0);

					if (!isset($monitor['min_time']) || !$monitor['min_time']) $monitor['min_time'] = $child['min_time']??0;
					else $monitor['min_time'] = min($monitor['min_time'], $child['min_time']??0);

					$monitor['availability_percent'] = min($monitor['availability_percent']??100, $child['availability_percent']??0);
				}
				$last_class = '';
				// Пробегаемся по историческому диапазону и выбираем «худшее»
				// состояние среди дочерних мониторов.
				for($i=$monitor['min_time']; $i<=$monitor['max_time']; $i+=intval($monitor['repeat_seconds'])) {
					if ($i > time()) continue;
					if ($i+$monitor['repeat_seconds'] > time()) continue;
					if (!isset($monitor['states'][$i])) {
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
					}
					$monitor['states'][$i]['class'] = 'bg-secondary';
					$has_error = false;
					$has_success = false;
					$has_unknown = false;
					$has_warning = false;
					foreach($monitor['children'] as $child) {
						if (!isset($child['states'][$i]) || !isset($child['states'][$i]['class'])) {
							$has_unknown = true;
							continue;
						}
						if ($child['states'][$i]['class'] == 'bg-danger') {
							$has_error = true;
						} elseif ($child['states'][$i]['class'] == 'bg-success') {
							$has_success = true;
						} elseif ($child['states'][$i]['class'] == 'bg-warning') {
							$has_warning = true;
						} else {
							$has_unknown = true;
						}
					}
					if (isset($monitor['maintenances']) && $monitor['maintenances']) {
						$monitor['states'][$i]['class'] = 'bg-primary';
					} elseif ($has_error) {
						$monitor['states'][$i]['class'] = 'bg-danger';
					} elseif ($has_warning) {
						$monitor['states'][$i]['class'] = 'bg-warning';
					} elseif ($has_success) {
						$monitor['states'][$i]['class'] = 'bg-success';
					} else {
						$monitor['states'][$i]['class'] = 'bg-secondary';
					}
					$last_class = $monitor['states'][$i]['class'];
				}
				$monitor['badge_class'] = $last_class;
				unset($child);
			} else {
				// Обычный монитор: построение временной шкалы и вычисление
				// агрегированных классов состояния.
				$monitor['repeat_seconds'] ??= 60;
				$monitor['max_time'] ??= time();
				$monitor['min_time'] ??= 0;
				$monitor['availability_percent'] ??= 100;

				$last_class = '';
				for($i=$monitor['min_time']; $i<=$monitor['max_time']; $i+=intval($monitor['repeat_seconds'])) {
					if ($i > time()) continue;
					if ($i+$monitor['repeat_seconds'] > time()) continue;
					$workers = [];
					foreach($monitor['workers'] as $worker_id=>$start_time) {
						if ($start_time > $i) {
							continue;
						}
						$workers[$worker_id] = $start_time;
					}
					$monitor['states'][$i]['class'] = 'bg-secondary';

					$monitor['states'][$i]['workers'] ??= [];
					$has_unknown = 0;
					$has_error = 0;
					$has_success = 0;
					foreach($workers as $worker_id=>$start_time) {
						$state = $monitor['states'][$i]['workers'][$worker_id]??static::STATE_UNKNOWN;
						if ($state == static::STATE_ERROR) {
							$has_error++;
						}
						if ($state == static::STATE_SUCCESS) {
							$has_success++;
						}
						if ($state == static::STATE_UNKNOWN) {
							$has_unknown++;
						}
					}
					if (isset($monitor['maintenances']) && $monitor['maintenances']) {
						$monitor['states'][$i]['class'] = 'bg-primary';
					} elseif ($has_error && $has_success && $has_error <= $has_success) {
						$monitor['states'][$i]['class'] = 'bg-warning';
					} elseif($has_error && $has_success && $has_error > $has_success) {
						$monitor['states'][$i]['class'] = 'bg-danger';
					} elseif ($has_error && !$has_success) {
						$monitor['states'][$i]['class'] = 'bg-danger';
					} elseif ($has_success && !$has_error) {
						$monitor['states'][$i]['class'] = 'bg-success';
					} else {
						$monitor['states'][$i]['class'] = 'bg-secondary';
					}
					$last_class = $monitor['states'][$i]['class'];
				}
				$monitor['badge_class'] = $last_class;
			}
		}
		unset($monitor);
		return $data;
	}

	/**
	 * Возвращает дерево мониторов с агрегированными состояниями за последние
	 * $count интервалов.
	 *
	 * Используется для основной вкладки мониторинга.
	 */
	static public function getMonitorsWithData(int $count=5, ?int $max_time=null): array {
		if ($count < 1) return [];
		if (!$max_time) $max_time = time();

		// Загружаем плоский список мониторов без сортировки (ORDER BY NULL быстрее).
		$data = \Monitors::data(false,' ORDER BY NULL ');

		$monitors = [];
		foreach($data as $item) {
			$monitors[$item['id']] = $item;
		}
		$monitor_workers_hash = [];

		// Работники, которые привязаны к каждому монитору (по самим джобам).
		$monitor_workers_data = \MonitoringJobs::data(false, ' ORDER BY NULL', '`monitor_id`, `worker_id`, `create_time`');

		if ($monitor_workers_data) {
			foreach($monitor_workers_data as $item) {
				if (!isset($monitor_workers_hash[$item['monitor_id']])) $monitor_workers_hash[$item['monitor_id']] = [];
				$monitor_workers_hash[$item['monitor_id']][$item['worker_id']] = $item['create_time'] ? $item['create_time'] : 1;
			}
		}

		$dockers_hash = [];
		$dockers = \MonitoringDockers::data(false, ' ORDER BY NULL', '`docker_id`, `worker_id`, `create_time`');
		if ($dockers) {
			foreach($dockers as $item) {
				$dockers_hash[$item['docker_id']][$item['worker_id']] = $item['create_time'] ? $item['create_time'] : 1;
			}
		}
		if ($dockers_hash) {
			foreach($monitors as $monitor_id=>&$monitor) {
				if ($monitor['type'] != \Monitors::TYPE_DOCKER) continue;
				if (!isset($monitor['docker']) || !$monitor['docker']) continue;
				if (!isset($dockers_hash[$monitor['docker']])) continue;
				$monitor_workers_hash[$monitor_id] = $dockers_hash[$monitor['docker']];
			}
		}

		// Кэшируем метрику AvailabilityPercent, чтобы не дергать БД на каждый запрос.
		$cache = new \Cache('AvailabilityPercent_86400');
		if ($cache->isValid()) {
			$availability = $cache->get();
		} else {
			$availability = \MonitoringAvailabilityPercent::getAvailabilityPercent();
			if ($availability) {
				$availability = get_hash($availability, 'monitor_id', 'availability_percent');
			}
			$cache->update($availability, 30*60);
		}
		$dockers_hash = [];
		$dockers = \Dockers::data(false, '', '`id`, `title`, `repeat_seconds`, `status`');
		if ($dockers) {
			foreach($dockers as $item) {
				$dockers_hash[$item['id']] = $item;
			}
		}

		$repeat_seconds = [];
		$global_min_time = 0;
		$global_max_time = 0;
		$monitor_ids = [];
		// Собираем дерево: фильтруем выключенные и без прав.
		foreach($monitors as $monitor_id=>&$monitor) {
			if (!isset($monitor['type'])) continue;
			if ($monitor['status'] != \Monitors::STATUS_ENABLED) continue;
			if (!\Monitors::is_enable($monitor_id)) continue;
			if ($monitor['type'] != \Monitors::TYPE_FOLDER) {
				if (!\Sessions::checkPermission(\Monitors::PERMISSION_ACCESS, $monitor_id, READ)) {
					unset($monitors[$monitor_id]);
					continue;
				}
			}

			if ($monitor['type'] == \Monitors::TYPE_DOCKER) {
				if (!isset($monitor['docker']) || !$monitor['docker']) continue;
				if (!isset($dockers_hash[$monitor['docker']])) continue;
				if ($dockers_hash[$monitor['docker']]['status'] != \Dockers::STATUS_ENABLED) continue;
				$monitor['repeat_seconds'] = $dockers_hash[$monitor['docker']]['repeat_seconds'] ?? 0;
			}

			$monitor_ids[$monitor_id] = $monitor_id;
			if (!$monitor['repeat_seconds']) $monitor['repeat_seconds'] = 30;

			$repeat_seconds[$monitor['repeat_seconds']] = $monitor['repeat_seconds'];

			$maintenances = \MonitoringMaintenances::getMaintenancesForMonitor($monitor_id);
			if ($maintenances) {
				$monitor['maintenances'] = $maintenances;
			} else {
				unset($monitor['maintenances']);
			}

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
		// Загружаем фактические логи за выбранный период.
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
		$monitors = static::getDataForParents($monitors[0]['children']??[]);
		unset($parent);
		return $monitors;
	}

	/**
	 * Детальный отчёт по одному монитору.
	 *
	 * Возвращает метрики для карточки в UI (история логов, uptime, графики).
	 */
	static public function getMonitorData(int $monitor_id, float $period): array {
		$cache = new \Cache('monitor_data_'.md5($monitor_id.'__'.$period));
		if ($cache->isValid()) {
			$from_cache = json_decode($cache->get(), true);
			if (
				$from_cache && is_array($from_cache)
			) {
				return $from_cache;
			}
		}

		$return = [];
		$return['start_work_time'] = microtime(true);
		$max_time = time();

		if ($monitor_id) {
			// Без прав доступа отдаём пустой ответ, но всё равно замеряем время.
			if (!\Sessions::checkPermission(\Monitors::PERMISSION_ACCESS, $monitor_id, READ)) return $return;
			// Get Monitor
			$start_time = microtime(true);
			$monitor = \Monitors::get(['id' => $monitor_id]);
			$return['mysql_time']['Monitors::get'] = round(microtime(true) - $start_time,3);

			if (!$monitor) return $return;
			if(!isset($monitor['repeat_seconds']) || !$monitor['repeat_seconds']) $monitor['repeat_seconds'] = 60;
			$return['monitor'] = $monitor;
			$return['monitor']['can_read'] = \Sessions::checkPermission(\Monitors::PERMISSION_ACCESS, $monitor_id, READ);
			$return['monitor']['can_write'] = \Sessions::checkPermission(\Monitors::PERMISSION_ACCESS, $monitor_id, WRITE);
			$current_time = intval(time() / intval($monitor['repeat_seconds'])) * intval($monitor['repeat_seconds']);

			// Get MonitoringJobs
			if ($monitor['type'] == \Monitors::TYPE_DOCKER) {
				$start_time = microtime(true);
				$jobs = $monitor['docker'] ? \MonitoringDockers::data(['docker_id' => $monitor['docker']], '','`worker_id`, `create_time`') : [];
				$return['mysql_time']['MonitoringDockers::data'] = round(microtime(true) - $start_time,3);
			} else {
				$start_time = microtime(true);
				$jobs = \MonitoringJobs::data(['monitor_id' => $monitor['id']],'','`worker_id`, `create_time`');
				$return['mysql_time']['MonitoringJobs::data'] = round(microtime(true) - $start_time,3);
			}

			$workers_hash = [];
			$worker_data_for_charts = [];
			foreach($jobs as $item) {
				$workers_hash[$item['worker_id']] = $item;
				$worker_data_for_charts[$item['worker_id']] = [
					'status' => null,
					'time' => null
				];
			}
			if ($workers_hash) {
				$start_time = microtime(true);
				$workers = \Workers::data(['id' => array_keys($workers_hash)],' ORDER BY title ASC','`id`, `title`');
				$return['mysql_time']['Workers::data'] = round(microtime(true) - $start_time,3);
				foreach($workers as $item) {
					$workers_hash[$item['id']]['title'] = $item['title'] ?? '';
				}
				uasort($workers_hash, function($a, $b) {
					return strcasecmp($a['title'] ?? '', $b['title'] ?? '');
				});
			}
			$return['workers'] = $workers_hash;

			$sql = '';
			$sql .= sql_pholder(' AND `monitor_id` = ?', $monitor_id);
			if ($workers_hash) {
				$sql .= sql_pholder(' AND `worker_id` IN (?@)', array_keys($workers_hash));
			}
			$sql .= sql_pholder(' AND `update_time` >= ? AND `update_time` <= ? ', $max_time - 60*60 - intval($monitor['repeat_seconds']), $max_time);
			$sql .= ' ORDER BY `interval_time` ASC';
			$fields = '`worker_id`, `status`, ';
			$fields .= 'FLOOR(`update_time` / '.intval($monitor['repeat_seconds']).') * '.intval($monitor['repeat_seconds']).' as `interval_time`,';
			$fields .= '`total_time_us`, `status_code`, `response_error`, `cert_expire` ';

			$start_time = microtime(true);
			$logs = static::data(false, $sql, $fields);
			$return['mysql_time']['MonitoringLogs::data_logs'] = round(microtime(true) - $start_time,3);

			$logs_hash = [];
			$last_data = [];

			$maintenances = \MonitoringMaintenances::getMaintenancesForMonitor($monitor_id);
			if ($maintenances) {
				$return['monitor']['maintenances'] = [];
				foreach($maintenances as $item) {
					$return['monitor']['maintenances'][$item['id']] = date("Y-m-d H:i:s",$item['start_time']).' - '.date("Y-m-d H:i:s",$item['finish_time']);
				}
			} else {
				unset($return['monitor']['maintenances']);
			}

			for($i=$max_time - 60*60; $i <= $max_time; $i+=intval($monitor['repeat_seconds'])) {
				$time = intval($i / intval($monitor['repeat_seconds'])) * intval($monitor['repeat_seconds']);
				if ($time == $current_time) continue;
				$logs_hash[$time] = [
					'title' => date("Y-m-d H:i:s",$time),
				];
			}
			foreach($logs as $item) {
				if ($item['interval_time'] == $current_time) continue;
				$item['title'] = date("Y-m-d H:i:s",$item['interval_time']).' - '.$item['status_code'].' '.$item['response_error'];
				$logs_hash[$item['interval_time']]['title'] = date("H:i:s",$item['interval_time']);
				$logs_hash[$item['interval_time']][$item['worker_id']] = $item;
				$last_data[$item['worker_id']] = $item;
			}
			$return['logs'] = $logs_hash;

			$interval = intval($monitor['repeat_seconds']);
			$sql = '';
			$sql .= sql_pholder(' AND `monitor_id` = ?', $monitor_id);
			if ($workers_hash) {
				$sql .= sql_pholder(' AND `worker_id` IN (?@)', array_keys($workers_hash));
			}
			$sql .= sql_pholder(' AND `update_time` >= ? AND `update_time` <= ? ', $max_time - $period*60*60 - $interval, $max_time);
			// $sql .= ' AND `status_code` <> 0';
			$sql .= ' ORDER BY 2 ASC';
			$fields = '`worker_id`, ';
			$fields .= 'FLOOR(`update_time` / '.intval($interval).') * '.intval($interval).' as `interval_time`,';
			$fields .= '`total_time_us`, `status`';
			$start_time = microtime(true);
			$logs = static::data(false, $sql, $fields);
			$return['mysql_time']['MonitoringLogs::data_logs_chart'] = round(microtime(true) - $start_time,3);
			$logs_hash = [];
			for($i=$max_time - $period*60*60; $i<$max_time; $i+=intval($interval)) {
				$time = intval($i / intval($interval)) * intval($interval);
				if ($time == $current_time) continue;
				$logs_hash[$time] = $worker_data_for_charts;
			}
			foreach($logs as $item) {
				if ($item['interval_time'] == $current_time) continue;
				if (isset($logs_hash[$item['interval_time']][$item['worker_id']])) {
					$logs_hash[$item['interval_time']][$item['worker_id']]['status'] = min($logs_hash[$item['interval_time']][$item['worker_id']]['status']??\MonitoringLogs::STATUS_GOOD, intval($item['status']));
					$logs_hash[$item['interval_time']][$item['worker_id']]['time'] = max($logs_hash[$item['interval_time']][$item['worker_id']]['time'], ceil($item['total_time_us']/1000));
				} else {
					$logs_hash[$item['interval_time']][$item['worker_id']] = [
						'status' => intval($item['status']),
						'time' => ceil($item['total_time_us']/1000)
					];
				}
			}
			$return['logs_chart'] = $logs_hash;

			$start_time = microtime(true);
			$uptime_30 = \MonitoringAvailabilityPercent::getAvailabilityPercent(30*86400, $monitor_id);
			$return['mysql_time']['MonitoringAvailabilityPercent::getAvailabilityPercent_30'] = round(microtime(true) - $start_time,3);

			$start_time = microtime(true);
			$uptime_1 = \MonitoringAvailabilityPercent::getAvailabilityPercent(86400, $monitor_id);
			$return['mysql_time']['MonitoringAvailabilityPercent::getAvailabilityPercent_1'] = round(microtime(true) - $start_time,3);

			if ($uptime_1 && isset($uptime_1[0]) && $uptime_1[0]['avg_response_time']) {
				$return['avg_response_time_1d'] = ceil($uptime_1[0]['avg_response_time']).' ms';
			} else {
				$return['avg_response_time_1d'] = '';
			}

			if ($uptime_30 && isset($uptime_30[0]) && $uptime_30[0]['avg_response_time']) {
				$return['avg_response_time_30d'] = ceil($uptime_30[0]['avg_response_time']).' ms';
			} else {
				$return['avg_response_time_30d'] = '';
			}

			$uptime_30 = $uptime_30 && $uptime_30[0] ? $uptime_30[0]['availability_percent']??'' : '';
			$uptime_1 = $uptime_1 && $uptime_1[0] ? $uptime_1[0]['availability_percent']??'' : '';
			if ($uptime_30 >=100) $uptime_30 = 100;
			if ($uptime_1 >=100) $uptime_1 = 100;
			$return['uptime_30'] = $uptime_30;
			$return['uptime_1'] = $uptime_1;

			$avg_response_time = 0;
			$cert_expire_time = 0;
			foreach($last_data as $item) {
				$avg_response_time += $item['total_time_us'];
				if (!$cert_expire_time) $cert_expire_time = $item['cert_expire'];
				else $cert_expire_time = min($cert_expire_time, $item['cert_expire']);
			}
			$count = 1;
			if ($last_data) {
				$count = count($last_data);
			}
			$avg_response_time = ceil(($avg_response_time / $count) / 1000);
			$return['avg_response_time'] = $avg_response_time.' ms';

			if (!$cert_expire_time || $cert_expire_time < time()) {
				$return['cert_expire'] = '';
				$return['cert_expire_days'] = '';
			} else {
				$cert_expire_days = intval( ( $cert_expire_time - time() ) / 86400 );
				$return['cert_expire'] = date("Y-m-d",$cert_expire_time);
				$return['cert_expire_days'] = $cert_expire_days . ' '.(\T::declension($cert_expire_days, \T::Dashboard_Day(), \T::Dashboard_Days(), \T::Dashboard_Days5()));
			}
			$return['events'] = \MonitorsEvents::getEventsForMonitor($monitor_id);
		} else {
			$return['workers'] = make_hash(\Workers::data(false, '', '`id`, `title`'));
			$return['monitors'] = \Monitors::get_hash();
			$return['events'] = \MonitorsEvents::getEventsForMonitor(0);
		}

		$return['finish_work_time'] = microtime(true);
		$return['work_time'] = round($return['finish_work_time'] - $return['start_work_time'], 3);

		$cache->update(json_encode($return), 30);
		return $return;
	}
}
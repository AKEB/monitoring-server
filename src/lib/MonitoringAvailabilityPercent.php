<?php

class MonitoringAvailabilityPercent {

	private const CACHE_TTL_TODAY = 90;
	private const CACHE_TTL_PERIOD = 120;

	public const WINDOWS = [
		'1m' => 60,
		'5m' => 300,
		'10m' => 600,
		'1h' => 3600,
		'4h' => 14400,
		'8h' => 28800,
	];

	public static function getAvailabilityPercent(int $seconds = 86400, ?int $monitor_id = null): array {
		if ($seconds <= 0) {
			$seconds = 86400;
		}

		if ($monitor_id) {
			$cached = self::getCachedPeriod($monitor_id, $seconds);
			if ($cached !== null) {
				return $cached;
			}
		}

		$current_time = time();
		$time_threshold = $current_time - $seconds;

		// Если период меньше или равен 1 дня, используем сырые данные
		if ($seconds <= 86400) {
			$result = self::getFromRawData($time_threshold, $monitor_id);
		} else {
			// Для периодов больше 1 дня комбинируем агрегаты и сырые данные
			$result = self::getFromCombinedData($time_threshold, $monitor_id, $current_time);
		}

		if ($monitor_id) {
			self::setCachedPeriod($monitor_id, $seconds, $result);
		}

		return $result;
	}

	/**
	 * Uptime 24h и 30d за один проход: «сегодня» из raw считается один раз.
	 *
	 * @return array{1d: array, 30d: array}
	 */
	public static function getMonitorAvailability(int $monitor_id): array {
		$cached1 = self::getCachedPeriod($monitor_id, 86400);
		$cached30 = self::getCachedPeriod($monitor_id, 30 * 86400);
		if ($cached1 !== null && $cached30 !== null) {
			return ['1d' => $cached1, '30d' => $cached30];
		}

		$current_time = time();
		$today_start = strtotime('today', $current_time);
		$thresh_30 = $current_time - 30 * 86400;
		$thresh_1 = $current_time - 86400;

		$today_end = $today_start + 86400;
		if (\MonitoringLogsAggregate::isTodayFresh($monitor_id, $today_start)) {
			$uptime_30 = self::getFromAggregates($thresh_30, $today_end, $monitor_id);
		} else {
			$aggregate_data = [];
			if ($thresh_30 < $today_start) {
				$aggregate_data = self::getFromAggregates($thresh_30, $today_start, $monitor_id);
			}
			$today_raw = self::getTodayRawData($monitor_id, $today_start);
			$uptime_30 = self::mergeResults($aggregate_data, $today_raw);
		}

		$rolling_raw = self::getFromRawData($thresh_1, $monitor_id);
		$uptime_1 = $rolling_raw;

		self::setCachedPeriod($monitor_id, 30 * 86400, $uptime_30);
		self::setCachedPeriod($monitor_id, 86400, $uptime_1);

		return ['1d' => $uptime_1, '30d' => $uptime_30];
	}

	private static function getCachedPeriod(int $monitor_id, int $seconds): ?array {
		$cache = new \Cache(self::cacheKeyPeriod($monitor_id, $seconds));
		if (!$cache->isValid()) {
			return null;
		}
		$data = json_decode($cache->get(), true);
		return is_array($data) ? $data : null;
	}

	private static function setCachedPeriod(int $monitor_id, int $seconds, array $data): void {
		$ttl = $seconds <= 86400 ? self::CACHE_TTL_TODAY : self::CACHE_TTL_PERIOD;
		$cache = new \Cache(self::cacheKeyPeriod($monitor_id, $seconds));
		$cache->update(json_encode($data), $ttl);
	}

	public static function percent(int $good, int $total): ?float {
		if ($total <= 0) {
			return null;
		}
		$percent = round(($good / $total) * 100, 2);
		if ($percent > 100) {
			$percent = 100.0;
		}
		return $percent;
	}

	public static function emptyWindows(): array {
		$windows = [];
		foreach (array_keys(self::WINDOWS) as $key) {
			$windows[$key] = null;
		}
		return $windows;
	}

	/**
	 * Availability for 1m/5m/10m/1h/4h/8h grouped by monitor and worker.
	 *
	 * @param int[] $monitor_ids
	 * @return array<int, array<int, array{good: array<string,int>, total: array<string,int>, percent: array<string,?float>}>>
	 */
	public static function getWindowsByWorker(array $monitor_ids): array {
		$monitor_ids = array_values(array_unique(array_filter(array_map('intval', $monitor_ids))));
		if (!$monitor_ids) {
			return [];
		}

		$now = time();
		$max_seconds = max(self::WINDOWS);
		$fields = ['`monitor_id`', '`worker_id`'];
		foreach (self::WINDOWS as $key => $seconds) {
			$threshold = $now - $seconds;
			$fields[] = sql_pholder('SUM(`status` = 1 AND `update_time` >= ?) AS `good_'.$key.'`', $threshold);
			$fields[] = sql_pholder('SUM(`update_time` >= ?) AS `total_'.$key.'`', $threshold);
		}

		$sql = sql_pholder(' AND `update_time` >= ? AND `monitor_id` IN (?@)', $now - $max_seconds, $monitor_ids);
		$sql .= ' GROUP BY `monitor_id`, `worker_id` ORDER BY NULL';

		$rows = \MonitoringLogs::data(false, $sql, implode(",\n", $fields));
		if (!$rows || !is_array($rows)) {
			return [];
		}

		$result = [];
		foreach ($rows as $row) {
			$monitor_id = intval($row['monitor_id'] ?? 0);
			$worker_id = intval($row['worker_id'] ?? 0);
			if ($monitor_id < 1 || $worker_id < 1) {
				continue;
			}
			$good = [];
			$total = [];
			$percent = [];
			foreach (array_keys(self::WINDOWS) as $key) {
				$good[$key] = intval($row['good_'.$key] ?? 0);
				$total[$key] = intval($row['total_'.$key] ?? 0);
				$percent[$key] = self::percent($good[$key], $total[$key]);
			}
			$result[$monitor_id][$worker_id] = [
				'good' => $good,
				'total' => $total,
				'percent' => $percent,
			];
		}
		return $result;
	}

	/**
	 * Roll worker-window counts up to monitor level (sum of checks, not average of percents).
	 *
	 * @param array<int, array{good: array<string,int>, total: array<string,int>, percent: array<string,?float>}> $by_worker
	 * @return array<string, float|null>
	 */
	public static function rollupWindows(array $by_worker): array {
		$good = [];
		$total = [];
		foreach (array_keys(self::WINDOWS) as $key) {
			$good[$key] = 0;
			$total[$key] = 0;
		}
		foreach ($by_worker as $row) {
			foreach (array_keys(self::WINDOWS) as $key) {
				$good[$key] += intval($row['good'][$key] ?? 0);
				$total[$key] += intval($row['total'][$key] ?? 0);
			}
		}
		$percent = [];
		foreach (array_keys(self::WINDOWS) as $key) {
			$percent[$key] = self::percent($good[$key], $total[$key]);
		}
		return $percent;
	}

	/**
	 * Получение данных из сырых логов
	 */
	private static function getFromRawData(int $time_threshold, ?int $monitor_id): array {
		$fields = '`monitor_id`,
				SUM(`status` = 1) as `good_count`,
				COUNT(*) as `total_count`,
				ROUND( SUM(`total_time_us`) / 1000 ) as `sum_total_time_ms`,
				ROUND((SUM(`status` = 1) / COUNT(*)) * 100, 2) as `availability_percent`,
				ROUND((SUM(`total_time_us`) / COUNT(*)) / 1000, 0) as `avg_response_time`';

		$sql = sql_pholder(' AND update_time >= ?', $time_threshold);

		if ($monitor_id) {
			$sql .= sql_pholder(' AND monitor_id = ?', $monitor_id);
		}

		$sql .= ' GROUP BY 1 ORDER BY NULL';

		return \MonitoringLogs::data(false, $sql, $fields);
	}

	/**
	 * Получение данных из агрегатов + сырые данные за сегодня
	 */
	private static function getFromCombinedData(int $time_threshold, ?int $monitor_id, int $current_time): array {
		$today_start = strtotime('today', $current_time);
		$today_end = $today_start + 86400;

		// Суточной агрегат за сегодня обновляет mngr_aggregate_today (raw не трогаем)
		if (self::canUseTodayAggregate($monitor_id, $today_start)) {
			return self::getFromAggregates($time_threshold, $today_end, $monitor_id);
		}

		$result = [];
		if ($time_threshold < $today_start) {
			$aggregate_data = self::getFromAggregates($time_threshold, $today_start, $monitor_id);
			$result = $aggregate_data ?? [];
		}

		return self::mergeResults($result, self::getTodayRawData($monitor_id, $today_start));
	}

	private static function canUseTodayAggregate(?int $monitor_id, int $today_start): bool {
		if ($monitor_id) {
			return \MonitoringLogsAggregate::isTodayFresh($monitor_id, $today_start);
		}
		return \MonitoringLogsAggregate::isTodayFresh(null, $today_start);
	}

	/**
	 * Статистика за сегодня из monitoring_logs (узкое место getAvailabilityPercent_30).
	 */
	private static function getTodayRawData(?int $monitor_id, int $today_start): array {
		if (!$monitor_id) {
			return self::getFromRawData($today_start, null);
		}
		$cached = self::getCachedToday($monitor_id, $today_start);
		if ($cached !== null) {
			return $cached;
		}
		$data = self::getFromRawData($today_start, $monitor_id);
		self::setCachedToday($monitor_id, $today_start, $data);
		return $data;
	}

	private static function getCachedToday(int $monitor_id, int $today_start): ?array {
		$cache = new \Cache(self::cacheKeyToday($monitor_id, $today_start));
		if (!$cache->isValid()) {
			return null;
		}
		$data = json_decode($cache->get(), true);
		return is_array($data) ? $data : null;
	}

	private static function setCachedToday(int $monitor_id, int $today_start, array $data): void {
		$cache = new \Cache(self::cacheKeyToday($monitor_id, $today_start));
		$cache->update(json_encode($data), self::CACHE_TTL_TODAY);
	}

	private static function cacheKeyToday(int $monitor_id, int $today_start): string {
		return 'MonitoringAvailabilityPercent_today_'.md5($monitor_id.'_'.$today_start);
	}

	private static function cacheKeyPeriod(int $monitor_id, int $seconds): string {
		return 'MonitoringAvailabilityPercent_'.md5($monitor_id.'_'.$seconds);
	}

	/**
	 * Получение данных из таблицы агрегатов
	 */
	private static function getFromAggregates(int $start_time, int $end_time, ?int $monitor_id): array {
		$fields = '`monitor_id`,
				SUM(`good_count`) as `good_count`,
				SUM(`total_count`) as `total_count`,
				SUM(`sum_total_time_ms`) as `sum_total_time_ms`,
				ROUND(SUM(`good_count`) / SUM(`total_count`) * 100, 2) as `availability_percent`,
				ROUND(SUM(`sum_total_time_ms`) / SUM(`total_count`), 0) as `avg_response_time`';

		$sql = sql_pholder(' AND `time` >= ? AND `time` < ?', $start_time, $end_time);

		if ($monitor_id) {
			$sql .= sql_pholder(' AND monitor_id = ?', $monitor_id);
		}

		$sql .= ' GROUP BY 1 ORDER BY NULL';

		return \MonitoringLogsAggregate::data(false, $sql, $fields);
	}

	/**
	 * Объединение результатов из разных источников
	 */
	private static function mergeResults(array $aggregate_data, array $raw_data): array {
		if (empty($aggregate_data)) {
			return $raw_data;
		}

		if (empty($raw_data)) {
			return $aggregate_data;
		}

		$merged = [];

		// Собираем все monitor_id
		$all_monitor_ids = array_unique(
			array_merge(
				array_column($aggregate_data, 'monitor_id'),
				array_column($raw_data, 'monitor_id')
			)
		);

		foreach ($all_monitor_ids as $monitor_id) {
			$agg_item = self::findByMonitorId($aggregate_data, $monitor_id);
			$raw_item = self::findByMonitorId($raw_data, $monitor_id);

			if ($agg_item && $raw_item) {
				// Объединяем взвешенные средние
				$total_count_agg = $agg_item['total_count'] ?? 1;
				$total_count_raw = $raw_item['total_count'] ?? 1;
				$total_count = $total_count_agg + $total_count_raw;

				$merged[] = [
					'monitor_id' => $monitor_id,
					'availability_percent' => round(($agg_item['good_count'] + $raw_item['good_count']) / $total_count * 100, 2),
					'avg_response_time' => ceil(($agg_item['sum_total_time_ms'] + $raw_item['sum_total_time_ms']) / $total_count),
				];
			} else {
				$merged[] = $agg_item ?: $raw_item;
			}
		}

		return $merged;
	}

	/**
	 * Поиск элемента по monitor_id
	 */
	private static function findByMonitorId(array $data, int $monitor_id): ?array {
		foreach ($data as $item) {
			if ($item['monitor_id'] == $monitor_id) {
				return $item;
			}
		}
		return null;
	}

}


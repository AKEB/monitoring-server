<?php

class MonitoringAvailabilityPercent {

	public static function getAvailabilityPercent(int $seconds = 86400, ?int $monitor_id = null): array {
		if ($seconds <= 0) {
			$seconds = 86400;
		}

		$current_time = time();
		$time_threshold = $current_time - $seconds;

		// Если период меньше или равен 1 дня, используем сырые данные
		if ($seconds <= 86400) {
			return self::getFromRawData($time_threshold, $monitor_id);
		}

		// Для периодов больше 1 дня комбинируем агрегаты и сырые данные
		return self::getFromCombinedData($time_threshold, $monitor_id, $current_time);
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
		// Определяем границу между агрегатами и сырыми данными
		// (например, берем агрегаты до начала текущего дня, а сегодняшние данные из сырых)
		$today_start = strtotime('today', $current_time);

		$result = [];

		// 1. Данные из агрегатов (исторические данные)
		if ($time_threshold < $today_start) {
			$aggregate_data = self::getFromAggregates($time_threshold, $today_start, $monitor_id);
			$result = $aggregate_data??[];
		}

		// 2. Данные из сырых логов за сегодня
		$raw_data = self::getFromRawData(max($time_threshold, $today_start), $monitor_id);

		// Объединяем результаты
		return self::mergeResults($result, $raw_data);
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


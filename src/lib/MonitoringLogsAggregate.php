<?php

class MonitoringLogsAggregate extends \DB\MySQLObject {
	static public $table = 'monitoring_logs_aggregate';

	/** Максимальный возраст суточного агрегата, чтобы UI читал его вместо SUM по raw. */
	public const TODAY_MAX_AGE = 600;

	/**
	 * Пересчёт суточных bucket'ов за сегодня из monitoring_logs.
	 * Raw-логи не удаляются (нужны графикам и rolling 24h).
	 */
	public static function refreshTodayFromRaw(): void {
		$today_start = strtotime('today');
		$fields = '`monitor_id`, `worker_id`, '
			. 'COUNT(*) AS `total_count`, '
			. 'SUM(`status` = '.intval(\MonitoringLogs::STATUS_GOOD).') AS `good_count`, '
			. 'SUM(`status` = '.intval(\MonitoringLogs::STATUS_FAIL).') AS `error_count`, '
			. 'SUM(CEIL(`total_time_us` / 1000)) AS `sum_total_time_ms`, '
			. 'MAX(CEIL(`total_time_us` / 1000)) AS `max_total_time_ms`, '
			. 'MIN(CEIL(`total_time_us` / 1000)) AS `min_total_time_ms`';
		$sql = sql_pholder(' AND `update_time` >= ?', $today_start);
		$sql .= ' GROUP BY `monitor_id`, `worker_id` ORDER BY NULL';
		$rows = \MonitoringLogs::data(false, $sql, $fields);
		if (!$rows) {
			return;
		}
		$now = time();
		foreach ($rows as $row) {
			$monitor_id = intval($row['monitor_id'] ?? 0);
			$worker_id = intval($row['worker_id'] ?? 0);
			$total_count = intval($row['total_count'] ?? 0);
			// worker_id=0 invalid; External uses Monitors::EXTERNAL_WORKER_ID (-1).
			if ($monitor_id < 1 || $worker_id === 0 || $total_count < 1) {
				continue;
			}
			$good_count = intval($row['good_count'] ?? 0);
			$error_count = intval($row['error_count'] ?? 0);
			$sum_total_time_ms = intval($row['sum_total_time_ms'] ?? 0);
			$item = [
				'monitor_id' => $monitor_id,
				'worker_id' => $worker_id,
				'time' => $today_start,
				'total_count' => $total_count,
				'good_count' => $good_count,
				'error_count' => $error_count,
				'sum_total_time_ms' => $sum_total_time_ms,
				'max_total_time_ms' => intval($row['max_total_time_ms'] ?? 0),
				'min_total_time_ms' => intval($row['min_total_time_ms'] ?? 0),
				'availability_percent' => round($good_count / $total_count * 100, 2),
				'avg_total_time_ms' => round($sum_total_time_ms / $total_count),
				'update_time' => $now,
			];
			$existing = static::get([
				'monitor_id' => $monitor_id,
				'worker_id' => $worker_id,
				'time' => $today_start,
			]);
			if ($existing && !empty($existing['id'])) {
				$item['id'] = $existing['id'];
				$item['_mode'] = \DB\Common::CSMODE_UPDATE;
			} else {
				$item['create_time'] = $now;
				$item['_mode'] = \DB\Common::CSMODE_INSERT;
			}
			static::save($item);
		}
	}

	/**
	 * Есть ли недавно обновлённые агрегаты за текущие сутки.
	 */
	public static function isTodayFresh(?int $monitor_id, ?int $today_start = null): bool {
		$today_start = $today_start ?? strtotime('today');
		$sql = sql_pholder(
			' AND `time` = ? AND `update_time` >= ?',
			$today_start,
			time() - self::TODAY_MAX_AGE
		);
		if ($monitor_id) {
			$sql .= sql_pholder(' AND `monitor_id` = ?', $monitor_id);
		}
		$rows = static::data(false, $sql, '`id`');
		return !empty($rows);
	}

}

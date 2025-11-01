<?php

set_time_limit(0);
ini_set('memory_limit', '1024M');
require_once("../autoload.php");

$__worker_sleep = 3600;

$__worker_title = strval($_SERVER['argv'][0] ?? 'worker');
$__worker_number = intval($_SERVER['argv'][1] ?? 0);

$__worker_pid = getmypid();
$__worker_errorlog_prefix = "$__worker_title"."[$__worker_number]"."($__worker_pid): ";

error_log("$__worker_errorlog_prefix: Started");
$__start_time = time();
// Main Work

$today_start_time = time() - (time() % 86400);

$ALL_DONE_DATA = [];
$last_id = 0;
do {
	$field = '`id`, `monitor_id`, `worker_id`, `status`, `update_time`, `total_time_us`';
	$sql = '';
	$sql .= sql_pholder(' AND id > ?', $last_id);
	$sql .= sql_pholder(' AND update_time < ?', time() - 86400);
	$sql .= ' ORDER BY `id` ASC';
	$sql .= ' LIMIT 1000';
	$data = [];
	$data = \MonitoringLogs::data(false, $sql, $field);
	if (!$data) break;
	if (count($data) < 1) break;
	foreach($data as $item) {
		$item['time'] = $item['update_time'] - ($item['update_time'] % 86400);
		$key = ($item['monitor_id']??0).'__'.($item['worker_id']??0).'__'.($item['time']??0);
		$item['total_time_ms'] = ceil($item['total_time_us'] / 1000);
		if (!isset($ALL_DONE_DATA[$key])) {
			// Try Get From Database Old Data
			$old_data = \MonitoringLogsAggregate::get([
				'monitor_id' => $item['monitor_id'],
				'worker_id' => $item['worker_id'],
				'time' => $item['time']
			]);
			if (isset($old_data) && $old_data) {
				$ALL_DONE_DATA[$key] = $old_data;
			}
		}

		if (!isset($ALL_DONE_DATA[$key])) {
			$ALL_DONE_DATA[$key] = [
				'monitor_id' => $item['monitor_id'],
				'worker_id' => $item['worker_id'],
				'time' => $item['time'],
				'total_count' => 1,
				'good_count' => $item['status'] == \MonitoringLogs::STATUS_GOOD ? 1 : 0,
				'error_count' => $item['status'] == \MonitoringLogs::STATUS_FAIL ? 1 : 0,
				'sum_total_time_ms' => $item['total_time_ms'],
				'max_total_time_ms' => $item['total_time_ms'],
				'min_total_time_ms' => $item['total_time_ms'],
			];
		} else {
			$ALL_DONE_DATA[$key]['total_count']++;
			$ALL_DONE_DATA[$key]['good_count'] += $item['status'] == \MonitoringLogs::STATUS_GOOD ? 1 : 0;
			$ALL_DONE_DATA[$key]['error_count'] += $item['status'] == \MonitoringLogs::STATUS_FAIL ? 1 : 0;
			$ALL_DONE_DATA[$key]['sum_total_time_ms'] += $item['total_time_ms'];
			$ALL_DONE_DATA[$key]['max_total_time_ms'] = max($ALL_DONE_DATA[$key]['max_total_time_ms'], $item['total_time_ms']);
			$ALL_DONE_DATA[$key]['min_total_time_ms'] = min($ALL_DONE_DATA[$key]['min_total_time_ms'], $item['total_time_ms']);
		}
		$ALL_DONE_DATA[$key]['delete_id'][] = $item['id'];
		$last_id = $item['id'];
	}

} while(true);

// Save Aggregate Data
if ($ALL_DONE_DATA) {
	foreach($ALL_DONE_DATA as $item) {
		$delete_ids = $item['delete_id'];
		unset($item['delete_id']);
		$item['availability_percent'] = round($item['good_count'] / $item['total_count'] * 100, 2);
		$item['avg_total_time_ms'] = round($item['sum_total_time_ms'] / $item['total_count']);
		$item['update_time'] = time();
		if (!isset($item['id']) || !$item['id']) {
			$item['create_time'] = time();
			$item['_mode'] = \DB\Common::CSMODE_INSERT;
		} else {
			$item['_mode'] = \DB\Common::CSMODE_UPDATE;
		}
		$status = \MonitoringLogsAggregate::save($item);
		if ($status) {
			if (count($delete_ids) > 0) {
				$delete_chunks = array_chunk($delete_ids, 100);
				foreach ($delete_chunks as $chunk) {
					if (!empty($chunk)) {
						\MonitoringLogs::delete(false, sql_pholder(' AND id IN (?@)', $chunk));
					}
				}
			}
		}
	}
}

$__work_time = time() - $__start_time;
$sleepTime = max($__worker_sleep - $__work_time,0);
error_log("$__worker_errorlog_prefix: Finished $__work_time sec; sleep " . intval($sleepTime) . " sec");
echo "sleep " . intval($sleepTime);

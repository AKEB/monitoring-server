<?php

namespace Api\V2;

class LogsService {

	/**
	 * @param array<string,mixed> $user
	 * @param array<string,mixed> $query
	 */
	static public function list(array $user, array $query): array {
		$from = trim(strval($query['from'] ?? ''));
		$to = trim(strval($query['to'] ?? ''));
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
			return Envelope::error('from/to dates required as YYYY-MM-DD') + ['http' => 422];
		}
		$start = strtotime($from.' 00:00:00');
		$end = strtotime($to.' 23:59:59');
		if (!$start || !$end) {
			return Envelope::error('Invalid date range') + ['http' => 422];
		}

		$store = Runtime::store();
		if ($store) {
			$rows = [];
			foreach ($store->all('logs') as $row) {
				$time = intval($row['time'] ?? 0);
				if ($time < $start || $time > $end) {
					continue;
				}
				if (!empty($query['object']) && strval($row['object'] ?? '') !== strval($query['object'])) {
					continue;
				}
				if (isset($query['object_id']) && intval($query['object_id']) > 0 && intval($row['object_id'] ?? 0) !== intval($query['object_id'])) {
					continue;
				}
				$rows[] = $row;
			}
			return Envelope::ok($rows, ['total' => count($rows), 'from' => $from, 'to' => $to]);
		}

		if (!Runtime::$bypassAcl && !\Sessions::checkPermission(\Permissions::LOGS, 0, READ, $user)) {
			return Envelope::error('Forbidden') + ['http' => 403];
		}

		$sql = sql_pholder(' AND t.`time`>=? AND t.`time`<=?', $start, $end);
		if (!empty($query['object'])) {
			$sql .= sql_pholder(' AND t.`object`=?', strval($query['object']));
		}
		if (isset($query['object_id']) && intval($query['object_id']) > 0) {
			$sql .= sql_pholder(' AND t.`object_id`=?', intval($query['object_id']));
		}
		$sql .= ' ORDER BY t.`id` ASC';
		$logs = \Logs::data(false, $sql, 't.*') ?: [];
		$out = [];
		foreach ($logs as $log) {
			$out[] = [
				'id' => intval($log['id'] ?? 0),
				'user_id' => intval($log['user_id'] ?? 0),
				'action' => intval($log['action'] ?? 0),
				'object' => strval($log['object'] ?? ''),
				'object_id' => intval($log['object_id'] ?? 0),
				'time' => intval($log['time'] ?? 0),
				'comment' => strval($log['comment'] ?? ''),
				'json_data' => is_string($log['json_data'] ?? null)
					? (json_decode($log['json_data'], true) ?: [])
					: ($log['json_data'] ?? []),
			];
		}
		return Envelope::ok($out, ['total' => count($out), 'from' => $from, 'to' => $to]);
	}
}

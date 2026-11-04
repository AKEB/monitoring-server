<?php

class ServerMetrics extends \DB\MySQLObject {
	static public $table = 'server_metrics';

	const LOGS_OBJECT = 'ServerMetrics';

	/** Keep minute samples for 14 days. */
	const RETENTION_SEC = 14 * 86400;

	static public function purgeOld(): int {
		$before = time() - static::RETENTION_SEC;
		$rows = static::data(false, sql_pholder(' AND `collected_at` < ? LIMIT 5000', $before), '`id`') ?: [];
		$n = 0;
		foreach ($rows as $row) {
			static::delete(['id' => intval($row['id'])]);
			$n++;
		}
		return $n;
	}
}

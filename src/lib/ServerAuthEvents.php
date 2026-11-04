<?php

class ServerAuthEvents extends \DB\MySQLObject {
	static public $table = 'server_auth_events';

	const LOGS_OBJECT = 'ServerAuthEvents';

	const RETENTION_SEC = 30 * 86400;

	static public function purgeOld(): int {
		$before = time() - static::RETENTION_SEC;
		$rows = static::data(false, sql_pholder(' AND `event_time` < ? LIMIT 5000', $before), '`id`') ?: [];
		$n = 0;
		foreach ($rows as $row) {
			static::delete(['id' => intval($row['id'])]);
			$n++;
		}
		return $n;
	}
}

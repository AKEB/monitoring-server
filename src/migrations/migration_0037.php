<?php
class Migration_0037 {
	static public $previous = [
		'migration_0036',
	];

	static function install() {
		global $db;
		$db->execSQL("
			CREATE TABLE IF NOT EXISTS `signatures` (
				`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				`project` VARCHAR(255) NOT NULL DEFAULT '',
				`title` VARCHAR(255) NOT NULL DEFAULT '',
				`text` TEXT NOT NULL,
				`status` INT NOT NULL DEFAULT 1,
				`create_time` INT UNSIGNED NOT NULL DEFAULT 0,
				`update_time` INT UNSIGNED NOT NULL DEFAULT 0,
				PRIMARY KEY (`id`),
				UNIQUE KEY `project_title` (`project`, `title`),
				INDEX (`status`, `project`)
			) ENGINE = InnoDB;
		");

		// DDL cannot be rolled back in MySQL — keep steps idempotent so a partial
		// previous run does not block this migration (and everything after it).
		if (!static::hasColumn($db, 'monitoring_monitor', 'signature_id')) {
			$after = static::hasColumn($db, 'monitoring_monitor', 'additional_text')
				? ' AFTER `additional_text`'
				: '';
			$db->execSQL("ALTER TABLE `monitoring_monitor`
				 ADD `signature_id` BIGINT UNSIGNED NOT NULL DEFAULT 0{$after},
				 ADD INDEX (`signature_id`)
			;");
		}

		if (static::hasColumn($db, 'monitoring_monitor', 'additional_text')) {
			$rows = [];
			$db->db_GetQueryArray(
				"SELECT `id`, `additional_text` FROM `monitoring_monitor`
				 WHERE TRIM(`additional_text`) != '' AND `signature_id` = 0",
				$rows
			);

			$groups = [];
			foreach ($rows as $row) {
				$text = trim((string)($row['additional_text'] ?? ''));
				if ($text === '') continue;
				$groups[$text][] = intval($row['id']);
			}

			$now = time();
			$used_titles = static::existingTitles($db, 'Импорт');
			$n = 0;
			$default_group_id = 2; // Groups::DEFAULT_GROUP_ID
			foreach ($groups as $text => $monitor_ids) {
				$n++;
				$title = static::makeTitle($text, $n, $used_titles);
				$used_titles[$title] = true;

				$project = 'Импорт';
				$db->execSQL(sql_pholder(
					'INSERT INTO `signatures` (`project`, `title`, `text`, `status`, `create_time`, `update_time`)
					 VALUES (?, ?, ?, 1, ?, ?)',
					$project, $title, $text, $now, $now
				));
				$signature_id = intval($db->insert_id());
				if (!$signature_id) continue;

				// READ for Default group (everyone)
				$db->execSQL(sql_pholder(
					'INSERT INTO `object_permissions`
						(`object`, `object_id`, `subject`, `subject_id`, `read`, `write`, `delete`, `access_read`, `access_write`, `access_change`, `create_time`, `update_time`)
					 VALUES
						(?, ?, ?, ?, 1, 0, 0, 0, 0, 0, ?, ?)',
					'group', $default_group_id, 'signature', $signature_id, $now, $now
				));

				$creator_ids = static::monitorCreatorIds($db, $monitor_ids);
				foreach ($creator_ids as $creator_user_id) {
					$db->execSQL(sql_pholder(
						'INSERT INTO `object_permissions`
							(`object`, `object_id`, `subject`, `subject_id`, `read`, `write`, `delete`, `access_read`, `access_write`, `access_change`, `create_time`, `update_time`)
						 VALUES
							(?, ?, ?, ?, 1, 1, 1, 1, 1, 1, ?, ?)',
						'user', $creator_user_id, 'signature', $signature_id, $now, $now
					));
				}

				if (!$monitor_ids) continue;
				$db->execSQL(sql_pholder(
					'UPDATE `monitoring_monitor` SET `signature_id` = ? WHERE `id` IN (?@)',
					$signature_id, $monitor_ids
				));
			}

			$db->execSQL("ALTER TABLE `monitoring_monitor` DROP `additional_text`;");
		}
	}

	static function uninstall() {
		global $db;
		if (!static::hasColumn($db, 'monitoring_monitor', 'additional_text')) {
			$db->execSQL("ALTER TABLE `monitoring_monitor`
				 ADD `additional_text` TEXT NOT NULL AFTER `method`
			;");
		}

		$rows = [];
		$db->db_GetQueryArray(
			"SELECT m.`id` AS monitor_id, s.`text` AS signature_text
			 FROM `monitoring_monitor` m
			 INNER JOIN `signatures` s ON s.`id` = m.`signature_id`
			 WHERE m.`signature_id` > 0",
			$rows
		);
		foreach ($rows as $row) {
			$monitor_id = intval($row['monitor_id'] ?? 0);
			$text = (string)($row['signature_text'] ?? '');
			if (!$monitor_id) continue;
			$db->execSQL(sql_pholder(
				'UPDATE `monitoring_monitor` SET `additional_text` = ? WHERE `id` = ?',
				$text, $monitor_id
			));
		}

		$db->execSQL("DELETE FROM `object_permissions` WHERE `subject` = 'signature';");
		if (static::hasColumn($db, 'monitoring_monitor', 'signature_id')) {
			$db->execSQL("ALTER TABLE `monitoring_monitor` DROP INDEX `signature_id`, DROP `signature_id`;");
		}
		$db->execSQL("DROP TABLE IF EXISTS `signatures`;");
	}

	/** @param \DB\Database $db */
	static private function hasColumn($db, string $table, string $column): bool {
		$fields = $db->getFields($table);
		if (!is_array($fields)) return false;
		foreach ($fields as $field) {
			if (($field->name ?? '') === $column) return true;
		}
		return false;
	}

	/** @param \DB\Database $db @return array<string,bool> */
	static private function existingTitles($db, string $project): array {
		$rows = [];
		$db->db_GetQueryArray(
			sql_pholder('SELECT `title` FROM `signatures` WHERE `project` = ?', $project),
			$rows
		);
		$used = [];
		foreach ($rows as $row) {
			$title = (string)($row['title'] ?? '');
			if ($title !== '') $used[$title] = true;
		}
		return $used;
	}

	static private function makeTitle(string $text, int $n, array $used_titles): string {
		$one_line = preg_replace('/\s+/u', ' ', $text) ?? $text;
		$title = mb_substr($one_line, 0, 60);
		if (mb_strlen($one_line) > 60) {
			$title .= '…';
		}
		if ($title === '') {
			$title = 'Подпись '.$n;
		}
		$base = $title;
		$i = 2;
		while (isset($used_titles[$title])) {
			$suffix = ' ('.$i.')';
			$max = 255 - mb_strlen($suffix);
			$title = mb_substr($base, 0, max(1, $max)).$suffix;
			$i++;
		}
		return $title;
	}

	/**
	 * Creators of monitors from create-logs; fallback — users with WRITE on the monitor.
	 * @param \DB\Database $db
	 * @param int[] $monitor_ids
	 * @return int[]
	 */
	static private function monitorCreatorIds($db, array $monitor_ids): array {
		$monitor_ids = array_values(array_unique(array_filter(array_map('intval', $monitor_ids))));
		if (!$monitor_ids) return [];

		$creators = [];

		$log_rows = [];
		$db->db_GetQueryArray(
			sql_pholder(
				'SELECT `object_id`, `user_id`
				 FROM `logs`
				 WHERE `object` = ?
				   AND `action` = ?
				   AND `object_id` IN (?@)
				   AND `user_id` > 0
				 ORDER BY `id` ASC',
				'Monitors', 2, $monitor_ids
			),
			$log_rows
		);
		$seen_monitors = [];
		foreach ($log_rows as $row) {
			$monitor_id = intval($row['object_id'] ?? 0);
			$user_id = intval($row['user_id'] ?? 0);
			if (!$monitor_id || !$user_id) continue;
			if (isset($seen_monitors[$monitor_id])) continue;
			$seen_monitors[$monitor_id] = true;
			$creators[$user_id] = $user_id;
		}

		$missing = array_diff($monitor_ids, array_keys($seen_monitors));
		if ($missing) {
			$perm_rows = [];
			$db->db_GetQueryArray(
				sql_pholder(
					'SELECT DISTINCT `object_id` AS `user_id`
					 FROM `object_permissions`
					 WHERE `object` = ?
					   AND `subject` = ?
					   AND `subject_id` IN (?@)
					   AND `write` = 1
					   AND `object_id` > 0',
					'user', 'monitor', array_values($missing)
				),
				$perm_rows
			);
			foreach ($perm_rows as $row) {
				$user_id = intval($row['user_id'] ?? 0);
				if ($user_id > 0) {
					$creators[$user_id] = $user_id;
				}
			}
		}

		return array_values($creators);
	}
}

<?php

class MonitoringMonitorTags extends \DB\MySQLObject {
	static public $table = 'monitoring_monitor_tags';

	const LOGS_OBJECT = 'MonitorMonitorTags';

	/**
	 * @return list<int>
	 */
	static public function tagIdsForMonitor(int $monitor_id): array {
		if ($monitor_id < 1) {
			return [];
		}
		$rows = static::data(['monitor_id' => $monitor_id], false, '`tag_id`') ?: [];
		$ids = [];
		foreach ($rows as $row) {
			$id = intval($row['tag_id'] ?? 0);
			if ($id > 0) {
				$ids[$id] = $id;
			}
		}
		return array_values($ids);
	}

	/**
	 * @param list<int> $monitor_ids
	 * @return array<int, list<array{id:int,title:string,color:string}>>
	 */
	static public function tagsByMonitorIds(array $monitor_ids): array {
		$monitor_ids = array_values(array_unique(array_filter(array_map('intval', $monitor_ids))));
		if (!$monitor_ids) {
			return [];
		}
		$links = static::data(false, sql_pholder(' AND `monitor_id` IN (?@)', $monitor_ids), '`monitor_id`, `tag_id`') ?: [];
		$tagIds = [];
		$byMonitor = [];
		foreach ($links as $link) {
			$mid = intval($link['monitor_id'] ?? 0);
			$tid = intval($link['tag_id'] ?? 0);
			if ($mid < 1 || $tid < 1) {
				continue;
			}
			$tagIds[$tid] = $tid;
			$byMonitor[$mid][] = $tid;
		}
		if (!$tagIds) {
			return [];
		}
		$tags = \MonitorTags::data(false, sql_pholder(' AND `id` IN (?@) ORDER BY `title` ASC', array_values($tagIds))) ?: [];
		$tagHash = [];
		foreach ($tags as $tag) {
			$id = intval($tag['id'] ?? 0);
			if ($id < 1) {
				continue;
			}
			$tagHash[$id] = [
				'id' => $id,
				'title' => strval($tag['title'] ?? ''),
				'color' => \MonitorTags::normalizeColor(strval($tag['color'] ?? \MonitorTags::DEFAULT_COLOR)),
			];
		}
		$out = [];
		foreach ($byMonitor as $mid => $tids) {
			$list = [];
			foreach ($tids as $tid) {
				if (isset($tagHash[$tid])) {
					$list[] = $tagHash[$tid];
				}
			}
			usort($list, static fn($a, $b) => strcasecmp($a['title'], $b['title']));
			$out[$mid] = $list;
		}
		return $out;
	}
}

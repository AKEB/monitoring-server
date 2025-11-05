<?php

class MonitoringNotify extends \DB\MySQLObject {
	static public $table = 'monitoring_notify';

	const LOGS_OBJECT = 'MonitorNotify';


	static public function getNotificationsForMonitor(int $monitor_id): array {

		$cache = new \Cache('MonitoringNotify_'.md5($monitor_id));
		if ($cache->isValid()) {
			$notifications = $cache->get();
		} else {
			$folders_hash = make_hash(\Monitors::data(false, sql_pholder(' AND (`type` = ? OR `id` = ?)', \Monitors::TYPE_FOLDER, $monitor_id), '`id`, `parent_id`'), 'id');
			$monitor_ids = $folders_hash ? array_keys($folders_hash) : [];
			if (!isset($monitor_ids[$monitor_id]) || !$monitor_ids[$monitor_id]) {
				$monitor_ids[] = $monitor_id;
			}
			$sql = '';
			if ($monitor_ids) {
				$sql = sql_pholder(' AND monitor_id IN (?@)', $monitor_ids);
			}
			$notify = \MonitoringNotify::data(false, $sql, '`monitor_id`, `notify_id`');
			$notify_hash = [];
			foreach($notify as $v) {
				$notify_hash[$v['monitor_id']][$v['notify_id']] = true;
			}
			$notifications = [];
			do {
				$monitor = $folders_hash[$monitor_id];
				$notify_hash[$monitor['id']] = $notify_hash[$monitor['id']] ?? [];
				foreach($notify_hash[$monitor['id']] as $notify_id => $_) {
					$notifications[$notify_id] = $notify_id;
				}
				$monitor_id = $monitor['parent_id'];
				if (!$monitor_id) break;
			} while($monitor['parent_id'] > 0);

			$notifications = array_keys($notifications);
			$notifications = $notifications ? \Notify::data(false, sql_pholder(' AND `id` IN (?@)', $notifications)) : [];

			$cache->update($notifications, 60);
		}
		return $notifications;
	}

	static public function sendNotification(int $monitor_id, string $text): bool {
		if (!$monitor_id) return false;
		if (!$text) return false;
		$notifications = static::getNotificationsForMonitor($monitor_id);
		if (!$notifications) return false;
		foreach($notifications as $notify) {
			$notify['json_data'] = json_decode($notify['json_data'], true);
			if ($notify['type'] == \Notify::TYPE_TELEGRAM) {
				try {
					$t = explode(':',$notify['receiver']);
					$telegram_chat_id = $t[0];
					if (isset($t[1]) && $t[1]) {
						$telegram_thread_id = $t[1];
					} else {
						$telegram_thread_id = 0;
					}
					$bot = new \TelegramBot\Api\BotApi($notify['json_data']['telegram_bot_token']);
					@$bot->sendMessage(
						$telegram_chat_id,
						$text,
						'HTML', true, null, null, false,
						$telegram_thread_id
					);
				} catch (\Exception $e) {
					continue;
				}
				continue;
			} elseif ($notify['type'] == \Notify::TYPE_MATTERMOST) {
				try {
					$mattermost = new \Mattermost($notify['json_data']['mattermost_url'], $notify['json_data']['mattermost_token']);
					if (strpos($notify['receiver'], '@') !== false) {
						$mattermost->sendMessageByEmail(
							$notify['receiver'],
							$text,
						);
					} else {
						$mattermost->sendMessage(
							$notify['receiver'],
							$text,
						);
					}
				} catch (\Exception $e) {
					continue;
				}
				continue;
			} else {
				continue;
			}
		}
		return true;
	}

}
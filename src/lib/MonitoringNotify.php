<?php

class MonitoringNotify extends \DB\MySQLObject {
	static public $table = 'monitoring_notify';

	const LOGS_OBJECT = 'MonitorNotify';


	static public function getNotificationsForMonitor(int $monitor_id): array {

		$cache = new \Cache('MonitoringNotify_'.md5($monitor_id));
		if ($cache->isValid()) {
			$notifications = $cache->get();
		} else {
			$data = \Monitors::get_hash();
			$folders_hash = [];
			foreach($data as $id=>$monitor) {
				if ($id == $monitor_id) {
					$folders_hash[$id] = $monitor;
				}
				if ($monitor['type'] == \Monitors::TYPE_FOLDER) {
					$folders_hash[$id] = $monitor;
				}
			}
			$monitor_ids = $folders_hash ? array_keys($folders_hash) : [];
			if (!isset($monitor_ids[$monitor_id]) || !$monitor_ids[$monitor_id]) {
				$monitor_ids[] = $monitor_id;
			}
			$sql = '';
			if ($monitor_ids) {
				$sql = sql_pholder(' AND monitor_id IN (?@)', $monitor_ids);
			}
			$notify = static::data(false, $sql, '`monitor_id`, `notify_id`');
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
			if ($notifications) {
				$sql = sql_pholder(' AND `id` IN (?@)', $notifications);
				$sql .= sql_pholder(' AND `status` = ? ', \Notify::STATUS_ENABLED);
				$notifications = \Notify::data(false, $sql);
			} else {
				$notifications = [];
			}
			$cache->update($notifications, 60);
		}
		return $notifications;
	}

	static public function sendNotification(int $monitor_id, string $text, ?string $title='', ?bool $status=false): bool {
		if (!$monitor_id) return false;
		if (!$text) return false;
		$notifications = static::getNotificationsForMonitor($monitor_id);
		if (!$notifications) return false;
		foreach($notifications as $notify) {
			$text_send = $text??'';
			$title_send = $title??'';
			$notify['json_data'] = json_decode($notify['json_data'], true);
			if ($notify['type'] == \Notify::TYPE_TELEGRAM) {
				$text_send = $title_send."\n".$text_send;
				$text_send .= "\n\n".'<i>Alert: '.$notify['title'].'</i>';
				try {
					$t = explode(':',$notify['receiver']);
					$telegram_chat_id = $t[0];
					if (isset($t[1]) && $t[1]) {
						$telegram_thread_id = $t[1];
					} else {
						$telegram_thread_id = 0;
					}
					$bot = new \TelegramBot\Api\BotApi($notify['json_data']['telegram_bot_token']);
					if (\Config::getInstance()->telegram_proxy_host) {
						$bot->setProxy((\Config::getInstance()->telegram_proxy_host).':'.(\Config::getInstance()->telegram_proxy_port), \Config::getInstance()->telegram_proxy_socks5);
						if (\Config::getInstance()->telegram_proxy_username && \Config::getInstance()->telegram_proxy_password) {
							$bot->setCurlOption(CURLOPT_PROXYUSERPWD, \Config::getInstance()->telegram_proxy_username.':'.\Config::getInstance()->telegram_proxy_password);
						}
					}
					@$bot->sendMessage(
						$telegram_chat_id,
						$text_send,
						'HTML', true, null, null, false,
						$telegram_thread_id
					);
				} catch (\Exception $e) {
					continue;
				}
				continue;
			} elseif ($notify['type'] == \Notify::TYPE_MATTERMOST) {
				try {
					$text_send = str_replace(
						['<br/>', '<br />', '<br>', '<strong>', '</strong>', '<b>', '</b>', '<i>', '</i>', '<em>', '</em>', '<u>', '</u>'],
						["\n",    "\n",     "\n",   '**',       '**',        '**',  '**',   '*',   '*',    '*',    '*',     '__',  '__'],
						$text_send
					);
					$props = [
						'attachments' => [
							[
								'color'       => $status ? '#00FF00' : '#FF0000',
								'author_name' => 'Monitoring Alert Manager',
								'title'       => $title_send,
								'text'        => $text_send,
								'ts'          => time(),
								'footer'      => 'Alert: '.$notify['title'],
							],
						],
					];

					$mattermost = new \Mattermost($notify['json_data']['mattermost_url'], $notify['json_data']['mattermost_token']);
					if (strpos($notify['receiver'], '@') !== false) {
						$mattermost->sendMessageByEmail(
							$notify['receiver'],
							'',
							$props
						);
					} else {
						$mattermost->sendMessage(
							$notify['receiver'],
							'',
							$props
						);
					}
				} catch (\Exception $e) {
					continue;
				}
				continue;

			} elseif ($notify['type'] == \Notify::TYPE_GOTIFY) {
				try {
					$text_send = $title_send."\n".$text_send;
					$text_send .= "\n\n".'<i>Alert: '.$notify['title'].'</i>';
					$text_send = str_replace(
						['<br/>', '<br />', '<br>', '<strong>', '</strong>', '<b>', '</b>', '<i>', '</i>', '<em>', '</em>', '<u>', '</u>'],
						["\n",    "\n",     "\n",   '',         '',          '',    '',     '',    '',     '',     '',      '',    ''],
						$text_send
					);
					$curl = new \AKEB\CurlGet(($notify['json_data']['gotify_url']??'').'/message?token='.($notify['receiver']??''), [], [], ['Content-Type: application/json;charset=utf-8']);
					$curl->setBody(json_encode([
						'message' => $text_send,
						'priority' => intval($notify['json_data']['gotify_priority']??0),
					]));
					$curl->exec();
				} catch (\Exception $e) {
					continue;
				}
				continue;
			} elseif ($notify['type'] == \Notify::TYPE_MAX) {
				try {
					$text_send = $title_send."\n".$text_send;
					$text_send .= "\n\n".'<i>Alert: '.$notify['title'].'</i>';
					$token = $notify['json_data']['max_token'] ?? '';
					if ($token === '' || !isset($notify['receiver']) || trim((string) $notify['receiver']) === '') {
						continue;
					}
					$body = mb_substr($text_send, 0, 4000);
					\MaxMessenger::send(
						$token,
						trim((string) $notify['receiver']),
						$body,
						'html'
					);
				} catch (\Exception $e) {
					continue;
				}
				continue;
			} elseif ($notify['type'] == \Notify::TYPE_WEBHOOK) {
				try {
					$url = trim((string) ($notify['receiver'] ?? ''));
					if ($url === '') {
						continue;
					}
					$payload = \NotifyWebhook::buildPayload(
						$monitor_id,
						$text_send,
						$title_send,
						(bool) $status,
						strval($notify['title'] ?? '')
					);
					\NotifyWebhook::send(
						$url,
						strval($notify['json_data']['webhook_username'] ?? ''),
						strval($notify['json_data']['webhook_password'] ?? ''),
						$payload
					);
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
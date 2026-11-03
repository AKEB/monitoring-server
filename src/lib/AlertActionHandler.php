<?php

/**
 * Shared alert-button click processing for webhooks and Telegram getUpdates poller.
 */
class AlertActionHandler {

	/**
	 * @return array{ok: bool, toast: string, new_text: string, monitor_id: int}
	 */
	static public function apply(
		array $notify,
		?array $parsed,
		string $author,
		string $channel,
		string $chatId,
		string $messageId,
		string $currentText = ''
	): array {
		$empty = ['ok' => false, 'toast' => 'Error', 'new_text' => '', 'monitor_id' => 0];
		if ($parsed === null) {
			return $empty + ['toast' => 'Unknown action'];
		}
		$monitorId = intval($parsed['monitor_id']);
		$notifyId = intval($parsed['notify_id']);
		if ($notifyId > 0 && $notifyId !== intval($notify['id'])) {
			return $empty + ['toast' => 'Notify mismatch'];
		}
		$notifyId = intval($notify['id']);
		// Direct join or folder-inherited notify (same as send path).
		$linked = false;
		foreach (\MonitoringNotify::getNotificationsForMonitor($monitorId) as $linkedNotify) {
			if (intval($linkedNotify['id'] ?? 0) === $notifyId) {
				$linked = true;
				break;
			}
		}
		if (!$linked) {
			return $empty + ['toast' => 'Monitor not linked'];
		}
		$monitor = \Monitors::get(['id' => $monitorId], '', '`id`, `status`, `last_send_status`, `alert_start_time`');
		if (!$monitor || intval($monitor['status']) !== \Monitors::STATUS_ENABLED) {
			return $empty + ['toast' => 'Monitor not found'];
		}
		if (intval($monitor['last_send_status']) !== \MonitoringLogs::STATUS_FAIL || intval($monitor['alert_start_time']) < 1) {
			return $empty + ['toast' => 'No active alert'];
		}

		$saved = \AlertActions::appendAction($monitorId, $parsed['action'], $author);
		if (!$saved) {
			return $empty + ['toast' => 'Save failed'];
		}

		$line = \AlertActions::formatLogLine($author, $parsed['action'], intval($saved['action_time']));
		$stored = \AlertActions::getMessage($monitorId, $notifyId);
		$baseText = '';
		if ($stored && trim(strval($stored['message_text'] ?? '')) !== '') {
			$baseText = strval($stored['message_text']);
		} elseif (trim($currentText) !== '') {
			$baseText = $currentText;
		}
		if ($baseText === '') {
			$baseText = $line;
		} else {
			$baseText = rtrim($baseText)."\n".$line;
		}
		\AlertActions::updateStoredMessageText($monitorId, $notifyId, $baseText);
		if (static::isValidMessageId($channel, $messageId)) {
			\AlertActions::saveMessage($monitorId, $notifyId, $channel, $chatId, $messageId, $baseText);
		}

		return [
			'ok' => true,
			'toast' => \AlertActions::actionButtonText($parsed['action']),
			'new_text' => $baseText,
			'monitor_id' => $monitorId,
		];
	}

	static public function isValidMessageId(string $channel, string $messageId): bool {
		$messageId = trim($messageId);
		if ($messageId === '' || $messageId === 'x' || $messageId === '0') {
			return false;
		}
		if ($channel === \AlertActions::CHANNEL_TELEGRAM) {
			return preg_match('/^\d+$/', $messageId) === 1;
		}
		if ($channel === \AlertActions::CHANNEL_MATTERMOST) {
			return preg_match('/^[a-z0-9]{26}$/', $messageId) === 1;
		}
		if ($channel === \AlertActions::CHANNEL_MAX) {
			return strlen($messageId) >= 3;
		}
		return false;
	}

	/**
	 * Process one Telegram callback_query payload (from webhook or getUpdates).
	 *
	 * @param array $webhookNotify any notify that shares this bot token (for auth/fallback)
	 * @param array $callbackQuery Telegram callback_query object
	 */
	static public function processTelegramCallbackQuery(array $webhookNotify, array $callbackQuery): void {
		$callbackData = strval($callbackQuery['data'] ?? '');
		$parsed = \AlertActions::parseCallbackData($callbackData);
		$user = is_array($callbackQuery['from'] ?? null) ? $callbackQuery['from'] : [];
		$author = '';
		if (!empty($user['username'])) {
			$author = '@'.strval($user['username']);
		} elseif (!empty($user['first_name'])) {
			$author = strval($user['first_name']);
			if (!empty($user['last_name'])) {
				$author .= ' '.strval($user['last_name']);
			}
		} else {
			$author = 'user'.strval($user['id'] ?? '');
		}
		$callbackQueryId = strval($callbackQuery['id'] ?? '');
		$message = is_array($callbackQuery['message'] ?? null) ? $callbackQuery['message'] : [];
		$chatId = strval($message['chat']['id'] ?? '');
		$messageId = strval($message['message_id'] ?? '');
		$currentText = strval($message['text'] ?? ($message['caption'] ?? ''));

		$targetNotify = \AlertActions::resolveNotifyForCallback($webhookNotify, $parsed);
		$bot = \MonitoringNotify::telegramBot(strval(($targetNotify ?? $webhookNotify)['json_data']['telegram_bot_token'] ?? ''));

		// Dedup: Telegram (and our poller) can redeliver the same callback_query.
		// Without this, one click becomes an endless "взял/позвонил/всё ок" loop.
		if ($callbackQueryId !== '' && static::isCallbackAlreadyProcessed($callbackQueryId)) {
			if ($bot) {
				static::answerTelegramCallback($bot, $callbackQueryId, 'OK');
			}
			return;
		}
		if ($callbackQueryId !== '') {
			static::markCallbackProcessed($callbackQueryId);
		}

		if ($targetNotify === null) {
			if ($bot && $callbackQueryId !== '') {
				static::answerTelegramCallback($bot, $callbackQueryId, 'Notify mismatch');
			}
			return;
		}

		// Answer before DB/edit work — late answers make Telegram clients resend the click.
		$toast = $parsed ? \AlertActions::actionButtonText($parsed['action']) : 'OK';
		if ($bot && $callbackQueryId !== '') {
			static::answerTelegramCallback($bot, $callbackQueryId, $toast);
		}

		$result = static::apply($targetNotify, $parsed, $author, \AlertActions::CHANNEL_TELEGRAM, $chatId, $messageId, $currentText);
		if ($bot && !empty($result['ok']) && !empty($result['new_text']) && $chatId !== '' && $messageId !== '') {
			try {
				// Rebuild keyboard after mute/unmute so Mute ↔ Unmute swaps on the message.
				$keyboard = \AlertActions::telegramKeyboard(intval($result['monitor_id']), intval($targetNotify['id']));
				@$bot->editMessageText($chatId, intval($messageId), $result['new_text'], 'HTML', true, $keyboard);
			} catch (\Exception $e) {
			}
		}
	}

	/**
	 * Poll Telegram getUpdates for all unique bot tokens (webhook unreachable behind filter/proxy).
	 *
	 * @return int processed callback queries
	 */
	static public function pollTelegramUpdates(): int {
		$rows = \Notify::data(false, sql_pholder(
			' AND `type` = ? AND `status` = ?',
			\Notify::TYPE_TELEGRAM,
			\Notify::STATUS_ENABLED
		));
		if (!is_array($rows) || !$rows) {
			return 0;
		}
		$byBot = [];
		foreach ($rows as $row) {
			$json = $row['json_data'] ?? [];
			if (is_string($json)) {
				$json = json_decode($json, true) ?: [];
			}
			if (!is_array($json)) {
				continue;
			}
			$botToken = trim(strval($json['telegram_bot_token'] ?? ''));
			if ($botToken === '') {
				continue;
			}
			$row['json_data'] = $json;
			if (!isset($byBot[$botToken])) {
				$byBot[$botToken] = $row;
			}
		}

		$processed = 0;
		foreach ($byBot as $botToken => $notify) {
			$bot = \MonitoringNotify::telegramBot($botToken);
			if (!$bot) {
				continue;
			}
			// Force file cache: memcache lock failures used to drop the offset and
			// getUpdates(0) re-delivered the same callbacks forever.
			$cacheKey = 'tg_alert_update_offset_'.md5($botToken);
			$cache = new \Cache($cacheKey, 'default', true);
			$offset = 0;
			if ($cache->isValid()) {
				$offset = intval($cache->get());
			}
			try {
				$updates = $bot->getUpdates($offset, 50, 0);
			} catch (\Exception $e) {
				error_log('AlertActionHandler pollTelegram getUpdates: '.$e->getMessage());
				continue;
			}
			if (!is_array($updates) && !($updates instanceof \Traversable)) {
				continue;
			}
			$maxUpdateId = $offset > 0 ? $offset - 1 : 0;
			$callbacks = [];
			foreach ($updates as $update) {
				$updateId = 0;
				$cq = null;
				if (is_object($update) && method_exists($update, 'getUpdateId')) {
					$updateId = intval($update->getUpdateId());
					if (method_exists($update, 'getCallbackQuery') && $update->getCallbackQuery()) {
						$cqObj = $update->getCallbackQuery();
						$cq = method_exists($cqObj, 'toJson') ? $cqObj->toJson(true) : null;
						if (!is_array($cq)) {
							$cq = null;
						}
					}
				} elseif (is_array($update)) {
					$updateId = intval($update['update_id'] ?? 0);
					$cq = is_array($update['callback_query'] ?? null) ? $update['callback_query'] : null;
				}
				if ($updateId > $maxUpdateId) {
					$maxUpdateId = $updateId;
				}
				if (is_array($cq)) {
					$callbacks[] = $cq;
				}
			}

			// Confirm with Telegram BEFORE processing. Local cache alone is not enough:
			// if offset is lost, Telegram keeps returning the same updates every poll (~2s).
			if ($maxUpdateId > 0) {
				$nextOffset = $maxUpdateId + 1;
				try {
					$bot->getUpdates($nextOffset, 1, 0);
				} catch (\Exception $e) {
					error_log('AlertActionHandler pollTelegram confirm offset: '.$e->getMessage());
				}
				$cache->update(strval($nextOffset), 7 * 24 * 3600);
			}

			foreach ($callbacks as $cq) {
				static::processTelegramCallbackQuery($notify, $cq);
				$processed++;
			}
		}
		return $processed;
	}

	static private function answerTelegramCallback(\TelegramBot\Api\BotApi $bot, string $callbackQueryId, string $toast): void {
		if ($callbackQueryId === '') {
			return;
		}
		try {
			@$bot->answerCallbackQuery($callbackQueryId, $toast, false);
		} catch (\Exception $e) {
		}
	}

	static private function callbackDedupCache(string $callbackQueryId): \Cache {
		return new \Cache('tg_cq_seen_'.md5($callbackQueryId), 'default', true);
	}

	static private function isCallbackAlreadyProcessed(string $callbackQueryId): bool {
		$cache = static::callbackDedupCache($callbackQueryId);
		return $cache->isValid();
	}

	static private function markCallbackProcessed(string $callbackQueryId): void {
		$cache = static::callbackDedupCache($callbackQueryId);
		$cache->update('1', 24 * 3600);
	}
}

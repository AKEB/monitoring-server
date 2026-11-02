<?php

/**
 * Interactive alert buttons (Telegram / Mattermost / MAX):
 * log lines on click, Mute suppresses repeat alerts until green.
 */
class AlertActions extends \DB\MySQLObject {
	static public $table = 'monitoring_alert_actions';

	const ACTION_RESOLVE = 'resolve';
	const ACTION_TAKE = 'take';
	const ACTION_MUTE = 'mute';
	const ACTION_CALL = 'call';
	const ACTION_OK = 'ok';

	const CHANNEL_TELEGRAM = 'telegram';
	const CHANNEL_MATTERMOST = 'mattermost';
	const CHANNEL_MAX = 'max';

	static public function actionsHash(): array {
		return [
			static::ACTION_RESOLVE => [
				'button' => 'Решить',
				'log' => 'решил',
			],
			static::ACTION_TAKE => [
				'button' => 'Взять',
				'log' => 'взял',
			],
			static::ACTION_MUTE => [
				'button' => 'Mute',
				'log' => 'mute',
			],
			static::ACTION_CALL => [
				'button' => 'Позвонить',
				'log' => 'позвонил',
			],
			static::ACTION_OK => [
				'button' => 'Всё ок',
				'log' => 'всё ок',
			],
		];
	}

	static public function isValidAction(string $action): bool {
		return isset(static::actionsHash()[$action]);
	}

	static public function actionLogText(string $action): string {
		$hash = static::actionsHash();
		return $hash[$action]['log'] ?? $action;
	}

	static public function actionButtonText(string $action): string {
		$hash = static::actionsHash();
		return $hash[$action]['button'] ?? $action;
	}

	static public function formatAuthor(string $author): string {
		$author = trim($author);
		if ($author === '') {
			return '@unknown';
		}
		if ($author[0] !== '@') {
			$author = '@'.$author;
		}
		return $author;
	}

	static public function formatLogLine(string $author, string $action, ?int $actionTime = null): string {
		$ts = $actionTime ?? time();
		return '🔵 ['.date('Y-m-d H:i', $ts).'] '.static::formatAuthor($author).' '.static::actionLogText($action);
	}

	static public function generateToken(): string {
		return bin2hex(random_bytes(16));
	}

	static public function ensureToken(array &$jsonData): string {
		$token = trim(strval($jsonData['alert_action_token'] ?? ''));
		if ($token === '') {
			$token = static::generateToken();
			$jsonData['alert_action_token'] = $token;
		}
		return $token;
	}

	static public function webhookUrl(string $channel, string $token, ?string $baseUrl = null): string {
		$base = rtrim($baseUrl !== null && $baseUrl !== '' ? $baseUrl : \ExternalMonitors::baseUrl(), '/');
		return $base.'/api/alert-action/'.$channel.'/'.rawurlencode($token).'/';
	}

	static public function callbackData(string $action, int $monitorId, int $notifyId): string {
		return 'aa:'.$action.':'.$monitorId.':'.$notifyId;
	}

	/**
	 * @return array{action: string, monitor_id: int, notify_id: int}|null
	 */
	static public function parseCallbackData(string $payload): ?array {
		$payload = trim($payload);
		if (!preg_match('/^aa:([a-z]+):(\d+):(\d+)$/', $payload, $m)) {
			return null;
		}
		$action = $m[1];
		if (!static::isValidAction($action)) {
			return null;
		}
		return [
			'action' => $action,
			'monitor_id' => intval($m[2]),
			'notify_id' => intval($m[3]),
		];
	}

	static public function getAlertStartTime(int $monitorId): int {
		$monitor = \Monitors::get(['id' => $monitorId], '', '`id`, `alert_start_time`, `last_send_status`');
		if (!$monitor) {
			return 0;
		}
		return intval($monitor['alert_start_time'] ?? 0);
	}

	static public function isMuted(int $monitorId): bool {
		$monitor = \Monitors::get(['id' => $monitorId], '', '`id`, `alert_muted`');
		if (!$monitor) {
			return false;
		}
		return intval($monitor['alert_muted'] ?? 0) === 1;
	}

	static public function setMuted(int $monitorId, bool $muted): void {
		if ($monitorId < 1) {
			return;
		}
		\Monitors::save([
			'id' => $monitorId,
			'alert_muted' => $muted ? 1 : 0,
			'update_time' => time(),
		]);
	}

	/**
	 * @return list<array{action: string, author: string, action_time: int}>
	 */
	static public function getActions(int $monitorId, int $alertStartTime): array {
		if ($monitorId < 1 || $alertStartTime < 1) {
			return [];
		}
		$rows = static::data(
			false,
			sql_pholder(
				' AND `monitor_id` = ? AND `alert_start_time` = ? ORDER BY `action_time` ASC, `id` ASC',
				$monitorId,
				$alertStartTime
			),
			'`action`, `author`, `action_time`'
		);
		if (!is_array($rows)) {
			return [];
		}
		$out = [];
		foreach ($rows as $row) {
			$out[] = [
				'action' => strval($row['action'] ?? ''),
				'author' => strval($row['author'] ?? ''),
				'action_time' => intval($row['action_time'] ?? 0),
			];
		}
		return $out;
	}

	static public function formatActionsBlock(int $monitorId, int $alertStartTime): string {
		$actions = static::getActions($monitorId, $alertStartTime);
		if (!$actions) {
			return '';
		}
		$lines = [];
		foreach ($actions as $row) {
			$lines[] = static::formatLogLine($row['author'], $row['action'], $row['action_time']);
		}
		return implode("\n", $lines);
	}

	static public function appendToText(string $text, int $monitorId, int $alertStartTime): string {
		$block = static::formatActionsBlock($monitorId, $alertStartTime);
		if ($block === '') {
			return $text;
		}
		$text = rtrim($text);
		return $text."\n\n".$block;
	}

	static public function appendAction(int $monitorId, string $action, string $author, ?int $actionTime = null): ?array {
		if ($monitorId < 1 || !static::isValidAction($action)) {
			return null;
		}
		$alertStartTime = static::getAlertStartTime($monitorId);
		if ($alertStartTime < 1) {
			return null;
		}
		$now = $actionTime ?? time();
		$id = static::save([
			'monitor_id' => $monitorId,
			'alert_start_time' => $alertStartTime,
			'action' => $action,
			'author' => static::formatAuthor($author),
			'action_time' => $now,
			'create_time' => time(),
			'_mode' => \DB\Common::CSMODE_INSERT,
		]);
		if (!$id) {
			return null;
		}
		if ($action === static::ACTION_MUTE) {
			static::setMuted($monitorId, true);
		}
		return [
			'id' => $id,
			'monitor_id' => $monitorId,
			'alert_start_time' => $alertStartTime,
			'action' => $action,
			'author' => static::formatAuthor($author),
			'action_time' => $now,
			'line' => static::formatLogLine($author, $action, $now),
		];
	}

	static public function clearForMonitor(int $monitorId): void {
		if ($monitorId < 1) {
			return;
		}
		$db = static::getDatabase();
		$db->execSQL(sql_pholder('DELETE FROM `'.static::$table.'` WHERE `monitor_id` = ?', $monitorId));
		$db->execSQL(sql_pholder('DELETE FROM `monitoring_alert_messages` WHERE `monitor_id` = ?', $monitorId));
		static::setMuted($monitorId, false);
	}

	static public function saveMessage(
		int $monitorId,
		int $notifyId,
		string $channel,
		string $chatId,
		string $messageId,
		string $messageText
	): void {
		if ($monitorId < 1 || $notifyId < 1 || $messageId === '') {
			return;
		}
		$db = static::getDatabase();
		$now = time();
		$existing = null;
		$db->db_GetQueryRow(
			sql_pholder(
				'SELECT `id` FROM `monitoring_alert_messages` WHERE `monitor_id` = ? AND `notify_id` = ? LIMIT 1',
				$monitorId,
				$notifyId
			),
			$existing
		);
		if ($existing && !empty($existing['id'])) {
			$db->execSQL(sql_pholder(
				'UPDATE `monitoring_alert_messages` SET `channel` = ?, `chat_id` = ?, `message_id` = ?, `message_text` = ?, `update_time` = ? WHERE `id` = ?',
				$channel,
				$chatId,
				$messageId,
				$messageText,
				$now,
				intval($existing['id'])
			));
			return;
		}
		$db->execSQL(sql_pholder(
			'INSERT INTO `monitoring_alert_messages` (`monitor_id`, `notify_id`, `channel`, `chat_id`, `message_id`, `message_text`, `update_time`) VALUES (?, ?, ?, ?, ?, ?, ?)',
			$monitorId,
			$notifyId,
			$channel,
			$chatId,
			$messageId,
			$messageText,
			$now
		));
	}

	static public function getMessage(int $monitorId, int $notifyId): ?array {
		if ($monitorId < 1 || $notifyId < 1) {
			return null;
		}
		$row = null;
		static::getDatabase()->db_GetQueryRow(
			sql_pholder(
				'SELECT * FROM `monitoring_alert_messages` WHERE `monitor_id` = ? AND `notify_id` = ? LIMIT 1',
				$monitorId,
				$notifyId
			),
			$row
		);
		return is_array($row) && !empty($row['id']) ? $row : null;
	}

	static public function updateStoredMessageText(int $monitorId, int $notifyId, string $messageText): void {
		if ($monitorId < 1 || $notifyId < 1) {
			return;
		}
		static::getDatabase()->execSQL(sql_pholder(
			'UPDATE `monitoring_alert_messages` SET `message_text` = ?, `update_time` = ? WHERE `monitor_id` = ? AND `notify_id` = ?',
			$messageText,
			time(),
			$monitorId,
			$notifyId
		));
	}

	/**
	 * @return list<array{text: string, callback_data: string}>
	 */
	static public function buttonDefs(int $monitorId, int $notifyId): array {
		$defs = [];
		foreach (static::actionsHash() as $action => $meta) {
			$defs[] = [
				'text' => $meta['button'],
				'callback_data' => static::callbackData($action, $monitorId, $notifyId),
				'action' => $action,
			];
		}
		return $defs;
	}

	static public function telegramKeyboard(int $monitorId, int $notifyId): \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup {
		$row = [];
		foreach (static::buttonDefs($monitorId, $notifyId) as $btn) {
			$row[] = [
				'text' => $btn['text'],
				'callback_data' => $btn['callback_data'],
			];
		}
		return new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup([$row]);
	}

	/**
	 * Mattermost attachment actions.
	 *
	 * @return list<array<string, mixed>>
	 */
	static public function mattermostActions(int $monitorId, int $notifyId, string $webhookUrl): array {
		$actions = [];
		foreach (static::buttonDefs($monitorId, $notifyId) as $btn) {
			$actions[] = [
				'id' => $btn['action'],
				'name' => $btn['text'],
				'integration' => [
					'url' => $webhookUrl,
					'context' => [
						'action' => $btn['action'],
						'monitor_id' => $monitorId,
						'notify_id' => $notifyId,
					],
				],
			];
		}
		return $actions;
	}

	/**
	 * MAX inline_keyboard attachment.
	 *
	 * @return array{type: string, payload: array{buttons: list<list<array<string, string>>>}}
	 */
	static public function maxInlineKeyboard(int $monitorId, int $notifyId): array {
		$row = [];
		foreach (static::buttonDefs($monitorId, $notifyId) as $btn) {
			$row[] = [
				'type' => 'callback',
				'text' => $btn['text'],
				'payload' => $btn['callback_data'],
			];
		}
		return [
			'type' => 'inline_keyboard',
			'payload' => [
				'buttons' => [$row],
			],
		];
	}

	static public function findNotifyByActionToken(string $token): ?array {
		$token = trim($token);
		if ($token === '' || !preg_match('/^[a-f0-9]{32}$/', $token)) {
			return null;
		}
		$rows = \Notify::data(false, sql_pholder(' AND `status` = ?', \Notify::STATUS_ENABLED));
		if (!is_array($rows)) {
			return null;
		}
		foreach ($rows as $row) {
			$json = $row['json_data'] ?? [];
			if (is_string($json)) {
				$json = json_decode($json, true) ?: [];
			}
			if (!is_array($json)) {
				continue;
			}
			if (($json['alert_action_token'] ?? '') === $token) {
				$row['json_data'] = $json;
				return $row;
			}
		}
		return null;
	}
}

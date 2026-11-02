<?php

namespace App\Api;

/**
 * Public webhook for interactive alert buttons.
 * /api/alert-action/{channel}/{token}/
 */
class AlertAction extends \Routing_Parent implements \Routing_Interface {

	public function Run(string $channel = '', string $token = '') {
		$channel = strtolower(trim($channel));
		$token = trim($token);
		$notify = \AlertActions::findNotifyByActionToken($token);
		if (!$notify) {
			$this->jsonError('Not found', 404);
			return;
		}
		$type = intval($notify['type'] ?? -1);
		if ($channel === \AlertActions::CHANNEL_TELEGRAM && $type === \Notify::TYPE_TELEGRAM) {
			$this->handleTelegram($notify);
			return;
		}
		if ($channel === \AlertActions::CHANNEL_MATTERMOST && $type === \Notify::TYPE_MATTERMOST) {
			$this->handleMattermost($notify);
			return;
		}
		if ($channel === \AlertActions::CHANNEL_MAX && $type === \Notify::TYPE_MAX) {
			$this->handleMax($notify);
			return;
		}
		$this->jsonError('Channel mismatch', 400);
	}

	private function handleTelegram(array $notify): void {
		$raw = file_get_contents('php://input');
		$data = json_decode($raw ?: '', true);
		if (!is_array($data)) {
			$this->jsonError('Bad request', 400);
			return;
		}
		$cq = $data['callback_query'] ?? null;
		if (!is_array($cq)) {
			http_response_code(200);
			echo 'ok';
			exit(0);
		}
		$callbackData = strval($cq['data'] ?? '');
		$parsed = \AlertActions::parseCallbackData($callbackData);
		$user = is_array($cq['from'] ?? null) ? $cq['from'] : [];
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
		$callbackQueryId = strval($cq['id'] ?? '');
		$message = is_array($cq['message'] ?? null) ? $cq['message'] : [];
		$chatId = strval($message['chat']['id'] ?? '');
		$messageId = strval($message['message_id'] ?? '');

		$targetNotify = \AlertActions::resolveNotifyForCallback($notify, $parsed);
		if ($targetNotify === null) {
			$bot = \MonitoringNotify::telegramBot(strval($notify['json_data']['telegram_bot_token'] ?? ''));
			if ($bot && $callbackQueryId !== '') {
				try {
					@$bot->answerCallbackQuery($callbackQueryId, 'Notify mismatch', false);
				} catch (\Exception $e) {
				}
			}
			http_response_code(200);
			echo 'ok';
			exit(0);
		}

		$currentText = strval($message['text'] ?? ($message['caption'] ?? ''));
		$result = $this->applyAction($targetNotify, $parsed, $author, \AlertActions::CHANNEL_TELEGRAM, $chatId, $messageId, $currentText);

		$bot = \MonitoringNotify::telegramBot(strval($targetNotify['json_data']['telegram_bot_token'] ?? ''));
		if ($bot && $callbackQueryId !== '') {
			try {
				@$bot->answerCallbackQuery($callbackQueryId, $result['toast'] ?? 'OK', false);
			} catch (\Exception $e) {
			}
		}
		if ($bot && !empty($result['ok']) && !empty($result['new_text']) && $chatId !== '' && $messageId !== '') {
			try {
				$keyboard = \AlertActions::telegramKeyboard(intval($result['monitor_id']), intval($targetNotify['id']));
				@$bot->editMessageText($chatId, intval($messageId), $result['new_text'], 'HTML', true, $keyboard);
			} catch (\Exception $e) {
			}
		}
		http_response_code(200);
		echo 'ok';
		exit(0);
	}

	private function handleMattermost(array $notify): void {
		$raw = file_get_contents('php://input');
		$data = json_decode($raw ?: '', true);
		if (!is_array($data)) {
			// Mattermost may send form-encoded
			$data = $_POST;
		}
		$context = [];
		if (isset($data['context']) && is_array($data['context'])) {
			$context = $data['context'];
		} elseif (isset($data['context']) && is_string($data['context'])) {
			$decoded = json_decode($data['context'], true);
			if (is_array($decoded)) {
				$context = $decoded;
			}
		}
		$action = strval($context['action'] ?? ($data['action'] ?? ''));
		$monitorId = intval($context['monitor_id'] ?? 0);
		$notifyId = intval($context['notify_id'] ?? ($notify['id'] ?? 0));
		if ($action !== '' && $monitorId > 0) {
			$parsed = [
				'action' => $action,
				'monitor_id' => $monitorId,
				'notify_id' => $notifyId,
			];
			if (!\AlertActions::isValidAction($action)) {
				$parsed = null;
			}
		} else {
			$parsed = null;
		}

		$userName = strval($data['user_name'] ?? '');
		if ($userName === '' && isset($data['user']['username'])) {
			$userName = strval($data['user']['username']);
		}
		$author = $userName !== '' ? '@'.$userName : 'mattermost';

		$postId = strval($data['post_id'] ?? '');
		$channelId = strval($data['channel_id'] ?? '');

		$currentText = '';
		$mm = null;
		if ($postId !== '') {
			try {
				$mm = new \Mattermost(
					strval($notify['json_data']['mattermost_url'] ?? ''),
					strval($notify['json_data']['mattermost_token'] ?? '')
				);
				$postPreview = \MattermostPosts::getPost($mm, $postId);
				if (is_array($postPreview) && isset($postPreview['props']['attachments'][0]['text'])) {
					$currentText = strval($postPreview['props']['attachments'][0]['text']);
				}
			} catch (\Exception $e) {
			}
		}
		$result = $this->applyAction($notify, $parsed, $author, \AlertActions::CHANNEL_MATTERMOST, $channelId, $postId, $currentText);

		if (!empty($result['ok']) && !empty($result['new_text']) && $postId !== '' && $mm) {
			try {
				$post = \MattermostPosts::getPost($mm, $postId);
				$props = is_array($post) && isset($post['props']) && is_array($post['props']) ? $post['props'] : [];
				if (!isset($props['attachments'][0]) || !is_array($props['attachments'][0])) {
					$props['attachments'] = [[
						'color' => '#FF0000',
						'author_name' => 'Monitoring Alert Manager',
						'title' => 'Alert',
						'text' => $result['new_text'],
						'ts' => time(),
					]];
				} else {
					$props['attachments'][0]['text'] = $result['new_text'];
					$token = \AlertActions::ensureToken($notify['json_data']);
					$baseUrl = strval($notify['json_data']['alert_action_base_url'] ?? '');
					$webhookUrl = \AlertActions::webhookUrl(
						\AlertActions::CHANNEL_MATTERMOST,
						$token,
						$baseUrl !== '' ? $baseUrl : null
					);
					$props['attachments'][0]['actions'] = \AlertActions::mattermostActions(
						intval($result['monitor_id']),
						intval($notify['id']),
						$webhookUrl
					);
				}
				\MattermostPosts::updatePost($mm, $postId, strval(is_array($post) ? ($post['message'] ?? '') : ''), $props);
			} catch (\Exception $e) {
			}
		}

		$ephemeral = [
			'ephemeral_text' => strval($result['toast'] ?? 'OK'),
		];
		http_response_code(200);
		header('Content-Type: application/json;charset=utf-8');
		echo json_encode($ephemeral, JSON_UNESCAPED_UNICODE);
		exit(0);
	}

	private function handleMax(array $notify): void {
		$raw = file_get_contents('php://input');
		$data = json_decode($raw ?: '', true);
		if (!is_array($data)) {
			$this->jsonError('Bad request', 400);
			return;
		}
		// Single update or wrapper with updates[]
		$updates = [];
		if (isset($data['update_type'])) {
			$updates = [$data];
		} elseif (isset($data['updates']) && is_array($data['updates'])) {
			$updates = $data['updates'];
		}
		$handled = false;
		foreach ($updates as $u) {
			if (!is_array($u)) {
				continue;
			}
			if (($u['update_type'] ?? '') !== 'message_callback') {
				continue;
			}
			$cb = isset($u['callback']) && is_array($u['callback']) ? $u['callback'] : [];
			$payload = strval($cb['payload'] ?? '');
			$parsed = \AlertActions::parseCallbackData($payload);
			$user = isset($cb['user']) && is_array($cb['user']) ? $cb['user'] : [];
			$author = '';
			if (!empty($user['username'])) {
				$author = '@'.strval($user['username']);
			} elseif (!empty($user['name'])) {
				$author = strval($user['name']);
			} elseif (!empty($user['first_name'])) {
				$author = strval($user['first_name']);
			} else {
				$author = 'user'.strval($user['user_id'] ?? '');
			}
			$callbackId = strval($cb['callback_id'] ?? '');
			$msg = isset($u['message']) && is_array($u['message']) ? $u['message'] : [];
			$body = isset($msg['body']) && is_array($msg['body']) ? $msg['body'] : [];
			$messageId = '';
			if (!empty($body['mid'])) {
				$messageId = strval($body['mid']);
			} elseif (!empty($msg['body']['mid'])) {
				$messageId = strval($msg['body']['mid']);
			}
			$recipient = isset($msg['recipient']) && is_array($msg['recipient']) ? $msg['recipient'] : [];
			$chatId = strval($recipient['chat_id'] ?? ($u['chat_id'] ?? ''));

			$targetNotify = \AlertActions::resolveNotifyForCallback($notify, $parsed);
			if ($targetNotify === null) {
				$token = strval($notify['json_data']['max_token'] ?? '');
				$callbackId = strval($cb['callback_id'] ?? '');
				if ($token !== '' && $callbackId !== '') {
					\MaxMessenger::answerCallback($token, $callbackId, null, 'Notify mismatch');
				}
				$handled = true;
				continue;
			}
			$currentText = strval($body['text'] ?? '');
			$result = $this->applyAction($targetNotify, $parsed, $author, \AlertActions::CHANNEL_MAX, $chatId, $messageId, $currentText);
			$token = strval($targetNotify['json_data']['max_token'] ?? '');
			if ($token !== '' && $callbackId !== '') {
				$messageBody = null;
				if (!empty($result['ok']) && !empty($result['new_text'])) {
					$messageBody = [
						'text' => mb_substr($result['new_text'], 0, 4000),
						'format' => 'html',
						'attachments' => [
							\AlertActions::maxInlineKeyboard(intval($result['monitor_id']), intval($targetNotify['id'])),
						],
					];
				}
				\MaxMessenger::answerCallback($token, $callbackId, $messageBody, strval($result['toast'] ?? 'OK'));
			}
			$handled = true;
		}
		if (!$handled) {
			http_response_code(200);
			echo 'ok';
			exit(0);
		}
		http_response_code(200);
		echo 'ok';
		exit(0);
	}

	/**
	 * @param array{action: string, monitor_id: int, notify_id: int}|null $parsed
	 * @return array{ok: bool, toast: string, new_text: string, monitor_id: int}
	 */
	private function applyAction(
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
		if ($messageId !== '') {
			\AlertActions::saveMessage($monitorId, $notifyId, $channel, $chatId, $messageId, $baseText);
		}

		$toast = \AlertActions::actionButtonText($parsed['action']);
		if ($parsed['action'] === \AlertActions::ACTION_MUTE) {
			$toast = 'Mute';
		}
		return [
			'ok' => true,
			'toast' => $toast,
			'new_text' => $baseText,
			'monitor_id' => $monitorId,
		];
	}

	private function jsonError(string $error, int $http_code): void {
		http_response_code($http_code);
		header('Content-Type: application/json;charset=utf-8');
		header('Cache-Control: no-store');
		echo json_encode(['status' => 1, 'error' => $error], JSON_UNESCAPED_UNICODE);
		exit(0);
	}
}

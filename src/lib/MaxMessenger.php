<?php

class MaxMessenger {

	const API_BASE = 'https://platform-api.max.ru/messages';

	const UPDATES_URL = 'https://platform-api.max.ru/updates';

	const CHATS_URL = 'https://platform-api.max.ru/chats';

	/**
	 * @param string $accessToken заголовок Authorization (токен из кабинета бота)
	 * @param int|string $receiverId user_id > 0 или chat_id < 0 (целое, допускается строка для больших id)
	 * @param string $text тело сообщения (до 4000 символов на стороне API)
	 * @param string|null $format "html"|"markdown"|null
	 */
	public static function send(string $accessToken, $receiverId, string $text, ?string $format = 'html'): bool {
		$raw = trim((string) $receiverId);
		if ($raw === '' || !preg_match('/^-?\d+$/', $raw)) {
			return false;
		}
		if ($raw === '0' || $raw === '-0' || $raw === '+0') {
			return false;
		}
		$query = [];
		if ($raw[0] === '-') {
			$query['chat_id'] = $raw;
		} else {
			$query['user_id'] = $raw;
		}
		$query['disable_link_preview'] = false;
		$url = self::API_BASE . '?' . http_build_query($query);
		$body = ['text' => $text];
		if ($format !== null && $format !== '') {
			$body['format'] = $format;
		}
		$curl = new \AKEB\CurlGet($url, [], [], [
			'Authorization: ' . $accessToken,
			'Content-Type: application/json;charset=utf-8',
		]);
		$curl->setBody(json_encode($body, JSON_UNESCAPED_UNICODE));
		$curl->exec();
		$code = $curl->responseCode ?? 0;
		return $code >= 200 && $code < 300;
	}

	/**
	 * GET /updates — последние события бота.
	 *
	 * @return array{ok: bool, http_code: int, data: array|null, raw_body: string}
	 */
	public static function fetchUpdates(string $accessToken): array {
		$curl = new \AKEB\CurlGet(self::UPDATES_URL, [
			'limit' => 100,
			'timeout' => 5,
		], [], [
			'Authorization: ' . $accessToken,
		]);
		$curl->exec();
		$code = intval($curl->responseCode ?? 0);
		$body = $curl->responseBody;
		if (is_array($body)) {
			return ['ok' => $code >= 200 && $code < 300, 'http_code' => $code, 'data' => $body, 'raw_body' => ''];
		}
		$decoded = is_string($body) ? json_decode($body, true) : null;
		return [
			'ok' => $code >= 200 && $code < 300,
			'http_code' => $code,
			'data' => is_array($decoded) ? $decoded : null,
			'raw_body' => is_string($body) ? $body : '',
		];
	}

	/**
	 * GET /chats — групповые и прочие чаты, где состоит бот.
	 *
	 * @return array{ok: bool, http_code: int, data: array|null, raw_body: string}
	 */
	public static function fetchChats(string $accessToken): array {
		$curl = new \AKEB\CurlGet(self::CHATS_URL, [], [], [
			'Authorization: ' . $accessToken,
		]);
		$curl->exec();
		$code = intval($curl->responseCode ?? 0);
		$body = $curl->responseBody;
		if (is_array($body)) {
			return ['ok' => $code >= 200 && $code < 300, 'http_code' => $code, 'data' => $body, 'raw_body' => ''];
		}
		$decoded = is_string($body) ? json_decode($body, true) : null;
		return [
			'ok' => $code >= 200 && $code < 300,
			'http_code' => $code,
			'data' => is_array($decoded) ? $decoded : null,
			'raw_body' => is_string($body) ? $body : '',
		];
	}

	/**
	 * Текст из тела сообщения (пустая строка — тела нет или текст пустой).
	 */
	private static function extractTextFromMessageBody($body): string {
		if (!is_array($body)) {
			return '';
		}
		if (isset($body['text']) && (string) $body['text'] !== '') {
			return (string) $body['text'];
		}
		return '';
	}

	/**
	 * Последнее осмысленное обновление с чатом/отправителем (с конца списка updates).
	 * Учитывает message_created и message_callback (в группах часто приходит callback).
	 *
	 * @return array{chat_id: string, user_id: string, message: string, message_empty: bool}|null
	 */
	public static function parseLastMessageFromUpdates(array $decoded): ?array {
		$updates = $decoded['updates'] ?? null;
		if (!is_array($updates) || !$updates) {
			return null;
		}
		for ($i = count($updates) - 1; $i >= 0; $i--) {
			$u = $updates[$i];
			if (!is_array($u) || empty($u['message']) || !is_array($u['message'])) {
				continue;
			}
			$msg = $u['message'];
			$cb = isset($u['callback']) && is_array($u['callback']) ? $u['callback'] : [];
			$senderIsBot = !empty($msg['sender']['is_bot']);
			if ($senderIsBot && empty($cb['user'])) {
				continue;
			}

			$recipient = isset($msg['recipient']) && is_array($msg['recipient']) ? $msg['recipient'] : [];
			$chatId = '';
			if (isset($recipient['chat_id'])) {
				$chatId = (string) $recipient['chat_id'];
			}
			if ($chatId === '' && isset($u['chat_id'])) {
				$chatId = (string) $u['chat_id'];
			}
			if ($chatId === '' && isset($msg['chat_id'])) {
				$chatId = (string) $msg['chat_id'];
			}

			$userId = '';
			if (isset($cb['user']['user_id'])) {
				$userId = (string) $cb['user']['user_id'];
			}
			if ($userId === '' && !empty($msg['sender']['user_id'])) {
				$userId = (string) $msg['sender']['user_id'];
			}
			if ($userId === '' && isset($recipient['user_id'])) {
				$userId = (string) $recipient['user_id'];
			}

			$body = isset($msg['body']) && is_array($msg['body']) ? $msg['body'] : null;
			$text = self::extractTextFromMessageBody($body);
			$hasAttachments = is_array($body) && !empty($body['attachments']);
			$messageEmpty = ($text === '');
			if ($messageEmpty && !$hasAttachments && $chatId === '' && $userId === '') {
				continue;
			}

			return [
				'chat_id' => $chatId,
				'user_id' => $userId,
				'message' => $text,
				'message_empty' => $messageEmpty,
			];
		}
		return null;
	}
}

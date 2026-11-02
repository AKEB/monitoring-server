<?php

class MaxMessenger {

	private const API_HOST = 'https://platform-api2.max.ru';

	private static ?string $caBundlePath = null;

	private static function apiUrl(string $path): string {
		return self::API_HOST . $path;
	}

	private static function caBundlePath(): string {
		if (self::$caBundlePath !== null) {
			return self::$caBundlePath;
		}
		$candidates = [
			'/etc/ssl/certs/ca-certificates.crt',
			'/etc/pki/tls/certs/ca-bundle.crt',
			dirname(__DIR__) . '/certs/russian_trusted_ca.pem',
			dirname(__DIR__) . '/vendor/akeb/curlget/src/cacert.pem',
		];
		foreach ($candidates as $path) {
			if (is_readable($path)) {
				self::$caBundlePath = $path;
				return $path;
			}
		}
		self::$caBundlePath = dirname(__DIR__) . '/vendor/akeb/curlget/src/cacert.pem';
		return self::$caBundlePath;
	}

	private static function configureCurl(\AKEB\CurlGet $curl): void {
		$curl->setSslCert(self::caBundlePath());
	}

	/**
	 * @return array{ok: bool, http_code: int, data: array|null, raw_body: string}
	 */
	private static function request(
		string $method,
		string $path,
		array $query = [],
		?string $accessToken = null,
		?array $body = null
	): array {
		$headers = [];
		if ($accessToken !== null && $accessToken !== '') {
			$headers[] = 'Authorization: ' . $accessToken;
		}
		if ($body !== null) {
			$headers[] = 'Content-Type: application/json;charset=utf-8';
		}

		$curl = new \AKEB\CurlGet(self::apiUrl($path), $query, [], $headers);
		self::configureCurl($curl);
		if ($method !== 'GET') {
			$curl->setMethod($method);
		}
		if ($body !== null) {
			$curl->setBody(json_encode($body, JSON_UNESCAPED_UNICODE));
		}
		$curl->exec();

		$code = intval($curl->responseCode ?? 0);
		$responseBody = $curl->responseBody;
		if (is_array($responseBody)) {
			return ['ok' => $code >= 200 && $code < 300, 'http_code' => $code, 'data' => $responseBody, 'raw_body' => ''];
		}
		$rawBody = is_string($responseBody) ? $responseBody : '';
		$decoded = $rawBody !== '' ? json_decode($rawBody, true) : null;
		return [
			'ok' => $code >= 200 && $code < 300,
			'http_code' => $code,
			'data' => is_array($decoded) ? $decoded : null,
			'raw_body' => $rawBody,
		];
	}

	/**
	 * @param string $accessToken заголовок Authorization (токен из кабинета бота)
	 * @param int|string $receiverId user_id > 0 или chat_id < 0 (целое, допускается строка для больших id)
	 * @param string $text тело сообщения (до 4000 символов на стороне API)
	 * @param string|null $format "html"|"markdown"|null
	 * @param array|null $attachments вложения (например inline_keyboard)
	 * @return array{ok: bool, message_id: string, data: array|null}
	 */
	public static function send(
		string $accessToken,
		$receiverId,
		string $text,
		?string $format = 'html',
		?array $attachments = null
	): array {
		$raw = trim((string) $receiverId);
		if ($raw === '' || !preg_match('/^-?\d+$/', $raw)) {
			return ['ok' => false, 'message_id' => '', 'data' => null];
		}
		if ($raw === '0' || $raw === '-0' || $raw === '+0') {
			return ['ok' => false, 'message_id' => '', 'data' => null];
		}
		$query = ['disable_link_preview' => false];
		if ($raw[0] === '-') {
			$query['chat_id'] = $raw;
		} else {
			$query['user_id'] = $raw;
		}
		$body = ['text' => $text];
		if ($format !== null && $format !== '') {
			$body['format'] = $format;
		}
		if ($attachments !== null) {
			$body['attachments'] = $attachments;
		}
		$res = self::request('POST', '/messages', $query, $accessToken, $body);
		$messageId = '';
		if (is_array($res['data'])) {
			$messageId = self::extractMessageId($res['data']);
		}
		return [
			'ok' => !empty($res['ok']),
			'message_id' => $messageId,
			'data' => $res['data'],
		];
	}

	/**
	 * PUT /messages — редактирование сообщения.
	 *
	 * @param array|null $attachments null = не трогать вложения; [] = удалить; иначе заменить
	 */
	public static function edit(
		string $accessToken,
		string $messageId,
		string $text,
		?string $format = 'html',
		?array $attachments = null,
		bool $notify = false
	): bool {
		$messageId = trim($messageId);
		if ($messageId === '') {
			return false;
		}
		$body = [
			'text' => $text,
			'notify' => $notify,
		];
		if ($format !== null && $format !== '') {
			$body['format'] = $format;
		}
		if ($attachments !== null) {
			$body['attachments'] = $attachments;
		}
		return self::request('PUT', '/messages', ['message_id' => $messageId], $accessToken, $body)['ok'];
	}

	/**
	 * POST /answers — ответ на callback-кнопку (может обновить сообщение).
	 *
	 * @param array|null $messageBody NewMessageBody или null
	 */
	public static function answerCallback(
		string $accessToken,
		string $callbackId,
		?array $messageBody = null,
		?string $notification = null
	): bool {
		$callbackId = trim($callbackId);
		if ($callbackId === '') {
			return false;
		}
		$body = [];
		if ($messageBody !== null) {
			$body['message'] = $messageBody;
		}
		if ($notification !== null && $notification !== '') {
			$body['notification'] = $notification;
		}
		if (!$body) {
			$body['notification'] = 'OK';
		}
		return self::request('POST', '/answers', ['callback_id' => $callbackId], $accessToken, $body)['ok'];
	}

	/**
	 * Подписка на webhook (если API поддерживает).
	 */
	public static function subscribeWebhook(string $accessToken, string $url): bool {
		$url = trim($url);
		if ($url === '') {
			return false;
		}
		$res = self::request('POST', '/subscriptions', [], $accessToken, [
			'url' => $url,
			'update_types' => ['message_callback'],
		]);
		return !empty($res['ok']);
	}

	private static function extractMessageId(array $data): string {
		if (isset($data['message']['body']['mid'])) {
			return strval($data['message']['body']['mid']);
		}
		if (isset($data['message']['body']['seq'])) {
			return strval($data['message']['body']['seq']);
		}
		if (isset($data['message']['message_id'])) {
			return strval($data['message']['message_id']);
		}
		if (isset($data['message_id'])) {
			return strval($data['message_id']);
		}
		if (isset($data['body']['mid'])) {
			return strval($data['body']['mid']);
		}
		if (isset($data['mid'])) {
			return strval($data['mid']);
		}
		return '';
	}

	/**
	 * GET /updates — последние события бота.
	 *
	 * @return array{ok: bool, http_code: int, data: array|null, raw_body: string}
	 */
	public static function fetchUpdates(string $accessToken): array {
		return self::request('GET', '/updates', [
			'limit' => 100,
			'timeout' => 5,
		], $accessToken);
	}

	/**
	 * Уникальные чаты из истории /updates (GET /chats с июня 2026 не поддерживается).
	 *
	 * @return array<int, array{chat_id: string, type: string, title: string}>
	 */
	public static function parseChatsFromUpdates(array $decoded): array {
		$updates = $decoded['updates'] ?? null;
		if (!is_array($updates) || !$updates) {
			return [];
		}

		$chats = [];
		foreach ($updates as $u) {
			if (!is_array($u)) {
				continue;
			}

			$chatId = '';
			$type = '';
			$title = '';

			if (isset($u['chat_id']) && (string) $u['chat_id'] !== '' && (string) $u['chat_id'] !== '0') {
				$chatId = (string) $u['chat_id'];
			}
			if (isset($u['is_channel'])) {
				$type = !empty($u['is_channel']) ? 'channel' : 'chat';
			}
			if (($u['update_type'] ?? '') === 'chat_title_changed' && isset($u['title'])) {
				$title = (string) $u['title'];
			}

			if (isset($u['message']) && is_array($u['message'])) {
				$msg = $u['message'];
				$recipient = isset($msg['recipient']) && is_array($msg['recipient']) ? $msg['recipient'] : [];
				if ($chatId === '' && isset($recipient['chat_id'])) {
					$chatId = (string) $recipient['chat_id'];
				}
				if ($chatId === '' && isset($msg['chat_id'])) {
					$chatId = (string) $msg['chat_id'];
				}
				if ($type === '' && isset($recipient['chat_type'])) {
					$type = (string) $recipient['chat_type'];
				}
			}

			if ($chatId === '' || $chatId === '0') {
				continue;
			}

			if (!isset($chats[$chatId])) {
				$chats[$chatId] = ['chat_id' => $chatId, 'type' => $type, 'title' => $title];
				continue;
			}
			if ($type !== '') {
				$chats[$chatId]['type'] = $type;
			}
			if ($title !== '') {
				$chats[$chatId]['title'] = $title;
			}
		}

		return array_values($chats);
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

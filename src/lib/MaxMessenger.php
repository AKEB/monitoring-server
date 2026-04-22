<?php

class MaxMessenger {

	const API_BASE = 'https://platform-api.max.ru/messages';

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
}

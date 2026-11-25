<?php

class NotifyDiscord {

	public const COLOR_OK = 0x2ecc71;
	public const COLOR_FAIL = 0xe74c3c;

	static public function buildPayload(string $text, string $title, bool $success): array {
		$titlePlain = \NotifyWebhook::stripHtml($title);
		$desc = \NotifyWebhook::stripHtml($text);
		if ($titlePlain === '') {
			$titlePlain = $success ? 'Resolved' : 'Alert';
		}
		return [
			'embeds' => [[
				'title' => mb_substr($titlePlain, 0, 256),
				'description' => mb_substr($desc !== '' ? $desc : $titlePlain, 0, 4096),
				'color' => $success ? self::COLOR_OK : self::COLOR_FAIL,
			]],
		];
	}

	static public function buildTestPayload(string $notifyTitle = ''): array {
		$title = $notifyTitle !== '' ? $notifyTitle : 'Discord';
		return static::buildPayload(
			'Discord test notification from monitoring '.date('Y-m-d H:i:s'),
			'Test: '.$title,
			false
		);
	}

	/**
	 * @return bool true if HTTP 2xx
	 */
	static public function send(string $webhookUrl, array $payload): bool {
		$webhookUrl = trim($webhookUrl);
		if ($webhookUrl === '' || !preg_match('#^https?://#i', $webhookUrl)) {
			return false;
		}
		$headers = [
			'Content-Type: application/json;charset=utf-8',
			'Accept: application/json',
		];
		$curl = new \AKEB\CurlGet($webhookUrl, [], [], $headers);
		$curl->setMethod('POST');
		$curl->setBody(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
		$curl->exec();
		$code = intval($curl->responseCode ?? 0);
		return $code >= 200 && $code < 300;
	}
}

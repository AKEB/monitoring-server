<?php

class NotifySlack {

	static public function buildPayload(string $text, string $title, bool $success): array {
		$titlePlain = \NotifyWebhook::stripHtml($title);
		$body = \NotifyWebhook::stripHtml($text);
		$status = $success ? 'resolved' : 'firing';
		$lines = [];
		if ($titlePlain !== '') {
			$lines[] = '*'.$titlePlain.'*';
		}
		$lines[] = '_'.$status.'_';
		if ($body !== '') {
			$lines[] = $body;
		}
		return [
			'text' => implode("\n", $lines),
		];
	}

	static public function buildTestPayload(string $notifyTitle = ''): array {
		$title = $notifyTitle !== '' ? $notifyTitle : 'Slack';
		return static::buildPayload(
			'Slack test notification from monitoring '.date('Y-m-d H:i:s'),
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

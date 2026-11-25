<?php

class NotifyNtfy {

	static public function buildPublishUrl(string $baseUrl, string $topic): string {
		$baseUrl = rtrim(trim($baseUrl), '/');
		$topic = trim($topic);
		$topic = ltrim($topic, '/');
		if ($baseUrl === '' || $topic === '') {
			return '';
		}
		return $baseUrl.'/'.$topic;
	}

	/**
	 * @return list<string>
	 */
	static public function buildHeaders(string $title, string $token = '', int $priority = 3): array {
		$headers = [
			'Content-Type: text/plain; charset=utf-8',
		];
		$title = \NotifyWebhook::stripHtml($title);
		if ($title !== '') {
			$headers[] = 'Title: '.mb_substr($title, 0, 250);
		}
		$priority = max(1, min(5, $priority));
		$headers[] = 'Priority: '.$priority;
		$token = trim($token);
		if ($token !== '') {
			$headers[] = 'Authorization: Bearer '.$token;
		}
		return $headers;
	}

	static public function buildMessage(string $text, string $title = ''): string {
		$plain = \NotifyWebhook::stripHtml($text);
		$titlePlain = \NotifyWebhook::stripHtml($title);
		if ($titlePlain !== '' && $plain !== '' && strpos($plain, $titlePlain) !== 0) {
			return $titlePlain."\n".$plain;
		}
		if ($plain !== '') {
			return $plain;
		}
		return $titlePlain;
	}

	/**
	 * @return bool true if HTTP 2xx
	 */
	static public function send(
		string $baseUrl,
		string $topic,
		string $message,
		string $title = '',
		string $token = '',
		int $priority = 3
	): bool {
		$url = static::buildPublishUrl($baseUrl, $topic);
		if ($url === '' || trim($message) === '') {
			return false;
		}
		$headers = static::buildHeaders($title, $token, $priority);
		$curl = new \AKEB\CurlGet($url, [], [], $headers);
		$curl->setMethod('POST');
		$curl->setBody($message);
		$curl->exec();
		$code = intval($curl->responseCode ?? 0);
		return $code >= 200 && $code < 300;
	}
}

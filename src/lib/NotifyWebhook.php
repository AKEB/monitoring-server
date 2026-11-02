<?php

class NotifyWebhook {

	private const ZERO_TIME = '0001-01-01T00:00:00Z';

	static public function buildPayload(
		int $monitor_id,
		string $text,
		string $title,
		bool $success,
		string $notifyTitle = ''
	): array {
		$status = $success ? 'resolved' : 'firing';
		$now = time();
		$baseUrl = \ExternalMonitors::baseUrl();

		$monitor = [];
		$alertName = 'Monitor-'.$monitor_id;
		$instance = '';
		$startsAtTs = $now;

		if ($monitor_id > 0) {
			$monitor = \Monitors::get(['id' => $monitor_id]) ?: [];
			$path = \Monitors::get_monitor_title_with_folders($monitor_id);
			if ($path !== '') {
				$alertName = $path;
			}
			$alertName = mb_substr($alertName, 0, 255);

			$url = trim(strval($monitor['url'] ?? ''));
			$host = trim(strval($monitor['host'] ?? ''));
			$port = intval($monitor['port'] ?? 0);
			if ($url !== '') {
				$instance = $url;
			} elseif ($host !== '') {
				$instance = $port > 0 ? $host.':'.$port : $host;
			}

			$alertStart = intval($monitor['alert_start_time'] ?? 0);
			if ($alertStart > 0) {
				$startsAtTs = $alertStart;
			}
		} else {
			$alertName = mb_substr($alertName, 0, 255);
		}

		$fingerprint = substr(sha1('monitor:'.$monitor_id), 0, 16);
		$labels = [
			'alertname' => $alertName,
			'monitor_id' => strval($monitor_id),
		];
		if ($instance !== '') {
			$labels['instance'] = mb_substr($instance, 0, 255);
		}

		$annotations = [
			'summary' => static::stripHtml($title),
			'description' => static::stripHtml($text),
		];

		$alert = [
			'status' => $status,
			'labels' => $labels,
			'annotations' => $annotations,
			'startsAt' => static::formatRfc3339($startsAtTs),
			'endsAt' => $success ? static::formatRfc3339($now) : self::ZERO_TIME,
			'generatorURL' => $baseUrl.'/monitor/'.$monitor_id.'/',
			'fingerprint' => $fingerprint,
		];

		return [
			'version' => '4',
			'groupKey' => '{}/{monitor_id="'.$monitor_id.'"}',
			'truncatedAlerts' => 0,
			'status' => $status,
			'receiver' => $notifyTitle !== '' ? $notifyTitle : 'webhook',
			'groupLabels' => $labels,
			'commonLabels' => $labels,
			'commonAnnotations' => $annotations,
			'externalURL' => $baseUrl,
			'alerts' => [$alert],
		];
	}

	static public function buildTestPayload(string $notifyTitle = ''): array {
		$now = time();
		$baseUrl = \ExternalMonitors::baseUrl();
		$labels = [
			'alertname' => 'TestWebhook',
			'monitor_id' => '0',
		];
		$annotations = [
			'summary' => 'Test Message '.date('Y-m-d H:i:s', $now),
			'description' => 'WebHook test notification from monitoring',
		];
		$alert = [
			'status' => 'firing',
			'labels' => $labels,
			'annotations' => $annotations,
			'startsAt' => static::formatRfc3339($now),
			'endsAt' => self::ZERO_TIME,
			'generatorURL' => $baseUrl.'/',
			'fingerprint' => 'test',
		];
		return [
			'version' => '4',
			'groupKey' => '{}/{alertname="TestWebhook"}',
			'truncatedAlerts' => 0,
			'status' => 'firing',
			'receiver' => $notifyTitle !== '' ? $notifyTitle : 'webhook',
			'groupLabels' => $labels,
			'commonLabels' => $labels,
			'commonAnnotations' => $annotations,
			'externalURL' => $baseUrl,
			'alerts' => [$alert],
		];
	}

	/**
	 * @return bool true if HTTP 2xx
	 */
	static public function send(string $url, string $username, string $password, array $payload): bool {
		$url = trim($url);
		if ($url === '') {
			return false;
		}

		$headers = [
			'Content-Type: application/json;charset=utf-8',
			'Accept: application/json',
		];
		$password = strval($password);
		if ($password !== '') {
			$headers[] = 'Authorization: Basic '.base64_encode($username.':'.$password);
		}

		$curl = new \AKEB\CurlGet($url, [], [], $headers);
		$curl->setMethod('POST');
		$curl->setBody(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
		$curl->exec();
		$code = intval($curl->responseCode ?? 0);
		return $code >= 200 && $code < 300;
	}

	static public function formatRfc3339(int $ts): string {
		return gmdate('Y-m-d\TH:i:s\Z', $ts);
	}

	static public function stripHtml(string $text): string {
		$text = str_replace(
			['<br/>', '<br />', '<br>', '</p>', '</div>'],
			["\n", "\n", "\n", "\n", "\n"],
			$text
		);
		$text = strip_tags($text);
		$text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
		$text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
		return trim($text);
	}
}

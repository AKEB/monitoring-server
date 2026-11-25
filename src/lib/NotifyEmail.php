<?php

class NotifyEmail {

	/**
	 * @return list<string>
	 */
	static public function normalizeRecipients(string $receiver): array {
		$parts = preg_split('/[,;]+/', $receiver) ?: [];
		$out = [];
		foreach ($parts as $part) {
			$email = trim($part);
			if ($email !== '') {
				$out[] = $email;
			}
		}
		return $out;
	}

	static public function buildSubject(string $title, string $notifyTitle = ''): string {
		$title = \NotifyWebhook::stripHtml($title);
		if ($title === '') {
			$title = 'Monitoring alert';
		}
		if ($notifyTitle !== '') {
			return mb_substr($title.' — '.$notifyTitle, 0, 200);
		}
		return mb_substr($title, 0, 200);
	}

	static public function buildHtmlBody(
		string $text,
		string $title,
		string $notifyTitle = '',
		bool $omitAlertFooter = false
	): string {
		$parts = [];
		$title = trim($title);
		if ($title !== '') {
			$parts[] = '<p><strong>'.htmlspecialchars($title, ENT_QUOTES | ENT_HTML5, 'UTF-8').'</strong></p>';
		}
		$body = trim($text);
		if ($body !== '') {
			if (strip_tags($body) === $body) {
				$body = nl2br(htmlspecialchars($body, ENT_QUOTES | ENT_HTML5, 'UTF-8'), false);
			}
			$parts[] = '<div>'.$body.'</div>';
		}
		if (!$omitAlertFooter && $notifyTitle !== '') {
			$parts[] = '<p><em>Alert: '.htmlspecialchars($notifyTitle, ENT_QUOTES | ENT_HTML5, 'UTF-8').'</em></p>';
		}
		return implode("\n", $parts);
	}

	/**
	 * @return bool true on success
	 */
	static public function send(string $to, string $subject, string $html): bool {
		$recipients = static::normalizeRecipients($to);
		if (!$recipients) {
			return false;
		}
		$joined = implode(', ', $recipients);
		$result = \Mail::send($joined, $subject, $html, true);
		return $result === true;
	}
}

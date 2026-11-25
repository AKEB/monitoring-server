<?php

error_reporting(E_ALL);

class NotifyChannelsWave2Test extends \PHPUnit\Framework\TestCase {

	function test_email_normalize_recipients_and_subject_body() {
		$this->assertSame(
			['ops@example.com', 'oncall@example.com'],
			\NotifyEmail::normalizeRecipients(' ops@example.com, oncall@example.com ,, ')
		);
		$this->assertSame([], \NotifyEmail::normalizeRecipients(''));

		$subject = \NotifyEmail::buildSubject('Down: api', 'Ops mail');
		$this->assertStringContainsString('Down: api', $subject);

		$html = \NotifyEmail::buildHtmlBody(
			'Body <b>bold</b><br/>line2',
			'Down: api',
			'Ops mail',
			false
		);
		$this->assertStringContainsString('Down: api', $html);
		$this->assertStringContainsString('Body', $html);
		$this->assertStringContainsString('Alert: Ops mail', $html);

		$htmlOmit = \NotifyEmail::buildHtmlBody('Body', 'T', 'Ops mail', true);
		$this->assertStringNotContainsString('Alert: Ops mail', $htmlOmit);

		$this->assertFalse(\NotifyEmail::send('', 'subj', '<p>x</p>'));
	}

	function test_ntfy_url_headers_and_message() {
		$url = \NotifyNtfy::buildPublishUrl('https://ntfy.example.com/', 'alerts');
		$this->assertSame('https://ntfy.example.com/alerts', $url);

		$headers = \NotifyNtfy::buildHeaders('Down', 'tok-secret', 4);
		$this->assertContains('Title: Down', $headers);
		$this->assertContains('Priority: 4', $headers);
		$this->assertContains('Authorization: Bearer tok-secret', $headers);

		$plain = \NotifyNtfy::buildMessage('<b>Hi</b><br/>there', 'Title');
		$this->assertStringNotContainsString('<b>', $plain);
		$this->assertStringContainsString('Hi', $plain);

		$this->assertFalse(\NotifyNtfy::send('', 'topic', 'msg', 'title'));
		$this->assertFalse(\NotifyNtfy::send('https://ntfy.example.com', '', 'msg', 'title'));
	}

	function test_discord_payload_embed_colors() {
		$fail = \NotifyDiscord::buildPayload('Line1<br/>Line2', 'API down', false);
		$this->assertArrayHasKey('embeds', $fail);
		$this->assertSame('API down', $fail['embeds'][0]['title']);
		$this->assertStringContainsString('Line1', $fail['embeds'][0]['description']);
		$this->assertSame(\NotifyDiscord::COLOR_FAIL, $fail['embeds'][0]['color']);

		$ok = \NotifyDiscord::buildPayload('Recovered', 'API up', true);
		$this->assertSame(\NotifyDiscord::COLOR_OK, $ok['embeds'][0]['color']);

		$this->assertFalse(\NotifyDiscord::send('', $fail));
		$this->assertFalse(\NotifyDiscord::send('not-a-url', $fail));
	}

	function test_slack_payload_text() {
		$fail = \NotifySlack::buildPayload('Body <i>x</i>', 'Site down', false);
		$this->assertArrayHasKey('text', $fail);
		$this->assertStringContainsString('Site down', $fail['text']);
		$this->assertStringContainsString('Body', $fail['text']);
		$this->assertStringNotContainsString('<i>', $fail['text']);

		$ok = \NotifySlack::buildPayload('ok', 'Site up', true);
		$this->assertStringContainsString('Site up', $ok['text']);

		$this->assertFalse(\NotifySlack::send('', $fail));
		$this->assertFalse(\NotifySlack::send('ftp://bad', $fail));
	}
}

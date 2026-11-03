<?php

error_reporting(E_ALL);

class AlertActionsTest extends PHPUnit\Framework\TestCase {

	function test_actions_hash_keys_and_texts() {
		$hash = \AlertActions::actionsHash();
		$this->assertSame(['resolve', 'take', 'mute', 'unmute', 'call', 'ok'], array_keys($hash));
		$this->assertSame('Решить', $hash['resolve']['button']);
		$this->assertSame('решил', $hash['resolve']['log']);
		$this->assertSame('Взять', $hash['take']['button']);
		$this->assertSame('Mute', $hash['mute']['button']);
		$this->assertSame('Unmute', $hash['unmute']['button']);
		$this->assertSame('Позвонить', $hash['call']['button']);
		$this->assertSame('Всё ок', $hash['ok']['button']);
	}

	function test_is_valid_action() {
		foreach (array_keys(\AlertActions::actionsHash()) as $action) {
			$this->assertTrue(\AlertActions::isValidAction($action));
		}
		$this->assertFalse(\AlertActions::isValidAction('foo'));
	}

	function test_action_texts_fallback() {
		$this->assertSame('решил', \AlertActions::actionLogText('resolve'));
		$this->assertSame('Решить', \AlertActions::actionButtonText('resolve'));
		$this->assertSame('custom', \AlertActions::actionLogText('custom'));
		$this->assertSame('custom', \AlertActions::actionButtonText('custom'));
	}

	function test_format_author() {
		$this->assertSame('@alice', \AlertActions::formatAuthor('alice'));
		$this->assertSame('@alice', \AlertActions::formatAuthor('@alice'));
		$this->assertSame('@alice', \AlertActions::formatAuthor('  alice  '));
		$this->assertSame('@unknown', \AlertActions::formatAuthor(''));
	}

	function test_format_log_line() {
		$line = \AlertActions::formatLogLine('bob', 'take', 1700000000);
		$this->assertStringContainsString('@bob', $line);
		$this->assertStringContainsString('взял', $line);
		$this->assertStringContainsString(date('Y-m-d H:i', 1700000000), $line);
		$this->assertStringStartsWith('🔵 [', $line);
	}

	function test_generate_and_ensure_token() {
		$token = \AlertActions::generateToken();
		$this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $token);

		$json = [];
		$created = \AlertActions::ensureToken($json);
		$this->assertSame($created, $json['alert_action_token']);
		$this->assertSame($created, \AlertActions::ensureToken($json));
	}

	function test_bot_secret_field() {
		$this->assertSame('telegram_bot_token', \AlertActions::botSecretField(\Notify::TYPE_TELEGRAM));
		$this->assertSame('max_token', \AlertActions::botSecretField(\Notify::TYPE_MAX));
		$this->assertNull(\AlertActions::botSecretField(\Notify::TYPE_MATTERMOST));
		$this->assertNull(\AlertActions::botSecretField(\Notify::TYPE_WEBHOOK));
	}

	function test_channel_matches_notify_type() {
		$this->assertTrue(\AlertActions::channelMatchesNotifyType('telegram', \Notify::TYPE_TELEGRAM));
		$this->assertTrue(\AlertActions::channelMatchesNotifyType('mattermost', \Notify::TYPE_MATTERMOST));
		$this->assertTrue(\AlertActions::channelMatchesNotifyType('max', \Notify::TYPE_MAX));
		$this->assertFalse(\AlertActions::channelMatchesNotifyType('telegram', \Notify::TYPE_MAX));
		$this->assertFalse(\AlertActions::channelMatchesNotifyType('webhook', \Notify::TYPE_WEBHOOK));
	}

	function test_webhook_url_and_callback_data() {
		$url = \AlertActions::webhookUrl('telegram', 'abc123', 'https://mon.example/');
		$this->assertSame('https://mon.example/api/alert-action/telegram/abc123/', $url);

		$this->assertSame('aa:resolve:12:3', \AlertActions::callbackData('resolve', 12, 3));
		$this->assertSame(
			['action' => 'resolve', 'monitor_id' => 12, 'notify_id' => 3],
			\AlertActions::parseCallbackData('aa:resolve:12:3')
		);
		$this->assertNull(\AlertActions::parseCallbackData('xx:resolve:12:3'));
		$this->assertNull(\AlertActions::parseCallbackData('aa:nope:12:3'));
		$this->assertNull(\AlertActions::parseCallbackData('aa:resolve:x:3'));
	}
}

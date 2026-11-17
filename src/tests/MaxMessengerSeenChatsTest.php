<?php

error_reporting(E_ALL);

class MaxMessengerSeenChatsTest extends PHPUnit\Framework\TestCase {

	function test_extract_chat_from_message_created() {
		$u = [
			'update_type' => 'message_created',
			'message' => [
				'recipient' => ['chat_id' => -1001, 'chat_type' => 'chat'],
				'sender' => ['user_id' => 42, 'is_bot' => false],
				'body' => ['text' => 'hello'],
			],
		];
		$chat = \MaxMessenger::extractChatFromUpdate($u);
		$this->assertNotNull($chat);
		$this->assertSame('-1001', $chat['chat_id']);
		$this->assertSame('chat', $chat['type']);
	}

	function test_extract_chat_from_bot_added() {
		$u = [
			'update_type' => 'bot_added',
			'chat_id' => -55,
			'is_channel' => true,
			'title' => 'VPN channel',
		];
		$chat = \MaxMessenger::extractChatFromUpdate($u);
		$this->assertSame('-55', $chat['chat_id']);
		$this->assertSame('channel', $chat['type']);
		$this->assertSame('VPN channel', $chat['title']);
	}

	function test_merge_seen_chat_dedup_and_remove() {
		$chats = [];
		$chats = \MaxMessenger::mergeSeenChat($chats, ['chat_id' => '-1', 'type' => 'chat', 'title' => 'A']);
		$chats = \MaxMessenger::mergeSeenChat($chats, ['chat_id' => '-2', 'type' => 'channel', 'title' => 'B']);
		$chats = \MaxMessenger::mergeSeenChat($chats, ['chat_id' => '-1', 'type' => 'chat', 'title' => 'A2']);
		$this->assertCount(2, $chats);
		$this->assertSame('-1', $chats[0]['chat_id']);
		$this->assertSame('A2', $chats[0]['title']);

		$chats = \MaxMessenger::mergeSeenChat($chats, ['chat_id' => '-1'], true);
		$this->assertCount(1, $chats);
		$this->assertSame('-2', $chats[0]['chat_id']);
	}

	function test_apply_update_to_notify_json() {
		$json = [];
		$u = [
			'update_type' => 'message_created',
			'message' => [
				'recipient' => ['chat_id' => 123, 'chat_type' => 'dialog', 'user_id' => 123],
				'sender' => ['user_id' => 123, 'is_bot' => false],
				'body' => ['text' => 'ping'],
			],
		];
		$this->assertTrue(\MaxMessenger::applyUpdateToNotifyJson($json, $u));
		$this->assertCount(1, $json['max_seen_chats']);
		$this->assertSame('123', $json['max_seen_chats'][0]['chat_id']);
		$this->assertSame('123', $json['max_last_inbound']['user_id']);
		$this->assertSame('ping', $json['max_last_inbound']['message']);

		$removed = ['update_type' => 'bot_removed', 'chat_id' => 123];
		$this->assertTrue(\MaxMessenger::applyUpdateToNotifyJson($json, $removed));
		$this->assertSame([], $json['max_seen_chats']);
		$this->assertArrayNotHasKey('max_last_inbound', $json);
	}

	function test_webhook_update_types_include_discovery() {
		$this->assertContains('message_callback', \MaxMessenger::WEBHOOK_UPDATE_TYPES);
		$this->assertContains('message_created', \MaxMessenger::WEBHOOK_UPDATE_TYPES);
		$this->assertContains('bot_added', \MaxMessenger::WEBHOOK_UPDATE_TYPES);
		$this->assertContains('bot_started', \MaxMessenger::WEBHOOK_UPDATE_TYPES);
		$this->assertContains('bot_removed', \MaxMessenger::WEBHOOK_UPDATE_TYPES);
	}
}

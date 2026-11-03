<?php

error_reporting(E_ALL);

class ApiV2NotifyTest extends ApiV2TestCase {

	private function user(): array {
		return ['id' => 11, 'status' => \Users::STATUS_ACTIVE];
	}

	function test_create_list_get_delete() {
		$created = \Api\V2\NotifyService::create($this->user(), [
			'title' => 'TG channel',
			'type' => \Notify::TYPE_TELEGRAM,
			'receiver' => '@chat',
			'status' => true,
			'json_data' => ['telegram_bot_token' => 'secret-token'],
		]);
		$this->assertSame(0, $created['status'], json_encode($created));
		$id = intval($created['data']['id'] ?? 0);
		$this->assertGreaterThan(0, $id);

		$list = \Api\V2\NotifyService::list($this->user());
		$this->assertSame(0, $list['status']);
		$this->assertIsArray($list['data']);

		$got = \Api\V2\NotifyService::get($this->user(), $id);
		$this->assertSame(0, $got['status']);
		$this->assertSame('TG channel', $got['data']['title']);

		$deleted = \Api\V2\NotifyService::delete($this->user(), $id);
		$this->assertSame(0, $deleted['status']);
	}

	function test_serialize_hides_secrets_by_default() {
		$row = [
			'id' => 1,
			'title' => 'N',
			'type' => \Notify::TYPE_TELEGRAM,
			'json_data' => ['telegram_bot_token' => 'secret-token', 'other' => 'ok'],
		];
		$user = $this->user();
		$serialized = \Api\V2\NotifyService::serialize($user, $row);
		$this->assertIsArray($serialized);
		$this->assertArrayNotHasKey('status', $serialized);
		$json = $serialized['json_data'] ?? [];
		$this->assertSame('ok', $json['other'] ?? null);
		$this->assertTrue(
			!isset($json['telegram_bot_token']) || $json['telegram_bot_token'] === '' || $json['telegram_bot_token'] === '***',
			'secret must be redacted without notify_secrets'
		);
	}

	function test_create_rejects_short_title() {
		$result = \Api\V2\NotifyService::create($this->user(), [
			'title' => 'x',
			'type' => \Notify::TYPE_WEBHOOK,
			'receiver' => 'https://example.com/hook',
		]);
		$this->assertSame(1, $result['status']);
		$this->assertSame(422, $result['http'] ?? 0);
	}
}

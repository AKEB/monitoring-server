<?php

error_reporting(E_ALL);

class ApiV2TagsTest extends ApiV2TestCase {

	function test_create_get_list_update_delete() {
		$user = ['id' => 1];
		$created = \Api\V2\TagsService::create($user, [
			'title' => 'production',
			'color' => '#198754',
		]);
		$this->assertSame(0, $created['status'] ?? 1, json_encode($created));
		$id = intval($created['data']['id'] ?? 0);
		$this->assertGreaterThan(0, $id);
		$this->assertSame('production', $created['data']['title']);
		$this->assertSame('#198754', $created['data']['color']);

		$got = \Api\V2\TagsService::get($user, $id);
		$this->assertSame(0, $got['status'] ?? 1);
		$this->assertSame('production', $got['data']['title']);

		$list = \Api\V2\TagsService::list($user);
		$this->assertSame(0, $list['status'] ?? 1);
		$this->assertGreaterThanOrEqual(1, intval($list['meta']['total'] ?? 0));

		$updated = \Api\V2\TagsService::update($user, $id, [
			'title' => 'prod',
			'color' => 'dc3545',
		]);
		$this->assertSame(0, $updated['status'] ?? 1);
		$this->assertSame('prod', $updated['data']['title']);
		$this->assertSame('#dc3545', $updated['data']['color']);

		$dup = \Api\V2\TagsService::create($user, ['title' => 'prod', 'color' => '#0d6efd']);
		$this->assertSame(1, $dup['status'] ?? 0);
		$this->assertSame(422, intval($dup['http'] ?? 0));

		$deleted = \Api\V2\TagsService::delete($user, $id);
		$this->assertSame(0, $deleted['status'] ?? 1);
		$missing = \Api\V2\TagsService::get($user, $id);
		$this->assertSame(1, $missing['status'] ?? 0);
	}

	function test_invalid_title_rejected() {
		$user = ['id' => 1];
		$bad = \Api\V2\TagsService::create($user, ['title' => '']);
		$this->assertSame(1, $bad['status'] ?? 0);
		$this->assertSame(422, intval($bad['http'] ?? 0));
	}
}

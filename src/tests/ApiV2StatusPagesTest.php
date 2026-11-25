<?php

error_reporting(E_ALL);

class ApiV2StatusPagesTest extends ApiV2TestCase {

	function test_create_get_list_update_delete() {
		$user = ['id' => 1];
		$created = \Api\V2\StatusPagesService::create($user, [
			'title' => 'Public status',
			'slug' => 'public-status',
			'status' => 1,
			'folder_ids' => [1, 2],
			'monitor_ids' => [10],
		]);
		$this->assertSame(0, $created['status'] ?? 1, json_encode($created));
		$id = intval($created['data']['id'] ?? 0);
		$this->assertGreaterThan(0, $id);
		$this->assertSame('public-status', $created['data']['slug']);
		$this->assertEqualsCanonicalizing([1, 2], $created['data']['folder_ids']);
		$this->assertEqualsCanonicalizing([10], $created['data']['monitor_ids']);

		$got = \Api\V2\StatusPagesService::get($user, $id);
		$this->assertSame(0, $got['status'] ?? 1);
		$this->assertSame('Public status', $got['data']['title']);

		$list = \Api\V2\StatusPagesService::list($user);
		$this->assertSame(0, $list['status'] ?? 1);
		$this->assertGreaterThanOrEqual(1, intval($list['meta']['total'] ?? 0));

		$updated = \Api\V2\StatusPagesService::update($user, $id, [
			'title' => 'Renamed',
			'slug' => 'renamed-slug',
			'folder_ids' => [3],
			'monitor_ids' => [],
		]);
		$this->assertSame(0, $updated['status'] ?? 1);
		$this->assertSame('Renamed', $updated['data']['title']);
		$this->assertSame('renamed-slug', $updated['data']['slug']);
		$this->assertEqualsCanonicalizing([3], $updated['data']['folder_ids']);
		$this->assertSame([], $updated['data']['monitor_ids']);

		$deleted = \Api\V2\StatusPagesService::delete($user, $id);
		$this->assertSame(0, $deleted['status'] ?? 1);
		$missing = \Api\V2\StatusPagesService::get($user, $id);
		$this->assertSame(1, $missing['status'] ?? 0);
	}

	function test_invalid_slug_rejected() {
		$user = ['id' => 1];
		$bad = \Api\V2\StatusPagesService::create($user, [
			'title' => 'Bad',
			'slug' => 'Bad Slug!',
			'status' => 1,
		]);
		$this->assertSame(1, $bad['status'] ?? 0);
		$this->assertSame(422, intval($bad['http'] ?? 0));
	}

	function test_incident_create_and_list_on_page() {
		$user = ['id' => 1];
		$page = \Api\V2\StatusPagesService::create($user, [
			'title' => 'Inc page',
			'slug' => 'inc-page',
			'status' => 1,
		]);
		$pageId = intval($page['data']['id'] ?? 0);
		$this->assertGreaterThan(0, $pageId);

		$inc = \Api\V2\StatusPagesService::createIncident($user, $pageId, [
			'title' => 'Outage',
			'message' => 'Investigating latency',
			'state' => 'investigating',
			'active' => 1,
		]);
		$this->assertSame(0, $inc['status'] ?? 1, json_encode($inc));
		$this->assertSame('investigating', $inc['data']['state']);
		$this->assertSame(1, intval($inc['data']['active']));

		$list = \Api\V2\StatusPagesService::listIncidents($user, $pageId);
		$this->assertSame(0, $list['status'] ?? 1);
		$this->assertGreaterThanOrEqual(1, count($list['data'] ?? []));

		$incId = intval($inc['data']['id'] ?? 0);
		$this->assertGreaterThan(0, $incId);
		$deleted = \Api\V2\StatusPagesService::deleteIncident($user, $pageId, $incId);
		$this->assertSame(0, $deleted['status'] ?? 1, json_encode($deleted));
		$listAfter = \Api\V2\StatusPagesService::listIncidents($user, $pageId);
		$this->assertSame(0, $listAfter['status'] ?? 1);
		foreach ($listAfter['data'] ?? [] as $row) {
			$this->assertNotSame($incId, intval($row['id'] ?? 0));
		}
	}
}

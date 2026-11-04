<?php

error_reporting(E_ALL);

/**
 * Smoke contracts for remaining CRUD entities and read-only users/groups/logs.
 */
class ApiV2EntitiesSmokeTest extends ApiV2TestCase {

	private function user(): array {
		return ['id' => 12, 'status' => \Users::STATUS_ACTIVE];
	}

	function test_signatures_crud_contract() {
		$created = \Api\V2\SignaturesService::create($this->user(), [
			'project' => 'demo',
			'title' => 'Sig',
			'text' => 'hello',
			'status' => true,
		]);
		$this->assertSame(0, $created['status'], json_encode($created));
		$id = intval($created['data']['id']);
		$this->assertSame(0, \Api\V2\SignaturesService::get($this->user(), $id)['status']);
		$this->assertSame(0, \Api\V2\SignaturesService::update($this->user(), $id, ['text' => 'upd'])['status']);
		$this->assertSame(0, \Api\V2\SignaturesService::delete($this->user(), $id)['status']);
	}

	function test_workers_list_and_serialize_hides_key() {
		$list = \Api\V2\WorkersService::list($this->user());
		$this->assertSame(0, $list['status'], json_encode($list));
		$row = [
			'id' => 1,
			'title' => 'w',
			'worker_key_hash' => 'super-secret-key',
		];
		$serialized = \Api\V2\WorkersService::serialize($this->user(), $row);
		$this->assertIsArray($serialized);
		$this->assertTrue(
			!isset($serialized['worker_key_hash']) || $serialized['worker_key_hash'] === '' || $serialized['worker_key_hash'] === '***',
			'worker key must be redacted without worker_key_hash permission'
		);
	}

	function test_maintenances_reject_bad_time_order() {
		$result = \Api\V2\MaintenancesService::create($this->user(), [
			'title' => 'MW',
			'start_time' => 2000,
			'finish_time' => 1000,
		]);
		$this->assertSame(1, $result['status']);
		$this->assertSame(422, $result['http'] ?? 0);
	}

	function test_dockers_list_contract() {
		$result = \Api\V2\DockersService::list($this->user());
		$this->assertSame(0, $result['status'], json_encode($result));
		$this->assertIsArray($result['data']);
	}

	function test_servers_crud_contract() {
		$user = $this->user() + ['__agent_key_hash' => true];
		$created = \Api\V2\ServersService::create($user, ['title' => 'Host A', 'status' => 1]);
		$this->assertSame(0, $created['status'], json_encode($created));
		$id = intval($created['data']['id']);
		$this->assertNotEmpty($created['data']['agent_key_hash'] ?? '');
		$this->assertSame(0, \Api\V2\ServersService::get($user, $id)['status']);
		$this->assertSame(0, \Api\V2\ServersService::update($user, $id, ['title' => 'Host B'])['status']);
		$list = \Api\V2\ServersService::list($user);
		$this->assertSame(0, $list['status'], json_encode($list));
		$this->assertSame(0, \Api\V2\ServersService::delete($user, $id)['status']);
	}

	function test_users_and_groups_are_read_only_list() {
		$users = \Api\V2\UsersService::list($this->user());
		$this->assertSame(0, $users['status'], json_encode($users));
		$this->assertIsArray($users['data']);

		$groups = \Api\V2\GroupsService::list($this->user());
		$this->assertSame(0, $groups['status'], json_encode($groups));
		$this->assertIsArray($groups['data']);
	}

	function test_logs_require_query_dates() {
		$missing = \Api\V2\LogsService::list($this->user(), []);
		$this->assertSame(1, $missing['status']);
		$this->assertSame(422, $missing['http'] ?? 0);

		$ok = \Api\V2\LogsService::list($this->user(), [
			'from' => '2026-11-03',
			'to' => '2026-11-03',
		]);
		$this->assertSame(0, $ok['status'], json_encode($ok));
		$this->assertIsArray($ok['data']);
	}

	function test_permissions_get_set_contract() {
		$set = \Api\V2\PermissionsService::set($this->user(), 'monitor', 1, [
			['object' => 'user', 'object_id' => 12, 'read' => 1, 'write' => 1],
		]);
		$this->assertSame(0, $set['status'], json_encode($set));
		$get = \Api\V2\PermissionsService::get($this->user(), 'monitor', 1);
		$this->assertSame(0, $get['status']);
		$this->assertCount(1, $get['data']);
	}
}

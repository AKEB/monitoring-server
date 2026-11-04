<?php

error_reporting(E_ALL);

class McpServerTest extends ApiV2TestCase {

	function test_initialize_has_instructions_and_guidance() {
		$init = \Mcp\Server::initializeResult();
		$this->assertSame('monitoring', $init['serverInfo']['name']);
		$this->assertNotEmpty($init['instructions']);
		$this->assertStringContainsString('api_enabled', $init['instructions']);
		$this->assertStringContainsString('ACL', $init['instructions']);
		$this->assertStringContainsString('/api/v1/status/', $init['instructions']);
	}

	function test_tools_catalog_covers_core_entities() {
		$tools = \Mcp\Server::tools();
		$this->assertNotEmpty($tools);
		$names = array_column($tools, 'name');
		foreach ([
			'api_docs',
			'monitors_list', 'monitors_get', 'monitors_create', 'monitors_update', 'monitors_delete',
			'notify_list', 'notify_get', 'notify_create', 'notify_update', 'notify_delete',
			'signatures_list', 'signatures_create', 'signatures_update', 'signatures_delete',
			'workers_list', 'workers_get', 'workers_create', 'workers_update', 'workers_delete',
			'maintenances_list', 'maintenances_create', 'maintenances_update', 'maintenances_delete',
			'dockers_list', 'dockers_get', 'dockers_create', 'dockers_update', 'dockers_delete',
			'servers_list', 'servers_get', 'servers_create', 'servers_update', 'servers_delete',
			'users_list', 'groups_list', 'logs_list',
			'permissions_get', 'permissions_set',
		] as $required) {
			$this->assertContains($required, $names, "missing tool $required");
		}
		foreach ($tools as $tool) {
			$this->assertNotEmpty($tool['description']);
			$this->assertArrayHasKey('inputSchema', $tool);
		}
	}

	function test_api_docs_index_and_path_slice() {
		$user = ['id' => 1, 'status' => \Users::STATUS_ACTIVE];
		$index = \Mcp\Server::callTool($user, 'api_docs', []);
		$this->assertSame(0, $index['status'], json_encode($index));
		$this->assertIsArray($index['data']['operations'] ?? null);
		$this->assertNotEmpty($index['data']['operations']);
		$paths = array_column($index['data']['operations'], 'path');
		$this->assertContains('/api/v2/monitors/', $paths);
		$this->assertContains('/api/v1/status/', $paths);

		$slice = \Mcp\Server::callTool($user, 'api_docs', [
			'path' => '/api/v2/monitors/',
			'operation' => 'post',
		]);
		$this->assertSame(0, $slice['status'], json_encode($slice));
		$this->assertArrayHasKey('post', $slice['data']['pathItem'] ?? []);
		$this->assertArrayHasKey('MonitorWrite', $slice['data']['components']['schemas'] ?? []);
	}

	function test_initialize_mentions_api_docs() {
		$init = \Mcp\Server::initializeResult();
		$this->assertStringContainsString('api_docs', $init['instructions']);
	}

	function test_tool_descriptions_contain_guidance() {
		$tools = \Mcp\Server::tools();
		$byName = [];
		foreach ($tools as $tool) {
			$byName[$tool['name']] = $tool['description'];
		}
		$this->assertArrayHasKey('monitors_create', $byName);
		$this->assertMatchesRegularExpression('/api_enabled|status API|opt-in/i', $byName['monitors_create']);
		$this->assertArrayHasKey('maintenances_create', $byName);
		$this->assertMatchesRegularExpression('/finish|start|time/i', $byName['maintenances_create']);
	}

	function test_prompts_include_how_to_guides() {
		$prompts = \Mcp\Server::prompts();
		$names = array_column($prompts, 'name');
		$this->assertContains('how_to_create_http_monitor', $names);
		$this->assertContains('how_to_schedule_maintenance', $names);
		$this->assertContains('status_api_vs_management_api', $names);
		$this->assertContains('how_to_add_host_agent', $names);
	}

	function test_call_tool_servers_crud_envelope() {
		$user = ['id' => 1, 'status' => \Users::STATUS_ACTIVE, '__agent_key_hash' => true];
		$created = \Mcp\Server::callTool($user, 'servers_create', ['title' => 'mcp-host']);
		$this->assertSame(0, $created['status'], json_encode($created));
		$id = intval($created['data']['id'] ?? 0);
		$this->assertGreaterThan(0, $id);
		$this->assertNotEmpty($created['data']['agent_key_hash'] ?? '');

		$list = \Mcp\Server::callTool($user, 'servers_list', []);
		$this->assertSame(0, $list['status'], json_encode($list));
		$this->assertNotEmpty($list['data']);

		$got = \Mcp\Server::callTool($user, 'servers_get', ['id' => $id]);
		$this->assertSame(0, $got['status'], json_encode($got));
		$this->assertSame('mcp-host', $got['data']['title'] ?? null);

		$updated = \Mcp\Server::callTool($user, 'servers_update', ['id' => $id, 'title' => 'mcp-host-2']);
		$this->assertSame(0, $updated['status'], json_encode($updated));
		$this->assertSame('mcp-host-2', $updated['data']['title'] ?? null);

		$deleted = \Mcp\Server::callTool($user, 'servers_delete', ['id' => $id]);
		$this->assertSame(0, $deleted['status'], json_encode($deleted));
		$this->assertTrue(!empty($deleted['data']['deleted']));
	}

	function test_call_tool_monitors_list_returns_envelope() {
		$user = ['id' => 1, 'status' => \Users::STATUS_ACTIVE];
		$result = \Mcp\Server::callTool($user, 'monitors_list', []);
		$this->assertSame(0, $result['status'], json_encode($result));
		$this->assertIsArray($result['data']);
	}

	function test_call_unknown_tool_errors() {
		$user = ['id' => 1, 'status' => \Users::STATUS_ACTIVE];
		$result = \Mcp\Server::callTool($user, 'no_such_tool', []);
		$this->assertSame(1, $result['status']);
		$this->assertSame(404, $result['http'] ?? 0);
	}
}

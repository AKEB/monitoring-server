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
			'monitors_list', 'monitors_get', 'monitors_create', 'monitors_update', 'monitors_delete',
			'notify_list', 'notify_get', 'notify_create', 'notify_update', 'notify_delete',
			'signatures_list', 'signatures_create', 'signatures_update', 'signatures_delete',
			'workers_list', 'workers_get', 'workers_create', 'workers_update', 'workers_delete',
			'maintenances_list', 'maintenances_create', 'maintenances_update', 'maintenances_delete',
			'dockers_list', 'dockers_get', 'dockers_create', 'dockers_update', 'dockers_delete',
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

<?php

error_reporting(E_ALL);

/**
 * Contract tests for management monitors API.
 * Expected to fail until MonitorsService is implemented.
 */
class ApiV2MonitorsTest extends ApiV2TestCase {

	private function user(int $id = 10): array {
		return ['id' => $id, 'status' => \Users::STATUS_ACTIVE];
	}

	function test_list_returns_ok_envelope_with_data_array() {
		$result = \Api\V2\MonitorsService::list($this->user());
		$this->assertSame(0, $result['status'], 'list must succeed for authorized user (got: '.json_encode($result).')');
		$this->assertIsArray($result['data']);
		$this->assertArrayHasKey('meta', $result);
		$this->assertArrayHasKey('total', $result['meta']);
	}

	function test_create_rejects_short_title() {
		$result = \Api\V2\MonitorsService::create($this->user(), [
			'title' => 'a',
			'type' => \Monitors::TYPE_HTTPS,
			'url' => 'https://example.com',
		]);
		$this->assertSame(1, $result['status']);
		$this->assertSame(422, $result['http'] ?? 0);
		$this->assertNotEmpty($result['error'] ?? '');
	}

	function test_create_forces_api_enabled_off_for_folder() {
		$result = \Api\V2\MonitorsService::create($this->user(), [
			'title' => 'Folder',
			'type' => \Monitors::TYPE_FOLDER,
			'api_enabled' => true,
		]);
		$this->assertSame(0, $result['status'], json_encode($result));
		$this->assertSame(0, intval($result['data']['api_enabled'] ?? 1));
	}

	function test_create_and_get_roundtrip() {
		$created = \Api\V2\MonitorsService::create($this->user(), [
			'title' => 'HTTPS check',
			'type' => \Monitors::TYPE_HTTPS,
			'url' => 'https://example.com/health',
			'status' => true,
			'api_enabled' => false,
		]);
		$this->assertSame(0, $created['status'], json_encode($created));
		$id = intval($created['data']['id'] ?? 0);
		$this->assertGreaterThan(0, $id);

		$got = \Api\V2\MonitorsService::get($this->user(), $id);
		$this->assertSame(0, $got['status']);
		$this->assertSame('HTTPS check', $got['data']['title']);
		$this->assertSame(\Monitors::TYPE_HTTPS, intval($got['data']['type']));
	}

	function test_update_and_delete() {
		$created = \Api\V2\MonitorsService::create($this->user(), [
			'title' => 'To update',
			'type' => \Monitors::TYPE_PING,
			'host' => '1.1.1.1',
		]);
		$this->assertSame(0, $created['status'], json_encode($created));
		$id = intval($created['data']['id']);

		$updated = \Api\V2\MonitorsService::update($this->user(), $id, ['title' => 'Updated']);
		$this->assertSame(0, $updated['status']);
		$this->assertSame('Updated', $updated['data']['title']);

		$deleted = \Api\V2\MonitorsService::delete($this->user(), $id);
		$this->assertSame(0, $deleted['status']);

		$missing = \Api\V2\MonitorsService::get($this->user(), $id);
		$this->assertSame(1, $missing['status']);
		$this->assertSame(404, $missing['http'] ?? 0);
	}

	function test_get_unknown_returns_404() {
		$result = \Api\V2\MonitorsService::get($this->user(), 999999999);
		$this->assertSame(1, $result['status']);
		$this->assertSame(404, $result['http'] ?? 0);
	}

	function test_create_server_port_requires_valid_port() {
		$bad = \Api\V2\MonitorsService::create($this->user(), [
			'title' => 'Port check',
			'type' => \Monitors::TYPE_SERVER_PORT,
			'server' => 1,
			'port' => 0,
		]);
		$this->assertSame(1, $bad['status']);
		$this->assertSame(422, $bad['http'] ?? 0);

		$ok = \Api\V2\MonitorsService::create($this->user(), [
			'title' => 'SSH listening',
			'type' => \Monitors::TYPE_SERVER_PORT,
			'server' => 1,
			'port' => 22,
			'port_protocol' => 'udp',
		]);
		$this->assertSame(0, $ok['status'], json_encode($ok));
		$this->assertSame(22, intval($ok['data']['port'] ?? 0));
		$this->assertSame('udp', strval($ok['data']['port_protocol'] ?? ''));
		$this->assertSame(1, intval($ok['data']['server'] ?? 0));

		$id = intval($ok['data']['id'] ?? 0);
		$updated = \Api\V2\MonitorsService::update($this->user(), $id, [
			'port' => 53,
			'port_protocol' => 'tcp',
		]);
		$this->assertSame(0, $updated['status'], json_encode($updated));
		$this->assertSame(53, intval($updated['data']['port'] ?? 0));
		$this->assertSame('tcp', strval($updated['data']['port_protocol'] ?? ''));
	}

	function test_openapi_documents_server_port_fields() {
		$raw = file_get_contents(__DIR__.'/../openapi/openapi.json');
		$this->assertNotFalse($raw);
		$doc = json_decode($raw, true);
		$this->assertIsArray($doc);
		$write = $doc['components']['schemas']['MonitorWrite']['properties'] ?? [];
		$this->assertArrayHasKey('port_protocol', $write);
		$this->assertArrayHasKey('server', $write);
		$this->assertSame(['tcp', 'udp'], $write['port_protocol']['enum'] ?? null);
		$read = $doc['components']['schemas']['Monitor']['properties'] ?? [];
		$this->assertArrayHasKey('port_protocol', $read);
		$this->assertStringContainsString('17', strval($read['type']['description'] ?? ''));
	}
}

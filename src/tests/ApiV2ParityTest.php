<?php

error_reporting(E_ALL);

class ApiV2ParityTest extends ApiV2TestCase {

	private function user(): array {
		return ['id' => 42, 'status' => \Users::STATUS_ACTIVE];
	}

	function test_monitor_create_with_workers_notify_and_http_fields() {
		$created = \Api\V2\MonitorsService::create($this->user(), [
			'title' => 'API HTTPS',
			'type' => \Monitors::TYPE_HTTPS,
			'url' => 'https://example.com/health',
			'method' => 'GET',
			'ssl_verify' => true,
			'ssl_cert_expire_alert' => true,
			'ssl_cert_expire_alert_days' => [15, 7],
			'response_code' => [200, 201],
			'request_headers' => "X-Test: 1\n",
			'workers' => [1, 2],
			'notify' => [5],
			'api_enabled' => false,
			'status' => true,
		]);
		$this->assertSame(0, $created['status'], json_encode($created));
		$data = $created['data'];
		$this->assertSame('https://example.com/health', $data['url']);
		$this->assertSame('GET', $data['method']);
		$this->assertSame(1, intval($data['ssl_verify']));
		$this->assertSame('15,7', $data['ssl_cert_expire_alert_days']);
		$this->assertSame('200,201', $data['response_code']);
		$this->assertSame([1, 2], $data['workers']);
		$this->assertSame([5], $data['notify']);
	}

	function test_monitor_external_clears_workers() {
		$created = \Api\V2\MonitorsService::create($this->user(), [
			'title' => 'Push',
			'type' => \Monitors::TYPE_EXTERNAL,
			'workers' => [1, 2],
		]);
		$this->assertSame(0, $created['status'], json_encode($created));
		$this->assertSame([], $created['data']['workers']);
		$this->assertSame(60, intval($created['data']['repeat_seconds']));
		$this->assertNotEmpty($created['data']['external_hash']);
	}

	function test_monitor_folder_forces_api_enabled_off() {
		$created = \Api\V2\MonitorsService::create($this->user(), [
			'title' => 'Folder',
			'type' => \Monitors::TYPE_FOLDER,
			'api_enabled' => true,
		]);
		$this->assertSame(0, $created['status']);
		$this->assertSame(0, intval($created['data']['api_enabled']));
	}

	function test_docker_tls_blob_and_workers() {
		$pem = "-----BEGIN CERTIFICATE-----\nABC\n-----END CERTIFICATE-----";
		$created = \Api\V2\DockersService::create($this->user(), [
			'title' => 'Docker host',
			'host' => '127.0.0.1',
			'port' => 2376,
			'tls' => true,
			'tls_ca_file' => $pem,
			'tls_certificate' => $pem,
			'tls_key' => $pem,
			'workers' => [3],
		]);
		$this->assertSame(0, $created['status'], json_encode($created));
		$this->assertSame([3], $created['data']['workers']);
		$this->assertTrue($created['data']['tls_present']['tls_ca_file']);
		$this->assertArrayNotHasKey('tls_ca_file', $created['data']);

		$id = intval($created['data']['id']);
		$updated = \Api\V2\DockersService::update($this->user(), $id, [
			'tls' => true,
			'workers' => [3, 4],
		]);
		$this->assertSame(0, $updated['status']);
		$this->assertSame([3, 4], $updated['data']['workers']);
		$this->assertTrue($updated['data']['tls_present']['tls_ca_file']);
	}

	function test_worker_tuning_and_assignments() {
		$created = \Api\V2\WorkersService::create($this->user(), [
			'title' => 'Worker A',
			'worker_threads' => 4,
			'jobs_get_timeout' => 10,
			'monitors' => [11],
			'dockers' => [21],
		]);
		$this->assertSame(0, $created['status'], json_encode($created));
		$this->assertSame(4, intval($created['data']['worker_threads']));
		$this->assertSame([11], $created['data']['monitors']);
		$this->assertSame([21], $created['data']['dockers']);
		$this->assertSame('***', $created['data']['worker_key_hash']);

		$id = intval($created['data']['id']);
		$updated = \Api\V2\WorkersService::update($this->user(), $id, [
			'restart_worker' => true,
			'monitors' => [11, 12],
		]);
		$this->assertSame(0, $updated['status']);
		$this->assertSame(1, intval($updated['data']['restart_requested']));
		$this->assertSame([11, 12], $updated['data']['monitors']);
	}

	function test_relations_tls_normalize() {
		$pem = "-----BEGIN CERTIFICATE-----\nx\n-----END CERTIFICATE-----";
		$encoded = \Api\V2\Relations::normalizeTlsBlob($pem);
		$this->assertSame(base64_encode($pem), $encoded);
		$this->assertSame($encoded, \Api\V2\Relations::normalizeTlsBlob($encoded));
	}

	function test_relations_diff_links() {
		$diff = \Api\V2\Relations::diffLinks([1, 2], [2, 3], fn(int $id) => true);
		$this->assertSame(-1, $diff[1]);
		$this->assertSame(1, $diff[3]);
		$this->assertArrayNotHasKey(2, $diff);
	}
}

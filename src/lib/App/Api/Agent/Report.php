<?php

namespace App\Api\Agent;

/**
 * Push ingest for host agents: POST /api/agent/report/
 * Body JSON authenticated by agent_key_hash (no browser CSRF).
 */
class Report extends \Routing_Parent implements \Routing_Interface {

	private array $data = [];

	public function Run() {
		$this->processAgentRequest();
		if (!$this->data) {
			$this->responseError('Invalid JSON body', 400);
			return;
		}
		$result = \ServerAgent::applyReport($this->data);
		if (!$result) {
			$this->responseError('Server not found or disabled', 404);
			return;
		}
		$this->responseSuccess($result);
	}

	protected function processAgentRequest(): void {
		$this->error = '';
		$data = $this->get_request_body();
		if ($data) {
			$this->data = $data;
		}
	}

	/** @param array<string,mixed> $response */
	private function responseSuccess(array $response = []): void {
		$response['status'] = 0;
		http_response_code(200);
		header('Content-Encoding: UTF-8');
		header('Content-Type: application/json;charset=utf-8');
		header('Cache-Control: no-store');
		echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		exit(0);
	}

	private function responseError(string $error, int $http_code): void {
		http_response_code($http_code);
		header('Content-Encoding: UTF-8');
		header('Content-Type: application/json;charset=utf-8');
		header('Cache-Control: no-store');
		echo json_encode([
			'status' => 1,
			'error' => $error,
		], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		exit(0);
	}
}

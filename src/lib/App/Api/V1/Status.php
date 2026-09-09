<?php

namespace App\Api\V1;

class Status extends \Routing_Parent implements \Routing_Interface {

	public function Run() {
		$auth = \ApiAuth::authenticate();
		if (!$auth) {
			$this->responseError('Unauthorized', 401);
			return;
		}

		$token_id = intval($auth['token']['id'] ?? 0);
		if ($token_id > 0 && \ApiAuth::isRateLimited($token_id)) {
			$this->responseError('Too many requests', 429);
			return;
		}

		$user_id = intval($auth['user']['id'] ?? 0);
		$cached = \ApiAuth::cachedStatusPayload($user_id);
		if ($cached !== null) {
			$this->printJson($cached, 200);
			return;
		}

		$data = \MonitoringApiStatus::buildForUser($auth['user']);
		$payload = json_encode([
			'status' => 0,
			'generated_at' => time(),
			'resources' => $data['resources'] ?? [],
			'maintenances' => $data['maintenances'] ?? [],
		], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if (!is_string($payload) || $payload === '') {
			$this->responseError('Unable to encode response', 500);
			return;
		}
		\ApiAuth::storeStatusPayload($user_id, $payload);
		$this->printJson($payload, 200);
	}

	private function responseError(string $error, int $http_code): void {
		$payload = json_encode([
			'status' => 1,
			'error' => $error,
		], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		$this->printJson(is_string($payload) ? $payload : '{"status":1,"error":"Error"}', $http_code);
	}

	private function printJson(string $payload, int $http_code): void {
		http_response_code($http_code);
		header('Content-Encoding: UTF-8');
		header('Content-Type: application/json;charset=utf-8');
		header('Cache-Control: no-store');
		echo $payload;
		exit(0);
	}
}

<?php

namespace App\Api;

/**
 * Serves OpenAPI JSON at /api/docs/openapi.json (auth required).
 */
class OpenApi extends \Routing_Parent implements \Routing_Interface {

	public function Run() {
		$this->check_auth();
		try {
			$raw = \Api\OpenApiSpec::rawJson();
		} catch (\Throwable $e) {
			http_response_code(500);
			header('Content-Type: application/json;charset=utf-8');
			echo json_encode(['status' => 1, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
			exit(0);
		}
		http_response_code(200);
		header('Content-Type: application/json;charset=utf-8');
		header('Cache-Control: no-store');
		echo $raw;
		exit(0);
	}
}

<?php

namespace App\Mcp;

/**
 * Remote MCP JSON-RPC endpoint at /mcp.
 * Auth: same Bearer / X-Api-Token as /api/v2.
 */
class Endpoint extends \Routing_Parent implements \Routing_Interface {

	public function Run() {
		$auth = \ApiAuth::authenticate();
		if (!$auth) {
			$this->respond([
				'jsonrpc' => '2.0',
				'error' => ['code' => -32001, 'message' => 'Unauthorized'],
				'id' => null,
			], 401);
			return;
		}
		$user = $auth['user'];
		$raw = file_get_contents('php://input') ?: '';
		$req = json_decode($raw, true);
		if (!is_array($req)) {
			$this->respond([
				'jsonrpc' => '2.0',
				'error' => ['code' => -32700, 'message' => 'Parse error'],
				'id' => null,
			], 400);
			return;
		}
		$id = $req['id'] ?? null;
		$method = strval($req['method'] ?? '');
		$params = is_array($req['params'] ?? null) ? $req['params'] : [];

		$result = match ($method) {
			'initialize' => \Mcp\Server::initializeResult(),
			'notifications/initialized' => new \stdClass(),
			'tools/list' => ['tools' => \Mcp\Server::tools()],
			'tools/call' => $this->toolCall($user, $params),
			'prompts/list' => ['prompts' => \Mcp\Server::prompts()],
			'ping' => new \stdClass(),
			default => null,
		};

		if ($result === null) {
			$this->respond([
				'jsonrpc' => '2.0',
				'error' => ['code' => -32601, 'message' => 'Method not found'],
				'id' => $id,
			], 404);
			return;
		}

		$this->respond([
			'jsonrpc' => '2.0',
			'result' => $result,
			'id' => $id,
		], 200);
	}

	/**
	 * @param array<string,mixed> $user
	 * @param array<string,mixed> $params
	 * @return array<string,mixed>
	 */
	private function toolCall(array $user, array $params): array {
		$name = strval($params['name'] ?? '');
		$arguments = $params['arguments'] ?? [];
		if (is_string($arguments)) {
			$decoded = json_decode($arguments, true);
			$arguments = is_array($decoded) ? $decoded : [];
		}
		if (!is_array($arguments)) {
			$arguments = [];
		}
		$out = \Mcp\Server::callTool($user, $name, $arguments);
		$isError = intval($out['status'] ?? 1) !== 0;
		return [
			'content' => [[
				'type' => 'text',
				'text' => json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
			]],
			'isError' => $isError,
		];
	}

	/** @param array<string,mixed>|\stdClass $body */
	private function respond(array|\stdClass $body, int $http): void {
		http_response_code($http);
		header('Content-Type: application/json;charset=utf-8');
		header('Cache-Control: no-store');
		echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		exit(0);
	}
}

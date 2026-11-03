<?php

namespace App\Api\V2;

/**
 * HTTP front for /api/v2/* — auth, rate limit, method dispatch to Api\V2 services.
 */
class Gateway extends \Routing_Parent implements \Routing_Interface {

	public function Run(string $resource = '', string $id = '', string $action = '') {
		$auth = \ApiAuth::authenticate();
		if (!$auth) {
			$this->respond(\Api\V2\Envelope::error('Unauthorized'), 401);
			return;
		}
		$tokenId = intval($auth['token']['id'] ?? 0);
		if ($tokenId > 0 && static::isRateLimited($tokenId)) {
			$this->respond(\Api\V2\Envelope::error('Too many requests'), 429);
			return;
		}

		$user = $auth['user'];
		$method = strtoupper(strval($_SERVER['REQUEST_METHOD'] ?? 'GET'));
		$resource = strtolower(trim($resource));
		$idInt = intval($id);
		$payload = static::readJsonBody();
		if ($method === 'GET' && !$payload) {
			$payload = $_GET;
		}

		$result = $this->dispatch($user, $method, $resource, $idInt, $action, is_array($payload) ? $payload : []);
		$http = intval($result['http'] ?? (($result['status'] ?? 1) === 0 ? 200 : 400));
		unset($result['http']);
		$this->respond($result, $http);
	}

	/**
	 * @param array<string,mixed> $user
	 * @param array<string,mixed> $payload
	 * @return array<string,mixed>
	 */
	private function dispatch(array $user, string $method, string $resource, int $id, string $action, array $payload): array {
		if ($action === 'permissions' || $resource === 'permissions') {
			return $this->dispatchPermissions($user, $method, $resource, $id, $payload);
		}
		return match ($resource) {
			'monitors' => $this->crud($user, $method, $id, $payload, \Api\V2\MonitorsService::class),
			'notify' => $this->crud($user, $method, $id, $payload, \Api\V2\NotifyService::class),
			'signatures' => $this->crud($user, $method, $id, $payload, \Api\V2\SignaturesService::class),
			'workers' => $this->crud($user, $method, $id, $payload, \Api\V2\WorkersService::class),
			'maintenances' => $this->crud($user, $method, $id, $payload, \Api\V2\MaintenancesService::class),
			'dockers' => $this->crud($user, $method, $id, $payload, \Api\V2\DockersService::class),
			'users' => $this->readOnly($user, $method, $id, \Api\V2\UsersService::class),
			'groups' => $this->readOnly($user, $method, $id, \Api\V2\GroupsService::class),
			'logs' => $method === 'GET'
				? \Api\V2\LogsService::list($user, $payload)
				: \Api\V2\Envelope::error('Method not allowed') + ['http' => 405],
			default => \Api\V2\Envelope::error('Not found') + ['http' => 404],
		};
	}

	/**
	 * @param array<string,mixed> $user
	 * @param array<string,mixed> $payload
	 * @param class-string $class
	 * @return array<string,mixed>
	 */
	private function crud(array $user, string $method, int $id, array $payload, string $class): array {
		return match ($method) {
			'GET' => $id > 0 ? $class::get($user, $id) : $class::list($user, $payload),
			'POST' => $class::create($user, $payload),
			'PUT', 'PATCH' => $id > 0
				? $class::update($user, $id, $payload)
				: \Api\V2\Envelope::error('Missing id') + ['http' => 422],
			'DELETE' => $id > 0
				? $class::delete($user, $id)
				: \Api\V2\Envelope::error('Missing id') + ['http' => 422],
			default => \Api\V2\Envelope::error('Method not allowed') + ['http' => 405],
		};
	}

	/**
	 * @param array<string,mixed> $user
	 * @param class-string $class
	 * @return array<string,mixed>
	 */
	private function readOnly(array $user, string $method, int $id, string $class): array {
		if ($method !== 'GET') {
			return \Api\V2\Envelope::error('Method not allowed') + ['http' => 405];
		}
		return $id > 0 ? $class::get($user, $id) : $class::list($user);
	}

	/**
	 * @param array<string,mixed> $user
	 * @param array<string,mixed> $payload
	 * @return array<string,mixed>
	 */
	private function dispatchPermissions(array $user, string $method, string $resource, int $id, array $payload): array {
		$subject = $resource === 'permissions' ? strval($payload['subject'] ?? '') : $resource;
		$subjectId = $resource === 'permissions' ? intval($payload['subject_id'] ?? 0) : $id;
		if ($method === 'GET') {
			return \Api\V2\PermissionsService::get($user, $subject, $subjectId);
		}
		if (in_array($method, ['PUT', 'PATCH', 'POST'], true)) {
			$rows = $payload['rows'] ?? $payload;
			if (!is_array($rows)) {
				$rows = [];
			}
			// If payload is {rows:[...]} use rows; if list of ACL rows use as-is.
			if (isset($payload['rows']) && is_array($payload['rows'])) {
				$rows = $payload['rows'];
			} elseif ($rows !== [] && array_is_list($rows)) {
				// ok
			} else {
				$rows = [];
			}
			return \Api\V2\PermissionsService::set($user, $subject, $subjectId, $rows);
		}
		return \Api\V2\Envelope::error('Method not allowed') + ['http' => 405];
	}

	static private function isRateLimited(int $tokenId): bool {
		$key = \Api\V2\RateLimit::cacheKey($tokenId);
		$cache = new \Cache($key);
		$count = 0;
		if ($cache->isValid()) {
			$count = intval($cache->get());
		}
		if ($count >= \Api\V2\RateLimit::MAX_REQUESTS) {
			return true;
		}
		$cache->update(strval($count + 1), \Api\V2\RateLimit::WINDOW_SECONDS);
		return false;
	}

	/** @return array<string,mixed>|null */
	static private function readJsonBody(): ?array {
		$raw = file_get_contents('php://input');
		if ($raw === false || trim($raw) === '') {
			return null;
		}
		$data = json_decode($raw, true);
		return is_array($data) ? $data : null;
	}

	/** @param array<string,mixed> $body */
	private function respond(array $body, int $http): void {
		http_response_code($http);
		header('Content-Type: application/json;charset=utf-8');
		header('Cache-Control: no-store');
		echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		exit(0);
	}
}

<?php

namespace Mcp;

use Api\V2\DockersService;
use Api\V2\Envelope;
use Api\V2\GroupsService;
use Api\V2\LogsService;
use Api\V2\MaintenancesService;
use Api\V2\MonitorsService;
use Api\V2\NotifyService;
use Api\V2\PermissionsService;
use Api\V2\ServersService;
use Api\V2\SignaturesService;
use Api\V2\UsersService;
use Api\V2\WorkersService;

/**
 * Remote MCP server over the same auth as /api/v2.
 * Tools call Api\V2 services — no HTTP loopback.
 */
class Server {

	const PROTOCOL_VERSION = '2024-11-05';

	/**
	 * @return array<string,mixed>
	 */
	static public function initializeResult(): array {
		return [
			'protocolVersion' => self::PROTOCOL_VERSION,
			'capabilities' => [
				'tools' => new \stdClass(),
				'prompts' => new \stdClass(),
			],
			'serverInfo' => [
				'name' => 'monitoring',
				'version' => '0.2.0',
			],
			'instructions' => static::instructions(),
		];
	}

	static public function instructions(): string {
		return implode("\n", [
			'You manage a monitoring service via tools backed by /api/v2.',
			'Always respect ACL: only act on objects the token user can access.',
			'GET /api/v1/status/ is a separate opt-in feed (api_enabled flag); do not enable it for every monitor.',
			'Prefer reading an object before updating. Validate required fields for the monitor type.',
			'When fields, types, or response shapes are unclear, call api_docs (optionally with tag/path/operation) — same OpenAPI as /api/docs/.',
			'Never expose notify/worker/server agent secrets unless the user explicitly needs them and has rights.',
			'Servers are host agents (monitoring-agent): create returns agent_key_hash for install; regenerate_key rotates it.',
			'Agent reports may include listeners (TCP/UDP listening ports). Monitor type 17 (server listening port) checks that a port+protocol is present in listeners on the selected server.',
			'For maintenances, ensure finish_time >= start_time and only attach readable monitors.',
		]);
	}

	/**
	 * @return list<array{name:string,description:string,inputSchema:array}>
	 */
	static public function tools(): array {
		$empty = ['type' => 'object', 'properties' => new \stdClass()];
		$idSchema = [
			'type' => 'object',
			'properties' => ['id' => ['type' => 'integer']],
			'required' => ['id'],
		];
		return [
			['name' => 'api_docs', 'description' => 'Read OpenAPI documentation (same as /api/docs/). No args = index of tags and METHOD path. Optional tag, path, and/or operation (get/post/patch/put/delete) return a focused slice with schemas.', 'inputSchema' => ['type' => 'object', 'properties' => ['tag' => ['type' => 'string', 'description' => 'OpenAPI tag, e.g. monitors'], 'path' => ['type' => 'string', 'description' => 'Exact path key, e.g. /api/v2/monitors/'], 'operation' => ['type' => 'string', 'description' => 'HTTP method under path, e.g. post']]]],
			['name' => 'monitors_list', 'description' => 'List monitors visible to the current user.', 'inputSchema' => $empty],
			['name' => 'monitors_get', 'description' => 'Get one monitor by id.', 'inputSchema' => $idSchema],
			['name' => 'monitors_create', 'description' => 'Create a monitor. Types include 0 HTTPS, 2 TCP, 3 ping, 8 HTTP JSON, 9 external, 10–16 server metrics, 17 server listening port (needs server+port+port_protocol tcp|udp). Set api_enabled only for /api/v1/status/ opt-in.', 'inputSchema' => ['type' => 'object', 'properties' => [
				'title' => ['type' => 'string'],
				'type' => ['type' => 'integer', 'description' => '0 HTTPS, 2 TCP, 3 ping, 4 docker, 8 HTTP JSON, 9 external, 10–16 server agent, 17 listening port'],
				'url' => ['type' => 'string'],
				'host' => ['type' => 'string'],
				'port' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 65535],
				'port_protocol' => ['type' => 'string', 'enum' => ['tcp', 'udp'], 'description' => 'For type 17'],
				'server' => ['type' => 'integer', 'description' => 'Host agent server id (types 10–17)'],
				'disk_mount' => ['type' => 'string'],
				'agent_container' => ['type' => 'string'],
				'value' => ['type' => 'integer'],
				'api_enabled' => ['type' => 'boolean'],
				'workers' => ['type' => 'array', 'items' => ['type' => 'integer']],
				'notify' => ['type' => 'array', 'items' => ['type' => 'integer']],
			], 'required' => ['title', 'type']]],
			['name' => 'monitors_update', 'description' => 'Update a monitor. Read it first. For type 17 use server, port (1–65535), port_protocol tcp|udp. api_enabled is /api/v1/status/ opt-in only.', 'inputSchema' => ['type' => 'object', 'properties' => [
				'id' => ['type' => 'integer'],
				'title' => ['type' => 'string'],
				'type' => ['type' => 'integer'],
				'port' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 65535],
				'port_protocol' => ['type' => 'string', 'enum' => ['tcp', 'udp']],
				'server' => ['type' => 'integer'],
				'disk_mount' => ['type' => 'string'],
				'agent_container' => ['type' => 'string'],
				'value' => ['type' => 'integer'],
				'api_enabled' => ['type' => 'boolean'],
				'workers' => ['type' => 'array', 'items' => ['type' => 'integer']],
				'notify' => ['type' => 'array', 'items' => ['type' => 'integer']],
			], 'required' => ['id']]],
			['name' => 'monitors_delete', 'description' => 'Delete a monitor by id.', 'inputSchema' => $idSchema],
			['name' => 'notify_list', 'description' => 'List notification channels.', 'inputSchema' => $empty],
			['name' => 'notify_get', 'description' => 'Get a notification channel. Secrets are redacted without notify_secrets.', 'inputSchema' => $idSchema],
			['name' => 'notify_create', 'description' => 'Create a notification channel (Telegram/Mattermost/Gotify/MAX/Webhook).', 'inputSchema' => ['type' => 'object', 'properties' => ['title' => ['type' => 'string'], 'type' => ['type' => 'integer'], 'receiver' => ['type' => 'string']], 'required' => ['title', 'type']]],
			['name' => 'notify_update', 'description' => 'Update a notification channel. Omitted secrets are preserved.', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']], 'required' => ['id']]],
			['name' => 'notify_delete', 'description' => 'Delete a notification channel.', 'inputSchema' => $idSchema],
			['name' => 'signatures_list', 'description' => 'List signatures (reusable alert text blocks).', 'inputSchema' => $empty],
			['name' => 'signatures_create', 'description' => 'Create a signature. Title is required; project+title must be unique.', 'inputSchema' => ['type' => 'object', 'properties' => ['project' => ['type' => 'string'], 'title' => ['type' => 'string'], 'text' => ['type' => 'string']], 'required' => ['title']]],
			['name' => 'signatures_update', 'description' => 'Update a signature.', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']], 'required' => ['id']]],
			['name' => 'signatures_delete', 'description' => 'Delete a signature.', 'inputSchema' => $idSchema],
			['name' => 'workers_list', 'description' => 'List workers (handlers). Keys are redacted without permission.', 'inputSchema' => $empty],
			['name' => 'workers_get', 'description' => 'Get a worker by id.', 'inputSchema' => $idSchema],
			['name' => 'workers_create', 'description' => 'Create a worker/handler.', 'inputSchema' => ['type' => 'object', 'properties' => ['title' => ['type' => 'string']], 'required' => ['title']]],
			['name' => 'workers_update', 'description' => 'Update a worker.', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']], 'required' => ['id']]],
			['name' => 'workers_delete', 'description' => 'Delete a worker.', 'inputSchema' => $idSchema],
			['name' => 'maintenances_list', 'description' => 'List planned maintenances.', 'inputSchema' => $empty],
			['name' => 'maintenances_create', 'description' => 'Create maintenance. finish_time must be >= start_time (unix timestamps). Attach only monitors the user can read.', 'inputSchema' => ['type' => 'object', 'properties' => ['title' => ['type' => 'string'], 'start_time' => ['type' => 'integer'], 'finish_time' => ['type' => 'integer']], 'required' => ['title', 'start_time', 'finish_time']]],
			['name' => 'maintenances_update', 'description' => 'Update maintenance. Keep finish_time >= start_time.', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']], 'required' => ['id']]],
			['name' => 'maintenances_delete', 'description' => 'Delete a maintenance.', 'inputSchema' => $idSchema],
			['name' => 'dockers_list', 'description' => 'List Docker hosts.', 'inputSchema' => $empty],
			['name' => 'dockers_get', 'description' => 'Get a Docker host. TLS blobs are never returned.', 'inputSchema' => $idSchema],
			['name' => 'dockers_create', 'description' => 'Create a Docker host entry.', 'inputSchema' => ['type' => 'object', 'properties' => ['title' => ['type' => 'string'], 'host' => ['type' => 'string']], 'required' => ['title']]],
			['name' => 'dockers_update', 'description' => 'Update a Docker host.', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']], 'required' => ['id']]],
			['name' => 'dockers_delete', 'description' => 'Delete a Docker host.', 'inputSchema' => $idSchema],
			['name' => 'servers_list', 'description' => 'List host servers (monitoring-agent).', 'inputSchema' => $empty],
			['name' => 'servers_get', 'description' => 'Get a host server. agent_key_hash is redacted without secrets permission; payload is decoded last report.', 'inputSchema' => $idSchema],
			['name' => 'servers_create', 'description' => 'Create a host server entry and agent_key_hash for monitoring-agent install.', 'inputSchema' => ['type' => 'object', 'properties' => ['title' => ['type' => 'string'], 'status' => ['type' => 'integer'], 'desired_agent_version' => ['type' => 'string']], 'required' => ['title']]],
			['name' => 'servers_update', 'description' => 'Update a host server. regenerate_key rotates agent_key_hash; request_agent_update clears update error state.', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'title' => ['type' => 'string'], 'status' => ['type' => 'integer'], 'desired_agent_version' => ['type' => 'string'], 'regenerate_key' => ['type' => 'boolean'], 'request_agent_update' => ['type' => 'boolean']], 'required' => ['id']]],
			['name' => 'servers_delete', 'description' => 'Delete a host server and its derived monitors/metrics.', 'inputSchema' => $idSchema],
			['name' => 'users_list', 'description' => 'List users (read-only).', 'inputSchema' => $empty],
			['name' => 'groups_list', 'description' => 'List groups (read-only).', 'inputSchema' => $empty],
			['name' => 'logs_list', 'description' => 'Read audit logs like /admin/logs/{from}/{to}/. Requires from/to YYYY-MM-DD.', 'inputSchema' => ['type' => 'object', 'properties' => ['from' => ['type' => 'string'], 'to' => ['type' => 'string']], 'required' => ['from', 'to']]],
			['name' => 'permissions_get', 'description' => 'Get object ACL rows for a subject (monitor/notify/…).', 'inputSchema' => ['type' => 'object', 'properties' => ['subject' => ['type' => 'string'], 'subject_id' => ['type' => 'integer']], 'required' => ['subject', 'subject_id']]],
			['name' => 'permissions_set', 'description' => 'Replace object ACL rows for a subject.', 'inputSchema' => ['type' => 'object', 'properties' => ['subject' => ['type' => 'string'], 'subject_id' => ['type' => 'integer'], 'rows' => ['type' => 'array']], 'required' => ['subject', 'subject_id', 'rows']]],
		];
	}

	/**
	 * @param array<string,mixed> $user
	 * @param array<string,mixed>|null $arguments
	 * @return array{status:int,data?:mixed,error?:string,http?:int}
	 */
	static public function callTool(array $user, string $name, ?array $arguments = null): array {
		$arguments = $arguments ?? [];
		return match ($name) {
			'api_docs' => \Api\OpenApiSpec::query($arguments),
			'monitors_list' => MonitorsService::list($user, $arguments),
			'monitors_get' => MonitorsService::get($user, intval($arguments['id'] ?? 0)),
			'monitors_create' => MonitorsService::create($user, $arguments),
			'monitors_update' => MonitorsService::update($user, intval($arguments['id'] ?? 0), $arguments),
			'monitors_delete' => MonitorsService::delete($user, intval($arguments['id'] ?? 0)),
			'notify_list' => NotifyService::list($user, $arguments),
			'notify_get' => NotifyService::get($user, intval($arguments['id'] ?? 0)),
			'notify_create' => NotifyService::create($user, $arguments),
			'notify_update' => NotifyService::update($user, intval($arguments['id'] ?? 0), $arguments),
			'notify_delete' => NotifyService::delete($user, intval($arguments['id'] ?? 0)),
			'signatures_list' => SignaturesService::list($user, $arguments),
			'signatures_create' => SignaturesService::create($user, $arguments),
			'signatures_update' => SignaturesService::update($user, intval($arguments['id'] ?? 0), $arguments),
			'signatures_delete' => SignaturesService::delete($user, intval($arguments['id'] ?? 0)),
			'workers_list' => WorkersService::list($user, $arguments),
			'workers_get' => WorkersService::get($user, intval($arguments['id'] ?? 0)),
			'workers_create' => WorkersService::create($user, $arguments),
			'workers_update' => WorkersService::update($user, intval($arguments['id'] ?? 0), $arguments),
			'workers_delete' => WorkersService::delete($user, intval($arguments['id'] ?? 0)),
			'maintenances_list' => MaintenancesService::list($user, $arguments),
			'maintenances_create' => MaintenancesService::create($user, $arguments),
			'maintenances_update' => MaintenancesService::update($user, intval($arguments['id'] ?? 0), $arguments),
			'maintenances_delete' => MaintenancesService::delete($user, intval($arguments['id'] ?? 0)),
			'dockers_list' => DockersService::list($user, $arguments),
			'dockers_get' => DockersService::get($user, intval($arguments['id'] ?? 0)),
			'dockers_create' => DockersService::create($user, $arguments),
			'dockers_update' => DockersService::update($user, intval($arguments['id'] ?? 0), $arguments),
			'dockers_delete' => DockersService::delete($user, intval($arguments['id'] ?? 0)),
			'servers_list' => ServersService::list($user, $arguments),
			'servers_get' => ServersService::get($user, intval($arguments['id'] ?? 0)),
			'servers_create' => ServersService::create($user, $arguments),
			'servers_update' => ServersService::update($user, intval($arguments['id'] ?? 0), $arguments),
			'servers_delete' => ServersService::delete($user, intval($arguments['id'] ?? 0)),
			'users_list' => UsersService::list($user),
			'groups_list' => GroupsService::list($user),
			'logs_list' => LogsService::list($user, $arguments),
			'permissions_get' => PermissionsService::get($user, strval($arguments['subject'] ?? ''), intval($arguments['subject_id'] ?? 0)),
			'permissions_set' => PermissionsService::set($user, strval($arguments['subject'] ?? ''), intval($arguments['subject_id'] ?? 0), $arguments['rows'] ?? []),
			default => Envelope::error('Unknown tool') + ['http' => 404],
		};
	}

	/**
	 * @return list<array{name:string,description:string}>
	 */
	static public function prompts(): array {
		return [
			[
				'name' => 'how_to_create_http_monitor',
				'description' => 'Guide for creating an HTTPS/HTTP JSON monitor with workers and notify channels.',
			],
			[
				'name' => 'how_to_schedule_maintenance',
				'description' => 'Guide for scheduling maintenance with correct time order and monitor links.',
			],
			[
				'name' => 'status_api_vs_management_api',
				'description' => 'Explains /api/v1/status/ opt-in (api_enabled) vs full /api/v2 management access.',
			],
			[
				'name' => 'how_to_add_host_agent',
				'description' => 'Guide for creating a server entry, copying agent_key_hash, and installing monitoring-agent.',
			],
			[
				'name' => 'how_to_create_server_port_monitor',
				'description' => 'Guide for type 17 server listening port monitors: pick server, port 1–65535, port_protocol tcp|udp; agent must report listeners.',
			],
		];
	}
}

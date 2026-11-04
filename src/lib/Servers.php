<?php

class Servers extends \DB\MySQLObject implements \PermissionSubject_Interface {

	static public $table = 'servers';

	const LOGS_OBJECT = 'Servers';

	const PERMISSION_ACCESS = 'server';
	const PERMISSION_SECRETS = 'agent_key_hash';
	const PERMISSION_CREATE = 'create_server';

	const STATUS_DISABLED = 0;
	const STATUS_ENABLED = 1;

	/** Seconds without report before server is considered offline in UI. */
	const ONLINE_WINDOW_SEC = 180;

	/** Virtual worker_id for server-derived monitors. */
	const SERVER_WORKER_ID = -2;

	static public function subject_hash(): array {
		$data_hash = [];
		foreach (static::permissions_subject_hash() as $subject_id => $permissionTitle) {
			if (!\Sessions::checkPermission(static::PERMISSION_ACCESS, $subject_id, ACCESS_WRITE)) {
				continue;
			}
			$data_hash[$subject_id] = $permissionTitle;
		}
		return $data_hash;
	}

	static public function status_hash(): array {
		return [
			static::STATUS_DISABLED => \T::Server_Status_Disabled(),
			static::STATUS_ENABLED => \T::Server_Status_Enabled(),
		];
	}

	static public function permissions_subject_hash(): array {
		$data = static::data(false, ' ORDER BY title ASC');
		$permissions_hash = [];
		foreach ($data as $item) {
			$permissions_hash[$item['id']] = $item['title'];
		}
		return $permissions_hash;
	}

	static public function permissions_hash(): array {
		return [
			static::PERMISSION_ACCESS => \T::Server_Permissions_Access(),
			static::PERMISSION_SECRETS => \T::Server_Permissions_Secret(),
		];
	}

	static public function getUserPermissions(array $user): array {
		return [];
	}

	static public function generateAgentKeyHash(): string {
		do {
			$hash = hash('sha512', md5(microtime() . random_int(100000000, 999999999) . random_bytes(16)));
			$exists = static::get(['agent_key_hash' => $hash]);
		} while ($exists);
		return $hash;
	}

	static public function isOnline(array $server): bool {
		$last = intval($server['last_seen_time'] ?? 0);
		return $last > 0 && ($last + static::ONLINE_WINDOW_SEC) > time();
	}

	/**
	 * @param array<string,mixed> $server
	 */
	static public function effectiveDesiredAgentVersion(array $server): string {
		return \AgentUpdate::desiredForServer($server);
	}

	/**
	 * @param array<string,mixed> $server
	 */
	static public function agentNeedsUpdate(array $server): bool {
		$desired = static::effectiveDesiredAgentVersion($server);
		if ($desired === '') {
			return false;
		}
		$current = trim(strval($server['agent_version'] ?? ''));
		if ($current === '') {
			return true;
		}
		return !\AgentUpdate::versionsEqual($current, $desired);
	}

	/**
	 * @return array<int,string>
	 */
	static public function options_hash_for_user(): array {
		$out = [];
		foreach (static::data(false, ' ORDER BY title ASC') ?: [] as $row) {
			$id = intval($row['id'] ?? 0);
			if ($id < 1) {
				continue;
			}
			if (!\Sessions::checkPermission(static::PERMISSION_ACCESS, $id, READ)) {
				continue;
			}
			$out[$id] = strval($row['title'] ?? '');
		}
		return $out;
	}

	/** Fallback label for the virtual server-agent worker_id. */
	static public function workerTitle(): string {
		return \T::Monitor_Edit_Server();
	}

	/**
	 * Prefer linked server title/hostname for monitor detail UI.
	 *
	 * @param array<string,mixed> $monitor
	 */
	static public function workerTitleForMonitor(array $monitor): string {
		$serverId = intval($monitor['server'] ?? 0);
		if ($serverId > 0) {
			$server = static::get(['id' => $serverId]);
			if ($server) {
				$title = trim(strval($server['title'] ?? ''));
				if ($title !== '') {
					return $title;
				}
				$hostname = trim(strval($server['hostname'] ?? ''));
				if ($hostname !== '') {
					return $hostname;
				}
			}
		}
		return static::workerTitle();
	}
}

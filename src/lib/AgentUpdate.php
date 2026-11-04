<?php

/**
 * Decide whether a host agent should self-update from GitHub Releases.
 */
class AgentUpdate {

	/** Minimum seconds between re-issuing the same update command. */
	const COOLDOWN_SEC = 900;

	/**
	 * Effective desired version for a server row (per-server override, else global).
	 *
	 * @param array<string,mixed> $server
	 */
	static public function desiredForServer(array $server): string {
		$override = \AppOptions::normalizeVersion(strval($server['desired_agent_version'] ?? ''));
		if ($override !== '') {
			return $override;
		}
		return \AppOptions::getDesiredAgentVersion();
	}

	/**
	 * @param array<string,mixed> $server
	 * @param array<string,mixed> $payload
	 * @return array{version:string,url:string,sha256:string,arch:string}|null
	 */
	static public function buildCommand(array $server, array $payload, bool $force = false): ?array {
		$desired = static::desiredForServer($server);
		if ($desired === '') {
			return null;
		}
		$current = trim(strval($payload['agent_version'] ?? $server['agent_version'] ?? ''));
		if ($current !== '' && static::versionsEqual($current, $desired)) {
			return null;
		}
		$host = is_array($payload['host'] ?? null) ? $payload['host'] : [];
		$arch = \GithubReleaseMeta::normalizeArch(strval($host['arch'] ?? $server['arch'] ?? ''));
		if ($arch === '') {
			return null;
		}
		if (!$force) {
			$requestedAt = intval($server['update_requested_at'] ?? 0);
			if ($requestedAt > 0 && ($requestedAt + static::COOLDOWN_SEC) > time()) {
				return null;
			}
		}
		$meta = \GithubReleaseMeta::resolve(\GithubReleaseMeta::PRODUCT_AGENT, $desired, $arch);
		if (!$meta) {
			return null;
		}
		return [
			'version' => $meta['version'],
			'url' => $meta['url'],
			'sha256' => $meta['sha256'],
			'arch' => $meta['arch'],
		];
	}

	static public function versionsEqual(string $a, string $b): bool {
		return \AppOptions::normalizeVersion($a) === \AppOptions::normalizeVersion($b);
	}

	/**
	 * Mark that an update command was sent (starts cooldown).
	 *
	 * @param array<string,mixed> $server
	 */
	static public function markRequested(array $server): void {
		$id = intval($server['id'] ?? 0);
		if ($id < 1) {
			return;
		}
		\Servers::save([
			'id' => $id,
			'update_requested_at' => time(),
			'update_time' => time(),
			'_mode' => \DB\Common::CSMODE_UPDATE,
		]);
	}

	/**
	 * Force next report to receive update (admin "update now").
	 */
	static public function requestNow(int $serverId, string $version = ''): void {
		if ($serverId < 1) {
			return;
		}
		$patch = [
			'id' => $serverId,
			'update_requested_at' => 0,
			'update_error' => '',
			'update_time' => time(),
			'_mode' => \DB\Common::CSMODE_UPDATE,
		];
		$version = \AppOptions::normalizeVersion($version);
		if ($version !== '') {
			$patch['desired_agent_version'] = $version;
		}
		\Servers::save($patch);
	}
}

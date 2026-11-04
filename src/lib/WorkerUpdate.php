<?php

/**
 * Decide whether a Go worker should self-update from GitHub Releases.
 */
class WorkerUpdate {

	const COOLDOWN_SEC = 900;

	/**
	 * @param array<string,mixed> $worker
	 */
	static public function desiredForWorker(array $worker): string {
		$override = \AppOptions::normalizeVersion(strval($worker['desired_worker_version'] ?? ''));
		if ($override !== '') {
			return $override;
		}
		return \AppOptions::getDesiredWorkerVersion();
	}

	/**
	 * @param array<string,mixed> $worker
	 * @param array<string,mixed> $payload
	 * @return array{version:string,url:string,sha256:string,arch:string}|null
	 */
	static public function buildCommand(array $worker, array $payload, bool $force = false): ?array {
		$desired = static::desiredForWorker($worker);
		if ($desired === '') {
			return null;
		}
		$current = trim(strval($payload['worker_version'] ?? $worker['worker_version'] ?? ''));
		if ($current !== '' && static::versionsEqual($current, $desired)) {
			return null;
		}
		$arch = \GithubReleaseMeta::normalizeArch(strval($payload['arch'] ?? $worker['arch'] ?? ''));
		if ($arch === '') {
			return null;
		}
		if (!$force) {
			$requestedAt = intval($worker['update_requested_at'] ?? 0);
			if ($requestedAt > 0 && ($requestedAt + static::COOLDOWN_SEC) > time()) {
				return null;
			}
		}
		$meta = \GithubReleaseMeta::resolve(\GithubReleaseMeta::PRODUCT_WORKER, $desired, $arch);
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
	 * @param array<string,mixed> $worker
	 */
	static public function markRequested(array $worker): void {
		$id = intval($worker['id'] ?? 0);
		if ($id < 1) {
			return;
		}
		\Workers::save([
			'id' => $id,
			'update_requested_at' => time(),
			'update_time' => time(),
			'_mode' => \DB\Common::CSMODE_UPDATE,
		]);
	}

	static public function requestNow(int $workerId, string $version = ''): void {
		if ($workerId < 1) {
			return;
		}
		$patch = [
			'id' => $workerId,
			'update_requested_at' => 0,
			'update_error' => '',
			'update_time' => time(),
			'_mode' => \DB\Common::CSMODE_UPDATE,
		];
		$version = \AppOptions::normalizeVersion($version);
		if ($version !== '') {
			$patch['desired_worker_version'] = $version;
		}
		\Workers::save($patch);
	}
}

<?php

/**
 * Cached GitHub Release asset metadata (url + sha256) for agent/worker binaries.
 */
class GithubReleaseMeta extends \DB\MySQLObject {

	static public $table = 'github_release_meta';

	const PRODUCT_AGENT = 'agent';
	const PRODUCT_WORKER = 'worker';
	const CACHE_TTL_SEC = 3600;

	/** @var array<string,array{repo:string,asset:string}> */
	const PRODUCTS = [
		self::PRODUCT_AGENT => [
			'repo' => 'AKEB/monitoring-agent',
			'asset' => 'monitoring-agent-linux-',
		],
		self::PRODUCT_WORKER => [
			'repo' => 'AKEB/monitoring-worker-go',
			'asset' => 'monitoring-worker-linux-',
		],
	];

	/**
	 * @return array{version:string,arch:string,url:string,sha256:string}|null
	 */
	static public function resolve(string $product, string $version, string $arch): ?array {
		$product = trim($product);
		if (!isset(static::PRODUCTS[$product])) {
			return null;
		}
		$version = \AppOptions::normalizeVersion($version);
		$arch = static::normalizeArch($arch);
		if ($version === '' || $arch === '') {
			return null;
		}
		$cached = static::get(['product' => $product, 'version' => $version, 'arch' => $arch]);
		$now = time();
		if ($cached
			&& strval($cached['url'] ?? '') !== ''
			&& strval($cached['sha256'] ?? '') !== ''
			&& intval($cached['fetched_at'] ?? 0) + static::CACHE_TTL_SEC > $now
		) {
			return [
				'version' => $version,
				'arch' => $arch,
				'url' => strval($cached['url']),
				'sha256' => strval($cached['sha256']),
			];
		}
		$meta = static::fetchFromGitHub($product, $version, $arch);
		if (!$meta) {
			if ($cached && strval($cached['url'] ?? '') !== '' && strval($cached['sha256'] ?? '') !== '') {
				return [
					'version' => $version,
					'arch' => $arch,
					'url' => strval($cached['url']),
					'sha256' => strval($cached['sha256']),
				];
			}
			return null;
		}
		static::upsert($product, $meta);
		return $meta;
	}

	/**
	 * @param array{version:string,arch:string,url:string,sha256:string} $meta
	 */
	static private function upsert(string $product, array $meta): void {
		$now = time();
		// Table PK is (product, version, arch) — no `id` column. Framework save()
		// always injects $ref_name (default `id`), so pass an existing column.
		static::save([
			'product' => $product,
			'version' => $meta['version'],
			'arch' => $meta['arch'],
			'url' => $meta['url'],
			'sha256' => $meta['sha256'],
			'fetched_at' => $now,
			'_mode' => \DB\Common::CSMODE_INSERT,
			'_on_duplicate' => sql_pholder(
				'`url`=?, `sha256`=?, `fetched_at`=?',
				$meta['url'],
				$meta['sha256'],
				$now
			),
		], '', 'product');
	}

	/**
	 * @return array{version:string,arch:string,url:string,sha256:string}|null
	 */
	static private function fetchFromGitHub(string $product, string $version, string $arch): ?array {
		$cfg = static::PRODUCTS[$product];
		$repo = $cfg['repo'];
		$assetName = $cfg['asset'].$arch;
		$shaName = $assetName.'.sha256';
		$binURL = 'https://github.com/'.$repo.'/releases/download/'.$version.'/'.$assetName;
		$shaURL = 'https://github.com/'.$repo.'/releases/download/'.$version.'/'.$shaName;

		// Prefer GitHub API when available (resolves asset CDN urls), but do not
		// hard-fail on 403 rate limits — public release download URLs still work.
		$apiURL = 'https://api.github.com/repos/'.$repo.'/releases/tags/'.rawurlencode($version);
		$release = static::httpGetJSON($apiURL);
		if (is_array($release)) {
			$assets = is_array($release['assets'] ?? null) ? $release['assets'] : [];
			foreach ($assets as $asset) {
				if (!is_array($asset)) {
					continue;
				}
				$name = strval($asset['name'] ?? '');
				$url = strval($asset['browser_download_url'] ?? '');
				if ($name === $assetName && $url !== '') {
					$binURL = $url;
				} elseif ($name === $shaName && $url !== '') {
					$shaURL = $url;
				}
			}
		}

		$sha256 = static::parseSha256(static::httpGetBody($shaURL));
		if ($sha256 === '') {
			$checksumsURL = 'https://github.com/'.$repo.'/releases/download/'.$version.'/checksums.txt';
			$sha256 = static::parseSha256FromChecksums(static::httpGetBody($checksumsURL), $assetName);
		}
		if ($sha256 === '') {
			$sha256 = static::sha256OfURL($binURL);
		}
		if ($sha256 === '') {
			return null;
		}
		return [
			'version' => $version,
			'arch' => $arch,
			'url' => $binURL,
			'sha256' => $sha256,
		];
	}

	static private function sha256OfURL(string $url): string {
		$body = static::httpGetBody($url);
		if ($body === '' || strlen($body) < 1024) {
			return '';
		}
		return hash('sha256', $body);
	}

	static public function normalizeArch(string $arch): string {
		$arch = strtolower(trim($arch));
		return match ($arch) {
			'amd64', 'x86_64', 'x64' => 'amd64',
			'arm64', 'aarch64' => 'arm64',
			default => '',
		};
	}

	static private function parseSha256(string $body): string {
		$body = trim($body);
		if ($body === '') {
			return '';
		}
		if (preg_match('/\b([a-fA-F0-9]{64})\b/', $body, $m)) {
			return strtolower($m[1]);
		}
		return '';
	}

	static private function parseSha256FromChecksums(string $body, string $assetName): string {
		foreach (preg_split('/\R/', $body) ?: [] as $line) {
			$line = trim($line);
			if ($line === '' || !str_contains($line, $assetName)) {
				continue;
			}
			$sum = static::parseSha256($line);
			if ($sum !== '') {
				return $sum;
			}
		}
		return '';
	}

	/** @return array<string,mixed>|null */
	static private function httpGetJSON(string $url): ?array {
		$body = static::httpGetBody($url);
		if ($body === '') {
			return null;
		}
		$decoded = @json_decode($body, true);
		return is_array($decoded) ? $decoded : null;
	}

	static private function httpGetBody(string $url): string {
		if (function_exists('curl_init')) {
			$ch = curl_init($url);
			if ($ch === false) {
				return '';
			}
			curl_setopt_array($ch, [
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_TIMEOUT => 60,
				CURLOPT_USERAGENT => 'akeb-monitoring-server-release-meta',
				CURLOPT_HTTPHEADER => ['Accept: application/vnd.github+json'],
			]);
			$body = curl_exec($ch);
			$code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
			curl_close($ch);
			if ($body === false || $code < 200 || $code >= 300) {
				return '';
			}
			return strval($body);
		}
		$ctx = stream_context_create([
			'http' => [
				'method' => 'GET',
				'timeout' => 60,
				'header' => "User-Agent: akeb-monitoring-server-release-meta\r\nAccept: application/vnd.github+json\r\n",
			],
		]);
		$body = @file_get_contents($url, false, $ctx);
		return is_string($body) ? $body : '';
	}
}

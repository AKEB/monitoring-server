<?php

/**
 * Public shields-style SVG badges for monitors (Uptime Kuma–like).
 */
class BadgeSvg {

	const STATUS_UP = 'up';
	const STATUS_DOWN = 'down';
	const STATUS_MAINTENANCE = 'maintenance';
	const STATUS_UNKNOWN = 'unknown';

	/** Right-side fills (approx. Bootstrap success/danger/warning/secondary). */
	const COLORS = [
		self::STATUS_UP => '#198754',
		self::STATUS_DOWN => '#dc3545',
		self::STATUS_MAINTENANCE => '#ffc107',
		self::STATUS_UNKNOWN => '#6c757d',
	];

	const LABEL_BG = '#555';
	const TEXT_LIGHT = '#fff';
	const TEXT_DARK = '#212529';

	static public function isValidHash(string $hash): bool {
		return (bool) preg_match('/^[a-fA-F0-9]{64}$/', $hash);
	}

	static public function generateHash(): string {
		do {
			$hash = bin2hex(random_bytes(32));
			$exists = \Monitors::get(['badge_hash' => $hash]);
		} while ($exists);
		return $hash;
	}

	static public function badgeUrl(string $hash): string {
		$hash = trim($hash);
		if ($hash === '' || !static::isValidHash($hash)) {
			return '';
		}
		return \ExternalMonitors::baseUrl().'/badge/'.rawurlencode(strtolower($hash)).'.svg';
	}

	static public function statusLabel(string $status): string {
		return match ($status) {
			self::STATUS_UP => 'Up',
			self::STATUS_DOWN => 'Down',
			self::STATUS_MAINTENANCE => 'Maintenance',
			default => 'Unknown',
		};
	}

	static public function statusColor(string $status): string {
		return self::COLORS[$status] ?? self::COLORS[self::STATUS_UNKNOWN];
	}

	/**
	 * Map MonitoringApiStatus status codes to badge status.
	 */
	static public function normalizeStatus(string $status): string {
		$status = strtolower(trim($status));
		return match ($status) {
			\MonitoringApiStatus::STATUS_UP, self::STATUS_UP => self::STATUS_UP,
			\MonitoringApiStatus::STATUS_DOWN, self::STATUS_DOWN => self::STATUS_DOWN,
			\MonitoringApiStatus::STATUS_MAINTENANCE, self::STATUS_MAINTENANCE => self::STATUS_MAINTENANCE,
			default => self::STATUS_UNKNOWN,
		};
	}

	/**
	 * Resolve live status for a monitor row (no api_enabled filter).
	 */
	static public function statusForMonitor(array $monitor): string {
		if (intval($monitor['status'] ?? 0) !== 1) {
			return self::STATUS_UNKNOWN;
		}
		$id = intval($monitor['id'] ?? 0);
		if ($id < 1) {
			return self::STATUS_UNKNOWN;
		}
		try {
			if (\MonitoringMaintenances::getMaintenancesForMonitor($id)) {
				return self::STATUS_MAINTENANCE;
			}
			$payload = \MonitoringApiStatus::buildPayloadForMonitors([$monitor]);
			$code = strval($payload['resources'][0]['status'] ?? self::STATUS_UNKNOWN);
			return static::normalizeStatus($code);
		} catch (\Throwable $e) {
			return self::STATUS_UNKNOWN;
		}
	}

	/**
	 * Approximate text width for DejaVu-like sans at 11px (shields heuristic).
	 */
	static public function textWidth(string $text): float {
		$len = mb_strlen($text);
		if ($len < 1) {
			return 0.0;
		}
		return $len * 6.5 + 10;
	}

	static public function render(string $label, string $status): string {
		$status = static::normalizeStatus($status);
		$label = trim($label);
		if ($label === '') {
			$label = 'monitor';
		}
		$label = mb_substr($label, 0, 48);
		$right = static::statusLabel($status);
		$leftW = max(28.0, static::textWidth($label));
		$rightW = max(28.0, static::textWidth($right));
		$total = $leftW + $rightW;
		$color = static::statusColor($status);
		$rightText = ($status === self::STATUS_MAINTENANCE) ? self::TEXT_DARK : self::TEXT_LIGHT;

		$esc = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');

		return '<?xml version="1.0" encoding="UTF-8"?>'
			.'<svg xmlns="http://www.w3.org/2000/svg" width="'.round($total).'" height="20" role="img" aria-label="'.$esc($label.': '.$right).'">'
			.'<title>'.$esc($label.': '.$right).'</title>'
			.'<linearGradient id="s" x2="0" y2="100%">'
			.'<stop offset="0" stop-color="#bbb" stop-opacity=".1"/>'
			.'<stop offset="1" stop-opacity=".1"/>'
			.'</linearGradient>'
			.'<clipPath id="r"><rect width="'.round($total).'" height="20" rx="3" fill="#fff"/></clipPath>'
			.'<g clip-path="url(#r)">'
			.'<rect width="'.round($leftW).'" height="20" fill="'.self::LABEL_BG.'"/>'
			.'<rect x="'.round($leftW).'" width="'.round($rightW).'" height="20" fill="'.$color.'"/>'
			.'<rect width="'.round($total).'" height="20" fill="url(#s)"/>'
			.'</g>'
			.'<g fill="'.self::TEXT_LIGHT.'" text-anchor="middle" font-family="DejaVu Sans,Verdana,Geneva,sans-serif" font-size="11" text-rendering="geometricPrecision">'
			.'<text x="'.round($leftW / 2).'" y="14">'.$esc($label).'</text>'
			.'</g>'
			.'<g fill="'.$rightText.'" text-anchor="middle" font-family="DejaVu Sans,Verdana,Geneva,sans-serif" font-size="11" text-rendering="geometricPrecision">'
			.'<text x="'.round($leftW + $rightW / 2).'" y="14">'.$esc($right).'</text>'
			.'</g>'
			.'</svg>';
	}

	/**
	 * @return array{monitor:?array, svg:string, http:int}
	 */
	static public function renderForHash(string $hash): array {
		$hash = strtolower(trim($hash));
		if (!static::isValidHash($hash)) {
			return ['monitor' => null, 'svg' => '', 'http' => 404];
		}
		try {
			$monitor = \Monitors::get(['badge_hash' => $hash]);
		} catch (\Throwable $e) {
			return ['monitor' => null, 'svg' => '', 'http' => 404];
		}
		if (!$monitor || intval($monitor['badge_enabled'] ?? 0) !== 1) {
			return ['monitor' => null, 'svg' => '', 'http' => 404];
		}
		$status = static::statusForMonitor($monitor);
		$label = strval($monitor['title'] ?? 'monitor');
		return [
			'monitor' => $monitor,
			'svg' => static::render($label, $status),
			'http' => 200,
		];
	}
}

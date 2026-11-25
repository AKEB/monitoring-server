<?php

namespace App\Status;

/**
 * Anonymous public status page (no auth). Visual language inspired by Uptime Kuma.
 */
class PublicPage extends \Routing_Parent implements \Routing_Interface {

	const LANG_COOKIE = 'status_lang';
	const LANGS = ['en', 'ru'];

	public function Run(string $slug = '') {
		$lang = static::resolveLanguage();
		\T::setCurrentLanguage($lang);

		$slug = \StatusPageMembership::normalizeSlug($slug);
		$data = \StatusPageBuilder::buildBySlugCached($slug);
		if (!$data) {
			http_response_code(404);
			header('Content-Type: text/html; charset=utf-8');
			echo '<!DOCTYPE html><html lang="'.htmlspecialchars($lang, ENT_QUOTES, 'UTF-8').'"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
			echo '<title>'.htmlspecialchars(\T::StatusPages_Public_NotFoundTitle(), ENT_QUOTES, 'UTF-8').'</title>';
			echo '<link rel="stylesheet" href="/css/status-public.css"></head>';
			echo '<body><div class="sp-wrap">';
			echo static::langSwitcherHtml($lang);
			echo '<h1 class="sp-title">'.htmlspecialchars(\T::StatusPages_Public_NotFoundTitle(), ENT_QUOTES, 'UTF-8').'</h1>';
			echo '<p class="sp-empty">'.htmlspecialchars(\T::StatusPages_Public_NotFoundBody(), ENT_QUOTES, 'UTF-8').'</p></div></body></html>';
			return;
		}

		header('Content-Type: text/html; charset=utf-8');
		header('Cache-Control: public, max-age=30');
		header('Vary: Cookie, Accept-Language');

		$title = htmlspecialchars(strval($data['title'] ?? 'Status'), ENT_QUOTES, 'UTF-8');
		$generated = intval($data['generated_at'] ?? time());
		$overallCode = strval($data['overall']['code'] ?? 'unknown');
		$overallLabel = htmlspecialchars(static::overallLabel($overallCode), ENT_QUOTES, 'UTF-8');
		$overallCodeSafe = htmlspecialchars($overallCode, ENT_QUOTES, 'UTF-8');

		echo '<!DOCTYPE html><html lang="'.htmlspecialchars($lang, ENT_QUOTES, 'UTF-8').'"><head><meta charset="UTF-8">';
		echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
		echo '<meta name="robots" content="noindex">';
		echo '<title>'.$title.'</title>';
		echo '<link rel="icon" href="/images/favicon.ico" type="image/x-icon">';
		echo '<link rel="stylesheet" href="/css/status-public.css">';
		echo '</head><body><div class="sp-wrap">';

		echo static::langSwitcherHtml($lang);

		echo '<h1 class="sp-title">';
		echo '<img class="sp-logo" src="/images/apple-icon-180x180.png" width="42" height="42" alt="">';
		echo '<span>'.$title.'</span></h1>';

		foreach ($data['incidents'] ?? [] as $incident) {
			$state = strval($incident['state'] ?? 'investigating');
			$stateClass = preg_replace('/[^a-z0-9_\-]/i', '', $state) ?: 'investigating';
			$it = htmlspecialchars(strval($incident['title'] ?? ''), ENT_QUOTES, 'UTF-8');
			$im = nl2br(htmlspecialchars(strval($incident['message'] ?? ''), ENT_QUOTES, 'UTF-8'));
			$is = htmlspecialchars(static::incidentStateLabel($state), ENT_QUOTES, 'UTF-8');
			$when = intval($incident['create_time'] ?? 0);
			echo '<div class="sp-card sp-incident is-'.$stateClass.'" role="alert">';
			echo '<div class="sp-state">'.$is.'</div>';
			echo '<h3>'.$it.'</h3>';
			echo '<div class="sp-msg">'.$im.'</div>';
			if ($when > 0) {
				echo '<div class="sp-when">'.htmlspecialchars(gmdate('Y-m-d H:i', $when), ENT_QUOTES, 'UTF-8').' UTC</div>';
			}
			echo '</div>';
		}

		echo '<div class="sp-card sp-overall is-'.$overallCodeSafe.'">';
		echo static::overallIconSvg($overallCode);
		echo '<span>'.$overallLabel.'</span></div>';

		$groups = $data['groups'] ?? [];
		if (!$groups) {
			echo '<p class="sp-empty">'.htmlspecialchars(\T::StatusPages_Public_NoMonitors(), ENT_QUOTES, 'UTF-8').'</p>';
		}

		$renderedGroups = 0;
		foreach ($groups as $group) {
			$resources = $group['resources'] ?? [];
			if (!$resources) {
				continue;
			}
			$renderedGroups++;
			$groupTitle = strval($group['title'] ?? '');
			if (intval($group['id'] ?? -1) === 0 || $groupTitle === \StatusPageMembership::OTHER_GROUP_TITLE) {
				$groupTitle = \T::StatusPages_Public_OtherGroup();
			}
			$gt = htmlspecialchars($groupTitle, ENT_QUOTES, 'UTF-8');
			echo '<section class="sp-group"><h2>'.$gt.'</h2>';
			echo '<div class="sp-card sp-list">';
			foreach ($resources as $res) {
				static::renderResource($res);
			}
			echo '</div></section>';
		}
		if ($groups && $renderedGroups === 0) {
			echo '<p class="sp-empty">'.htmlspecialchars(\T::StatusPages_Public_NoMonitors(), ENT_QUOTES, 'UTF-8').'</p>';
		}

		foreach ($data['maintenances'] ?? [] as $maint) {
			$mt = htmlspecialchars(strval($maint['title'] ?? \T::StatusPages_Public_StateMaintenance()), ENT_QUOTES, 'UTF-8');
			echo '<div class="sp-card sp-incident is-monitoring" role="status">';
			echo '<div class="sp-state">'.htmlspecialchars(\T::StatusPages_Public_StateMaintenance(), ENT_QUOTES, 'UTF-8').'</div><h3>'.$mt.'</h3></div>';
		}

		$updatedTpl = \T::StatusPages_Public_Updated('%s');
		$updatedFallback = \T::StatusPages_Public_Updated(gmdate('Y-m-d H:i:s', $generated).' UTC');
		echo '<footer class="sp-footer">';
		echo '<span class="sp-updated" data-time="'.intval($generated).'" data-template="'
			.htmlspecialchars($updatedTpl, ENT_QUOTES, 'UTF-8').'">'
			.htmlspecialchars($updatedFallback, ENT_QUOTES, 'UTF-8').'</span>';
		echo '<span>'.htmlspecialchars(\T::StatusPages_Public_PoweredBy(), ENT_QUOTES, 'UTF-8');
		echo ' <a href="https://akeb.github.io/monitoring/" rel="noopener">Monitoring</a></span>';
		echo '</footer>';

		echo '<script src="/js/status-public.js" defer></script>';
		echo '</div></body></html>';
	}

	static public function resolveLanguage(): string {
		$raw = '';
		if (isset($_GET['lang']) && is_string($_GET['lang'])) {
			$raw = strtolower(trim($_GET['lang']));
		} elseif (isset($_COOKIE[self::LANG_COOKIE]) && is_string($_COOKIE[self::LANG_COOKIE])) {
			$raw = strtolower(trim($_COOKIE[self::LANG_COOKIE]));
		} elseif (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE']) && is_string($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
			$raw = strtolower(substr(trim($_SERVER['HTTP_ACCEPT_LANGUAGE']), 0, 2));
		}
		$lang = in_array($raw, self::LANGS, true) ? $raw : 'en';
		if (isset($_GET['lang']) && in_array($lang, self::LANGS, true)) {
			$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
				|| (isset($_SERVER['SERVER_PORT']) && intval($_SERVER['SERVER_PORT']) === 443);
			setcookie(self::LANG_COOKIE, $lang, [
				'expires' => time() + 365 * 86400,
				'path' => '/',
				'secure' => $secure,
				'httponly' => false,
				'samesite' => 'Lax',
			]);
			$_COOKIE[self::LANG_COOKIE] = $lang;
		}
		return $lang;
	}

	static private function langSwitcherHtml(string $lang): string {
		$path = strval(parse_url(strval($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
		$query = [];
		parse_str(strval(parse_url(strval($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY) ?: ''), $query);
		$html = '<nav class="sp-lang" aria-label="'.htmlspecialchars(\T::StatusPages_Public_Language(), ENT_QUOTES, 'UTF-8').'">';
		foreach (self::LANGS as $code) {
			$query['lang'] = $code;
			$href = htmlspecialchars($path.'?'.http_build_query($query), ENT_QUOTES, 'UTF-8');
			$label = $code === 'ru' ? 'RU' : 'EN';
			$cls = $code === $lang ? ' is-active' : '';
			$html .= '<a class="sp-lang-link'.$cls.'" href="'.$href.'" hreflang="'.$code.'" lang="'.$code.'">'.$label.'</a>';
		}
		$html .= '</nav>';
		return $html;
	}

	static private function overallLabel(string $code): string {
		return match ($code) {
			'ok' => \T::StatusPages_Public_OverallOk(),
			'down' => \T::StatusPages_Public_OverallDown(),
			'partial' => \T::StatusPages_Public_OverallPartial(),
			'maint' => \T::StatusPages_Public_OverallMaint(),
			'empty' => \T::StatusPages_Public_OverallNoServices(),
			default => \T::StatusPages_Public_OverallUnknown(),
		};
	}

	static private function incidentStateLabel(string $state): string {
		return match ($state) {
			'investigating' => \T::StatusPages_Edit_StateInvestigating(),
			'identified' => \T::StatusPages_Edit_StateIdentified(),
			'monitoring' => \T::StatusPages_Edit_StateMonitoring(),
			'resolved' => \T::StatusPages_Edit_StateResolved(),
			default => $state,
		};
	}

	/**
	 * Split a leading country-flag emoji (regional-indicator pair) from a title
	 * so the flag can sit in a fixed-width column and names stay aligned.
	 *
	 * @return array{0:string,1:string} [flag, rest]
	 */
	static private function splitLeadingFlag(string $title): array {
		if (preg_match('/^([\x{1F1E6}-\x{1F1FF}]{2})\s*(.*)$/u', $title, $m)) {
			return [$m[1], $m[2]];
		}
		return ['', $title];
	}

	/** @param array<string,mixed> $res */
	static private function renderResource(array $res): void {
		[$flag, $name] = static::splitLeadingFlag(strval($res['title'] ?? ''));
		$flagHtml = htmlspecialchars($flag, ENT_QUOTES, 'UTF-8');
		$rt = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
		$st = strval($res['status'] ?? 'unknown');
		$url = trim(strval($res['check_url'] ?? ''));
		$badgeClass = match ($st) {
			'up' => 'is-up',
			'down' => 'is-down',
			'degraded' => 'is-degraded',
			'maintenance' => 'is-maintenance',
			default => 'is-pending',
		};
		$badgeLabel = match ($st) {
			'up' => \T::StatusPages_Public_StatusUp(),
			'down' => \T::StatusPages_Public_StatusDown(),
			'degraded' => \T::StatusPages_Public_StatusDegraded(),
			'maintenance' => \T::StatusPages_Public_StatusMaintenance(),
			default => \T::StatusPages_Public_StatusPending(),
		};

		// Uptime pill color follows current aggregated status (same idea as main list badge_class).
		$uptimeClass = match ($st) {
			'up' => '',
			'degraded' => 'is-warn',
			'down' => 'is-bad',
			'maintenance' => 'is-maint',
			default => 'is-empty',
		};
		$uptimeRaw = $res['availability']['8h'] ?? null;
		$availTitle = htmlspecialchars(\T::StatusPages_Public_Availability8h(), ENT_QUOTES, 'UTF-8');
		$uptimeHtml = '<span class="sp-uptime '.$uptimeClass.'" title="'.$availTitle.'">—</span>';
		if (is_numeric($uptimeRaw)) {
			$pct = floatval($uptimeRaw);
			$uptimeHtml = '<span class="sp-uptime '.$uptimeClass.'" title="'.$availTitle.'">'
				.htmlspecialchars(rtrim(rtrim(number_format($pct, 2, '.', ''), '0'), '.').'%', ENT_QUOTES, 'UTF-8')
				.'</span>';
		}

		echo '<div class="sp-item">';
		echo '<div class="sp-item-main">'.$uptimeHtml;
		echo '<span class="sp-flag" aria-hidden="true">'.$flagHtml.'</span>';
		if ($url !== '' && preg_match('#^https?://#i', $url)) {
			$href = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
			echo '<p class="sp-name"><a href="'.$href.'" target="_blank" rel="noopener noreferrer">'.$rt.'</a></p>';
		} else {
			echo '<p class="sp-name">'.$rt.'</p>';
		}
		echo '</div>';

		echo '<div class="sp-beats">';
		$beats = is_array($res['beats'] ?? null) ? $res['beats'] : [];
		if (!$beats) {
			for ($i = 0; $i < 24; $i++) {
				echo '<span class="sp-beat"></span>';
			}
		} else {
			foreach ($beats as $beat) {
				if (is_array($beat)) {
					$b = strval($beat['status'] ?? 'pending');
					$time = intval($beat['time'] ?? 0);
				} else {
					$b = strval($beat);
					$time = 0;
				}
				$class = match ($b) {
					'up' => 'is-up',
					'down' => 'is-down',
					'degraded' => 'is-degraded',
					'maint', 'maintenance' => 'is-maint',
					default => 'is-pending',
				};
				// data-time is unix; status-public.js rewrites title into the browser timezone.
				$timeAttr = '';
				if ($time > 0) {
					$fallback = htmlspecialchars(gmdate('Y-m-d H:i:s', $time).' UTC', ENT_QUOTES, 'UTF-8');
					$timeAttr = ' data-time="'.$time.'" title="'.$fallback.'"';
				}
				echo '<span class="sp-beat '.$class.'"'.$timeAttr.'></span>';
			}
		}
		echo '</div>';

		echo '<span class="sp-badge '.$badgeClass.'">'.htmlspecialchars($badgeLabel, ENT_QUOTES, 'UTF-8').'</span>';
		echo '</div>';
	}

	static private function overallIconSvg(string $code): string {
		$common = ' class="sp-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"';
		return match ($code) {
			'ok' => '<svg'.$common.'><path d="M12 2a10 10 0 1 0 .001 20.001A10 10 0 0 0 12 2Zm-1.1 14.2-3.6-3.6 1.4-1.4 2.2 2.2 5-5 1.4 1.4-6.4 6.4Z"/></svg>',
			'down' => '<svg'.$common.'><path d="M12 2a10 10 0 1 0 .001 20.001A10 10 0 0 0 12 2Zm3.5 12.1-1.4 1.4L12 13.4l-2.1 2.1-1.4-1.4L10.6 12 8.5 9.9l1.4-1.4L12 10.6l2.1-2.1 1.4 1.4L13.4 12l2.1 2.1Z"/></svg>',
			'maint' => '<svg'.$common.'><path d="M12 2a10 10 0 1 0 .001 20.001A10 10 0 0 0 12 2Zm1 5v6h-2V7h2Zm0 8v2h-2v-2h2Z"/></svg>',
			'partial' => '<svg'.$common.'><path d="M12 2a10 10 0 1 0 .001 20.001A10 10 0 0 0 12 2Zm1 5v7h-2V7h2Zm0 9v2h-2v-2h2Z"/></svg>',
			default => '<svg'.$common.'><path d="M12 2a10 10 0 1 0 .001 20.001A10 10 0 0 0 12 2Zm1 14h-2v-2h2v2Zm0-4h-2V6h2v6Z"/></svg>',
		};
	}
}

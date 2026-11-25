<?php

namespace App\Status;

/**
 * Anonymous public status page (no auth). Visual language inspired by Uptime Kuma.
 */
class PublicPage extends \Routing_Parent implements \Routing_Interface {

	public function Run(string $slug = '') {
		$slug = \StatusPageMembership::normalizeSlug($slug);
		$data = \StatusPageBuilder::buildBySlugCached($slug);
		if (!$data) {
			http_response_code(404);
			header('Content-Type: text/html; charset=utf-8');
			echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
			echo '<title>Not found</title><link rel="stylesheet" href="/css/status-public.css"></head>';
			echo '<body><div class="sp-wrap"><h1 class="sp-title">Status page not found</h1>';
			echo '<p class="sp-empty">Unknown or disabled slug.</p></div></body></html>';
			return;
		}

		header('Content-Type: text/html; charset=utf-8');
		header('Cache-Control: public, max-age=30');

		$title = htmlspecialchars(strval($data['title'] ?? 'Status'), ENT_QUOTES, 'UTF-8');
		$generated = intval($data['generated_at'] ?? time());
		$overall = is_array($data['overall'] ?? null) ? $data['overall'] : ['code' => 'unknown', 'label' => 'Status Unknown'];
		$overallCode = htmlspecialchars(strval($overall['code'] ?? 'unknown'), ENT_QUOTES, 'UTF-8');
		$overallLabel = htmlspecialchars(strval($overall['label'] ?? 'Status Unknown'), ENT_QUOTES, 'UTF-8');

		echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">';
		echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
		echo '<meta name="robots" content="noindex">';
		echo '<title>'.$title.'</title>';
		echo '<link rel="icon" href="/images/favicon.ico" type="image/x-icon">';
		echo '<link rel="stylesheet" href="/css/status-public.css">';
		echo '</head><body><div class="sp-wrap">';

		echo '<h1 class="sp-title">';
		echo '<img class="sp-logo" src="/images/apple-icon-180x180.png" width="42" height="42" alt="">';
		echo '<span>'.$title.'</span></h1>';

		foreach ($data['incidents'] ?? [] as $incident) {
			$state = strval($incident['state'] ?? 'investigating');
			$stateClass = preg_replace('/[^a-z0-9_\-]/i', '', $state) ?: 'investigating';
			$it = htmlspecialchars(strval($incident['title'] ?? ''), ENT_QUOTES, 'UTF-8');
			$im = nl2br(htmlspecialchars(strval($incident['message'] ?? ''), ENT_QUOTES, 'UTF-8'));
			$is = htmlspecialchars($state, ENT_QUOTES, 'UTF-8');
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

		echo '<div class="sp-card sp-overall is-'.$overallCode.'">';
		echo static::overallIconSvg($overallCode);
		echo '<span>'.$overallLabel.'</span></div>';

		$groups = $data['groups'] ?? [];
		if (!$groups) {
			echo '<p class="sp-empty">This status page has no monitors yet.</p>';
		}

		$renderedGroups = 0;
		foreach ($groups as $group) {
			$resources = $group['resources'] ?? [];
			if (!$resources) {
				continue;
			}
			$renderedGroups++;
			$gt = htmlspecialchars(strval($group['title'] ?? ''), ENT_QUOTES, 'UTF-8');
			echo '<section class="sp-group"><h2>'.$gt.'</h2>';
			echo '<div class="sp-card sp-list">';
			foreach ($resources as $res) {
				static::renderResource($res);
			}
			echo '</div></section>';
		}
		if ($groups && $renderedGroups === 0) {
			echo '<p class="sp-empty">This status page has no monitors yet.</p>';
		}

		foreach ($data['maintenances'] ?? [] as $maint) {
			$mt = htmlspecialchars(strval($maint['title'] ?? 'Maintenance'), ENT_QUOTES, 'UTF-8');
			echo '<div class="sp-card sp-incident is-monitoring" role="status">';
			echo '<div class="sp-state">maintenance</div><h3>'.$mt.'</h3></div>';
		}

		echo '<footer class="sp-footer">';
		echo '<span>Updated '.htmlspecialchars(gmdate('Y-m-d H:i:s', $generated), ENT_QUOTES, 'UTF-8').' UTC</span>';
		echo '<span>Powered by <a href="https://akeb.github.io/monitoring/" rel="noopener">Monitoring</a></span>';
		echo '</footer>';

		echo '</div></body></html>';
	}

	/** @param array<string,mixed> $res */
	static private function renderResource(array $res): void {
		$rt = htmlspecialchars(strval($res['title'] ?? ''), ENT_QUOTES, 'UTF-8');
		$st = strval($res['status'] ?? 'unknown');
		$url = trim(strval($res['check_url'] ?? ''));
		$badgeClass = match ($st) {
			'up' => 'is-up',
			'down' => 'is-down',
			'maintenance' => 'is-maintenance',
			default => 'is-pending',
		};
		$badgeLabel = match ($st) {
			'up' => 'Up',
			'down' => 'Down',
			'maintenance' => 'Maintenance',
			default => 'Pending',
		};

		$uptimeRaw = $res['availability']['8h'] ?? null;
		$uptimeHtml = '<span class="sp-uptime is-empty" title="8h availability">—</span>';
		if (is_numeric($uptimeRaw)) {
			$pct = floatval($uptimeRaw);
			$uptimeClass = $pct >= 99.0 ? '' : ($pct >= 95.0 ? 'is-warn' : 'is-bad');
			$uptimeHtml = '<span class="sp-uptime '.$uptimeClass.'" title="8h availability">'
				.htmlspecialchars(rtrim(rtrim(number_format($pct, 2, '.', ''), '0'), '.').'%', ENT_QUOTES, 'UTF-8')
				.'</span>';
		}

		echo '<div class="sp-item">';
		echo '<div class="sp-item-main">'.$uptimeHtml;
		if ($url !== '' && preg_match('#^https?://#i', $url)) {
			$href = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
			echo '<p class="sp-name"><a href="'.$href.'" target="_blank" rel="noopener noreferrer">'.$rt.'</a></p>';
		} else {
			echo '<p class="sp-name">'.$rt.'</p>';
		}
		echo '</div>';

		echo '<div class="sp-beats" aria-hidden="true">';
		$beats = is_array($res['beats'] ?? null) ? $res['beats'] : [];
		if (!$beats) {
			for ($i = 0; $i < 24; $i++) {
				echo '<span class="sp-beat"></span>';
			}
		} else {
			foreach ($beats as $beat) {
				$b = strval($beat);
				$class = match ($b) {
					'up' => 'is-up',
					'down' => 'is-down',
					'maint', 'maintenance' => 'is-maint',
					default => 'is-pending',
				};
				echo '<span class="sp-beat '.$class.'"></span>';
			}
		}
		echo '</div>';

		echo '<span class="sp-badge '.$badgeClass.'">'.$badgeLabel.'</span>';
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

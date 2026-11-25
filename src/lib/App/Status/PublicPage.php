<?php

namespace App\Status;

/**
 * Anonymous public status page (no auth).
 */
class PublicPage extends \Routing_Parent implements \Routing_Interface {

	public function Run(string $slug = '') {
		$slug = \StatusPageMembership::normalizeSlug($slug);
		$data = \StatusPageBuilder::buildBySlugCached($slug);
		if (!$data) {
			http_response_code(404);
			header('Content-Type: text/html; charset=utf-8');
			echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Not found</title>';
			echo '<link rel="stylesheet" href="/css/main.css"></head><body class="p-4">';
			echo '<h1>Status page not found</h1><p>Unknown or disabled slug.</p></body></html>';
			return;
		}

		header('Content-Type: text/html; charset=utf-8');
		header('Cache-Control: public, max-age=30');
		$title = htmlspecialchars(strval($data['title'] ?? 'Status'), ENT_QUOTES, 'UTF-8');
		$generated = intval($data['generated_at'] ?? time());

		echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">';
		echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
		echo '<title>'.$title.'</title>';
		echo '<link rel="stylesheet" href="/css/main.css">';
		echo '<style>
			body.status-public{background:var(--bs-body-bg,#f6f8fb);color:var(--bs-body-color,#1a2332);font-family:system-ui,-apple-system,sans-serif;margin:0}
			.status-public .wrap{max-width:920px;margin:0 auto;padding:1.5rem 1rem 3rem}
			.status-public h1{margin:0 0 .35rem;font-size:1.75rem}
			.status-public .meta{color:#5b6b7f;font-size:.9rem;margin-bottom:1.25rem}
			.status-public .incident{border:1px solid #f0c36d;background:#fff8e8;border-radius:10px;padding:1rem 1.1rem;margin-bottom:1rem}
			.status-public .incident .state{font-weight:700;text-transform:uppercase;font-size:.75rem;letter-spacing:.04em;color:#8a5a00}
			.status-public .group{margin:1.5rem 0}
			.status-public .group h2{font-size:1.15rem;margin:0 0 .75rem}
			.status-public .res{display:flex;justify-content:space-between;gap:1rem;align-items:center;padding:.7rem .85rem;border:1px solid #d7dee8;border-radius:10px;background:#fff;margin-bottom:.5rem}
			.status-public .badge-pill{display:inline-block;padding:.2rem .55rem;border-radius:999px;font-size:.8rem;font-weight:600;color:#fff}
			.status-public .st-up{background:#1f8a4c}.status-public .st-down{background:#c0392b}
			.status-public .st-maintenance{background:#0b6bcb}.status-public .st-unknown{background:#6c757d}
		</style></head><body class="status-public"><div class="wrap">';
		echo '<h1>'.$title.'</h1>';
		echo '<div class="meta">Updated '.htmlspecialchars(gmdate('Y-m-d H:i:s', $generated), ENT_QUOTES, 'UTF-8').' UTC</div>';

		foreach ($data['incidents'] ?? [] as $incident) {
			$it = htmlspecialchars(strval($incident['title'] ?? ''), ENT_QUOTES, 'UTF-8');
			$im = nl2br(htmlspecialchars(strval($incident['message'] ?? ''), ENT_QUOTES, 'UTF-8'));
			$is = htmlspecialchars(strval($incident['state'] ?? ''), ENT_QUOTES, 'UTF-8');
			echo '<div class="incident"><div class="state">'.$is.'</div><strong>'.$it.'</strong><div>'.$im.'</div></div>';
		}

		foreach ($data['groups'] ?? [] as $group) {
			$gt = htmlspecialchars(strval($group['title'] ?? ''), ENT_QUOTES, 'UTF-8');
			echo '<section class="group"><h2>'.$gt.'</h2>';
			$resources = $group['resources'] ?? [];
			if (!$resources) {
				echo '<p class="meta">No monitors in this group.</p>';
			}
			foreach ($resources as $res) {
				$rt = htmlspecialchars(strval($res['title'] ?? ''), ENT_QUOTES, 'UTF-8');
				$st = strval($res['status'] ?? 'unknown');
				$class = match ($st) {
					'up' => 'st-up',
					'down' => 'st-down',
					'maintenance' => 'st-maintenance',
					default => 'st-unknown',
				};
				$label = htmlspecialchars(strtoupper($st), ENT_QUOTES, 'UTF-8');
				echo '<div class="res"><div>'.$rt.'</div><span class="badge-pill '.$class.'">'.$label.'</span></div>';
			}
			echo '</section>';
		}

		if (!$data['groups']) {
			echo '<p class="meta">This status page has no monitors yet.</p>';
		}

		echo '</div></body></html>';
	}
}

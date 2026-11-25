<?php

namespace App\Badge;

/**
 * Anonymous SVG status badge (no auth): /badge/{hash}.svg
 */
class Svg extends \Routing_Parent implements \Routing_Interface {

	public function Run(string $hash = '') {
		$result = \BadgeSvg::renderForHash($hash);
		http_response_code(intval($result['http']));
		if (intval($result['http']) !== 200 || $result['svg'] === '') {
			header('Content-Type: text/plain; charset=utf-8');
			header('Cache-Control: no-store');
			echo 'Not found';
			return;
		}
		header('Content-Type: image/svg+xml; charset=utf-8');
		header('Cache-Control: public, max-age=30');
		header('X-Content-Type-Options: nosniff');
		echo $result['svg'];
	}
}

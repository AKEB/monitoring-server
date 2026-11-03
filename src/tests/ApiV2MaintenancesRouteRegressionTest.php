<?php

/**
 * Framework matches routes as URI suffixes (`preg_match('~'.$route.'$~')`).
 * `/maintenances/` must not steal `/api/v2/maintenances/` — API routes are registered first.
 */
class ApiV2MaintenancesRouteRegressionTest extends \PHPUnit\Framework\TestCase {

	public function testApiV2MaintenancesMatchesGatewayNotUiShow(): void {
		$routes = [
			'/api/v2/(?P<resource>[a-z]+)/' => '\\App\\Api\\V2\\Gateway',
			'/maintenances/' => '\\App\\Maintenances\\Show',
		];
		$uri = '/api/v2/maintenances/';
		$matched = null;
		foreach ($routes as $route => $class) {
			if ($uri === $route || preg_match('~'.$route.'$~', $uri)) {
				$matched = $class;
				break;
			}
		}
		$this->assertSame('\\App\\Api\\V2\\Gateway', $matched);
	}

	public function testUiMaintenancesStillMatchesOwnPath(): void {
		$routes = [
			'/api/v2/(?P<resource>[a-z]+)/' => '\\App\\Api\\V2\\Gateway',
			'/maintenances/' => '\\App\\Maintenances\\Show',
		];
		$uri = '/maintenances/';
		$matched = null;
		foreach ($routes as $route => $class) {
			if ($uri === $route || preg_match('~'.$route.'$~', $uri)) {
				$matched = $class;
				break;
			}
		}
		$this->assertSame('\\App\\Maintenances\\Show', $matched);
	}
}

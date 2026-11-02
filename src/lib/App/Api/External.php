<?php

namespace App\Api;

/**
 * Public GET callback for External monitors.
 * /api/external/{hash}/?status=ok|fail
 *
 * Optional alert text (shown in notifications as "Log: …"):
 *   HTTP header Log: …
 *   or query ?log=… / ?message=…
 */
class External extends \Routing_Parent implements \Routing_Interface {

	public function Run(string $hash = '') {
		$hash = trim($hash);
		$status_raw = isset($_GET['status']) ? strval($_GET['status']) : '';
		$status = \ExternalMonitors::parseStatusParam($status_raw);
		if ($status === null) {
			$this->responseError('Invalid status. Use status=ok or status=fail', 400);
			return;
		}
		$log = \ExternalMonitors::extractLogFromRequest();
		$result = \ExternalMonitors::applyCallback($hash, $status, $log);
		if (!$result) {
			$this->responseError('Monitor not found or disabled', 404);
			return;
		}
		$response = [
			'monitor_id' => $result['monitor_id'],
			'external_status' => $result['status'] === \MonitoringLogs::STATUS_GOOD ? 'ok' : 'fail',
		];
		if ($result['status'] === \MonitoringLogs::STATUS_FAIL && ($result['log'] ?? '') !== '') {
			$response['log'] = $result['log'];
		}
		$this->responseSuccess($response);
	}

	private function responseSuccess(array $response = []): void {
		$response['status'] = 0;
		http_response_code(200);
		header('Content-Encoding: UTF-8');
		header('Content-Type: application/json;charset=utf-8');
		header('Cache-Control: no-store');
		echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		exit(0);
	}

	private function responseError(string $error, int $http_code): void {
		http_response_code($http_code);
		header('Content-Encoding: UTF-8');
		header('Content-Type: application/json;charset=utf-8');
		header('Cache-Control: no-store');
		echo json_encode([
			'status' => 1,
			'error' => $error,
		], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		exit(0);
	}
}

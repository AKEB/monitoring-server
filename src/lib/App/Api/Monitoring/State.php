<?php

namespace App\Api\Monitoring;

class State extends \Routing_Parent implements \Routing_Interface {

	private ?array $worker;
	private ?array $data;

	public function Run() {
		$this->worker = [];
		$this->data = [];
		$this->processRequest();
		if (!$this->worker) {
			$this->responseError([
				'error' => 'Worker not found',
			]);
			return;
		}
		$this->updateWorker();
		if ($this->updateJobsState()) {
			$this->responseSuccess();
		} else {
			$this->responseError([
				'error' => 'Error syncing job state',
			]);
		}
	}

	private function responseSuccess(array $response=[]) {
		if (isset($response['error'])) unset($response['error']);
		if (!isset($response['status'])) $response['status'] = 0;
		if (!isset($response['server_time'])) $response['server_time'] = time();
		if (!isset($response['server_microtime'])) $response['server_microtime'] = microtime(true);
		header('Content-Encoding: UTF-8');
		header('Content-Type: application/json;charset=utf-8');
		echo json_encode($response);
		exit(0);
	}

	private function responseError(array $response=[]) {
		if (!isset($response['error'])) $response['error'] = 'Error';
		if (!isset($response['status'])) $response['status'] = 1;
		if (!isset($response['server_time'])) $response['server_time'] = time();
		if (!isset($response['server_microtime'])) $response['server_microtime'] = microtime(true);
		header('Content-Encoding: UTF-8');
		header('Content-Type: application/json;charset=utf-8');
		echo json_encode($response);
		exit(0);
	}

	private function updateWorker(): void {
		$save_data = [];
		if (isset($this->data['protocol_version']) && $this->data['protocol_version'] && $this->data['protocol_version'] != $this->worker['protocol_version']) {
			$save_data['protocol_version'] = $this->data['protocol_version'];
			$save_data['update_time'] = time();
		}
		if (isset($this->data['worker_version']) && $this->data['worker_version'] && $this->data['worker_version'] != $this->worker['worker_version']) {
			$save_data['worker_version'] = $this->data['worker_version'];
			$save_data['update_time'] = time();
		}
		if ($this->worker['last_active_time'] + 60 < time()) {
			$save_data['last_active_time'] = time();
		}
		if ($save_data) {
			$save_data['id'] = $this->worker['id'];
			$save_data['_mode'] = \DB\Common::CSMODE_UPDATE;
			\Workers::save($save_data);
		}
	}

	private function updateJobsState(): bool {
		if (!$this->worker || !$this->worker['id']) return false;
		if (!$this->data || !isset($this->data['jobs']) || !$this->data['jobs']) return false;
		$jobs = $this->data['jobs'];
		foreach ($jobs as $job) {
			$this->updateJobState($this->worker['id'], $job);
		}
		return true;
	}

	private function updateJobState(int $worker_id, array $job_response): bool {
		$job = \Jobs::get([
			'id' => intval($job_response['job_id']),
			'worker_id' => intval($worker_id),
			'monitor_id' => intval($job_response['monitor_id']),
		]);
		if (!$job) return false;
		$monitor = \Monitors::get($job['monitor_id']);
		if (!$monitor) return false;
		if ($monitor['status'] == \Monitors::STATUS_DISABLED) return false;

		if ($job['update_time'] + 30 < time()) {
			\Jobs::save([
				'id' => $job['id'],
				'update_time' => $job_response['update_time'] ?? 0,
			]);
		}
		if (!isset($job_response['response']) || !$job_response['response'] || !is_array($job_response['response'])) return false;
		$status = \MonitoringLogs::STATUS_FAIL;
		if ($job_response['response']['status_code'] >= 200 && $job_response['response']['status_code'] < 300) {
			$status = \MonitoringLogs::STATUS_GOOD;
		}
		$params = [
			'monitor_id' => $job['monitor_id'],                        // int DEFAULT 0,
			'worker_id' => $job['worker_id'],                          // int DEFAULT 0,
			'job_id' => $job['id'],                                    // int DEFAULT 0,
			'status' => $status,                                       // int DEFAULT 0,
			'update_time' => $job_response['update_time'] ?? 0,        // int DEFAULT 0,
			'status_code' => $job_response['response']['status_code'] ?? 0,        // int DEFAULT 0,
			'status_text' => $job_response['response']['status_text'] ?? '',        // char(16) DEFAULT '',
			// 'response_text' => $job_response['response']['response_text'] ?? '',      // char(64) DEFAULT '',

			'redirect_count' => $job_response['response']['redirect_count'] ?? 0,
			'total_time' => $job_response['response']['total_time'] ?? 0.0,
			'namelookup_time' => $job_response['response']['namelookup_time'] ?? 0.0,
			'connect_time' => $job_response['response']['connect_time'] ?? 0.0,
			'pretransfer_time' => $job_response['response']['pretransfer_time'] ?? 0.0,
			'starttransfer_time' => $job_response['response']['starttransfer_time'] ?? 0.0,
			'redirect_time' => $job_response['response']['redirect_time'] ?? 0.0,

			'total_time_us' => $job_response['response']['total_time_us'] ?? 0,
			'namelookup_time_us' => $job_response['response']['namelookup_time_us'] ?? 0,
			'connect_time_us' => $job_response['response']['connect_time_us'] ?? 0,
			'pretransfer_time_us' => $job_response['response']['pretransfer_time_us'] ?? 0,
			'starttransfer_time_us' => $job_response['response']['starttransfer_time_us'] ?? 0,
			'redirect_time_us' => $job_response['response']['redirect_time_us'] ?? 0,
			'appconnect_time_us' => $job_response['response']['appconnect_time_us'] ?? 0,
			'posttransfer_time_us' => $job_response['response']['posttransfer_time_us'] ?? 0,

			'effective_method' => $job_response['response']['effective_method'] ?? '',
			'primary_ip' => $job_response['response']['primary_ip'] ?? '',

			'primary_port' => $job_response['response']['primary_port'] ?? 0,
			'http_version' => $job_response['response']['http_version'] ?? 0,
			'protocol' => $job_response['response']['protocol'] ?? 0,
			'ssl_verifyresult' => $job_response['response']['ssl_verifyresult'] ?? 0,

			'scheme' => $job_response['response']['scheme'] ?? '',
			'cert_expire' => $job_response['response']['cert_expire'] ?? 0,
		];
		$state = \MonitoringLogs::save($params);
		return $state;
	}

	protected function handleBodyData(array $data) {
		// Обработка POST Body данных
		if (!isset($data)) return;
		if (isset($data['worker_key_hash']) && $data['worker_key_hash']) {
			$worker = \Workers::get(['worker_key_hash' => $data['worker_key_hash']]);
			if ($worker && $data['worker_key_hash'] == $worker['worker_key_hash']) {
				$this->worker = $worker;
				$this->data = $data;
			}
		}
	}

}

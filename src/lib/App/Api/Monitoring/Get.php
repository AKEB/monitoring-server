<?php

namespace App\Api\Monitoring;

class Get extends \Routing_Parent implements \Routing_Interface {

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

		$this->responseSuccess([
			'data' => $this->getWorkerData(),
			'jobs' => $this->getJobsData(),
			'dockers' => $this->getDockersData(),
		]);
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

	private function getWorkerData(): array {
		return [
			'worker_id' => $this->worker['id'],
			'worker_threads' => $this->worker['worker_threads'],
			'jobs_get_timeout' => $this->worker['jobs_get_timeout'],
			'loop_timeout' => $this->worker['loop_timeout'],
			'response_send_timeout' => $this->worker['response_send_timeout'],
			'logs_write_timeout' => $this->worker['logs_write_timeout'],
		];
	}

	private function getJobsData(): array {
		$jobs = [];
		if ($this->worker && $this->worker['id']) {
			$sql = sql_pholder(' AND M.`status` = ? ',\Monitors::STATUS_ENABLED);
			$sql .= sql_pholder(' AND M.`type` NOT IN (?@)', [\Monitors::TYPE_FOLDER, \Monitors::TYPE_DOCKER]);
			$jobs = \MonitoringJobs::data(
				[
					'worker_id' => $this->worker['id']
				],
				$sql,
				't.`id` as `job_id`, t.`update_time`, M.*',
				false,
				false,
				[
					'_join' => ' JOIN `'.\Monitors::getTable().'` M ON t.`monitor_id` = M.`id`'
				]
			);
			$enabled_jobs = [];
			foreach($jobs as $job) {
				if (!\Monitors::is_enable($job['id'])) {
					continue;
				}

				$enabled_jobs[] = $this->isProtocolV2() ? $this->slimMonitorJobRow($job) : $job;
			}
		}
		return $enabled_jobs;
	}

	private function getDockersData(): array {
		$jobs = [];
		if ($this->worker && $this->worker['id']) {
			$sql = sql_pholder(' AND D.`status` = ? ',\Dockers::STATUS_ENABLED);
			$jobs = \MonitoringDockers::data(
				[
					'worker_id' => $this->worker['id']
				],
				$sql,
				't.`id` as `job_id`, t.`update_time`, D.*',
				false,
				false,
				[
					'_join' => ' JOIN `'.\Dockers::getTable().'` D ON t.`docker_id` = D.`id`'
				]
			);
			if ($this->isProtocolV2()) {
				foreach ($jobs as $i => $job) {
					$jobs[$i] = $this->slimDockerJobRow($job);
				}
			}
		}
		return $jobs;
	}

	private function isProtocolV2(): bool {
		$v = isset($this->data['protocol_version']) ? (string)$this->data['protocol_version'] : '';
		return $v !== '' && version_compare($v, '2.0', '>=');
	}

	private function pickRowFields(array $row, array $keys): array {
		$out = [];
		foreach ($keys as $key) {
			if (array_key_exists($key, $row)) {
				$out[$key] = $row[$key];
			}
		}
		return $out;
	}

	private function slimMonitorJobRow(array $job): array {
		return $this->pickRowFields($job, [
			'job_id',
			'update_time',
			'id',
			'type',
			'url',
			'method',
			'host',
			'port',
			'timeout',
			'repeat_seconds',
			'request_headers',
			'proxy_host',
			'ssl_verify',
			'ssl_verify_host',
			'max_redirects',
			'json_path',
			'json_expected_value',
			'json_expected_type',
		]);
	}

	private function slimDockerJobRow(array $job): array {
		return $this->pickRowFields($job, [
			'job_id',
			'update_time',
			'id',
			'host',
			'port',
			'tls',
			'timeout',
			'repeat_seconds',
			'proxy_host',
			'tls_ca_file',
			'tls_certificate',
			'tls_key',
		]);
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

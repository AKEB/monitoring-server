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
		if (!$this->requiresProtocolV2()) {
			$this->responseError([
				'error' => 'protocol_version 2.0 or newer is required',
			]);
			return;
		}
		$this->updateWorker();
		$this->clearRestartRequested();

		$this->responseSuccess([
			'data' => $this->getWorkerData(),
			'jobs' => $this->getJobsData(),
			'dockers' => $this->getDockersData(),
		]);
	}

	private function responseSuccess(array $response=[]) {
		if (isset($response['error'])) unset($response['error']);
		if (!isset($response['status'])) $response['status'] = 0;
		header('Content-Encoding: UTF-8');
		header('Content-Type: application/json;charset=utf-8');
		echo json_encode($response);
		exit(0);
	}

	private function responseError(array $response=[]) {
		if (!isset($response['error'])) $response['error'] = 'Error';
		if (!isset($response['status'])) $response['status'] = 1;
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
		$data = [
			'worker_id' => $this->worker['id'],
			'worker_threads' => $this->worker['worker_threads'],
			'jobs_get_timeout' => $this->worker['jobs_get_timeout'],
			'loop_timeout' => $this->worker['loop_timeout'],
			'response_send_timeout' => $this->worker['response_send_timeout'],
			'logs_write_timeout' => $this->worker['logs_write_timeout'],
		];
		if (!empty($this->worker['restart_requested'])) {
			$data['restart'] = 'true';
		}
		return $data;
	}

	private function clearRestartRequested(): void {
		if (empty($this->worker['restart_requested'])) {
			return;
		}
		\Workers::save([
			'id' => $this->worker['id'],
			'restart_requested' => 0,
			'_mode' => \DB\Common::CSMODE_UPDATE,
		]);
		$this->worker['restart_requested'] = 0;
	}

	private function getJobsData(): array {
		$jobs = [];
		if ($this->worker && $this->worker['id']) {
			$sql = sql_pholder(' AND M.`status` = ? ',\Monitors::STATUS_ENABLED);
			$sql .= sql_pholder(' AND M.`type` NOT IN (?@)', [\Monitors::TYPE_FOLDER, \Monitors::TYPE_DOCKER]);
			$fields = 't.`id` AS `job_id`, t.`update_time`, M.`id`, M.`type`, M.`url`, M.`method`, M.`host`, M.`port`, '
				. 'M.`timeout`, M.`repeat_seconds`, M.`request_headers`, M.`proxy_host`, M.`proxy_type`, M.`ssl_verify`, M.`ssl_verify_host`, '
				. 'M.`max_redirects`, M.`json_path`, M.`json_expected_value`, M.`json_expected_type`';
			$jobs = \MonitoringJobs::data(
				[
					'worker_id' => $this->worker['id']
				],
				$sql,
				$fields,
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
				$enabled_jobs[] = $job;
			}
		}
		return $enabled_jobs;
	}

	private function getDockersData(): array {
		$jobs = [];
		if ($this->worker && $this->worker['id']) {
			$sql = sql_pholder(' AND D.`status` = ? ',\Dockers::STATUS_ENABLED);
			$fields = 't.`id` AS `job_id`, t.`update_time`, D.`id`, D.`host`, D.`port`, D.`tls`, D.`timeout`, D.`repeat_seconds`, '
				. 'D.`proxy_host`, D.`proxy_type`, D.`tls_ca_file`, D.`tls_certificate`, D.`tls_key`, D.`update_time` AS `docker_update_time`';
			$jobs = \MonitoringDockers::data(
				[
					'worker_id' => $this->worker['id']
				],
				$sql,
				$fields,
				false,
				false,
				[
					'_join' => ' JOIN `'.\Dockers::getTable().'` D ON t.`docker_id` = D.`id`'
				]
			);
			$tlsSync = $this->dockerTLSSyncFromRequest();
			foreach ($jobs as $i => $job) {
				$jobs[$i] = $this->slimDockerJobRow($job, $tlsSync);
			}
		}
		return $jobs;
	}

	private function requiresProtocolV2(): bool {
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

	private function dockerTLSSyncFromRequest(): array {
		$sync = [];
		if (!isset($this->data['docker_tls_sync']) || !is_array($this->data['docker_tls_sync'])) {
			return $sync;
		}
		foreach ($this->data['docker_tls_sync'] as $dockerID => $revision) {
			$id = intval($dockerID);
			if ($id <= 0) {
				continue;
			}
			$sync[$id] = intval($revision);
		}
		return $sync;
	}

	private function slimDockerJobRow(array $job, array $tlsSync = []): array {
		$keys = [
			'job_id',
			'update_time',
			'id',
			'host',
			'port',
			'tls',
			'timeout',
			'repeat_seconds',
			'proxy_host',
			'proxy_type',
			'docker_update_time',
		];
		$dockerID = intval($job['id'] ?? 0);
		$dockerUpdate = intval($job['docker_update_time'] ?? 0);
		$knownRevision = intval($tlsSync[$dockerID] ?? 0);
		if ($dockerUpdate <= 0 || $knownRevision < $dockerUpdate) {
			$keys = array_merge($keys, ['tls_ca_file', 'tls_certificate', 'tls_key']);
		}
		return $this->pickRowFields($job, $keys);
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

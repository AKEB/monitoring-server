<?php

namespace App\Api\Monitoring;

class Get extends \Routing_Parent implements \Routing_Interface {

	private ?array $worker;
	private ?array $data;

	public function Run() {
		$this->worker = [];
		$this->data = [];
		$this->processWorkerRequest();
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
		$workerVersion = isset($this->data['worker_version']) ? mb_substr(strval($this->data['worker_version']), 0, 64) : '';
		if ($workerVersion !== '' && $workerVersion != ($this->worker['worker_version'] ?? '')) {
			$save_data['worker_version'] = $workerVersion;
			$save_data['update_time'] = time();
		}
		if (isset($this->data['arch'])) {
			$arch = \GithubReleaseMeta::normalizeArch(strval($this->data['arch']));
			if ($arch !== '' && $arch !== strval($this->worker['arch'] ?? '')) {
				$save_data['arch'] = $arch;
				$save_data['update_time'] = time();
			}
		}
		$updateError = mb_substr(trim(strval($this->data['update_error'] ?? '')), 0, 512);
		if ($updateError !== '') {
			$save_data['update_error'] = $updateError;
			$save_data['update_time'] = time();
		} elseif ($workerVersion !== '') {
			$merged = array_merge($this->worker, $save_data);
			$desired = \WorkerUpdate::desiredForWorker($merged);
			if ($desired !== '' && \WorkerUpdate::versionsEqual($workerVersion, $desired)) {
				$save_data['update_error'] = '';
				$save_data['update_requested_at'] = 0;
				$save_data['update_time'] = time();
			}
		}
		if ($this->worker['last_active_time'] + 60 < time()) {
			$save_data['last_active_time'] = time();
		}
		if ($save_data) {
			$save_data['id'] = $this->worker['id'];
			$save_data['_mode'] = \DB\Common::CSMODE_UPDATE;
			\Workers::save($save_data);
			$this->worker = array_merge($this->worker, $save_data);
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
		$updateCmd = \WorkerUpdate::buildCommand($this->worker, is_array($this->data) ? $this->data : [], false);
		if ($updateCmd) {
			\WorkerUpdate::markRequested($this->worker);
			$data['update'] = $updateCmd;
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
		$enabled_jobs = [];
		if ($this->worker && $this->worker['id']) {
			$sql = sql_pholder(' AND M.`status` = ? ',\Monitors::STATUS_ENABLED);
			$sql .= sql_pholder(' AND M.`type` NOT IN (?@)', array_merge(
				[\Monitors::TYPE_FOLDER, \Monitors::TYPE_DOCKER, \Monitors::TYPE_EXTERNAL],
				\Monitors::server_types()
			));
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
				if (\MonitoringMaintenances::getMaintenancesForMonitor(intval($job['id']))) {
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
			$dockerIdsWithMonitors = $this->dockerIdsWithCheckableMonitors($jobs);
			$tlsSync = $this->dockerTLSSyncFromRequest();
			$filtered = [];
			foreach ($jobs as $job) {
				$dockerId = intval($job['id'] ?? 0);
				if (!$dockerId || !isset($dockerIdsWithMonitors[$dockerId])) {
					continue;
				}
				$filtered[] = $this->slimDockerJobRow($job, $tlsSync);
			}
			$jobs = $filtered;
		}
		return $jobs;
	}

	/**
	 * Docker hosts that have at least one enabled monitor not in active maintenance.
	 *
	 * @param array<int, array<string, mixed>> $dockerJobs
	 * @return array<int, true>
	 */
	private function dockerIdsWithCheckableMonitors(array $dockerJobs): array {
		$dockerIds = [];
		foreach ($dockerJobs as $job) {
			$dockerId = intval($job['id'] ?? 0);
			if ($dockerId > 0) {
				$dockerIds[$dockerId] = true;
			}
		}
		if (!$dockerIds) {
			return [];
		}
		$monitors = \Monitors::data(
			[
				'type' => \Monitors::TYPE_DOCKER,
				'status' => \Monitors::STATUS_ENABLED,
			],
			sql_pholder(' AND `docker` IN (?@)', array_keys($dockerIds)),
			'`id`, `docker`'
		);
		$result = [];
		foreach ($monitors as $monitor) {
			$monitorId = intval($monitor['id'] ?? 0);
			$dockerId = intval($monitor['docker'] ?? 0);
			if ($monitorId < 1 || $dockerId < 1) {
				continue;
			}
			if (!\Monitors::is_enable($monitorId)) {
				continue;
			}
			if (\MonitoringMaintenances::getMaintenancesForMonitor($monitorId)) {
				continue;
			}
			$result[$dockerId] = true;
		}
		return $result;
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

	/**
	 * Worker API authenticates via worker_key_hash in JSON body — skip browser CSRF.
	 */
	protected function processWorkerRequest(): void {
		$this->error = '';
		if ($_GET) {
			$this->handleGetData($_GET);
		}
		if ($_POST) {
			$this->handlePostData($_POST);
		}
		$data = $this->get_request_body();
		if ($data) {
			$this->handleBodyData($data);
		}
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

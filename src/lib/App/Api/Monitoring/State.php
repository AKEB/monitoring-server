<?php

namespace App\Api\Monitoring;

class State extends \Routing_Parent implements \Routing_Interface {

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
		if ($this->updateStates()) {
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
		}
	}

	private function updateStates(): bool {
		if (!$this->worker || !$this->worker['id']) return false;
		if (!$this->data) return false;
		if (
			(!isset($this->data['jobs']) || !$this->data['jobs']) &&
			(!isset($this->data['dockers']) || !$this->data['dockers'])
		) return false;
		$status1 = $this->updateJobsState();
		$status2 = $this->updateDockersState();
		return $status1 || $status2;
	}

	private function updateDockersState(): bool {
		if (!$this->worker || !$this->worker['id']) return false;
		if (!$this->data || !isset($this->data['dockers']) || !$this->data['dockers']) return false;
		$dockers = $this->data['dockers'];
		foreach ($dockers as $docker) {
			$result = $this->updateDockerState($this->worker['id'], $docker);
			if (!$result) continue;
			$this->updateDockerMonitors($result);
		}

		return true;
	}

	private function updateDockerMonitors(?array $param) {
		$docker_id = $param['id']??0;
		$container_states = $param['container_states']??'';
		$job_id = $param['job_id']??0;

		if (!isset($docker_id) || !$docker_id) return false;
		$docker = \Dockers::get(['id' => $docker_id]);
		if (!$docker) return false;

		if ($docker['status'] != \Dockers::STATUS_ENABLED) return false;

		$container_states = @json_decode($container_states, true);
		if (!is_array($container_states) || !$container_states) return false;
		$containers_hash = [];
		foreach ($container_states as $container_state) {
			if (!isset($container_state['id']) || !$container_state['id']) continue;
			$containers_hash[$container_state['name']] = $container_state;
		}
		$monitors = \Monitors::data(
			[
				'type' => \Monitors::TYPE_DOCKER,
				'status' => \Monitors::STATUS_ENABLED,
				'docker' => $docker_id,
			],
			'',
			'`id`, `container`'
		);
		if (!is_array($monitors) || !$monitors) return false;
		$notification_check_monitors = [];
		foreach ($monitors as $monitor) {
			if (!isset($monitor['id']) || !$monitor['id']) continue;

			if (!\Monitors::is_enable($monitor['id'])) continue;

			$maintenances = \MonitoringMaintenances::getMaintenancesForMonitor($monitor['id']);
			if ($maintenances) {
				continue;
			}

			if (!isset($monitor['container']) || !$monitor['container']) continue;
			if (!isset($containers_hash[$monitor['container']]) || !$containers_hash[$monitor['container']]) continue;
			$status = \MonitoringLogs::STATUS_FAIL;
			if ($containers_hash[$monitor['container']]['state'] == 'running') {
				$status = \MonitoringLogs::STATUS_GOOD;
			}
			$monitor_log = [
				'monitor_id' => $monitor['id'],
				'worker_id' => $this->worker['id'],
				'job_id' => $job_id,
				'status' => $status,
				'status_code' => intval($param['status_code'] ?? 0),
				'response_error' => $containers_hash[$monitor['container']]['status']??'',
				'total_time_us' => $containers_hash[$monitor['container']]['total_time_us']??0,
				'update_time' => $param['update_time']??time(),
			];
			$monitor_log['id'] = \MonitoringLogs::save($monitor_log);
			$monitor_log = \MonitoringLogs::get(['id' => $monitor_log['id']]);
			\MonitorsEvents::updateEvent($monitor_log);
			$notification_check_monitors[$monitor['id']] = true;
		}
		if ($notification_check_monitors && count($notification_check_monitors) > 0) {
			\MonitorsEvents::checkMonitors($notification_check_monitors);
		}
	}

	private function updateDockerState(int $worker_id, array $docker_response): bool|array {
		$dockerJob = \MonitoringDockers::get([
			'id' => intval($docker_response['job_id']),
			'worker_id' => intval($worker_id),
		]);
		if (!$dockerJob) return false;
		$docker = \Dockers::get($dockerJob['docker_id']);
		if (!$docker) return false;
		if ($docker['status'] == \Dockers::STATUS_DISABLED) return false;

		$pollTime = intval($docker_response['start_time'] ?? 0);
		if ($pollTime < 1) {
			$pollTime = intval($docker_response['update_time'] ?? 0);
		}
		if ($pollTime < 1) {
			$pollTime = time();
		}

		$response = [];
		if (isset($docker_response['response']) && is_array($docker_response['response'])) {
			$response = $docker_response['response'];
		}
		$response['status'] = intval($response['status'] ?? 0);
		$response['status_code'] = intval($response['status_code'] ?? 0);
		$response['response_error_num'] = intval($response['response_error_num'] ?? 0);
		$response['response_error'] = strval($response['response_error'] ?? '');

		$engineInfo = $this->resolveDockerEngineInfo($response);
		$engineVersion = '';
		if (is_array($engineInfo) && isset($engineInfo['Version'])) {
			$engineVersion = substr(strval($engineInfo['Version']), 0, 64);
		}

		$container_states = $this->resolveDockerContainerStates($response);
		$ok = (
			$response['status'] === 1 &&
			$response['status_code'] >= 200 &&
			$response['status_code'] < 300 &&
			is_array($container_states) &&
			$container_states
		);

		$jobSave = [
			'id' => $dockerJob['id'],
			'update_time' => $pollTime,
			'last_status' => $ok ? 1 : 0,
			'last_status_code' => $response['status_code'],
			'last_error' => $ok ? '' : substr($response['response_error'], 0, 512),
			'engine_version' => $engineVersion,
			'containers_count' => is_array($container_states) ? count($container_states) : 0,
			'_mode' => \DB\Common::CSMODE_UPDATE,
		];
		if ($ok) {
			$jobSave['last_success_time'] = $pollTime;
		}
		\MonitoringDockers::save($jobSave);

		if (!$ok) {
			return false;
		}

		$containerStatesJson = json_encode($container_states);
		$params = [
			'id' => $docker['id'],
			'container_states' => $containerStatesJson,
			'last_success_time' => $pollTime,
			'last_success_worker_id' => intval($worker_id),
			'_mode' => \DB\Common::CSMODE_UPDATE,
		];
		if (is_array($engineInfo) && $engineInfo) {
			$params['engine_info'] = json_encode($engineInfo);
		}
		$params['id'] = \Dockers::save($params);
		if ($params['id']) {
			$params['job_id'] = $dockerJob['id'];
			// HTTP status from Docker Engine API (for container monitor logs / tooltips).
			$params['status_code'] = $response['status_code'];
			$params['container_states'] = $containerStatesJson;
			return $params;
		}

		return false;
	}

	private function updateJobsState(): bool {
		if (!$this->worker || !$this->worker['id']) return false;
		if (!$this->data || !isset($this->data['jobs']) || !$this->data['jobs']) return false;
		$jobs = $this->data['jobs'];
		$notification_check_monitors = [];
		foreach ($jobs as $job) {
			$result = $this->updateJobState($this->worker['id'], $job);
			if (!$result) continue;
			if (
				isset($result['monitor_id']) && $result['monitor_id'] &&
				isset($result['worker_id']) && $result['worker_id']
			) {
				$notification_check_monitors[$result['monitor_id']] = true;
				\MonitorsEvents::updateEvent($result);
			}
		}
		if ($notification_check_monitors && count($notification_check_monitors) > 0) {
			\MonitorsEvents::checkMonitors($notification_check_monitors);
		}
		return true;
	}

	private function updateJobState(int $worker_id, array $job_response): bool|array {
		$job = \MonitoringJobs::get([
			'id' => intval($job_response['job_id']),
			'worker_id' => intval($worker_id),
			'monitor_id' => intval($job_response['monitor_id']),
		]);
		if (!$job) return false;
		$monitor = \Monitors::get($job['monitor_id']);
		if (!$monitor) return false;
		if ($monitor['status'] != \Monitors::STATUS_ENABLED) return false;

		if (!\Monitors::is_enable($job['monitor_id'])) return false;

		$maintenances = \MonitoringMaintenances::getMaintenancesForMonitor($job['monitor_id']);
		if ($maintenances) {
			return false;
		}

		if ($job['update_time'] + 30 < time()) {
			\MonitoringJobs::save([
				'id' => $job['id'],
				'update_time' => $job_response['update_time'] ?? 0,
			]);
		}
		if (!isset($job_response['response']) || !$job_response['response'] || !is_array($job_response['response'])) return false;
		$this->normalizeJobResponse($job_response);
		$status = \MonitoringLogs::STATUS_FAIL;

		$monitor['response_code'] = $this->parseMonitorResponseCodes($monitor['response_code'] ?? '');

		if ($monitor['type'] == \Monitors::TYPE_HTTPS) {
			if (
				($job_response['response']['status_code'] >= 100 && in_array($job_response['response']['status_code'], $monitor['response_code'])) ||
				(in_array(1, $monitor['response_code']) && $job_response['response']['status_code'] >= 100 && $job_response['response']['status_code'] < 200) ||
				(in_array(2, $monitor['response_code']) && $job_response['response']['status_code'] >= 200 && $job_response['response']['status_code'] < 300) ||
				(in_array(3, $monitor['response_code']) && $job_response['response']['status_code'] >= 300 && $job_response['response']['status_code'] < 400) ||
				(in_array(4, $monitor['response_code']) && $job_response['response']['status_code'] >= 400 && $job_response['response']['status_code'] < 500) ||
				(in_array(5, $monitor['response_code']) && $job_response['response']['status_code'] >= 500 && $job_response['response']['status_code'] < 600)
			) {
				$status = \MonitoringLogs::STATUS_GOOD;
			}
		} elseif ($monitor['type'] == \Monitors::TYPE_HTTP_JSON) {
			$httpStatusGood =
				($job_response['response']['status_code'] >= 100 && in_array($job_response['response']['status_code'], $monitor['response_code'])) ||
				(in_array(1, $monitor['response_code']) && $job_response['response']['status_code'] >= 100 && $job_response['response']['status_code'] < 200) ||
				(in_array(2, $monitor['response_code']) && $job_response['response']['status_code'] >= 200 && $job_response['response']['status_code'] < 300) ||
				(in_array(3, $monitor['response_code']) && $job_response['response']['status_code'] >= 300 && $job_response['response']['status_code'] < 400) ||
				(in_array(4, $monitor['response_code']) && $job_response['response']['status_code'] >= 400 && $job_response['response']['status_code'] < 500) ||
				(in_array(5, $monitor['response_code']) && $job_response['response']['status_code'] >= 500 && $job_response['response']['status_code'] < 600);
			$jsonValid = intval($job_response['response']['json_valid'] ?? 0) === 1;
			if ($httpStatusGood && $jsonValid) {
				$status = \MonitoringLogs::STATUS_GOOD;
			} else {
				$status = \MonitoringLogs::STATUS_FAIL;
				if (!$httpStatusGood && (!isset($job_response['response']['response_error']) || !$job_response['response']['response_error'])) {
					$job_response['response']['response_error'] = 'Unexpected HTTP status code: '.intval($job_response['response']['status_code'] ?? 0);
				}
			}
		} elseif ($monitor['type'] == \Monitors::TYPE_TCP || $monitor['type'] == \Monitors::TYPE_UDP) {
			if ($job_response['response']['status'] == 1) {
				$status = \MonitoringLogs::STATUS_GOOD;
			}
		} elseif ($monitor['type'] == \Monitors::TYPE_PING) {
			if ($job_response['response']['status'] == 1) {
				$status = \MonitoringLogs::STATUS_GOOD;
			}
		} elseif ($monitor['type'] == \Monitors::TYPE_EXPORTER_DISK || $monitor['type'] == \Monitors::TYPE_EXPORTER_MEMORY || $monitor['type'] == \Monitors::TYPE_EXPORTER_CPU) {
			if (isset($job_response['response']['result'])) {
				if ($job_response['response']['result'] <= $monitor['value']) {
					$status = \MonitoringLogs::STATUS_GOOD;
				} else {
					$status = \MonitoringLogs::STATUS_FAIL;
				}
				$job_response['response']['response_error'] = $job_response['response']['result'];
			}
		} else {
			return false;
		}

		// HTTP(s): persist response header Log on the monitor (like External).
		// Kept across checks without Log until green; next send_repeat_minute alert uses it.
		if (
			intval($monitor['type']) === \Monitors::TYPE_HTTPS
			|| intval($monitor['type']) === \Monitors::TYPE_HTTP_JSON
		) {
			$logFromCheck = \ExternalMonitors::parseLogBodyFromResponseError(
				strval($job_response['response']['response_error'] ?? '')
			);
			\ExternalMonitors::persistMonitorLog(
				intval($monitor['id']),
				strval($monitor['external_log'] ?? ''),
				$status,
				$logFromCheck
			);
		}

		// Prefer check start_time for the log bucket. Workers may send
		// update_time as a schedule cursor (now / now-repeat after errors);
		// flooring that into the "current" minute hides the previous oval.
		$logUpdateTime = intval($job_response['start_time'] ?? 0);
		if ($logUpdateTime < 1) {
			$logUpdateTime = intval($job_response['update_time'] ?? 0);
		}
		if ($logUpdateTime < 1) {
			$logUpdateTime = time();
		}
		$params = [
			'monitor_id' => $job['monitor_id'],
			'worker_id' => $job['worker_id'],
			'job_id' => $job['id'],
			'status' => $status,
			'update_time' => $logUpdateTime,
			'status_code' => intval($job_response['response']['status_code'] ?? 0),
			'response_error_num' => intval($job_response['response']['response_error_num'] ?? 0),
			'response_error' => $job_response['response']['response_error'] ?? '',
			'total_time_us' => $job_response['response']['total_time_us'] ?? 0,
			'cert_expire' => intval($job_response['response']['cert_expire'] ?? 0),
		];
		$params['id'] = \MonitoringLogs::save($params);
		if ($params['id']) {
			return $params;
		}
		return false;
	}

	/**
	 * @return array<int, array<string, mixed>>|false
	 */
	private function resolveDockerContainerStates(array $response): array|false {
		if (!isset($response['container_states']) || !is_array($response['container_states']) || !$response['container_states']) {
			return false;
		}
		return $response['container_states'];
	}

	/**
	 * Compact Docker Engine /version payload from the worker (optional).
	 *
	 * @return array<string, string>|false
	 */
	private function resolveDockerEngineInfo(array $response): array|false {
		if (!isset($response['engine_info']) || !is_array($response['engine_info'])) {
			return false;
		}
		$info = $response['engine_info'];
		$version = trim(strval($info['Version'] ?? ''));
		if ($version === '') {
			return false;
		}
		return [
			'Version' => $version,
			'ApiVersion' => strval($info['ApiVersion'] ?? ''),
			'MinAPIVersion' => strval($info['MinAPIVersion'] ?? ''),
			'Os' => strval($info['Os'] ?? ''),
			'Arch' => strval($info['Arch'] ?? ''),
			'KernelVersion' => strval($info['KernelVersion'] ?? ''),
		];
	}

	private function normalizeJobResponse(array &$job_response): void {
		if (!isset($job_response['response']) || !is_array($job_response['response'])) {
			return;
		}
		$r = &$job_response['response'];
		if (array_key_exists('status_code', $r)) {
			$r['status_code'] = intval($r['status_code']);
		}
		if (array_key_exists('status', $r)) {
			$r['status'] = intval($r['status']);
		}
		if (array_key_exists('json_valid', $r)) {
			$r['json_valid'] = intval($r['json_valid']);
		}
		if (array_key_exists('response_error_num', $r)) {
			$r['response_error_num'] = intval($r['response_error_num']);
		}
	}

	/**
	 * @return array<int, int>
	 */
	private function parseMonitorResponseCodes(string $raw): array {
		$codes = [];
		foreach (explode(',', $raw) as $part) {
			$part = trim($part);
			if ($part === '') {
				continue;
			}
			$codes[] = intval($part);
		}
		return $codes;
	}

	private function requiresProtocolV2(): bool {
		$v = isset($this->data['protocol_version']) ? (string)$this->data['protocol_version'] : '';
		return $v !== '' && version_compare($v, '2.0', '>=');
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

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
			$this->updateDockerState($this->worker['id'], $docker);
		}

		return true;
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
		if ($dockerJob['update_time'] + 30 < time()) {
			\MonitoringDockers::save([
				'id' => $dockerJob['id'],
				'update_time' => $docker_response['update_time'] ??	0,
			]);
		}
		if (!isset($docker_response['response']) || !$docker_response['response'] || !is_array($docker_response['response'])) return false;
		$response = $docker_response['response'] ?? [];
		if (!is_array($response)) return false;
		if (!$response) return false;
		$response['status'] ??= 0;
		$response['status_code'] ??= 0;
		$response['response_error_num'] ??= 0;
		$response['response_error'] ??= '';
		$response['body'] ??= [];
		if (!$response['status']) return false;
		if (!($response['status_code'] >= 200 && $response['status_code'] < 299)) return false;

		if (!$response['body']) return false;
		$container_states = [];
		foreach ($response['body'] as $docker_container) {
			if (!$docker_container) continue;
			$name = $docker_container['Names'][0] ?? '';
			$name = substr($name, 1, strlen($name));
			$state = $docker_container['State']??'';
			$created = $docker_container['Created']??0;
			$id = $docker_container['Id']??'';
			$image = $docker_container['Image']??'';
			$image_id = $docker_container['ImageID']??'';
			$ports = [];
			foreach($docker_container['Ports'] as $port) {
				if (!$port) continue;
				if (!isset($port['IP']) || !$port['IP']) continue;
				if (!isset($port['PublicPort']) || !$port['PublicPort']) continue;
				$port_with_host = '';
				if ($port['IP'] == '::' || $port['IP'] == '0.0.0.0') {
					$port_with_host = $port['PublicPort'];
				} else {
					$port_with_host = $port['IP'] . ':' . $port['PublicPort'];
				}
				if (isset($port['Type']) && $port['Type']) {
					$port_with_host = $port_with_host . '/' . $port['Type'];

				}
				$ports[$port_with_host] = $port_with_host;
			}
			$ports = implode(', ', $ports);
			$container_states[] = [
				'id' => $id,
				'image' => $image,
				'image_id' => $image_id,
				'name' => $name,
				'state' => $state,
				'created' => $created,
				'ports' => $ports,
			];
		}
		$container_states = json_encode($container_states);

		$params = [
			'id' => $docker['id'],
			'container_states' => $container_states,
			'update_time' => $docker_response['update_time'] ?? time(),
			'_mode' => \DB\Common::CSMODE_UPDATE,
		];
		$params['id'] = \Dockers::save($params);
		if ($params['id']) {
			return true;
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
		if ($monitor['status'] == \Monitors::STATUS_DISABLED) return false;

		if ($job['update_time'] + 30 < time()) {
			\MonitoringJobs::save([
				'id' => $job['id'],
				'update_time' => $job_response['update_time'] ?? 0,
			]);
		}
		if (!isset($job_response['response']) || !$job_response['response'] || !is_array($job_response['response'])) return false;
		$status = \MonitoringLogs::STATUS_FAIL;

		$monitor['response_code'] = explode(',', $monitor['response_code']??'');

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
		} elseif ($monitor['type'] == \Monitors::TYPE_TCP) {
			if ($job_response['response']['status'] == 1) {
				$status = \MonitoringLogs::STATUS_GOOD;
			}
		} elseif ($monitor['type'] == \Monitors::TYPE_PING) {
			if ($job_response['response']['status'] == 1) {
				$status = \MonitoringLogs::STATUS_GOOD;
			}
		} else {
			return false;
		}

		$params = [
			'monitor_id' => $job['monitor_id'],                        // int DEFAULT 0,
			'worker_id' => $job['worker_id'],                          // int DEFAULT 0,
			'job_id' => $job['id'],                                    // int DEFAULT 0,
			'status' => $status,                                       // int DEFAULT 0,
			'update_time' => $job_response['update_time'] ?? 0,        // int DEFAULT 0,

			'status_code' => $job_response['response']['status_code'] ?? 0,        // int DEFAULT 0,
			'response_error_num' => intval($job_response['response']['response_error_num'] ?? 0),        // int DEFAULT 0,
			'response_error' => $job_response['response']['response_error'] ?? '',        // char(255) DEFAULT '',
			'redirect_count' => intval($job_response['response']['redirect_count'] ?? 0),

			'total_time_us' => $job_response['response']['total_time_us'] ?? 0,

			'effective_method' => $job_response['response']['effective_method'] ?? '',
			'primary_ip' => $job_response['response']['primary_ip'] ?? '',

			'primary_port' => $job_response['response']['primary_port'] ?? 0,
			'http_version' => $job_response['response']['http_version'] ?? 0,
			'protocol' => $job_response['response']['protocol'] ?? 0,
			'ssl_verifyresult' => $job_response['response']['ssl_verifyresult'] ?? 0,

			'scheme' => $job_response['response']['scheme'] ?? '',
			'cert_expire' => $job_response['response']['cert_expire'] ?? 0,
		];
		$params['id'] = \MonitoringLogs::save($params);
		if ($params['id']) {
			return $params;
		}
		return false;
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

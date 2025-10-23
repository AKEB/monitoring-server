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
			$jobs = \Jobs::data(['worker_id' => $this->worker['id']], sql_pholder(' AND M.`status` = ? ',\Monitors::STATUS_ENABLED), 't.`id` as `job_id`, t.`update_time`, M.*', false, false, [
				'_join' => ' JOIN `'.\Monitors::getTable().'` M ON t.`monitor_id` = M.`id`'
			]);
		}
		return $jobs;
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

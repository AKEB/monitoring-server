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
			e403();
		}
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
		$return = [
			'data' => [
				'worker_id' => $this->worker['id'],
				'worker_threads' => $this->worker['worker_threads'],
				'jobs_get_timeout' => $this->worker['jobs_get_timeout'],
				'loop_timeout' => $this->worker['loop_timeout'],
				'response_send_timeout' => $this->worker['response_send_timeout'],
				'logs_write_timeout' => $this->worker['logs_write_timeout'],
			],
			'status' => 0,
			'server_time' => time(),
			'server_microtime' => microtime(true),
		];
		header('Content-Encoding: UTF-8');
		header('Content-Type: application/json;charset=utf-8');
		echo json_encode($return);
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

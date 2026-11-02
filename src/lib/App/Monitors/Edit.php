<?php
namespace App\Monitors;

class Edit extends \Routing_Parent implements \Routing_Interface {
	private int $parent_id = 0;
	private int $monitor_id = 0;
	private array $breadcrumb = [];
	private array $monitor = [];
	private bool $can_read = false;
	private bool $can_write = false;
	private bool $can_create = false;
	private array $workers_hash = [];
	private array $notify_hash = [];

	public function Run(int $monitor_id=0, int $parent_id=0) {
		$this->monitor_id = intval($monitor_id ?? 0);
		$this->parent_id = intval($parent_id ?? 0);
		$this->check_auth();
		$this->check_permissions();

		if (isset($_POST) && $_POST) {
			if (isset($_POST['method']) && $_POST['method'] == 'ajaxDockerContainers') {
				$this->ajaxDockerContainers(intval($_POST['selectedDocker']), trim($_POST['selectedContainer']));
				return
				exit;
			}
		}

		$this->processRequest();
		$this->get_data();
		$this->get_breadcrumb();
		$this->template = new \Template('Monitor edit');
		$this->print_header();
		$this->print_breadcrumb();
		$this->print_forms();
		$this->print_javascript();
	}

	private function ajaxDockerContainers(?int $docker_id, ?string $container) {
		$container ??= '';
		echo '<option value="" '.($container == '' ? 'selected' : '').'>-- None --</option>';
		if (!isset($docker_id)) {
			return;
		}
		if (!$docker_id) {
			return;
		}
		if (!\Sessions::checkPermission(\Dockers::PERMISSION_ACCESS, $docker_id, READ)) {
			return;
		}
		$docker = \Dockers::get(['id' => $docker_id]);
		if (!$docker) {
			return;
		}
		if (!$docker['container_states']) {
			return;
		}
		$docker['container_states'] = @json_decode($docker['container_states'], true);
		if (!is_array($docker['container_states']) || !$docker['container_states']) {
			return;
		}

		foreach($docker['container_states'] as $v) {
			$name = htmlspecialchars($v['name']);
			echo '<option value="'.$name.'" '.($container == $name ? 'selected' : '').'>'.$name.'</option>';
		}

		return;
	}

	protected function handlePostData(array $data) {
		// Обработка POST данных
		if (!isset($data)) return;
		$this->save_monitor($data);
	}

	private function save_monitor($data) {
		if (!isset($data)) return;
		if (!$data) return;

		$params = [];
		do {
			if (isset($data['id']) && $data['id'] && $data['id'] != '') {
				$data['id'] = trim($data['id']);
				if ($this->monitor_id != intval($data['id'])) {
					$this->error = \T::Monitor_Edit_MonitorNotFound();
					break;
				}
				$params['id'] = intval($data['id']);
			}
			if (isset($data['title'])) {
				$data['title'] = trim($data['title']);
				if (mb_strlen($data['title']) < 2 || mb_strlen($data['title']) > 255) {
					$this->error = \T::Monitor_Edit_NameLengthError();
					break;
				}
				$params['title'] = $data['title'];
			}
			if (isset($data['type'])) {
				$data['type'] = trim($data['type']);
				$params['type'] = intval($data['type']);
			}
			if (isset($data['status']) && $data['status'] == 'true') {
				$params['status'] = 1;
			} else {
				$params['status'] = 0;
			}
			if (isset($data['type']) && intval($data['type']) == \Monitors::TYPE_FOLDER) {
				$params['api_enabled'] = 0;
			} elseif (isset($data['api_enabled']) && $data['api_enabled'] == 'true') {
				$params['api_enabled'] = 1;
			} else {
				$params['api_enabled'] = 0;
			}
			$params['api_description_ru'] = isset($data['api_description_ru']) ? trim($data['api_description_ru']) : '';
			$params['api_description_en'] = isset($data['api_description_en']) ? trim($data['api_description_en']) : '';

			if (isset($data['send_repeat_minute'])) {
				$data['send_repeat_minute'] = trim($data['send_repeat_minute']);
				$params['send_repeat_minute'] = intval($data['send_repeat_minute']);
			}
			if (isset($data['alert_fail_mode'])) {
				$alert_fail_mode = intval($data['alert_fail_mode']);
				$params['alert_fail_mode'] = isset(\Monitors::alert_fail_mode_hash()[$alert_fail_mode])
					? $alert_fail_mode
					: \Monitors::ALERT_FAIL_MODE_MAJORITY;
			} else {
				$params['alert_fail_mode'] = \Monitors::ALERT_FAIL_MODE_MAJORITY;
			}
			if (isset($data['repeat_seconds'])) {
				$data['repeat_seconds'] = trim($data['repeat_seconds']);
				$params['repeat_seconds'] = intval($data['repeat_seconds']);
				if ($params['repeat_seconds'] < 60) $params['repeat_seconds'] = 60;
			}
			if (isset($data['timeout'])) {
				$data['timeout'] = trim($data['timeout']);
				$params['timeout'] = intval($data['timeout']);
			}

			if (isset($data['signature_id'])) {
				$params['signature_id'] = intval($data['signature_id']);
				if ($params['signature_id'] > 0) {
					if (!\Sessions::checkPermission(\Signatures::PERMISSION_ACCESS, $params['signature_id'], READ)) {
						$this->error = \T::Monitor_Edit_PermissionDenied();
						break;
					}
					$signature = \Signatures::get(['id' => $params['signature_id']]);
					if (!$signature) {
						$params['signature_id'] = 0;
					}
				}
			} else {
				$params['signature_id'] = 0;
			}

			if ($data['type'] == \Monitors::TYPE_HTTPS || $data['type'] == \Monitors::TYPE_HTTP_JSON) {
				if (isset($data['url'])) {
					$data['url'] = trim($data['url']);
					$params['url'] = $data['url'];
				}
				if (isset($data['method'])) {
					$data['method'] = trim($data['method']);
					$params['method'] = $data['method'];
				}
				if (isset($data['max_redirects'])) {
					$data['max_redirects'] = trim($data['max_redirects']);
					$params['max_redirects'] = intval($data['max_redirects']);
				}
				if (isset($data['ssl_verify']) && $data['ssl_verify'] == 'true') {
					$params['ssl_verify'] = 1;
				} else {
					$params['ssl_verify'] = 0;
				}
				if (isset($data['ssl_verify_host']) && $data['ssl_verify_host'] == 'true') {
					$params['ssl_verify_host'] = 1;
				} else {
					$params['ssl_verify_host'] = 0;
				}

				if (isset($data['ssl_cert_expire_alert']) && $data['ssl_cert_expire_alert'] == 'true') {
					$params['ssl_cert_expire_alert'] = 1;
				} else {
					$params['ssl_cert_expire_alert'] = 0;
				}

				$allowed_ssl_days = \Monitors::ssl_cert_expire_alert_days_list();
				if (isset($data['ssl_cert_expire_alert_days']) && is_array($data['ssl_cert_expire_alert_days'])) {
					$ssl_days = array_map('intval', array_map('trim', $data['ssl_cert_expire_alert_days']));
					$ssl_days = array_values(array_intersect($ssl_days, $allowed_ssl_days));
					$params['ssl_cert_expire_alert_days'] = $ssl_days
						? implode(',', $ssl_days)
						: \Monitors::ssl_cert_expire_alert_days_default();
				} else {
					$params['ssl_cert_expire_alert_days'] = \Monitors::ssl_cert_expire_alert_days_default();
				}

				if (isset($data['response_code'])) {
					$data['response_code'] = array_map('intval',array_map('trim',$data['response_code']));
					$params['response_code'] = implode(',', $data['response_code']);
				}
				if (isset($data['proxy_host'])) {
					$data['proxy_host'] = trim($data['proxy_host']);
					$params['proxy_host'] = $data['proxy_host'];
				}
				if (isset($data['proxy_type'])) {
					$data['proxy_type'] = trim($data['proxy_type']);
					$params['proxy_type'] = intval($data['proxy_type']);
				}
				if (isset($data['request_headers'])) {
					$data['request_headers'] = trim($data['request_headers']);
					$params['request_headers'] = $data['request_headers'];
				}
				if (isset($data['json_path'])) {
					$data['json_path'] = trim($data['json_path']);
					$params['json_path'] = $data['json_path'];
				}
				if (isset($data['json_expected_value'])) {
					$data['json_expected_value'] = trim($data['json_expected_value']);
					$params['json_expected_value'] = $data['json_expected_value'];
				}
				if (isset($data['json_expected_type'])) {
					$data['json_expected_type'] = trim($data['json_expected_type']);
					$params['json_expected_type'] = $data['json_expected_type'];
				}
			} elseif ($data['type'] == \Monitors::TYPE_EXPORTER_DISK || $data['type'] == \Monitors::TYPE_EXPORTER_MEMORY || $data['type'] == \Monitors::TYPE_EXPORTER_CPU) {
				if (isset($data['host'])) {
					$data['host'] = trim($data['host']);
					$params['host'] = $data['host'];
				}
				if (isset($data['port'])) {
					$data['port'] = trim($data['port']);
					$params['port'] = intval($data['port']);
				}
				if (isset($data['value'])) {
					$data['value'] = trim($data['value']);
					$params['value'] = intval($data['value']);
				}
				if (isset($data['proxy_host'])) {
					$data['proxy_host'] = trim($data['proxy_host']);
					$params['proxy_host'] = $data['proxy_host'];
				}
				if (isset($data['proxy_type'])) {
					$data['proxy_type'] = trim($data['proxy_type']);
					$params['proxy_type'] = intval($data['proxy_type']);
				}
			} elseif ($data['type'] == \Monitors::TYPE_TCP) {
				if (isset($data['host'])) {
					$data['host'] = trim($data['host']);
					$params['host'] = $data['host'];
				}
				if (isset($data['port'])) {
					$data['port'] = trim($data['port']);
					$params['port'] = intval($data['port']);
				}
			} elseif ($data['type'] == \Monitors::TYPE_PING) {
				if (isset($data['host'])) {
					$data['host'] = trim($data['host']);
					$params['host'] = $data['host'];
				}
			} elseif ($data['type'] == \Monitors::TYPE_DOCKER) {
				if (isset($data['docker'])) {
					$data['docker'] = trim($data['docker']);
					$params['docker'] = intval($data['docker']);
					if (!\Sessions::checkPermission(\Dockers::PERMISSION_ACCESS, $params['docker'], READ)) {
						$this->error = \T::Monitor_Edit_PermissionDenied();
						break;
					}
					$docker = \Dockers::get(['id' => $params['docker']]);
					if (!$docker) {
						$this->error = \T::Monitor_Edit_DockerNotFound();
						break;
					}
					$params['repeat_seconds'] = intval($docker['repeat_seconds']);
					$params['timeout'] = intval($docker['timeout']);
				}
				if (isset($data['container'])) {
					$data['container'] = trim($data['container']);
					$params['container'] = $data['container'];
				}

			} elseif (intval($data['type']) == \Monitors::TYPE_EXTERNAL) {
				$params['repeat_seconds'] = 60;
				$params['timeout'] = 60;
				$data['workers'] = [];
			}

			if (isset($data['parent_id'])) {
				$data['parent_id'] = trim($data['parent_id']);
				$params['parent_id'] = intval($data['parent_id']);
			} else {
				$params['parent_id'] = $this->parent_id;
			}
			$workers_hash = [];
			$all_jobs = \Workers::data(false, ' ORDER BY `title` ASC', '`id`, `title`');
			if ($all_jobs) {
				foreach($all_jobs as $v) {
					$workers_hash[$v['id']] = \Sessions::checkPermission(\Workers::PERMISSION_ACCESS, $v['id'], READ);
				}
			}
			$notify_hash = [];
			$all_notify = \Notify::data(false, ' ORDER BY `title` ASC', '`id`, `title`');
			if ($all_notify) {
				foreach($all_notify as $v) {
					$notify_hash[$v['id']] = \Sessions::checkPermission(\Notify::PERMISSION_ACCESS, $v['id'], READ);
				}
			}

			$old_monitor = [];
			$update_jobs = [];
			$update_notify = [];
			if (!isset($data['workers'])) $data['workers'] = [];
			if (!isset($data['notify'])) $data['notify'] = [];
			if (isset($params['id']) && $params['id']) {
				if (!$this->can_write) {
					$this->error = \T::Monitor_Edit_PermissionDenied();
					break;
				}
				$old_monitor = \Monitors::get(['id' => $params['id']]);
				if (!$old_monitor) {
					$this->error = \T::Monitor_Edit_MonitorNotFound();
					break;
				}
				$new_type = intval($params['type'] ?? $old_monitor['type'] ?? 0);
				if ($new_type == \Monitors::TYPE_EXTERNAL) {
					$data['workers'] = [];
					$params['repeat_seconds'] = 60;
					$params['timeout'] = 60;
					$old_hash = trim(strval($old_monitor['external_hash'] ?? ''));
					if (intval($old_monitor['type']) != \Monitors::TYPE_EXTERNAL || $old_hash === '') {
						$params['external_hash'] = \ExternalMonitors::generateHash();
						$params['external_status'] = \ExternalMonitors::STATUS_UNSET;
						$params['external_log'] = '';
					}
				} elseif (intval($old_monitor['type']) == \Monitors::TYPE_EXTERNAL) {
					$params['external_hash'] = '';
					$params['external_status'] = \ExternalMonitors::STATUS_UNSET;
					$params['external_log'] = '';
				}
				$old_jobs = get_hash(\MonitoringJobs::data(['monitor_id' => $params['id']]), 'worker_id', 'monitor_id');
				foreach($old_jobs as $worker_id=>$_) {
					if (intval($worker_id) === \Monitors::EXTERNAL_WORKER_ID) continue;
					if (!isset($workers_hash[$worker_id]) || !$workers_hash[$worker_id]) continue;
					if (!in_array($worker_id, $data['workers'])) {
						$update_jobs[$worker_id] = -1;
					}
				}
				if ($data['workers']) {
					foreach($data['workers'] as $worker_id) {
						if (isset($update_jobs[$worker_id])) continue;
						if (isset($old_jobs[$worker_id]) && $old_jobs[$worker_id]) continue;
						$update_jobs[$worker_id] = 1;
					}
				}

				$old_notify = get_hash(\MonitoringNotify::data(['monitor_id' => $params['id']]), 'notify_id', 'monitor_id');
				foreach($old_notify as $notify_id=>$monitor_id) {
					if (!isset($notify_hash[$notify_id]) || !$notify_hash[$notify_id]) continue;
					if (!in_array($notify_id, $data['notify'])) {
						$update_notify[$notify_id] = -1;
					}
				}
				if ($data['notify']) {
					foreach($data['notify'] as $notify_id) {
						if (isset($update_notify[$notify_id])) continue;
						if (isset($old_notify[$notify_id]) && $old_notify[$notify_id]) continue;
						$update_notify[$notify_id] = 1;
					}
				}

				foreach($params as $k=>$v) {
					if ($old_monitor[$k] == $v) {
						unset($params[$k]);
					}
				}
				if (!$params && !$update_jobs && !$update_notify) {
					$this->success = \T::Monitor_Edit_NotingChanged();
					break;
				}
				$params['id'] = $old_monitor['id'];
			} else {
				if (!\Sessions::checkPermission(\Monitors::PERMISSION_CREATE, 0, WRITE)) {
					$this->error = \T::Monitor_Create_PermissionDenied();
					break;
				}
				if (intval($params['type'] ?? $data['type'] ?? 0) == \Monitors::TYPE_EXTERNAL) {
					$data['workers'] = [];
					$params['repeat_seconds'] = 60;
					$params['timeout'] = 60;
					$params['external_hash'] = \ExternalMonitors::generateHash();
					$params['external_status'] = \ExternalMonitors::STATUS_UNSET;
					$params['external_log'] = '';
				}
				if ($data['workers']) {
					foreach($data['workers'] as $worker_id) {
						if (isset($update_jobs[$worker_id])) continue;
						$update_jobs[$worker_id] = 1;
					}
				}
				if ($data['notify']) {
					foreach($data['notify'] as $notify_id) {
						if (isset($update_notify[$notify_id])) continue;
						$update_notify[$notify_id] = 1;
					}
				}
				$params['create_time'] = time();
			}
			$params['update_time'] = time();

			$monitor_id = 0;
			if (isset($params['id']) && $params['id']) {
				// Update
				$params['_mode'] = \DB\Common::CSMODE_UPDATE;
				$monitor_id = \Monitors::save($params);
				$new_monitor = \Monitors::get(['id' => $params['id']]);
				\Logs::update_log(\Monitors::LOGS_OBJECT, $params['id'], $old_monitor, $new_monitor,[
					'_save_fields' => ['id', 'title'],
				]);
				$monitor = \Monitors::get(['id' => $monitor_id]);
				if (!$monitor) {
					$this->error = \T::Monitor_Edit_MonitorNotFound();
					break;
				}
				foreach ($update_jobs as $worker_id=>$status) {
					if ($status < 0) {
						$old_job = \MonitoringJobs::get([
							'monitor_id' => $params['id'],
							'worker_id' => $worker_id,
						]);
						if (isset($old_job['id']) && $old_job['id']) {
							\MonitoringJobs::delete($old_job['id']);
							$log_id = \Logs::delete_log(\MonitoringJobs::LOGS_OBJECT, $old_job['id'], $old_job);
							\Logs::add_tag($log_id, \Monitors::LOGS_OBJECT, $params['id']);
							\Logs::add_tag($log_id, \Workers::LOGS_OBJECT, $worker_id);
						}
					} else {
						$job = [
							'monitor_id' => $params['id'],
							'worker_id' => $worker_id,
							'create_time' => time(),
							'update_time' => time(),
						];
						$job['id'] = \MonitoringJobs::save($job);
						$log_id = \Logs::create_log(\MonitoringJobs::LOGS_OBJECT, $job['id'],$job);
						\Logs::add_tag($log_id, \Monitors::LOGS_OBJECT, $params['id']);
						\Logs::add_tag($log_id, \Workers::LOGS_OBJECT, $worker_id);
					}
				}

				foreach ($update_notify as $notify_id=>$status) {
					if ($status < 0) {
						$old_notify = \MonitoringNotify::get([
							'monitor_id' => $params['id'],
							'notify_id' => $notify_id,
						]);
						if (isset($old_notify['id']) && $old_notify['id']) {
							\MonitoringNotify::delete($old_notify['id']);
							$log_id = \Logs::delete_log(\MonitoringNotify::LOGS_OBJECT, $old_notify['id'], $old_notify);
							\Logs::add_tag($log_id, \Monitors::LOGS_OBJECT, $params['id']);
							\Logs::add_tag($log_id, \Notify::LOGS_OBJECT, $notify_id);
						}
					} else {
						$notify = [
							'monitor_id' => $params['id'],
							'notify_id' => $notify_id,
							'create_time' => time(),
							'update_time' => time(),
						];
						$notify['id'] = \MonitoringNotify::save($notify);
						$log_id = \Logs::create_log(\MonitoringNotify::LOGS_OBJECT, $notify['id'], $notify);
						\Logs::add_tag($log_id, \Monitors::LOGS_OBJECT, $params['id']);
						\Logs::add_tag($log_id, \Notify::LOGS_OBJECT, $notify_id);
					}
				}
			} else {
				// Create
				$params['response_code'] = $params['response_code']??'';
				$params['request_headers'] = $params['request_headers'] ?? '';
				$params['_mode'] = \DB\Common::CSMODE_INSERT;
				$monitor_id = \Monitors::save($params);
				$monitor = \Monitors::get(['id' => $monitor_id]);
				$log_id = \Logs::create_log(\Monitors::LOGS_OBJECT, $monitor_id, $monitor);
				if (!$monitor) {
					$this->error = \T::Monitor_Edit_MonitorNotFound();
					break;
				}
				foreach ($update_jobs as $worker_id=>$status) {
					$job = [
						'monitor_id' => $monitor_id,
						'worker_id' => $worker_id,
						'create_time' => time(),
						'update_time' => time(),
					];
					$job['id'] = \MonitoringJobs::save($job);
					$log_id = \Logs::create_log(\MonitoringJobs::LOGS_OBJECT, $job['id'], $job);
					\Logs::add_tag($log_id, \Monitors::LOGS_OBJECT, $monitor_id);
					\Logs::add_tag($log_id, \Workers::LOGS_OBJECT, $worker_id);
				}

				foreach ($update_notify as $notify_id=>$status) {
					$notify = [
						'monitor_id' => $monitor_id,
						'notify_id' => $notify_id,
						'create_time' => time(),
						'update_time' => time(),
					];
					$notify['id'] = \MonitoringNotify::save($notify);
					$log_id = \Logs::create_log(\MonitoringNotify::LOGS_OBJECT, $notify['id'], $notify);
					\Logs::add_tag($log_id, \Monitors::LOGS_OBJECT, $monitor_id);
					\Logs::add_tag($log_id, \Notify::LOGS_OBJECT, $notify_id);
				}

				if (!\Sessions::in_group(\Groups::ADMIN_GROUP_ID, intval(\Sessions::currentUser()['id']))) {
					$permissions = \Monitors::permissions_hash();
					foreach($permissions as $permission => $_) {
						$ObjectPermissions = [
							'object' => 'user',
							'object_id' => intval(\Sessions::currentUser()['id']),
							'subject' => $permission,
							'subject_id' => $monitor_id,
							READ => 1,
							WRITE => 1,
							DELETE => 1,
							ACCESS_READ => 1,
							ACCESS_WRITE => 1,
							ACCESS_CHANGE => 1,
							'create_time' => time(),
							'update_time' => time(),
							'_mode' => \DB\Common::CSMODE_INSERT,
						];
						$ObjectPermissions['id'] = \ObjectPermissions::save($ObjectPermissions);
						$log_id = \Logs::create_log(\ObjectPermissions::LOGS_OBJECT, $ObjectPermissions['id'], $ObjectPermissions);
						\Logs::add_tag($log_id, \Monitors::LOGS_OBJECT, $monitor_id);
					}
				}
			}
			if ($monitor_id) {
				$monitor = \Monitors::get(['id' => $monitor_id]);
				if (intval($monitor['type']) == \Monitors::TYPE_EXTERNAL) {
					\ExternalMonitors::clearRealJobs(intval($monitor_id));
					\ExternalMonitors::ensureVirtualJob(intval($monitor_id));
				} else {
					\ExternalMonitors::removeVirtualJob(intval($monitor_id));
				}
				if ($monitor['type'] == \Monitors::TYPE_FOLDER && !$old_monitor) {
					common_redirect('/admin/monitors/'.$monitor['id'].'/');
				} elseif (intval($monitor['type']) == \Monitors::TYPE_EXTERNAL) {
					// Stay on edit page so callback URLs are visible right after create.
					common_redirect('/admin/monitors/'.$monitor['parent_id'].'/edit/'.$monitor_id.'/');
				} else {
					common_redirect('/admin/monitors/'.$monitor['parent_id'].'/');
				}
				// common_redirect('/admin/monitors/'.$this->parent_id.'/edit/' . $monitor_id . '/');
			} else {
				common_redirect('/admin/monitors/'.$this->parent_id.'/');
			}
		} while(false);
		foreach($data as $key => $value) {
			$this->monitor[$key] = $value;
		}
	}

	private function check_permissions() {
		if ($this->monitor_id) {
			$this->monitor = \Monitors::get(['id' => $this->monitor_id]);
			if (!$this->monitor) {
				common_redirect('/admin/monitors/'.$this->parent_id.'/');
			}
			$this->can_read = \Sessions::checkPermission(\Monitors::PERMISSION_ACCESS, $this->monitor_id, READ);
			if (!$this->can_read) {
				e403();
			}
			$this->can_write = \Sessions::checkPermission(\Monitors::PERMISSION_ACCESS, $this->monitor_id, WRITE);
		} else {
			$this->can_create = \Sessions::checkPermission(\Monitors::PERMISSION_CREATE, 0, WRITE);
			if (!$this->can_create) {
				e403();
			}
		}
	}

	private function get_data() {
		if (!isset($this->monitor['id'])) $this->monitor['id'] = '';

		if ($this->monitor['id']) {
			$this->monitor['create_date'] = (
				isset($this->monitor['create_time']) && $this->monitor['create_time'] > 0 ?
				date('Y-m-d H:i:s', $this->monitor['create_time']) : ''
			);
			$this->monitor['update_date'] = (
				isset($this->monitor['update_time']) && $this->monitor['update_time'] > 0 ?
				date('Y-m-d H:i:s', $this->monitor['update_time']) : ''
			);
			if (intval($this->monitor['type'] ?? 0) == \Monitors::TYPE_EXTERNAL) {
				if (trim(strval($this->monitor['external_hash'] ?? '')) === '') {
					$hash = \ExternalMonitors::generateHash();
					\Monitors::save([
						'id' => intval($this->monitor['id']),
						'external_hash' => $hash,
						'external_status' => \ExternalMonitors::STATUS_UNSET,
						'repeat_seconds' => 60,
						'timeout' => 60,
						'update_time' => time(),
						'_mode' => \DB\Common::CSMODE_UPDATE,
					]);
					$this->monitor['external_hash'] = $hash;
					$this->monitor['external_status'] = \ExternalMonitors::STATUS_UNSET;
				}
				\ExternalMonitors::ensureVirtualJob(intval($this->monitor['id']));
			}
			$this->monitor['workers'] = [];
			$jobs = \MonitoringJobs::data(['monitor_id' => $this->monitor['id']], false, '`worker_id`');
			if ($jobs) {
				foreach($jobs as $v) {
					$this->monitor['workers'][$v['worker_id']] = true;
				}
			}
			$this->monitor['notify'] = [];
			$notify = \MonitoringNotify::data(['monitor_id' => $this->monitor['id']], false, '`notify_id`');
			if ($notify) {
				foreach($notify as $v) {
					$this->monitor['notify'][$v['notify_id']] = true;
				}
			}
			if (isset($this->monitor['response_code']) && !is_array($this->monitor['response_code'])) {
				$this->monitor['response_code'] = explode(',', $this->monitor['response_code']??'');
			}
			if (!isset($this->monitor['ssl_cert_expire_alert_days']) || !is_array($this->monitor['ssl_cert_expire_alert_days'])) {
				$this->monitor['ssl_cert_expire_alert_days'] = \Monitors::parse_ssl_cert_expire_alert_days(
					is_string($this->monitor['ssl_cert_expire_alert_days'] ?? null)
						? $this->monitor['ssl_cert_expire_alert_days']
						: null
				);
			}

		} else {
			$this->monitor['status'] ??= \Monitors::STATUS_ENABLED;
			$this->monitor['api_enabled'] ??= 0;
			$this->monitor['api_description_ru'] ??= '';
			$this->monitor['api_description_en'] ??= '';
			$this->monitor['type'] ??= \Monitors::TYPE_FOLDER;
			$this->monitor['method'] ??= 'GET';
			$this->monitor['url'] ??= 'https://';
			$this->monitor['timeout'] ??= 15;
			$this->monitor['max_redirects'] ??= 10;
			$this->monitor['send_repeat_minute'] ??= 15;
			$this->monitor['alert_fail_mode'] ??= \Monitors::ALERT_FAIL_MODE_MAJORITY;
			$this->monitor['repeat_seconds'] ??= 60;
			$this->monitor['ssl_verify'] ??= 1;
			$this->monitor['ssl_verify_host'] ??= 1;
			$this->monitor['ssl_cert_expire_alert'] ??= 1;
			$this->monitor['ssl_cert_expire_alert_days'] ??= \Monitors::ssl_cert_expire_alert_days_list();
			$this->monitor['proxy_type'] ??= 0;
			$this->monitor['workers'] ??= [];
			$this->monitor['notify'] ??= [];
			$this->monitor['docker'] ??= 0;
			$this->monitor['container'] ??= '';
			$this->monitor['response_code'] ??= [2];
			$this->monitor['parent_id'] ??= $this->parent_id;
			$this->monitor['signature_id'] ??= 0;
			$this->monitor['value'] ??= 80;
			$this->monitor['request_headers'] ??= '';
			$this->monitor['json_path'] ??= '';
			$this->monitor['json_expected_value'] ??= 'true';
			$this->monitor['json_expected_type'] ??= 'bool';
		}

		$this->workers_hash = [];
		$data = \Workers::data(false, ' ORDER BY `title` ASC', '`id`, `title`');
		if ($data) {
			foreach($data as $v) {
				$this->workers_hash[$v['id']] = [
					'title' => htmlspecialchars($v['title']),
					'enable' => \Sessions::checkPermission(\Workers::PERMISSION_ACCESS, $v['id'], READ),
				];
			}
		}

		$this->notify_hash = [];
		$data = \Notify::data(false, ' ORDER BY `title` ASC', '`id`, `title`');
		if ($data) {
			foreach($data as $v) {
				$this->notify_hash[$v['id']] = [
					'title' => htmlspecialchars($v['title']),
					'enable' => \Sessions::checkPermission(\Notify::PERMISSION_ACCESS, $v['id'], READ),
				];
			}
		}
	}

	private function get_breadcrumb() {
		$data_hash = \Monitors::get_hash();
		$folders = [];
		$parent_id = $this->parent_id;
		while(true && $parent_id) {
			$folders[] = [
				'id' => $data_hash[$parent_id]['id'],
				'title' => $data_hash[$parent_id]['title'],
			];
			if (!$data_hash[$parent_id]['parent_id']) break;
			$parent_id = $data_hash[$parent_id]['parent_id'];

		}
		$folders[] = [
			'id' => 0,
			'title' => \T::Monitor_Table_Root(),
		];
		$this->breadcrumb = array_reverse($folders);
	}

	private function print_header() {
		?>
		<div class="float-start"><h2><i class="bi bi-question-octagon"></i> <?=\T::Monitor_PageTitle();?></h2></div>
		<div class="clearfix"></div>
		<?php
	}

	private function print_breadcrumb() {
		?>
		<nav aria-label="breadcrumb">
			<ol class="breadcrumb">
				<?php
				$last_index = count($this->breadcrumb) - 1;
				// if ($last_index > 0) {
					foreach($this->breadcrumb as $k=>$item) {
						echo '<li class="breadcrumb-item'.($k == $last_index ? ' active" aria-current="page':'').'"><a href="/admin/monitors/'.($item['id'] ? $item['id'].'/':'').'">'.htmlspecialchars($item['title']).'</a></li>';
					}
				// }
				?>
			</ol>
		</nav>
		<?php
	}
	private function print_forms() {
		?>
		<div class="row d-flex justify-content-center">
			<div class="card bg-transparent col-xl-10 p-4 mt-2 mb-3">
				<div class="card-header bg-transparent"><h3>
					<?php
					if ($this->monitor['id']) {
						echo \T::Monitor_Edit_EditTitle($this->monitor['title'], $this->monitor['id']);
					} else {
						echo \T::Monitor_Edit_CreateTitle();
					}
					?>
				</h3></div>
				<form class="card-body needs-validation" method="post" novalidate>
					<input type="hidden" name="id" value="<?=$this->monitor['id'];?>"/>
					<?php
					echo $this->template->html_input("title", $this->monitor['title']??'', \T::Monitor_Edit_Title(), true, [
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'readonly' => !($this->can_write || $this->can_create),
					]);
					echo $this->template?->html_select('type', \Monitors::type_hash(), $this->monitor['type']??0, \T::Monitor_Edit_Type(), true,[
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'readonly' => !($this->can_write || $this->can_create),
					]);
					echo $this->template->html_switch("status", intval($this->monitor['status']??0), \T::Monitor_Edit_Status(), false,[
						'readonly' => !($this->can_write || $this->can_create),
					]);
					echo $this->template->html_switch("api_enabled", intval($this->monitor['api_enabled']??0), \T::Monitor_Edit_ApiEnabled(), false,[
						'readonly' => !($this->can_write || $this->can_create),
						'global-class' => 'type-dependence type-'.\Monitors::TYPE_HTTPS.' type-'.\Monitors::TYPE_HTTP_JSON.' type-'.\Monitors::TYPE_TCP.' type-'.\Monitors::TYPE_PING.' type-'.\Monitors::TYPE_DOCKER.' type-'.\Monitors::TYPE_EXPORTER_DISK.' type-'.\Monitors::TYPE_EXPORTER_MEMORY.' type-'.\Monitors::TYPE_EXPORTER_CPU.' type-'.\Monitors::TYPE_EXTERNAL,
					]);
					$api_description_class = 'api-description-dependence';
					echo $this->template->html_input("api_description_ru", htmlspecialchars(strval($this->monitor['api_description_ru']??''), ENT_QUOTES, 'UTF-8'), \T::Monitor_Edit_ApiDescriptionRu(), false, [
						'type' => 'textarea',
						'rows' => '4',
						'cols' => '60',
						'global-class' => $api_description_class,
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'readonly' => !($this->can_write || $this->can_create),
					]);
					echo $this->template->html_input("api_description_en", htmlspecialchars(strval($this->monitor['api_description_en']??''), ENT_QUOTES, 'UTF-8'), \T::Monitor_Edit_ApiDescriptionEn(), false, [
						'type' => 'textarea',
						'rows' => '4',
						'cols' => '60',
						'global-class' => $api_description_class,
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'readonly' => !($this->can_write || $this->can_create),
					]);

					echo $this->template->html_input("url", $this->monitor['url']??'https://', \T::Monitor_Edit_Url(), false, [
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'global-class' => 'type-dependence type-'.\Monitors::TYPE_HTTPS.' type-'.\Monitors::TYPE_HTTP_JSON,
						'readonly' => !($this->can_write || $this->can_create),
					]);

					$external_urls = \ExternalMonitors::urlsForMonitor($this->monitor ?? []);
					if (!empty($this->monitor['id']) && !empty($external_urls['ok'])) {
						echo '<div class="type-dependence type-'.\Monitors::TYPE_EXTERNAL.' mb-3">';
						echo '<p class="text-muted">'.\T::Monitor_Edit_ExternalUrlsHint().'</p>';
						$copy_btn = '<button class="btn btn-outline-secondary copy-external-url" type="button" tabindex="-1" title="Copy"><i class="bi bi-clipboard"></i></button>';
						echo $this->template->html_input("external_ok_url", htmlspecialchars($external_urls['ok'], ENT_QUOTES, 'UTF-8'), \T::Monitor_Edit_ExternalOkUrl(), false, [
							'type' => 'textarea',
							'rows' => '3',
							'class1' => 'col-12',
							'class2' => 'col-12',
							'readonly' => true,
							'add_after' => $copy_btn,
						]);
						echo $this->template->html_input("external_fail_url", htmlspecialchars($external_urls['fail'], ENT_QUOTES, 'UTF-8'), \T::Monitor_Edit_ExternalFailUrl(), false, [
							'type' => 'textarea',
							'rows' => '3',
							'class1' => 'col-12',
							'class2' => 'col-12',
							'readonly' => true,
							'add_after' => $copy_btn,
						]);
						echo '</div>';
					}

					echo $this->template?->html_select('docker', \Dockers::subject_hash(), $this->monitor['docker']??0, \T::Monitor_Edit_Docker(), false,[
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'global-class' => 'type-dependence type-'.\Monitors::TYPE_DOCKER,
						'readonly' => !($this->can_write || $this->can_create),
					]);

					echo $this->template?->html_select('container', [], $this->monitor['container']??'', \T::Monitor_Edit_Container(), false,[
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'global-class' => 'type-dependence type-'.\Monitors::TYPE_DOCKER,
						'readonly' => !($this->can_write || $this->can_create),
						'with-undefined' => true,
						'global-id' => 'container-div',
					]);

					echo $this->template->html_input("host", $this->monitor['host']??'', \T::Monitor_Edit_Host(), false, [
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'global-class' => 'type-dependence type-'.\Monitors::TYPE_TCP.' type-'.\Monitors::TYPE_PING.' type-'.\Monitors::TYPE_EXPORTER_DISK.' type-'.\Monitors::TYPE_EXPORTER_MEMORY.' type-'.\Monitors::TYPE_EXPORTER_CPU,
						'readonly' => !($this->can_write || $this->can_create),
					]);

					echo $this->template->html_input("port", $this->monitor['port']??'', \T::Monitor_Edit_Port(), false, [
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'global-class' => 'type-dependence type-'.\Monitors::TYPE_TCP.' type-'.\Monitors::TYPE_EXPORTER_DISK.' type-'.\Monitors::TYPE_EXPORTER_MEMORY.' type-'.\Monitors::TYPE_EXPORTER_CPU,
						'readonly' => !($this->can_write || $this->can_create),
					]);

					echo $this->template->html_input("value", $this->monitor['value']??'80', \T::Monitor_Edit_Value(), false, [
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'global-class' => 'type-dependence type-'.\Monitors::TYPE_EXPORTER_DISK.' type-'.\Monitors::TYPE_EXPORTER_MEMORY.' type-'.\Monitors::TYPE_EXPORTER_CPU,
						'readonly' => !($this->can_write || $this->can_create),
					]);

					echo $this->template->html_params("workers[]", $this->workers_hash, $this->monitor['workers']??[], \T::Monitor_Edit_Workers(),false,[
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'global-class' => 'type-dependence type-'.\Monitors::TYPE_HTTPS.' type-'.\Monitors::TYPE_HTTP_JSON.' type-'.\Monitors::TYPE_TCP.' type-'.\Monitors::TYPE_PING.' type-'.\Monitors::TYPE_EXPORTER_DISK.' type-'.\Monitors::TYPE_EXPORTER_MEMORY.' type-'.\Monitors::TYPE_EXPORTER_CPU,
						'readonly' => !($this->can_write || $this->can_create),
					]);

					echo $this->template->html_params("notify[]", $this->notify_hash, $this->monitor['notify']??[], \T::Monitor_Edit_Notify(),false,[
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'readonly' => !($this->can_write || $this->can_create),
					]);

					echo $this->template?->html_select('method', \Monitors::HTTP_methods(), $this->monitor['method']??'', \T::Monitor_Edit_Method(), false,[
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'global-class' => 'type-dependence type-'.\Monitors::TYPE_HTTPS.' type-'.\Monitors::TYPE_HTTP_JSON,
						'readonly' => !($this->can_write || $this->can_create),
					]);
					echo $this->template->html_input("timeout", $this->monitor['timeout']??0, \T::Monitor_Edit_Timeout(), false, [
						'type' => 'number',
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'global-class' => 'type-dependence type-'.\Monitors::TYPE_HTTPS.' type-'.\Monitors::TYPE_HTTP_JSON.' type-'.\Monitors::TYPE_TCP.' type-'.\Monitors::TYPE_PING.' type-'.\Monitors::TYPE_EXPORTER_DISK.' type-'.\Monitors::TYPE_EXPORTER_MEMORY.' type-'.\Monitors::TYPE_EXPORTER_CPU,
						'readonly' => !($this->can_write || $this->can_create),
					]);
					echo $this->template->html_input("repeat_seconds", $this->monitor['repeat_seconds']??0, \T::Monitor_Edit_RepeatSeconds(), false, [
						'type' => 'number',
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'global-class' => 'type-dependence type-'.\Monitors::TYPE_HTTPS.' type-'.\Monitors::TYPE_HTTP_JSON.' type-'.\Monitors::TYPE_TCP.' type-'.\Monitors::TYPE_PING.' type-'.\Monitors::TYPE_EXPORTER_DISK.' type-'.\Monitors::TYPE_EXPORTER_MEMORY.' type-'.\Monitors::TYPE_EXPORTER_CPU,
						'readonly' => !($this->can_write || $this->can_create),
					]);
					echo $this->template->html_input("max_redirects", $this->monitor['max_redirects']??0, \T::Monitor_Edit_MaxRedirects(), false, [
						'type' => 'number',
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'global-class' => 'type-dependence type-'.\Monitors::TYPE_HTTPS.' type-'.\Monitors::TYPE_HTTP_JSON,
						'readonly' => !($this->can_write || $this->can_create),
					]);
					echo $this->template->html_input("send_repeat_minute", $this->monitor['send_repeat_minute']??0, \T::Monitor_Edit_SendRepeatMinute(), false, [
						'type' => 'number',
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'global-class' => 'type-dependence type-'.\Monitors::TYPE_HTTPS.' type-'.\Monitors::TYPE_HTTP_JSON.' type-'.\Monitors::TYPE_TCP.' type-'.\Monitors::TYPE_PING.' type-'.\Monitors::TYPE_DOCKER.' type-'.\Monitors::TYPE_EXPORTER_DISK.' type-'.\Monitors::TYPE_EXPORTER_MEMORY.' type-'.\Monitors::TYPE_EXPORTER_CPU.' type-'.\Monitors::TYPE_EXTERNAL,
						'readonly' => !($this->can_write || $this->can_create),
					]);
					echo $this->template?->html_select('alert_fail_mode', \Monitors::alert_fail_mode_hash(), intval($this->monitor['alert_fail_mode']??\Monitors::ALERT_FAIL_MODE_MAJORITY), \T::Monitor_Edit_AlertFailMode(), false,[
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'global-class' => 'type-dependence type-'.\Monitors::TYPE_HTTPS.' type-'.\Monitors::TYPE_HTTP_JSON.' type-'.\Monitors::TYPE_TCP.' type-'.\Monitors::TYPE_PING.' type-'.\Monitors::TYPE_DOCKER.' type-'.\Monitors::TYPE_EXPORTER_DISK.' type-'.\Monitors::TYPE_EXPORTER_MEMORY.' type-'.\Monitors::TYPE_EXPORTER_CPU.' type-'.\Monitors::TYPE_EXTERNAL,
						'readonly' => !($this->can_write || $this->can_create),
					]);
					echo $this->template?->html_select('response_code[]', \Monitors::response_code_hash(), $this->monitor['response_code']??[], \T::Monitor_Edit_ResponseCode(), false,[
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'multiple' => 'multiple',
						'global-class' => 'type-dependence type-'.\Monitors::TYPE_HTTPS.' type-'.\Monitors::TYPE_HTTP_JSON,
						'readonly' => !($this->can_write || $this->can_create),
					]);
					echo $this->template->html_switch("ssl_verify", intval($this->monitor['ssl_verify']??0), \T::Monitor_Edit_SSLVerify(), false,[
						'global-class' => 'type-dependence type-'.\Monitors::TYPE_HTTPS.' type-'.\Monitors::TYPE_HTTP_JSON,
						'readonly' => !($this->can_write || $this->can_create),
					]);
					echo $this->template->html_switch("ssl_verify_host", intval($this->monitor['ssl_verify_host']??0), \T::Monitor_Edit_SSLVerifyHost(), false,[
						'global-class' => 'type-dependence type-'.\Monitors::TYPE_HTTPS.' type-'.\Monitors::TYPE_HTTP_JSON,
						'readonly' => !($this->can_write || $this->can_create),
					]);
					echo $this->template->html_switch("ssl_cert_expire_alert", intval($this->monitor['ssl_cert_expire_alert']??0), \T::Monitor_Edit_SSLCertExpireAlert(), false,[
						'global-class' => 'type-dependence type-'.\Monitors::TYPE_HTTPS.' type-'.\Monitors::TYPE_HTTP_JSON,
						'readonly' => !($this->can_write || $this->can_create),
					]);
					echo $this->template?->html_select('ssl_cert_expire_alert_days[]', \Monitors::ssl_cert_expire_alert_days_hash(), $this->monitor['ssl_cert_expire_alert_days']??[], \T::Monitor_Edit_SSLCertExpireAlertDays(), false,[
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'multiple' => 'multiple',
						'global-class' => 'type-dependence type-'.\Monitors::TYPE_HTTPS.' type-'.\Monitors::TYPE_HTTP_JSON,
						'readonly' => !($this->can_write || $this->can_create),
					]);
					echo $this->template->html_input("request_headers", $this->monitor['request_headers']??'', \T::Monitor_Edit_RequestHeaders(), false, [
						'type' => 'textarea',
						'rows' => '4',
						'cols' => '60',
						'global-class' => 'type-dependence type-'.\Monitors::TYPE_HTTP_JSON.' type-'.\Monitors::TYPE_HTTPS,
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'readonly' => !($this->can_write || $this->can_create),
					]);
					echo $this->template->html_input("json_path", $this->monitor['json_path']??'', \T::Monitor_Edit_JSONPath(), false, [
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'global-class' => 'type-dependence type-'.\Monitors::TYPE_HTTP_JSON,
						'readonly' => !($this->can_write || $this->can_create),
					]);
					echo $this->template->html_input("json_expected_value", $this->monitor['json_expected_value']??'', \T::Monitor_Edit_JSONExpectedValue(), false, [
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'global-class' => 'type-dependence type-'.\Monitors::TYPE_HTTP_JSON,
						'readonly' => !($this->can_write || $this->can_create),
					]);
					echo $this->template?->html_select('json_expected_type', [
						'string' => 'string',
						'number' => 'number',
						'bool' => 'bool',
					], $this->monitor['json_expected_type']??'bool', \T::Monitor_Edit_JSONExpectedType(), false,[
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'global-class' => 'type-dependence type-'.\Monitors::TYPE_HTTP_JSON,
						'readonly' => !($this->can_write || $this->can_create),
					]);

					$signature_type_class = 'type-dependence type-'.\Monitors::TYPE_TCP.' type-'.\Monitors::TYPE_PING.' type-'.\Monitors::TYPE_HTTPS.' type-'.\Monitors::TYPE_HTTP_JSON.' type-'.\Monitors::TYPE_DOCKER.' type-'.\Monitors::TYPE_EXPORTER_DISK.' type-'.\Monitors::TYPE_EXPORTER_MEMORY.' type-'.\Monitors::TYPE_EXPORTER_CPU.' type-'.\Monitors::TYPE_EXTERNAL;
					$signatures_list = \Signatures::active_list();
					$current_signature_id = intval($this->monitor['signature_id'] ?? 0);
					if ($current_signature_id > 0 && !isset($signatures_list[$current_signature_id])) {
						if (\Sessions::checkPermission(\Signatures::PERMISSION_ACCESS, $current_signature_id, READ)) {
							$current_sig = \Signatures::get(['id' => $current_signature_id]);
							if ($current_sig) {
								$signatures_list[$current_signature_id] = [
									'id' => $current_signature_id,
									'project' => (string)($current_sig['project'] ?? ''),
									'title' => (string)($current_sig['title'] ?? ''),
									'text' => (string)($current_sig['text'] ?? ''),
								];
							} else {
								$current_signature_id = 0;
							}
						} else {
							$current_signature_id = 0;
						}
					}
					$signature_projects = [];
					foreach ($signatures_list as $sig) {
						$project = (string)($sig['project'] ?? '');
						$key = $project === '' ? '__empty__' : $project;
						$signature_projects[$key] = $project !== '' ? $project : \T::Monitor_Edit_SignatureEmptyProject();
					}
					$selected_project = '';
					$selected_project_key = '';
					$selected_text = '';
					if ($current_signature_id > 0 && isset($signatures_list[$current_signature_id])) {
						$selected_project = (string)$signatures_list[$current_signature_id]['project'];
						$selected_project_key = $selected_project === '' ? '__empty__' : $selected_project;
						$selected_text = (string)$signatures_list[$current_signature_id]['text'];
					}
					$signature_titles = [0 => \T::Monitor_Edit_SignatureNone()];
					foreach ($signatures_list as $sig) {
						if ((string)($sig['project'] ?? '') !== $selected_project) continue;
						$signature_titles[intval($sig['id'])] = (string)$sig['title'];
					}
					echo $this->template?->html_select('signature_project', $signature_projects, $selected_project_key, \T::Monitor_Edit_SignatureProject(), false, [
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'global-class' => $signature_type_class,
						'readonly' => !($this->can_write || $this->can_create),
						'with-undefined' => true,
						'undefined-value' => '',
						'undefined-title' => \T::Monitor_Edit_SignatureNone(),
					]);
					echo $this->template?->html_select('signature_id', $signature_titles, $current_signature_id, \T::Monitor_Edit_SignatureTitle(), false, [
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'global-class' => $signature_type_class,
						'readonly' => !($this->can_write || $this->can_create),
					]);
					echo $this->template->html_input("signature_text", $selected_text, \T::Monitor_Edit_SignatureText(), false, [
						'type' => 'textarea',
						'rows' => '5',
						'cols' => '60',
						'global-class' => $signature_type_class,
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'readonly' => true,
					]);
					$signature_link_style = $current_signature_id > 0 ? '' : 'display:none;';
					echo '<div class="mb-3 row '.$signature_type_class.'" id="signature-edit-link-row" style="'.$signature_link_style.'">';
					echo '<label class="col-xs-12 col-sm-12 col-md-3 col-form-label"></label>';
					echo '<div class="col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6">';
					echo '<a href="/admin/signatures/edit/'.$current_signature_id.'/" id="signature-edit-link" target="_blank" rel="noopener">'.\T::Monitor_Edit_SignatureEditLink().'</a>';
					echo '</div></div>';
					echo '<script type="application/json" id="signatures-data">'.json_encode(array_values($signatures_list), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT).'</script>';

					echo $this->template->html_input("proxy_host", $this->monitor['proxy_host']??'', \T::Monitor_Edit_ProxyHost(), false, [
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'global-class' => 'type-dependence type-'.\Monitors::TYPE_HTTPS.' type-'.\Monitors::TYPE_HTTP_JSON.' type-'.\Monitors::TYPE_EXPORTER_DISK.' type-'.\Monitors::TYPE_EXPORTER_MEMORY.' type-'.\Monitors::TYPE_EXPORTER_CPU,
						'readonly' => !($this->can_write || $this->can_create),
					]);
					echo $this->template?->html_select('proxy_type', \Monitors::proxy_type_hash(), $this->monitor['proxy_type']??0, \T::Monitor_Edit_ProxyType(), false,[
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'global-class' => 'type-dependence type-'.\Monitors::TYPE_HTTPS.' type-'.\Monitors::TYPE_HTTP_JSON.' type-'.\Monitors::TYPE_EXPORTER_DISK.' type-'.\Monitors::TYPE_EXPORTER_MEMORY.' type-'.\Monitors::TYPE_EXPORTER_CPU,
						'readonly' => !($this->can_write || $this->can_create),
					]);
					echo $this->template?->html_select('parent_id', \Monitors::get_folder_hash_options_hash(), $this->monitor['parent_id']??0, \T::Monitor_Edit_ParentFolder(), false,[
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'readonly' => !($this->can_write || $this->can_create),
					]);
					if ($this->monitor['id']) {
						echo $this->template->html_input("create_date", $this->monitor['create_date']??'', \T::Monitor_Edit_CreateTime(), false, ['readonly' => true]);
						echo $this->template->html_input("update_date", $this->monitor['update_date']??'', \T::Monitor_Edit_UpdateTime(), false, ['readonly' => true]);
					}
					?>
					<?php if ($this->can_write || $this->can_create) {
						?>
						<div class="d-flex flex-row-reverse">
							<button type="submit" class="btn btn-primary" name="<?=$this->monitor['id'] ? 'editMonitor' : 'createMonitor';?>" value="true"><?=($this->monitor['id'] ? \T::Monitor_Edit_ChangeButton() : \T::Monitor_Edit_CreateButton());?></button>
						</div>
						<?php
					}
					?>
				</form>
			</div>
		</div>
		<?php
	}

	private function print_javascript() {
		?>
		<script nonce="<?=\CSP::nonceRandom();?>">

			function monitor_type_select() {
				const selected_type = $('#type').val();
				$('.type-dependence').each(function(){
					$(this).hide();
				});
				$('.type-' + selected_type).each(function(){
					$(this).show();
				});
				monitor_api_fields_toggle();
			}

			function monitor_api_fields_toggle() {
				const enabled = $('#api_enabled').is(':checked');
				const isFolder = $('#type').val() == '<?=intval(\Monitors::TYPE_FOLDER);?>';
				$('.api-description-dependence').toggle(enabled && !isFolder);
			}

			function monitor_docker_select() {
				const selected_docker = $('#docker').val();
				const selected_container = '<?=($this->monitor['container']??'');?>';
				$('#container').load(
					'?',
					{
						selectedDocker: selected_docker,
						selectedContainer: selected_container,
						ajax: '1',
						method: 'ajaxDockerContainers'
					},
					function(response, status, xhr) {
						// $('#container').val('').change();
						$('#container').selectpicker('destroy').selectpicker();
					}
				);
			}

			function monitor_signature_project_value() {
				const raw = ($('#signature_project').val() || '').toString();
				if (raw === '__empty__') return '';
				return raw;
			}

			function monitor_signature_refresh(keepSignatureId) {
				const $dataEl = $('#signatures-data');
				if (!$dataEl.length) return;
				let signatures = [];
				try {
					signatures = JSON.parse($dataEl.text() || '[]');
				} catch (e) {
					signatures = [];
				}
				const projectKey = ($('#signature_project').val() || '').toString();
				const project = monitor_signature_project_value();
				const $title = $('#signature_id');
				const currentId = keepSignatureId ? String(keepSignatureId) : String($title.val() || '0');
				const noneLabel = <?=json_encode(\T::Monitor_Edit_SignatureNone(), JSON_UNESCAPED_UNICODE);?>;
				$title.empty();
				$title.append($('<option>', {value: '0', text: noneLabel}));
				let matched = null;
				if (projectKey !== '') {
					signatures.forEach(function(sig) {
						const sigProject = (sig.project || '').toString();
						if (sigProject !== project) return;
						const option = $('<option>', {value: String(sig.id), text: sig.title || ('#' + sig.id)});
						if (String(sig.id) === currentId) {
							option.prop('selected', true);
							matched = sig;
						}
						$title.append(option);
					});
				}
				if (!matched) {
					$title.val('0');
				}
				$title.selectpicker('destroy').selectpicker();
				monitor_signature_preview();
			}

			function monitor_signature_preview() {
				const $dataEl = $('#signatures-data');
				if (!$dataEl.length) return;
				let signatures = [];
				try {
					signatures = JSON.parse($dataEl.text() || '[]');
				} catch (e) {
					signatures = [];
				}
				const id = parseInt($('#signature_id').val() || '0', 10) || 0;
				let text = '';
				signatures.forEach(function(sig) {
					if (parseInt(sig.id, 10) === id) {
						text = sig.text || '';
					}
				});
				$('#signature_text').val(text);
				if (id > 0) {
					$('#signature-edit-link').attr('href', '/admin/signatures/edit/' + id + '/');
					$('#signature-edit-link-row').show();
				} else {
					$('#signature-edit-link-row').hide();
				}
			}

			$(document).ready(function(){
				monitor_type_select();
				$('#type').change(function(){
					monitor_type_select();
				});
				$('#api_enabled').on('change', function(){
					monitor_api_fields_toggle();
				});

				monitor_docker_select();
				$('#docker').change(function(){
					monitor_docker_select();
				});

				$('#signature_project').on('changed.bs.select change', function(){
					monitor_signature_refresh(0);
				});
				$('#signature_id').on('changed.bs.select change', function(){
					monitor_signature_preview();
				});

				$('form').on('submit', function(event) {
					$('form').addClass("was-validated");
				});

				$('body').on('click', '.copy-external-url', function() {
					const $field = $(this).closest('.input-group').find('textarea, input');
					const text = ($field.val() || '').toString();
					if (!text) return;
					const done = function() {
						const $icon = $(this).find('i');
						$icon.removeClass('bi-clipboard').addClass('bi-clipboard-check');
						setTimeout(() => {
							$icon.removeClass('bi-clipboard-check').addClass('bi-clipboard');
						}, 1500);
					}.bind(this);
					if (navigator.clipboard && navigator.clipboard.writeText) {
						navigator.clipboard.writeText(text).then(done).catch(function() {
							$field.trigger('select');
							document.execCommand('copy');
							done();
						});
					} else {
						$field.trigger('select');
						document.execCommand('copy');
						done();
					}
				});
			});
		</script>
		<?php
	}
}
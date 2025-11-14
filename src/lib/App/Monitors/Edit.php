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

			if (isset($data['send_repeat_minute'])) {
				$data['send_repeat_minute'] = trim($data['send_repeat_minute']);
				$params['send_repeat_minute'] = intval($data['send_repeat_minute']);
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

			if (isset($data['additional_text'])) {
				$data['additional_text'] = trim($data['additional_text']);
				$params['additional_text'] = $data['additional_text'];
			} else {
				$params['additional_text'] = '';
			}

			if ($data['type'] == \Monitors::TYPE_HTTPS) {
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
				$old_jobs = get_hash(\MonitoringJobs::data(['monitor_id' => $params['id']]), 'worker_id', 'monitor_id');
				foreach($old_jobs as $worker_id=>$_) {
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
				if ($monitor['type'] == \Monitors::TYPE_FOLDER && !$old_monitor) {
					common_redirect('/monitors/'.$monitor['id'].'/');
				} else {
					common_redirect('/monitors/'.$monitor['parent_id'].'/');
				}
				// common_redirect('/monitors/'.$this->parent_id.'/edit/' . $monitor_id . '/');
			} else {
				common_redirect('/monitors/'.$this->parent_id.'/');
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
				common_redirect('/monitors/'.$this->parent_id.'/');
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

		} else {
			$this->monitor['status'] ??= \Monitors::STATUS_ENABLED;
			$this->monitor['type'] ??= \Monitors::TYPE_FOLDER;
			$this->monitor['method'] ??= 'GET';
			$this->monitor['url'] ??= 'https://';
			$this->monitor['timeout'] ??= 15;
			$this->monitor['max_redirects'] ??= 10;
			$this->monitor['send_repeat_minute'] ??= 15;
			$this->monitor['repeat_seconds'] ??= 60;
			$this->monitor['ssl_verify'] ??= 1;
			$this->monitor['ssl_verify_host'] ??= 1;
			$this->monitor['ssl_cert_expire_alert'] ??= 1;
			$this->monitor['proxy_type'] ??= 0;
			$this->monitor['workers'] ??= [];
			$this->monitor['notify'] ??= [];
			$this->monitor['docker'] ??= 0;
			$this->monitor['container'] ??= '';
			$this->monitor['response_code'] ??= [2];
			$this->monitor['parent_id'] ??= $this->parent_id;
			$this->monitor['additional_text'] ??= '';
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
						echo '<li class="breadcrumb-item'.($k == $last_index ? ' active" aria-current="page':'').'"><a href="/monitors/'.($item['id'] ? $item['id'].'/':'').'">'.htmlspecialchars($item['title']).'</a></li>';
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

					echo $this->template->html_input("url", $this->monitor['url']??'https://', \T::Monitor_Edit_Url(), false, [
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'global-class' => 'type-dependence type-'.\Monitors::TYPE_HTTPS,
						'readonly' => !($this->can_write || $this->can_create),
					]);

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
						'global-class' => 'type-dependence type-'.\Monitors::TYPE_TCP.' type-'.\Monitors::TYPE_PING,
						'readonly' => !($this->can_write || $this->can_create),
					]);

					echo $this->template->html_input("port", $this->monitor['port']??'', \T::Monitor_Edit_Port(), false, [
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'global-class' => 'type-dependence type-'.\Monitors::TYPE_TCP,
						'readonly' => !($this->can_write || $this->can_create),
					]);

					echo $this->template->html_params("workers[]", $this->workers_hash, $this->monitor['workers']??[], \T::Monitor_Edit_Workers(),false,[
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'global-class' => 'type-dependence type-'.\Monitors::TYPE_HTTPS.' type-'.\Monitors::TYPE_TCP.' type-'.\Monitors::TYPE_PING,
						'readonly' => !($this->can_write || $this->can_create),
					]);

					echo $this->template->html_params("notify[]", $this->notify_hash, $this->monitor['notify']??[], \T::Monitor_Edit_Notify(),false,[
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'readonly' => !($this->can_write || $this->can_create),
					]);

					echo $this->template?->html_select('method', \Monitors::HTTP_methods(), $this->monitor['method']??'', \T::Monitor_Edit_Method(), false,[
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'global-class' => 'type-dependence type-'.\Monitors::TYPE_HTTPS,
						'readonly' => !($this->can_write || $this->can_create),
					]);
					echo $this->template->html_input("timeout", $this->monitor['timeout']??0, \T::Monitor_Edit_Timeout(), false, [
						'type' => 'number',
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'global-class' => 'type-dependence type-'.\Monitors::TYPE_HTTPS.' type-'.\Monitors::TYPE_TCP.' type-'.\Monitors::TYPE_PING,
						'readonly' => !($this->can_write || $this->can_create),
					]);
					echo $this->template->html_input("repeat_seconds", $this->monitor['repeat_seconds']??0, \T::Monitor_Edit_RepeatSeconds(), false, [
						'type' => 'number',
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'global-class' => 'type-dependence type-'.\Monitors::TYPE_HTTPS.' type-'.\Monitors::TYPE_TCP.' type-'.\Monitors::TYPE_PING,
						'readonly' => !($this->can_write || $this->can_create),
					]);
					echo $this->template->html_input("max_redirects", $this->monitor['max_redirects']??0, \T::Monitor_Edit_MaxRedirects(), false, [
						'type' => 'number',
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'global-class' => 'type-dependence type-'.\Monitors::TYPE_HTTPS,
						'readonly' => !($this->can_write || $this->can_create),
					]);
					echo $this->template->html_input("send_repeat_minute", $this->monitor['send_repeat_minute']??0, \T::Monitor_Edit_SendRepeatMinute(), false, [
						'type' => 'number',
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'global-class' => 'type-dependence type-'.\Monitors::TYPE_HTTPS.' type-'.\Monitors::TYPE_TCP.' type-'.\Monitors::TYPE_PING.' type-'.\Monitors::TYPE_DOCKER,
						'readonly' => !($this->can_write || $this->can_create),
					]);
					echo $this->template?->html_select('response_code[]', \Monitors::response_code_hash(), $this->monitor['response_code']??[], \T::Monitor_Edit_ResponseCode(), false,[
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'multiple' => 'multiple',
						'global-class' => 'type-dependence type-'.\Monitors::TYPE_HTTPS,
						'readonly' => !($this->can_write || $this->can_create),
					]);
					echo $this->template->html_switch("ssl_verify", intval($this->monitor['ssl_verify']??0), \T::Monitor_Edit_SSLVerify(), false,[
						'global-class' => 'type-dependence type-'.\Monitors::TYPE_HTTPS,
						'readonly' => !($this->can_write || $this->can_create),
					]);
					echo $this->template->html_switch("ssl_verify_host", intval($this->monitor['ssl_verify_host']??0), \T::Monitor_Edit_SSLVerifyHost(), false,[
						'global-class' => 'type-dependence type-'.\Monitors::TYPE_HTTPS,
						'readonly' => !($this->can_write || $this->can_create),
					]);
					echo $this->template->html_switch("ssl_cert_expire_alert", intval($this->monitor['ssl_cert_expire_alert']??0), \T::Monitor_Edit_SSLCertExpireAlert(), false,[
						'global-class' => 'type-dependence type-'.\Monitors::TYPE_HTTPS,
						'readonly' => !($this->can_write || $this->can_create),
					]);

					echo $this->template->html_input("additional_text", $this->monitor['additional_text']??'', \T::Monitor_Edit_AdditionalAlertText(), false, [
						'type' => 'textarea',
						'rows' => '5',
						'cols' => '60',
						'global-class' => 'type-dependence type-'.\Monitors::TYPE_TCP.' type-'.\Monitors::TYPE_PING.' type-'.\Monitors::TYPE_HTTPS.' type-'.\Monitors::TYPE_DOCKER,
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6'
					]);

					echo $this->template->html_input("proxy_host", $this->monitor['proxy_host']??'', \T::Monitor_Edit_ProxyHost(), false, [
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'global-class' => 'type-dependence type-'.\Monitors::TYPE_HTTPS,
						'readonly' => !($this->can_write || $this->can_create),
					]);
					echo $this->template?->html_select('proxy_type', \Monitors::proxy_type_hash(), $this->monitor['proxy_type']??0, \T::Monitor_Edit_ProxyType(), false,[
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'global-class' => 'type-dependence type-'.\Monitors::TYPE_HTTPS,
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

			$(document).ready(function(){
				monitor_type_select();
				$('#type').change(function(){
					monitor_type_select();
				});

				monitor_docker_select();
				$('#docker').change(function(){
					monitor_docker_select();
				});

				$('form').on('submit', function(event) {
					$('form').addClass("was-validated");
				});
			});
		</script>
		<?php
	}
}
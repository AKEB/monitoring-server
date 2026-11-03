<?php
namespace App\Workers;

class Edit extends \Routing_Parent implements \Routing_Interface {
	private int $worker_id = 0;
	private array $worker = [];
	private bool $can_read = false;
	private bool $can_write = false;
	private bool $can_create = false;
	private array $monitors_options = [];
	private array $dockers_options = [];

	public function Run($worker_id=0) {
		$this->worker_id = intval($worker_id ?? 0);
		$this->check_auth();
		$this->check_permissions();

		$this->processRequest();
		$this->get_data();
		$this->template = new \Template('Worker edit');
		$this->print_header();

		$this->print_forms();
		$this->print_javascript();
	}

	protected function handlePostData(array $data) {
		// Обработка POST данных
		if (!isset($data)) return;
		$this->save_worker($data);
	}

	/**
	 * @param array<string,mixed> $data
	 */
	private function save_worker(array $data): void {
		if (!$data) return;

		if (!empty($data['restart_worker']) && !empty($data['id'])) {
			$workerId = intval($data['id']);
			if (!$workerId || $this->worker_id != $workerId) {
				$this->error = \T::Worker_Edit_WorkerNotFound();
				return;
			}
			if (!\Sessions::checkPermission(\Workers::PERMISSION_ACCESS, $workerId, WRITE)) {
				$this->error = \T::Worker_Edit_PermissionDenied();
				return;
			}
			\Workers::save([
				'id' => $workerId,
				'restart_requested' => 1,
				'update_time' => time(),
				'_mode' => \DB\Common::CSMODE_UPDATE,
			]);
			$this->worker = \Workers::get(['id' => $workerId]);
			return;
		}

		$params = [];
		do {
			if (isset($data['id']) && $data['id'] && $data['id'] != '') {
				$data['id'] = trim($data['id']);
				if ($this->worker_id != intval($data['id'])) {
					$this->error = \T::Worker_Edit_WorkerNotFound();
					break;
				}
				$params['id'] = intval($data['id']);
			}
			if (isset($data['title']) && $data['title'] && $data['title'] != '') {
				$data['title'] = trim($data['title']);
				if (!\EntityValidation::isTitleLengthValid($data['title'])) {
					$this->error = \T::Worker_Edit_NameLengthError();
					break;
				}
				$params['title'] = $data['title'];
			}

			if (isset($data['worker_threads']) && $data['worker_threads'] && $data['worker_threads'] != '') {
				$data['worker_threads'] = trim($data['worker_threads']);
				$params['worker_threads'] = intval($data['worker_threads']);
			}
			if (isset($data['jobs_get_timeout']) && $data['jobs_get_timeout'] && $data['jobs_get_timeout'] != '') {
				$data['jobs_get_timeout'] = trim($data['jobs_get_timeout']);
				$params['jobs_get_timeout'] = intval($data['jobs_get_timeout']);
			}
			if (isset($data['loop_timeout']) && $data['loop_timeout'] && $data['loop_timeout'] != '') {
				$data['loop_timeout'] = trim($data['loop_timeout']);
				$params['loop_timeout'] = intval($data['loop_timeout']);
			}
			if (isset($data['response_send_timeout']) && $data['response_send_timeout'] && $data['response_send_timeout'] != '') {
				$data['response_send_timeout'] = trim($data['response_send_timeout']);
				$params['response_send_timeout'] = intval($data['response_send_timeout']);
			}
			if (isset($data['logs_write_timeout']) && $data['logs_write_timeout'] && $data['logs_write_timeout'] != '') {
				$data['logs_write_timeout'] = trim($data['logs_write_timeout']);
				$params['logs_write_timeout'] = intval($data['logs_write_timeout']);
			}

			$old_worker = [];
			$update_jobs = [];
			$update_dockers = [];
			if (!isset($data['monitors']) || !is_array($data['monitors'])) {
				$data['monitors'] = [];
			}
			$data['monitors'] = array_map('intval', $data['monitors']);
			if (!isset($data['dockers']) || !is_array($data['dockers'])) {
				$data['dockers'] = [];
			}
			$data['dockers'] = array_map('intval', $data['dockers']);

			if (isset($params['id']) && $params['id']) {
				if (!\Sessions::checkPermission(\Workers::PERMISSION_ACCESS, $params['id'], WRITE)) {
					$this->error = \T::Worker_Edit_PermissionDenied();
					break;
				}
				$old_worker = \Workers::get(['id' => $params['id']]);
				if (!$old_worker) {
					$this->error = \T::Worker_Edit_WorkerNotFound();
					break;
				}
				$allowed_monitors = [];
				$all_monitors = \Monitors::data(false, sql_pholder(' AND `type` NOT IN (?@)', [\Monitors::TYPE_FOLDER, \Monitors::TYPE_DOCKER]), '`id`');
				if ($all_monitors) {
					foreach ($all_monitors as $monitor) {
						$allowed_monitors[intval($monitor['id'])] = \Sessions::checkPermission(\Monitors::PERMISSION_ACCESS, intval($monitor['id']), READ);
					}
				}
				$old_jobs = get_hash(\MonitoringJobs::data(['worker_id' => $params['id']], false, '`monitor_id`'), 'monitor_id', 'monitor_id');
				foreach ($old_jobs as $monitor_id => $_) {
					if (!isset($allowed_monitors[$monitor_id]) || !$allowed_monitors[$monitor_id]) continue;
					if (!in_array(intval($monitor_id), $data['monitors'], true)) {
						$update_jobs[intval($monitor_id)] = -1;
					}
				}
				foreach ($data['monitors'] as $monitor_id) {
					if (!isset($allowed_monitors[$monitor_id]) || !$allowed_monitors[$monitor_id]) continue;
					if (isset($update_jobs[$monitor_id])) continue;
					if (isset($old_jobs[$monitor_id]) && $old_jobs[$monitor_id]) continue;
					$update_jobs[$monitor_id] = 1;
				}

				$allowed_dockers = [];
				$all_dockers = \Dockers::data(false, ' ORDER BY NULL', '`id`');
				if ($all_dockers) {
					foreach ($all_dockers as $docker) {
						$allowed_dockers[intval($docker['id'])] = \Sessions::checkPermission(\Dockers::PERMISSION_ACCESS, intval($docker['id']), READ);
					}
				}
				$old_dockers = get_hash(\MonitoringDockers::data(['worker_id' => $params['id']], false, '`docker_id`'), 'docker_id', 'docker_id');
				foreach ($old_dockers as $docker_id => $_) {
					if (!isset($allowed_dockers[$docker_id]) || !$allowed_dockers[$docker_id]) continue;
					if (!in_array(intval($docker_id), $data['dockers'], true)) {
						$update_dockers[intval($docker_id)] = -1;
					}
				}
				foreach ($data['dockers'] as $docker_id) {
					if (!isset($allowed_dockers[$docker_id]) || !$allowed_dockers[$docker_id]) continue;
					if (isset($update_dockers[$docker_id])) continue;
					if (isset($old_dockers[$docker_id]) && $old_dockers[$docker_id]) continue;
					$update_dockers[$docker_id] = 1;
				}
				foreach($params as $k=>$v) {
					if ($old_worker[$k] == $v) {
						unset($params[$k]);
					}
				}
				if (!$params && !$update_jobs && !$update_dockers) {
					$this->success = \T::Worker_Edit_NotingChanged();
					break;
				}
				$params['id'] = $old_worker['id'];
			} else {
				if (!\Sessions::checkPermission(\Workers::PERMISSION_CREATE, 0, WRITE)) {
					$this->error = \T::Worker_Create_PermissionDenied();
					break;
				}

				do {
					$worker_key_hash = hash('sha512', md5($params['title'] . microtime() . random_int(100000000,999999999)));
					$worker = \Workers::get(['worker_key_hash' => $worker_key_hash]);
					if ($worker) continue;
					$params['worker_key_hash'] = $worker_key_hash;
					break;
				} while(true);

				$params['create_time'] = time();
			}
			$params['update_time'] = time();

			$worker_id = 0;
			if (isset($params['id']) && $params['id']) {
				// Update
				$params['_mode'] = \DB\Common::CSMODE_UPDATE;
				$worker_id = \Workers::save($params);
				$new_worker = \Workers::get(['id' => $params['id']]);
				\Logs::update_log(\Workers::LOGS_OBJECT, $params['id'], $old_worker, $new_worker,[
					'_save_fields' => ['id', 'title'],
				]);
				$worker = \Workers::get(['id' => $worker_id]);
				if (!$worker) {
					$this->error = \T::Worker_Edit_WorkerNotFound();
					break;
				}
				foreach ($update_jobs as $monitor_id => $status) {
					if ($status < 0) {
						$old_job = \MonitoringJobs::get([
							'monitor_id' => $monitor_id,
							'worker_id' => $params['id'],
						]);
						if (isset($old_job['id']) && $old_job['id']) {
							\MonitoringJobs::delete($old_job['id']);
							$log_id = \Logs::delete_log(\MonitoringJobs::LOGS_OBJECT, $old_job['id'], $old_job);
							\Logs::add_tag($log_id, \Monitors::LOGS_OBJECT, $monitor_id);
							\Logs::add_tag($log_id, \Workers::LOGS_OBJECT, $params['id']);
						}
					} else {
						$job = [
							'monitor_id' => $monitor_id,
							'worker_id' => $params['id'],
							'create_time' => time(),
							'update_time' => time(),
						];
						$job['id'] = \MonitoringJobs::save($job);
						$log_id = \Logs::create_log(\MonitoringJobs::LOGS_OBJECT, $job['id'], $job);
						\Logs::add_tag($log_id, \Monitors::LOGS_OBJECT, $monitor_id);
						\Logs::add_tag($log_id, \Workers::LOGS_OBJECT, $params['id']);
					}
				}
				foreach ($update_dockers as $docker_id => $status) {
					if ($status < 0) {
						$old_job = \MonitoringDockers::get([
							'docker_id' => $docker_id,
							'worker_id' => $params['id'],
						]);
						if (isset($old_job['id']) && $old_job['id']) {
							\MonitoringDockers::delete($old_job['id']);
							$log_id = \Logs::delete_log(\MonitoringDockers::LOGS_OBJECT, $old_job['id'], $old_job);
							\Logs::add_tag($log_id, \Dockers::LOGS_OBJECT, $docker_id);
							\Logs::add_tag($log_id, \Workers::LOGS_OBJECT, $params['id']);
						}
					} else {
						$job = [
							'docker_id' => $docker_id,
							'worker_id' => $params['id'],
							'create_time' => time(),
							'update_time' => time(),
						];
						$job['id'] = \MonitoringDockers::save($job);
						$log_id = \Logs::create_log(\MonitoringDockers::LOGS_OBJECT, $job['id'], $job);
						\Logs::add_tag($log_id, \Dockers::LOGS_OBJECT, $docker_id);
						\Logs::add_tag($log_id, \Workers::LOGS_OBJECT, $params['id']);
					}
				}
			} else {
				// Create
				$params['_mode'] = \DB\Common::CSMODE_INSERT;
				$worker_id = \Workers::save($params);
				$worker = \Workers::get(['id' => $worker_id]);
				$log_id = \Logs::create_log(\Workers::LOGS_OBJECT, $worker_id, $worker);
				if (!$worker) {
					$this->error = \T::Worker_Edit_WorkerNotFound();
					break;
				}

				if (!\Sessions::in_group(\Groups::ADMIN_GROUP_ID, intval(\Sessions::currentUser()['id']))) {
					$permissions = \Workers::permissions_hash();
					foreach($permissions as $permission => $_) {
						$object_permission = [
							'object' => 'user',
							'object_id' => intval(\Sessions::currentUser()['id']),
							'subject' => $permission,
							'subject_id' => $worker_id,
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
						$object_permission['id'] = \ObjectPermissions::save($object_permission);
						$log_id = \Logs::create_log(\ObjectPermissions::LOGS_OBJECT, $object_permission['id'], $object_permission);
						\Logs::add_tag($log_id, \Workers::LOGS_OBJECT, $worker_id);
					}
				}
			}
			if ($worker_id) {
				common_redirect('/admin/workers/edit/' . $worker_id . '/');
			} else {
				common_redirect('/admin/workers/');
			}
		} while(false);
		foreach($data as $key => $value) {
			$this->worker[$key] = $value;
		}
	}

	private function check_permissions() {
		if ($this->worker_id) {
			$this->worker = \Workers::get(['id' => $this->worker_id]);
			if (!$this->worker) {
				common_redirect('/admin/workers/');
			}
			$this->can_read = \Sessions::checkPermission(\Workers::PERMISSION_ACCESS, $this->worker_id, READ);
			if (!$this->can_read) {
				e403();
			}
			$this->can_write = \Sessions::checkPermission(\Workers::PERMISSION_ACCESS, $this->worker_id, WRITE);
		} else {
			$this->can_read = \Sessions::checkPermission(\Workers::PERMISSION_ACCESS, -1, READ);
			$this->can_create = \Sessions::checkPermission(\Workers::PERMISSION_CREATE, 0, WRITE);
			if (!$this->can_read && !$this->can_create) {
				e403();
			}
		}
	}

	private function get_data() {
		if (!isset($this->worker['id'])) $this->worker['id'] = '';
		$this->worker['monitors'] ??= [];
		$this->worker['dockers'] ??= [];

		if ($this->worker['id']) {
			$this->worker['create_date'] = (
				isset($this->worker['create_time']) && $this->worker['create_time'] > 0 ?
				date('Y-m-d H:i:s', $this->worker['create_time']) : ''
			);
			$this->worker['update_date'] = (
				isset($this->worker['update_time']) && $this->worker['update_time'] > 0 ?
				date('Y-m-d H:i:s', $this->worker['update_time']) : ''
			);
			$this->worker['last_active_date'] = (
				isset($this->worker['last_active_time']) && $this->worker['last_active_time'] > 0 ?
				date('Y-m-d H:i:s', $this->worker['last_active_time']) : ''
			);
			$jobs = \MonitoringJobs::data(['worker_id' => $this->worker['id']], false, '`monitor_id`');
			if ($jobs) {
				foreach ($jobs as $job) {
					$this->worker['monitors'][] = intval($job['monitor_id']);
				}
			}
			$dockerJobs = \MonitoringDockers::data(['worker_id' => $this->worker['id']], false, '`docker_id`');
			if ($dockerJobs) {
				foreach ($dockerJobs as $job) {
					$this->worker['dockers'][] = intval($job['docker_id']);
				}
			}
		}
		$monitors = \Monitors::data(
			false,
			sql_pholder(' AND `type` NOT IN (?@) ORDER BY `title` ASC', [\Monitors::TYPE_FOLDER, \Monitors::TYPE_DOCKER]),
			'`id`, `title`, `parent_id`'
		);
		if ($monitors) {
			foreach ($monitors as $monitor) {
				$monitor_id = intval($monitor['id']);
				if (!\Sessions::checkPermission(\Monitors::PERMISSION_ACCESS, $monitor_id, READ)) continue;
				$this->monitors_options[$monitor_id] = htmlspecialchars(\Monitors::get_monitor_title_with_folders($monitor_id));
			}
		}
		$dockers = \Dockers::data(false, ' ORDER BY `title` ASC', '`id`, `title`');
		if ($dockers) {
			foreach ($dockers as $docker) {
				$docker_id = intval($docker['id']);
				if (!\Sessions::checkPermission(\Dockers::PERMISSION_ACCESS, $docker_id, READ)) continue;
				$this->dockers_options[$docker_id] = htmlspecialchars(trim($docker['title'] ?? ''));
			}
		}
	}

	private function print_header() {
		?>
		<div class="float-start"><h2><i class="bi bi-hdd-network"></i> <?=\T::Worker_PageTitle();?></h2></div>
		<div class="clearfix"></div>
		<?php
	}

	private function print_forms() {
		?>
		<div class="row d-flex justify-content-center">
			<div class="card bg-transparent col-xl-10 p-4 mt-2 mb-3">
				<div class="card-header bg-transparent"><h3>
					<?php
					if ($this->worker['id']) {
						echo \T::Worker_Edit_EditTitle(htmlspecialchars($this->worker['title'] ?? '', ENT_QUOTES, 'UTF-8'), $this->worker['id']);
					} else {
						echo \T::Worker_Edit_CreateTitle();
					}
					?>
				</h3></div>
				<form class="card-body needs-validation" method="post" novalidate>
					<?= \Csrf::field(); ?>
					<input type="hidden" name="id" value="<?=$this->worker['id'];?>"/>
					<?php
					echo $this->template->html_input("title", htmlspecialchars($this->worker['title'] ?? '', ENT_QUOTES, 'UTF-8'), \T::Worker_Edit_Title(), true, [
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6'
					]);

					if ($this->worker['id']) {
						$status = 'Offline: '.($this->worker['last_active_date']??'');
						$status_class = 'text-danger';
						if (isset($this->worker['last_active_time']) && $this->worker['last_active_time'] + 3*60 > time()) {
							$status = 'Online: '.($this->worker['last_active_date']??'');
							$status_class = 'text-success';
						}
						echo $this->template->html_input("create_date", $this->worker['create_date']??'', \T::Worker_Edit_CreateTime(), false, ['readonly' => true]);
						echo $this->template->html_input("update_date", $this->worker['update_date']??'', \T::Worker_Edit_UpdateTime(), false, ['readonly' => true]);

						echo $this->template->html_input("protocol_version", $this->worker['protocol_version']??'', \T::Worker_Edit_ProtocolVersion(), false, ['readonly' => true]);
						echo $this->template->html_input("worker_version", $this->worker['worker_version']??'', \T::Worker_Edit_WorkerVersion(), false, ['readonly' => true]);

						echo $this->template->html_input("last_active_date", $status, \T::Worker_Edit_LastActiveTime(), false, [
							'readonly' => true,
							'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6 '.$status_class,
						]);

						if (\Sessions::checkPermission(\Workers::PERMISSION_SECRETS, $this->worker['id'], READ)) {
							echo $this->template->html_input("worker_key_hash", $this->worker['worker_key_hash']??'', \T::Worker_Edit_WorkerKeyHash(), false, [
								'readonly' => true,
								'type' => 'textarea',
								'rows' => '4',
								'cols' => '60',
								'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6'
							]);
						}
						echo $this->template->html_input("worker_threads", $this->worker['worker_threads']??'', \T::Worker_Edit_WorkerThreads(), false, [
							'type' => 'number',
							'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6'
						]);
						echo $this->template->html_input("jobs_get_timeout", $this->worker['jobs_get_timeout']??'', \T::Worker_Edit_JobsGetTimeout(), false, [
							'type' => 'number',
							'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6'
						]);
						echo $this->template->html_input("loop_timeout", $this->worker['loop_timeout']??'', \T::Worker_Edit_LoopTimeout(), false, [
							'type' => 'number',
							'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6'
						]);
						echo $this->template->html_input("response_send_timeout", $this->worker['response_send_timeout']??'', \T::Worker_Edit_ResponseSendTimeout(), false, [
							'type' => 'number',
							'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6'
						]);
						echo $this->template->html_input("logs_write_timeout", $this->worker['logs_write_timeout']??'', \T::Worker_Edit_LogsWriteTimeout(), false, [
							'type' => 'number',
							'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6'
						]);
						echo $this->template?->html_select("monitors[]", $this->monitors_options, $this->worker['monitors']??[], \T::Monitor_PageTitle(), false, [
							'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
							'multiple' => true,
							'readonly' => !$this->can_write,
						]);
						echo $this->template?->html_select("dockers[]", $this->dockers_options, $this->worker['dockers']??[], \T::Docker_PageTitle(), false, [
							'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
							'multiple' => true,
							'readonly' => !$this->can_write,
						]);
					}
					?>
					<?php if ($this->can_write || $this->can_create) { ?>
						<div class="d-flex flex-row-reverse gap-2">
							<button type="submit" class="btn btn-primary" name="<?=$this->worker['id'] ? 'editWorker' : 'createWorker';?>" value="true"><?=($this->worker['id'] ? \T::Worker_Edit_ChangeButton() : \T::Worker_Edit_CreateButton());?></button>
							<?php if ($this->worker['id'] && $this->can_write) { ?>
								<button type="submit" class="btn btn-outline-warning" name="restart_worker" value="1"><?=\T::Worker_Edit_RestartButton();?></button>
							<?php } ?>
						</div>
					<?php } ?>
				</form>
			</div>
			<?php if ($this->worker['id']) { ?>
				<div class="card bg-transparent col-xl-10 p-4 mt-2 mb-3">
					<div class="card-body">
						<?php
						$text = file_get_contents(SERVER_ROOT.'/lang/worker_add_'.\T::getCurrentLanguage().'.md');
						$html = \Michelf\Markdown::defaultTransform($text);
						echo $html;
						?>
					</div>
				</div>
			<?php } ?>
		</div>
		<?php
	}

	private function print_javascript() {
		?>
		<script nonce="<?=\CSP::nonceRandom();?>">
			$(document).ready(function(){
				$('form').on('submit', function(event) {
					$('form').addClass("was-validated");
				});
			});
		</script>
		<?php
	}
}
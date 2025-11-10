<?php
namespace App\Dockers;

class Edit extends \Routing_Parent implements \Routing_Interface {
	private int $docker_id = 0;
	private array $docker = [];
	private bool $can_read = false;
	private bool $can_write = false;
	private bool $can_create = false;
	private array $workers_hash = [];

	public function Run(int $docker_id=0) {
		$this->docker_id = intval($docker_id ?? 0);
		$this->check_auth();
		$this->check_permissions();

		$this->processRequest();
		$this->get_data();
		$this->template = new \Template('Docker edit');
		$this->print_header();

		$this->print_forms();
		$this->print_javascript();
	}

	protected function handlePostData(array $data) {
		// Обработка POST данных
		if (!isset($data)) return;
		$this->save_docker($data);
	}

	private function get_pem_file_data(array $file): string {
		if (!isset($file)) return '';
		if (!is_array($file)) return '';
		if (!isset($file['name'])) return '';
		if (!isset($file['type'])) return '';
		if (!isset($file['tmp_name'])) return '';
		if (!isset($file['size'])) return '';
		$data = file_get_contents($file['tmp_name']);
		if (!$data) return '';
		return base64_encode($data);
	}

	private function save_docker($data) {
		if (!isset($data)) return;
		if (!$data) return;

		$params = [];
		do {
			if (isset($data['id']) && trim($data['id']) != '') {
				$data['id'] = trim($data['id']);
				if ($this->docker_id != intval($data['id'])) {
					$this->error = \T::Docker_Edit_NotFound();
					break;
				}
				$params['id'] = intval($data['id']);
			}
			if (isset($data['title']) && trim($data['title']) != '') {
				$data['title'] = trim($data['title']);
				if (mb_strlen($data['title']) < 2 || mb_strlen($data['title']) > 255) {
					$this->error = \T::Docker_Edit_NameLengthError();
					break;
				}
				$params['title'] = $data['title'];
			}
			if (isset($data['status']) && trim($data['status']) != '') {
				$data['status'] = trim($data['status']);
				if ($data['status'] == 'true') {
					$data['status'] = 1;
				} else {
					$data['status'] = 0;
				}
				$params['status'] = intval($data['status']);
			} else {
				$data['status'] = 0;
				$params['status'] = 0;
			}

			if (isset($data['host']) && trim($data['host']) != '') {
				$params['host'] = trim($data['host']);
			}
			if (isset($data['port']) && trim($data['port']) != '') {
				$params['port'] = intval(trim($data['port']));
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

			if (isset($data['tls']) && trim($data['tls']) != '') {
				$data['tls'] = trim($data['tls']);
				if ($data['tls'] == 'true') {
					$data['tls'] = 1;
				} else {
					$data['tls'] = 0;
				}
				$params['tls'] = intval($data['tls']);
			} else {
				$data['tls'] = 0;
				$params['tls'] = 0;
			}

			if (isset($params['tls']) && $params['tls']) {
				if (isset($_FILES['tls_ca_file']) && is_array($_FILES['tls_ca_file']) && $_FILES['tls_ca_file'] && isset($_FILES['tls_ca_file']['tmp_name']) && $_FILES['tls_ca_file']['tmp_name']) {
					if (isset($_FILES['tls_ca_file']['error']) && $_FILES['tls_ca_file']['error']) {
						$this->error = \T::Docker_Edit_TlsError();
						break;
					}
					if (!isset($_FILES['tls_ca_file']['type']) || $_FILES['tls_ca_file']['type'] != 'application/x-x509-ca-cert') {
						$this->error = \T::Docker_Edit_TlsError();
						break;
					}
					$params['tls_ca_file'] = $this->get_pem_file_data($_FILES['tls_ca_file']);
				}
				if (isset($_FILES['tls_certificate']) && is_array($_FILES['tls_certificate']) && $_FILES['tls_certificate'] && isset($_FILES['tls_certificate']['tmp_name']) && $_FILES['tls_certificate']['tmp_name']) {
					if (isset($_FILES['tls_certificate']['error']) && $_FILES['tls_certificate']['error']) {
						$this->error = \T::Docker_Edit_TlsError();
						break;
					}
					if (!isset($_FILES['tls_certificate']['type']) || $_FILES['tls_certificate']['type'] != 'application/x-x509-ca-cert') {
						$this->error = \T::Docker_Edit_TlsError();
						break;
					}
					$params['tls_certificate'] = $this->get_pem_file_data($_FILES['tls_certificate']);
				}
				if (isset($_FILES['tls_key']) && is_array($_FILES['tls_key']) && $_FILES['tls_key'] && isset($_FILES['tls_key']['tmp_name']) && $_FILES['tls_key']['tmp_name']) {
					if (isset($_FILES['tls_key']['error']) && $_FILES['tls_key']['error']) {
						$this->error = \T::Docker_Edit_TlsError();
						break;
					}
					if (!isset($_FILES['tls_key']['type']) || $_FILES['tls_key']['type'] != 'application/x-x509-ca-cert') {
						$this->error = \T::Docker_Edit_TlsError();
						break;
					}
					$params['tls_key'] = $this->get_pem_file_data($_FILES['tls_key']);
				}
			}

			$workers_hash = [];
			$all_jobs = \Workers::data(false, ' ORDER BY `title` ASC', '`id`, `title`');
			if ($all_jobs) {
				foreach($all_jobs as $v) {
					$workers_hash[$v['id']] = \Sessions::checkPermission(\Workers::PERMISSION_ACCESS, $v['id'], READ);
				}
			}


			$old_docker = [];
			$update_jobs = [];
			if (!isset($data['workers'])) $data['workers'] = [];
			if (isset($params['id']) && $params['id']) {
				if (!\Sessions::checkPermission(\Dockers::PERMISSION_ACCESS, $params['id'], WRITE)) {
					$this->error = \T::Docker_Edit_PermissionDenied();
					break;
				}
				$old_docker = \Dockers::get(['id' => $params['id']]);
				if (!$old_docker) {
					$this->error = \T::Docker_Edit_NotFound();
					break;
				}

				$old_jobs = get_hash(\MonitoringDockers::data(['docker_id' => $params['id']]), 'worker_id', 'docker_id');
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

				if (isset($params['tls']) && $params['tls']) {
					if (isset($old_docker['tls_ca_file']) && $old_docker['tls_ca_file']) {
						if (!isset($params['tls_ca_file']) || !$params['tls_ca_file']) {
							$params['tls_ca_file'] = $old_docker['tls_ca_file'];
						}
					}
					if (isset($old_docker['tls_certificate']) && $old_docker['tls_certificate']) {
						if (!isset($params['tls_certificate']) || !$params['tls_certificate']) {
							$params['tls_certificate'] = $old_docker['tls_certificate'];
						}
					}
					if (isset($old_docker['tls_key']) && $old_docker['tls_key']) {
						if (!isset($params['tls_key']) || !$params['tls_key']) {
							$params['tls_key'] = $old_docker['tls_key'];
						}
					}
				}

				foreach($params as $k=>$v) {
					if ($old_docker[$k] == $v) {
						unset($params[$k]);
					}
				}
				if (!$params && !$update_jobs) {
					$this->success = \T::Docker_Edit_NotingChanged();
					break;
				}
				$params['id'] = $old_docker['id'];
			} else {
				if (!\Sessions::checkPermission(\Dockers::PERMISSION_CREATE, 0, WRITE)) {
					$this->error = \T::Docker_Create_PermissionDenied();
					break;
				}
				if ($data['workers']) {
					foreach($data['workers'] as $worker_id) {
						if (isset($update_jobs[$worker_id])) continue;
						$update_jobs[$worker_id] = 1;
					}
				}
				$params['tls_ca_file'] ??= '';
				$params['tls_certificate'] ??= '';
				$params['tls_key'] ??= '';
				$params['create_time'] = time();
			}
			$params['update_time'] = time();

			$docker_id = 0;
			if (isset($params['id']) && $params['id']) {
				// Update
				$params['_mode'] = \DB\Common::CSMODE_UPDATE;
				$docker_id = \Dockers::save($params);
				$new_docker = \Dockers::get(['id' => $params['id']]);
				\Logs::update_log(\Dockers::LOGS_OBJECT, $params['id'], $old_docker, $new_docker,[
					'_save_fields' => ['id', 'title'],
				]);
				$docker = \Dockers::get(['id' => $docker_id]);
				if (!$docker) {
					$this->error = \T::Docker_Edit_NotFound();
					break;
				}
				foreach ($update_jobs as $worker_id=>$status) {
					if ($status < 0) {
						$old_job = \MonitoringDockers::get([
							'docker_id' => $params['id'],
							'worker_id' => $worker_id,
						]);
						if (isset($old_job['id']) && $old_job['id']) {
							\MonitoringDockers::delete($old_job['id']);
							$log_id = \Logs::delete_log(\MonitoringDockers::LOGS_OBJECT, $old_job['id'], $old_job);
							\Logs::add_tag($log_id, \Dockers::LOGS_OBJECT, $params['id']);
							\Logs::add_tag($log_id, \Workers::LOGS_OBJECT, $worker_id);
						}
					} else {
						$job = [
							'docker_id' => $params['id'],
							'worker_id' => $worker_id,
							'create_time' => time(),
							'update_time' => time(),
						];
						$job['id'] = \MonitoringDockers::save($job);
						$log_id = \Logs::create_log(\MonitoringDockers::LOGS_OBJECT, $job['id'],$job);
						\Logs::add_tag($log_id, \Dockers::LOGS_OBJECT, $params['id']);
						\Logs::add_tag($log_id, \Workers::LOGS_OBJECT, $worker_id);
					}
				}
			} else {
				// Create
				$params['_mode'] = \DB\Common::CSMODE_INSERT;
				$docker_id = \Dockers::save($params);
				$docker = \Dockers::get(['id' => $docker_id]);
				$log_id = \Logs::create_log(\Dockers::LOGS_OBJECT, $docker_id, $docker);
				if (!$docker) {
					$this->error = \T::Docker_Edit_NotFound();
					break;
				}

				foreach ($update_jobs as $worker_id=>$status) {
					$job = [
						'docker_id' => $docker_id,
						'worker_id' => $worker_id,
						'create_time' => time(),
						'update_time' => time(),
					];
					$job['id'] = \MonitoringDockers::save($job);
					$log_id = \Logs::create_log(\MonitoringDockers::LOGS_OBJECT, $job['id'], $job);
					\Logs::add_tag($log_id, \Dockers::LOGS_OBJECT, $docker_id);
					\Logs::add_tag($log_id, \Workers::LOGS_OBJECT, $worker_id);
				}

				if (!\Sessions::in_group(\Groups::ADMIN_GROUP_ID, intval(\Sessions::currentUser()['id']))) {
					$permissions = \Dockers::permissions_hash();
					foreach($permissions as $permission => $_) {
						$ObjectPermissions = [
							'object' => 'user',
							'object_id' => intval(\Sessions::currentUser()['id']),
							'subject' => $permission,
							'subject_id' => $docker_id,
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
						\Logs::add_tag($log_id, \Dockers::LOGS_OBJECT, $docker_id);
					}
				}
			}
			if ($docker_id) {
				common_redirect('/dockers/edit/' . $docker_id . '/');
			} else {
				common_redirect('/dockers/');
			}
		} while(false);
		foreach($data as $key => $value) {
			$this->docker[$key] = $value;
		}
	}

	private function check_permissions() {
		if ($this->docker_id) {
			$this->docker = \Dockers::get(['id' => $this->docker_id]);
			if (!$this->docker) {
				common_redirect('/dockers/');
			}
			$this->can_read = \Sessions::checkPermission(\Dockers::PERMISSION_ACCESS, $this->docker_id, READ);
			$this->can_write = \Sessions::checkPermission(\Dockers::PERMISSION_ACCESS, $this->docker_id, WRITE);
			if (!$this->can_read) {
				e403();
			}
		} else {
			$this->can_read = \Sessions::checkPermission(\Dockers::PERMISSION_ACCESS, -1, READ);
			$this->can_create = \Sessions::checkPermission(\Dockers::PERMISSION_CREATE, 0, WRITE);
			if (!$this->can_read && !$this->can_create) {
				e403();
			}
		}
	}

	private function get_data() {
		if (!isset($this->docker['id'])) $this->docker['id'] = '';

		if ($this->docker['id']) {
			$this->docker['create_date'] = (
				isset($this->docker['create_time']) && $this->docker['create_time'] > 0 ?
				date('Y-m-d H:i:s', $this->docker['create_time']) : ''
			);
			$this->docker['update_date'] = (
				isset($this->docker['update_time']) && $this->docker['update_time'] > 0 ?
				date('Y-m-d H:i:s', $this->docker['update_time']) : ''
			);
			$this->docker['workers'] = [];
			$jobs = \MonitoringDockers::data(['docker_id' => $this->docker['id']], false, '`worker_id`');
			if ($jobs) {
				foreach($jobs as $v) {
					$this->docker['workers'][$v['worker_id']] = true;
				}
			}
		} else {
			$this->docker['status'] ??= 1;
			$this->docker['tls'] ??= 1;
			$this->docker['port'] ??= 2376;
			$this->docker['workers'] ??= [];
			$this->docker['timeout'] ??= 15;
			$this->docker['repeat_seconds'] ??= 60;
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
	}

	private function print_header() {
		?>
		<div class="float-start"><h2><i class="bi bi-box"></i> <?=\T::Docker_PageTitle();?></h2></div>
		<div class="clearfix"></div>
		<?php
	}

	private function print_forms() {
		?>
		<div class="row d-flex justify-content-center">
			<div class="card bg-transparent col-xl-10 p-4 mt-2 mb-3">
				<div class="card-header bg-transparent"><h3>
					<?php
					if ($this->docker['id']) {
						echo \T::Docker_Edit_EditTitle($this->docker['title'], $this->docker['id']);
					} else {
						echo \T::Docker_Edit_CreateTitle();
					}
					?>
				</h3></div>
				<form class="card-body needs-validation" enctype="multipart/form-data" method="post" novalidate>
					<input type="hidden" name="id" value="<?=$this->docker['id'];?>"/>
					<?php
					echo $this->template->html_input("title", $this->docker['title']??'', \T::Docker_Edit_Title(), true, [
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'readonly' => !($this->can_write || $this->can_create),
					]);
					echo $this->template->html_switch("status", intval($this->docker['status']??0), \T::Docker_Edit_Status(), false,[
						'readonly' => !($this->can_write || $this->can_create),
					]);

					echo $this->template->html_input("host", $this->docker['host']??'', \T::Docker_Edit_Host(), true, [
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'readonly' => !($this->can_write || $this->can_create),
					]);
					echo $this->template->html_input("port", $this->docker['port']??'', \T::Docker_Edit_Port(), true, [
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'readonly' => !($this->can_write || $this->can_create),
					]);

					echo $this->template->html_params("workers[]", $this->workers_hash, $this->docker['workers']??[], \T::Docker_Edit_Workers(),false,[
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'readonly' => !($this->can_write || $this->can_create),
					]);

					echo $this->template->html_input("timeout", $this->docker['timeout']??0, \T::Docker_Edit_Timeout(), false, [
						'type' => 'number',
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'readonly' => !($this->can_write || $this->can_create),
					]);
					echo $this->template->html_input("repeat_seconds", $this->docker['repeat_seconds']??0, \T::Docker_Edit_RepeatSeconds(), false, [
						'type' => 'number',
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'readonly' => !($this->can_write || $this->can_create),
					]);

					echo $this->template->html_switch("tls", intval($this->docker['tls']??0), \T::Docker_Edit_Tls(), false,[
						'readonly' => !($this->can_write || $this->can_create),
					]);

					echo $this->template->html_input("tls_ca_file", $this->docker['tls_ca_file']??'', \T::Docker_Edit_TlsCaFile(), true, [
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'global-class' => 'tls-fields',
						'type' => 'file',
						'accept' => '.pem',
						'placeholder' => 'ca.pem',
						'readonly' => !($this->can_write || $this->can_create),
					]);
					echo $this->template->html_input("tls_certificate", $this->docker['tls_certificate']??'', \T::Docker_Edit_TlsCertificate(), true, [
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'global-class' => 'tls-fields',
						'type' => 'file',
						'accept' => '.pem',
						'placeholder' => 'cert.pem',
						'readonly' => !($this->can_write || $this->can_create),
					]);
					echo $this->template->html_input("tls_key", $this->docker['tls_key']??'', \T::Docker_Edit_TlsKey(), true, [
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'global-class' => 'tls-fields',
						'type' => 'file',
						'accept' => '.pem',
						'placeholder' => 'key.pem',
						'readonly' => !($this->can_write || $this->can_create),
					]);

					if ($this->docker['id']) {
						echo $this->template->html_input("create_date", $this->docker['create_date']??'', \T::Docker_Edit_CreateTime(), false, ['readonly' => true]);
						echo $this->template->html_input("update_date", $this->docker['update_date']??'', \T::Docker_Edit_UpdateTime(), false, ['readonly' => true]);
					}
					?>
					<div class="d-flex flex-row-reverse">
						<button type="submit" class="btn btn-primary me-2" name="<?=$this->docker['id'] ? 'editDocker' : 'createDocker';?>" value="true"><?=($this->docker['id'] ? \T::Docker_Edit_ChangeButton() : \T::Docker_Edit_CreateButton());?></button>
					</div>
				</form>
			</div>

			<?php if ($this->docker['id'] && isset($this->docker['container_states']) && $this->docker['container_states']) { ?>
				<div class="card bg-transparent col-xl-10 p-4 mt-2 mb-3">
					<div class="card-body">
						<table class="table table-transparent table-responsive" id="Table">
							<thead class="">
								<tr>
									<th scope="col" class="align-middle" data-priority="1"><?=\T::Docker_Container_Table_Name();?></th>
									<th scope="col" class="align-middle" data-priority="2"><?=\T::Docker_Container_Table_State();?></th>
									<th scope="col" class="align-middle" data-priority="3"><?=\T::Docker_Container_Table_Created();?></th>
									<th scope="col" class="align-middle" data-priority="4"><?=\T::Docker_Container_Table_Ports();?></th>
									<th scope="col" class="align-middle" data-priority="5"><?=\T::Docker_Container_Table_Image();?></th>
									<th scope="col" class="align-middle" data-priority="6"><?=\T::Docker_Container_Table_ImageId();?></th>
									<th scope="col" class="align-middle" data-priority="7"><?=\T::Docker_Container_Table_Id();?></th>
								</tr>
							</thead>
							<tbody>
								<?php
									$this->docker['container_states'] = @json_decode($this->docker['container_states'], true);
									$this->docker['container_states'] ??= [];
									foreach ($this->docker['container_states'] as $container_state) {
										$params = [
											'id' => $container_state['id']??'',
											'name' => $container_state['name']??'',
											'image' => $container_state['image']??'',
											'ports' => $container_state['ports']??'',
											'image_id' => $container_state['image_id']??'',
											'state' => $container_state['state']??'',
											'created' => $container_state['created']??0,
										];
										if (!$params['id']) continue;
										if ($params['created']) {
											$params['created'] = date('Y-m-d H:i:s', $params['created']);
										} else {
											$params['created'] = '';
										}
										?>
										<tr>
											<th scope="row" class="align-middle"><?=$params['name'];?></th>
											<td class="align-middle text-center"><?=$params['state'];?></td>
											<td class="align-middle text-center"><?=$params['created'];?></td>
											<td class="align-middle"><?=$params['ports'];?></td>
											<td class="align-middle"><?=$params['image'];?></td>
											<td class="align-middle"><?=$params['image_id'];?></td>
											<td class="align-middle"><?=$params['id'];?></td>
										</tr>
										<?php
									}
								?>
							</tbody>
						</table>
					</div>
				</div>
			<?php } ?>
		</div>
		<?php
	}

	private function print_javascript() {
		?>
		<script nonce="<?=\CSP::nonceRandom();?>">
			function docker_tls_change() {
				if ($('#tls').is(':checked')) {
					$('.tls-fields').show();
					$('#tls_ca_file').prop('required', <?=(isset($this->docker['tls_ca_file']) && $this->docker['tls_ca_file'] ? 'false' : 'true');?>);
					$('#tls_certificate').prop('required', <?=(isset($this->docker['tls_certificate']) && $this->docker['tls_certificate'] ? 'false' : 'true');?>);
					$('#tls_key').prop('required', <?=(isset($this->docker['tls_key']) && $this->docker['tls_key'] ? 'false' : 'true');?>);
				} else {
					$('.tls-fields').hide();
					$('#tls_ca_file').prop('required', false);
					$('#tls_certificate').prop('required', false);
					$('#tls_key').prop('required', false);
				}
			}

			$(document).ready(function(){
				new DataTable('#Table');

				docker_tls_change();
				$('#tls').change(function(){
					docker_tls_change();
				});

				$('form').on('submit', function(event) {
					if (!$('#tls').is(':checked')) {
						$('#tls_ca_file').val('1');
						$('#tls_certificate').val('1');
						$('#tls_key').val('1');
						$('#tls_ca_file').addClass('was-validated');
						$('#tls_certificate').addClass('was-validated');
						$('#tls_key').addClass('was-validated');

					}
					$('form').addClass("was-validated");
				});

			});
		</script>
		<?php
	}
}
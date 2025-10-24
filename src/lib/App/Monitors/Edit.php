<?php
namespace App\Monitors;

class Edit extends \Routing_Parent implements \Routing_Interface {
	private int $parent_id = 0;
	private int $monitor_id = 0;
	private array $breadcrumb = [];
	private array $monitor = [];
	private bool $can_read = false;
	private bool $can_create = false;

	public function Run(int $monitor_id=0, int $parent_id=0) {
		$this->monitor_id = intval($monitor_id ?? 0);
		$this->parent_id = intval($parent_id ?? 0);
		$this->check_auth();
		$this->check_permissions();

		$this->processRequest();
		$this->get_data();
		$this->get_breadcrumb();
		$this->template = new \Template();
		$this->print_header();
		$this->print_breadcrumb();
		$this->print_forms();
		$this->print_javascript();
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
			if (isset($data['status'])) {
				$data['status'] = trim($data['status']);
				$params['status'] = intval($data['status']);
			}


			if ($data['type'] == \Monitors::TYPE_CURL) {
				if (isset($data['url'])) {
					$data['url'] = trim($data['url']);
					$params['url'] = $data['url'];
				}
				if (isset($data['method'])) {
					$data['method'] = trim($data['method']);
					$params['method'] = $data['method'];
				}
				if (isset($data['timeout'])) {
					$data['timeout'] = trim($data['timeout']);
					$params['timeout'] = intval($data['timeout']);
				}
				if (isset($data['repeat_seconds'])) {
					$data['repeat_seconds'] = trim($data['repeat_seconds']);
					$params['repeat_seconds'] = intval($data['repeat_seconds']);
				}
				if (isset($data['ssl_verify'])) {
					$data['ssl_verify'] = trim($data['ssl_verify']);
					$params['ssl_verify'] = intval($data['ssl_verify']);
				}
				if (isset($data['proxy_host'])) {
					$data['proxy_host'] = trim($data['proxy_host']);
					$params['proxy_host'] = $data['proxy_host'];
				}
				if (isset($data['proxy_port'])) {
					$data['proxy_port'] = trim($data['proxy_port']);
					$params['proxy_port'] = intval($data['proxy_port']);
				}
			}
			$old_monitor = [];
			if (isset($params['id']) && $params['id']) {
				if (!\Sessions::checkPermission(\Monitors::PERMISSION_ACCESS, $params['id'], WRITE)) {
					$this->error = \T::Monitor_Edit_PermissionDenied();
					break;
				}
				$old_monitor = \Monitors::get(['id' => $params['id']]);
				if (!$old_monitor) {
					$this->error = \T::Monitor_Edit_MonitorNotFound();
					break;
				}
				foreach($params as $k=>$v) {
					if ($old_monitor[$k] == $v) {
						unset($params[$k]);
					}
				}
				if (!$params) {
					$this->success = \T::Monitor_Edit_NotingChanged();
					break;
				}
				$params['id'] = $old_monitor['id'];
			} else {
				if (!\Sessions::checkPermission(\Monitors::PERMISSION_CREATE, 0, WRITE)) {
					$this->error = \T::Monitor_Create_PermissionDenied();
					break;
				}
				$params['create_time'] = time();
			}
			$params['parent_id'] = $this->parent_id;
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
			} else {
				// Create
				$params['_mode'] = \DB\Common::CSMODE_INSERT;
				$monitor_id = \Monitors::save($params);
				$monitor = \Monitors::get(['id' => $monitor_id]);
				$log_id = \Logs::create_log(\Monitors::LOGS_OBJECT, $monitor_id, $monitor);
				if (!$monitor) {
					$this->error = \T::Monitor_Edit_MonitorNotFound();
					break;
				}

				if (!\Sessions::in_group(\GROUPS::ADMIN_GROUP_ID, intval(\Sessions::currentUser()['id']))) {
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
				common_redirect('/monitors/'.$this->parent_id.'/edit/' . $monitor_id . '/');
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
		} else {
			$this->can_read = \Sessions::checkPermission(\Monitors::PERMISSION_ACCESS, -1, READ);
			$this->can_create = \Sessions::checkPermission(\Monitors::PERMISSION_CREATE, 0, WRITE);
			if (!$this->can_read && !$this->can_create) {
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
		} else {
			$this->monitor['status'] = \Monitors::STATUS_ENABLED;
			$this->monitor['type'] = 0;
			$this->monitor['method'] = 'GET';
			$this->monitor['timeout'] = 48;
			$this->monitor['repeat_seconds'] = 60;
			$this->monitor['ssl_verify'] = 1;
		}
	}

	private function get_breadcrumb() {
		// if (!$parent_id) return false;
		$sql = sql_pholder(' AND type = ?', \Monitors::TYPE_FOLDER);
		$data = \Monitors::data(false, $sql, '`id`, `title`, `parent_id`');
		$data_hash = [];
		$folders = [];
		foreach($data as $item) {
			$data_hash[$item['id']] = $item;
		}
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
				if ($last_index > 0) {
					foreach($this->breadcrumb as $k=>$item) {
						echo '<li class="breadcrumb-item'.($k == $last_index ? ' active" aria-current="page':'').'"><a href="/monitors/'.($item['id'] ? $item['id'].'/'.'">' : '').htmlspecialchars($item['title']).'</a></li>';
					}
				}
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
					<input type="hidden" name="parent_id" value="<?=$this->parent_id;?>"/>
					<?php
					echo $this->template->html_input("title", $this->monitor['title']??'', \T::Monitor_Edit_Title(), true, [
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6'
					]);
					echo $this->template?->html_select('type', \Monitors::type_hash(), $this->monitor['type']??0, \T::Monitor_Edit_Type(), true,[
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6'
					]);
					echo $this->template->html_switch("status", intval($this->monitor['status']??0), \T::Monitor_Edit_Status());

					echo $this->template->html_input("url", $this->monitor['url']??'', \T::Monitor_Edit_Url(), false, [
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'global-class' => 'type-dependence type-'.\Monitors::TYPE_CURL,
					]);
					echo $this->template?->html_select('method', \Monitors::HTTP_methods(), $this->monitor['method']??'', \T::Monitor_Edit_Method(), false,[
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'global-class' => 'type-dependence type-'.\Monitors::TYPE_CURL,
					]);
					echo $this->template->html_input("timeout", $this->monitor['timeout']??0, \T::Monitor_Edit_Timeout(), false, [
						'type' => 'number',
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'global-class' => 'type-dependence type-'.\Monitors::TYPE_CURL,
					]);
					echo $this->template->html_input("repeat_seconds", $this->monitor['repeat_seconds']??0, \T::Monitor_Edit_RepeatSeconds(), false, [
						'type' => 'number',
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'global-class' => 'type-dependence type-'.\Monitors::TYPE_CURL,
					]);
					echo $this->template->html_switch("ssl_verify", intval($this->monitor['ssl_verify']??0), \T::Monitor_Edit_SSLVerify(), false,[
						'global-class' => 'type-dependence type-'.\Monitors::TYPE_CURL,
					]);
					echo $this->template->html_input("proxy_host", $this->monitor['proxy_host']??'', \T::Monitor_Edit_ProxyHost(), false, [
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'global-class' => 'type-dependence type-'.\Monitors::TYPE_CURL,
					]);
					echo $this->template->html_input("proxy_port", $this->monitor['proxy_port']??'', \T::Monitor_Edit_ProxyPort(), false, [
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'global-class' => 'type-dependence type-'.\Monitors::TYPE_CURL,
					]);
					if ($this->monitor['id']) {
						echo $this->template->html_input("create_date", $this->monitor['create_date']??'', \T::Monitor_Edit_CreateTime(), false, ['readonly' => true]);
						echo $this->template->html_input("update_date", $this->monitor['update_date']??'', \T::Monitor_Edit_UpdateTime(), false, ['readonly' => true]);
					}
					?>
					<div class="d-flex flex-row-reverse">
						<button type="submit" class="btn btn-primary" name="<?=$this->monitor['id'] ? 'editMonitor' : 'createMonitor';?>" value="true"><?=($this->monitor['id'] ? \T::Monitor_Edit_ChangeButton() : \T::Monitor_Edit_CreateButton());?></button>
					</div>
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
				console.log(selected_type);
				$('.type-dependence').each(function(){
					$(this).hide();
				});
				$('.type-' + selected_type).each(function(){
					$(this).show();
				});
			}

			$(document).ready(function(){
				monitor_type_select();
				$('#type').change(function(){
					monitor_type_select();
				});
				$('form').on('submit', function(event) {
					$('form').addClass("was-validated");
				});
			});
		</script>
		<?php
	}
}
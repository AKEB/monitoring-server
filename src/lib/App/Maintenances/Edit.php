<?php
namespace App\Maintenances;

class Edit extends \Routing_Parent implements \Routing_Interface {
	private int $maintenance_id = 0;
	private array $maintenance = [];
	private bool $can_read = false;
	private bool $can_write = false;
	private bool $can_create = false;

	public function Run($maintenance_id=0) {
		$this->maintenance_id = intval($maintenance_id ?? 0);
		$this->check_auth();
		$this->check_permissions();

		$this->processRequest();
		$this->get_data();
		$this->template = new \Template('Maintenances edit');
		$this->print_header();

		$this->print_forms();
		$this->print_javascript();
	}

	protected function handlePostData(array $data) {
		// Обработка POST данных
		if (!isset($data)) return;
		$this->save_maintenance($data);
	}

	private function save_maintenance(array $data) {
		if (!isset($data)) return;
		if (!$data) return;

		$params = [];
		do {
			if (isset($data['id']) && trim($data['id']) != '') {
				$data['id'] = trim($data['id']);
				if ($this->maintenance_id != intval($data['id'])) {
					$this->error = \T::Maintenances_Edit_NotFound();
					break;
				}
				$params['id'] = intval($data['id']);
			}
			if (isset($data['title']) && trim($data['title']) != '') {
				$data['title'] = trim($data['title']);
				if (!\EntityValidation::isTitleLengthValid($data['title'])) {
					$this->error = \T::Maintenances_Edit_NameLengthError();
					break;
				}
				$params['title'] = $data['title'];
			}
			if (isset($data['start_time']) && trim($data['start_time']) != '') {
				$data['start_time'] = trim($data['start_time']);
				$params['start_time'] = strtotime($data['start_time']);
			}
			if (isset($data['finish_time']) && trim($data['finish_time']) != '') {
				$data['finish_time'] = trim($data['finish_time']);
				$params['finish_time'] = strtotime($data['finish_time']);
			}
			if (\EntityValidation::maintenanceTimeError(
				isset($params['start_time']) ? intval($params['start_time']) : null,
				isset($params['finish_time']) ? intval($params['finish_time']) : null
			) !== null) {
				$this->error = \T::Maintenances_Edit_TimeError();
				break;
			}

			$monitors_hash = [];
			$all_data = \Monitors::data(false, ' ORDER BY NULL', '`id`');
			if ($all_data) {
				foreach($all_data as $v) {
					$monitors_hash[$v['id']] = \Sessions::checkPermission(\Monitors::PERMISSION_ACCESS, $v['id'], READ);
				}
			}

			$old_maintenance = [];
			$update_monitors = [];
			if (!isset($data['monitor_id'])) $data['monitor_id'] = [];
			if (isset($params['id']) && $params['id']) {
				if (!\Sessions::checkPermission(\Maintenances::PERMISSION_ACCESS, $params['id'], WRITE)) {
					$this->error = \T::Maintenances_Edit_PermissionDenied();
					break;
				}
				$old_maintenance = \Maintenances::get(['id' => $params['id']]);
				if (!$old_maintenance) {
					$this->error = \T::Maintenances_Edit_NotFound();
					break;
				}

				$old_jobs = get_hash(\MonitoringMaintenances::data(['maintenance_id' => $params['id']]), 'monitor_id', 'maintenance_id');
				foreach($old_jobs as $monitor_id=>$_) {
					if (!isset($monitors_hash[$monitor_id]) || !$monitors_hash[$monitor_id]) continue;
					if (!in_array($monitor_id, $data['monitor_id'])) {
						$update_monitors[$monitor_id] = -1;
					}
				}
				if ($data['monitor_id']) {
					foreach($data['monitor_id'] as $monitor_id) {
						if (!isset($monitors_hash[$monitor_id]) || !$monitors_hash[$monitor_id]) continue;
						if (isset($update_monitors[$monitor_id])) continue;
						if (isset($old_jobs[$monitor_id]) && $old_jobs[$monitor_id]) continue;
						$update_monitors[$monitor_id] = 1;
					}
				}

				foreach($params as $k=>$v) {
					if ($old_maintenance[$k] == $v) {
						unset($params[$k]);
					}
				}
				if (!$params && !$update_monitors) {
					$this->success = \T::Maintenances_Edit_NotingChanged();
					break;
				}
				$params['id'] = $old_maintenance['id'];
			} else {
				if (!\Sessions::checkPermission(\Maintenances::PERMISSION_CREATE, 0, WRITE)) {
					$this->error = \T::Maintenances_Create_PermissionDenied();
					break;
				}
				if ($data['monitor_id']) {
					foreach($data['monitor_id'] as $monitor_id) {
						if (!isset($monitors_hash[$monitor_id]) || !$monitors_hash[$monitor_id]) continue;
						if (isset($update_monitors[$monitor_id])) continue;
						$update_monitors[$monitor_id] = 1;
					}
				}
				$params['create_time'] = time();
			}
			$params['update_time'] = time();

			$maintenance_id = 0;
			if (isset($params['id']) && $params['id']) {
				// Update
				$params['_mode'] = \DB\Common::CSMODE_UPDATE;
				$maintenance_id = \Maintenances::save($params);
				$new_maintenance = \Maintenances::get(['id' => $maintenance_id]);
				\Logs::update_log(\Maintenances::LOGS_OBJECT, $maintenance_id, $old_maintenance, $new_maintenance,[
					'_save_fields' => ['id', 'title'],
				]);
				if (!$new_maintenance) {
					$this->error = \T::Maintenances_Edit_NotFound();
					break;
				}
				foreach ($update_monitors as $monitor_id=>$status) {
					if ($status < 0) {
						$old_job = \MonitoringMaintenances::get([
							'maintenance_id' => $params['id'],
							'monitor_id' => $monitor_id,
						]);
						if (isset($old_job['id']) && $old_job['id']) {
							\MonitoringMaintenances::delete($old_job['id']);
							$log_id = \Logs::delete_log(\MonitoringMaintenances::LOGS_OBJECT, $old_job['id'], $old_job);
							\Logs::add_tag($log_id, \Maintenances::LOGS_OBJECT, $params['id']);
							\Logs::add_tag($log_id, \Monitors::LOGS_OBJECT, $monitor_id);
						}
					} else {
						$job = [
							'maintenance_id' => $params['id'],
							'monitor_id' => $monitor_id,
							'create_time' => time(),
							'update_time' => time(),
						];
						$job['id'] = \MonitoringMaintenances::save($job);
						$log_id = \Logs::create_log(\MonitoringMaintenances::LOGS_OBJECT, $job['id'],$job);
						\Logs::add_tag($log_id, \Maintenances::LOGS_OBJECT, $params['id']);
						\Logs::add_tag($log_id, \Monitors::LOGS_OBJECT, $monitor_id);
					}
				}
			} else {
				// Create
				$params['_mode'] = \DB\Common::CSMODE_INSERT;
				$maintenance_id = \Maintenances::save($params);
				$new_maintenance = \Maintenances::get(['id' => $maintenance_id]);
				$log_id = \Logs::create_log(\Maintenances::LOGS_OBJECT, $maintenance_id, $new_maintenance);
				if (!$new_maintenance) {
					$this->error = \T::Maintenances_Edit_NotFound();
					break;
				}
				foreach ($update_monitors as $monitor_id=>$status) {
					$job = [
						'maintenance_id' => $maintenance_id,
						'monitor_id' => $monitor_id,
						'create_time' => time(),
						'update_time' => time(),
					];
					$job['id'] = \MonitoringMaintenances::save($job);
					$log_id = \Logs::create_log(\MonitoringMaintenances::LOGS_OBJECT, $job['id'], $job);
					\Logs::add_tag($log_id, \Maintenances::LOGS_OBJECT, $maintenance_id);
					\Logs::add_tag($log_id, \Monitors::LOGS_OBJECT, $monitor_id);
				}

				if (!\Sessions::in_group(\Groups::ADMIN_GROUP_ID, intval(\Sessions::currentUser()['id']))) {
					$permissions = \Maintenances::permissions_hash();
					foreach($permissions as $permission => $_) {
						$object_permission = [
							'object' => 'user',
							'object_id' => intval(\Sessions::currentUser()['id']),
							'subject' => $permission,
							'subject_id' => $maintenance_id,
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
						\Logs::add_tag($log_id, \Maintenances::LOGS_OBJECT, $maintenance_id);
					}
				}
			}
			if ($maintenance_id) {
				common_redirect('/maintenances/edit/' . $maintenance_id . '/');
			} else {
				common_redirect('/maintenances/');
			}
		} while(false);
		$data['start_time'] = strtotime($data['start_time']);
		$data['finish_time'] = strtotime($data['finish_time']);

		foreach($data as $key => $value) {
			$this->maintenance[$key] = $value;
		}

	}

	private function check_permissions() {
		if ($this->maintenance_id) {
			$this->maintenance = \Maintenances::get(['id' => $this->maintenance_id]);
			if (!$this->maintenance) {
				common_redirect('/maintenances/');
			}
			$this->can_read = \Sessions::checkPermission(\Maintenances::PERMISSION_ACCESS, $this->maintenance_id, READ);
			$this->can_write = \Sessions::checkPermission(\Maintenances::PERMISSION_ACCESS, $this->maintenance_id, WRITE);
			if (!$this->can_read) {
				e403();
			}
		} else {
			$this->can_read = \Sessions::checkPermission(\Maintenances::PERMISSION_ACCESS, -1, READ);
			$this->can_create = \Sessions::checkPermission(\Maintenances::PERMISSION_CREATE, 0, WRITE);
			if (!$this->can_read && !$this->can_create) {
				e403();
			}
		}
	}

	private function get_data() {
		if (!isset($this->maintenance['id'])) $this->maintenance['id'] = '';

		if ($this->maintenance['id']) {
			$this->maintenance['create_date'] = (
				isset($this->maintenance['create_time']) && $this->maintenance['create_time'] > 0 ?
				date('Y-m-d H:i:s', $this->maintenance['create_time']) : ''
			);
			$this->maintenance['update_date'] = (
				isset($this->maintenance['update_time']) && $this->maintenance['update_time'] > 0 ?
				date('Y-m-d H:i:s', $this->maintenance['update_time']) : ''
			);
			$this->maintenance['monitor_id'] ??= [];
			$monitors = \MonitoringMaintenances::data(['maintenance_id' => $this->maintenance['id']]);
			foreach($monitors as $monitor) {
				$this->maintenance['monitor_id'][] = $monitor['monitor_id'];
			}
		} else {
			$this->maintenance['start_time'] ??= time();
			$this->maintenance['monitor_id'] ??= [];
		}
	}

	private function print_header() {
		?>
		<div class="float-start"><h2><i class="bi bi-hammer"></i> <?=\T::Maintenances_PageTitle();?></h2></div>
		<div class="clearfix"></div>
		<?php
	}

	private function print_forms() {
		?>
		<div class="row d-flex justify-content-center">
			<div class="card bg-transparent col-xl-10 p-4 mt-2 mb-3">
				<div class="card-header bg-transparent"><h3>
					<?php
					if ($this->maintenance['id']) {
						echo \T::Maintenances_Edit_EditTitle(htmlspecialchars($this->maintenance['title'] ?? '', ENT_QUOTES, 'UTF-8'), $this->maintenance['id']);
					} else {
						echo \T::Maintenances_Edit_CreateTitle();
					}
					?>
				</h3></div>
				<form class="card-body needs-validation" method="post" novalidate>
					<input type="hidden" name="id" value="<?=$this->maintenance['id'];?>"/>
					<?php
					echo $this->template?->html_input("title", htmlspecialchars($this->maintenance['title'] ?? '', ENT_QUOTES, 'UTF-8'), \T::Maintenances_Edit_Title(), true, [
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'readonly' => !($this->can_write || $this->can_create),
					]);
					?>
					<div class="mb-3 row ">
						<label for="start_date_input" class="col-xs-12 col-sm-12 col-md-3 col-form-label"><?=\T::Maintenances_Edit_StartTime();?> <sup>*</sup></label>
						<div class="col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6">
							<div class="input-group" id="start_date" data-td-target-input="nearest" data-td-target-toggle="nearest">
								<input id="start_date_input" required="required" type="text" class="<?=(!($this->can_write || $this->can_create) ? 'form-control-plaintext' : 'form-control');?>" data-td-target="#start_date" name="start_time" value="" <?=(!($this->can_write || $this->can_create) ? 'readonly="readonly"' : '');?>>
								<span class="input-group-text pointer" data-td-target="#start_date_input" data-td-toggle="datetimepicker">
									<i class="bi bi-calendar-fill"></i>
								</span>
								<div class="invalid-feedback">Пожалуйста, заполните это поле</div>
							</div>
						</div>
					</div>
					<div class="mb-3 row ">
						<label for="finish_date_input" class="col-xs-12 col-sm-12 col-md-3 col-form-label"><?=\T::Maintenances_Edit_FinishTime();?> <sup>*</sup></label>
						<div class="col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6">
							<div class="input-group" id="finish_date" data-td-target-input="nearest" data-td-target-toggle="nearest">
								<input id="finish_date_input" required="required" type="text" class="<?=(!($this->can_write || $this->can_create) ? 'form-control-plaintext' : 'form-control');?>" data-td-target="#finish_date" name="finish_time" value="" <?=(!($this->can_write || $this->can_create) ? 'readonly="readonly"' : '');?>>
								<span class="input-group-text pointer" data-td-target="#finish_date_input" data-td-toggle="datetimepicker">
									<i class="bi bi-calendar-fill"></i>
								</span>
								<div class="invalid-feedback">Пожалуйста, заполните это поле</div>
							</div>
						</div>
					</div>

					<?php

					echo $this->template?->html_select('monitor_id[]', \Monitors::get_folder_hash_options_hash(false), $this->maintenance['monitor_id']??[], \T::Maintenances_Edit_Monitors(), true,[
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'multiple' => true,
						'readonly' => !($this->can_write || $this->can_create),
					]);


					if ($this->maintenance['id']) {
						echo $this->template->html_input("create_date", $this->maintenance['create_date']??'', \T::Maintenances_Edit_CreateTime(), false, ['readonly' => true]);
						echo $this->template->html_input("update_date", $this->maintenance['update_date']??'', \T::Maintenances_Edit_UpdateTime(), false, ['readonly' => true]);
					}
					?>
					<div class="d-flex flex-row-reverse">
						<button type="submit" class="btn btn-primary me-2" name="<?=$this->maintenance['id'] ? 'editMaintenance' : 'createMaintenance';?>" value="true"><?=($this->maintenance['id'] ? \T::Maintenances_Edit_ChangeButton() : \T::Maintenances_Edit_CreateButton());?></button>
					</div>
				</form>
			</div>
		</div>
		<?php
	}

	private function print_javascript() {
		?>
		<script nonce="<?=\CSP::nonceRandom();?>">

			$(document).ready(function() {
				const pickerStart = new tempusDominus.TempusDominus(document.getElementById('start_date'), {
					allowInputToggle: true,
					dateRange: false,
					debug: false,

					display: {
						icons: {
							type: 'icons',
							time: 'bi bi-clock',
							date: 'bi bi-calendar-week',
							up: 'bi bi-arrow-up',
							down: 'bi bi-arrow-down',
							previous: 'bi bi-chevron-left',
							next: 'bi bi-chevron-right',
							today: 'bi bi-calendar-check',
							clear: 'bi bi-trash',
							close: 'bi bi-x'
						},
						keepOpen: false,
						buttons: {
							today: true,
							clear: true,
							close: true
						},
						viewMode: 'calendar',
						inline: false,
						theme: 'auto',
						components: {
							calendar: true,
							date: true,
							month: true,
							year: true,
							decades: true,
							clock: true,
							hours: true,
							minutes: true,
							seconds: true,
						},
					},
					localization: {
						today: '<?=\T::Framework_DateTimePicker_today();?>',
						clear: '<?=\T::Framework_DateTimePicker_clear();?>',
						close: '<?=\T::Framework_DateTimePicker_close();?>',
						selectMonth: '<?=\T::Framework_DateTimePicker_selectMonth();?>',
						previousMonth: '<?=\T::Framework_DateTimePicker_previousMonth();?>',
						nextMonth: '<?=\T::Framework_DateTimePicker_nextMonth();?>',
						selectYear: '<?=\T::Framework_DateTimePicker_selectYear();?>',
						previousYear: '<?=\T::Framework_DateTimePicker_previousYear();?>',
						nextYear: '<?=\T::Framework_DateTimePicker_nextYear();?>',
						selectDecade: '<?=\T::Framework_DateTimePicker_selectDecade();?>',
						previousDecade: '<?=\T::Framework_DateTimePicker_previousDecade();?>',
						nextDecade: '<?=\T::Framework_DateTimePicker_nextDecade();?>',
						previousCentury: '<?=\T::Framework_DateTimePicker_previousCentury();?>',
						nextCentury: '<?=\T::Framework_DateTimePicker_nextCentury();?>',
						pickHour: '<?=\T::Framework_DateTimePicker_pickHour();?>',
						incrementHour: '<?=\T::Framework_DateTimePicker_incrementHour();?>',
						decrementHour: '<?=\T::Framework_DateTimePicker_decrementHour();?>',
						pickMinute: '<?=\T::Framework_DateTimePicker_pickMinute();?>',
						incrementMinute: '<?=\T::Framework_DateTimePicker_incrementMinute();?>',
						decrementMinute: '<?=\T::Framework_DateTimePicker_decrementMinute();?>',
						pickSecond: '<?=\T::Framework_DateTimePicker_pickSecond();?>',
						incrementSecond: '<?=\T::Framework_DateTimePicker_incrementSecond();?>',
						decrementSecond: '<?=\T::Framework_DateTimePicker_decrementSecond();?>',
						toggleMeridiem: '<?=\T::Framework_DateTimePicker_toggleMeridiem();?>',
						selectTime: '<?=\T::Framework_DateTimePicker_selectTime();?>',
						selectDate: '<?=\T::Framework_DateTimePicker_selectDate();?>',
						locale: '<?=\T::getCurrentLanguage();?>',
						startOfTheWeek: 1,
						dateFormats: {
							LL: 'yyyy-MM-dd HH:mm:ss',
						},
						format: 'LL'
					},
				});

				const pickerFinish = new tempusDominus.TempusDominus(document.getElementById('finish_date'), {
					allowInputToggle: true,
					dateRange: false,
					debug: false,

					display: {
						icons: {
							type: 'icons',
							time: 'bi bi-clock',
							date: 'bi bi-calendar-week',
							up: 'bi bi-arrow-up',
							down: 'bi bi-arrow-down',
							previous: 'bi bi-chevron-left',
							next: 'bi bi-chevron-right',
							today: 'bi bi-calendar-check',
							clear: 'bi bi-trash',
							close: 'bi bi-x'
						},
						keepOpen: false,
						buttons: {
							today: true,
							clear: true,
							close: true
						},
						viewMode: 'calendar',
						inline: false,
						theme: 'auto',
						components: {
							calendar: true,
							date: true,
							month: true,
							year: true,
							decades: true,
							clock: true,
							hours: true,
							minutes: true,
							seconds: true,
						},
					},
					localization: {
						today: '<?=\T::Framework_DateTimePicker_today();?>',
						clear: '<?=\T::Framework_DateTimePicker_clear();?>',
						close: '<?=\T::Framework_DateTimePicker_close();?>',
						selectMonth: '<?=\T::Framework_DateTimePicker_selectMonth();?>',
						previousMonth: '<?=\T::Framework_DateTimePicker_previousMonth();?>',
						nextMonth: '<?=\T::Framework_DateTimePicker_nextMonth();?>',
						selectYear: '<?=\T::Framework_DateTimePicker_selectYear();?>',
						previousYear: '<?=\T::Framework_DateTimePicker_previousYear();?>',
						nextYear: '<?=\T::Framework_DateTimePicker_nextYear();?>',
						selectDecade: '<?=\T::Framework_DateTimePicker_selectDecade();?>',
						previousDecade: '<?=\T::Framework_DateTimePicker_previousDecade();?>',
						nextDecade: '<?=\T::Framework_DateTimePicker_nextDecade();?>',
						previousCentury: '<?=\T::Framework_DateTimePicker_previousCentury();?>',
						nextCentury: '<?=\T::Framework_DateTimePicker_nextCentury();?>',
						pickHour: '<?=\T::Framework_DateTimePicker_pickHour();?>',
						incrementHour: '<?=\T::Framework_DateTimePicker_incrementHour();?>',
						decrementHour: '<?=\T::Framework_DateTimePicker_decrementHour();?>',
						pickMinute: '<?=\T::Framework_DateTimePicker_pickMinute();?>',
						incrementMinute: '<?=\T::Framework_DateTimePicker_incrementMinute();?>',
						decrementMinute: '<?=\T::Framework_DateTimePicker_decrementMinute();?>',
						pickSecond: '<?=\T::Framework_DateTimePicker_pickSecond();?>',
						incrementSecond: '<?=\T::Framework_DateTimePicker_incrementSecond();?>',
						decrementSecond: '<?=\T::Framework_DateTimePicker_decrementSecond();?>',
						toggleMeridiem: '<?=\T::Framework_DateTimePicker_toggleMeridiem();?>',
						selectTime: '<?=\T::Framework_DateTimePicker_selectTime();?>',
						selectDate: '<?=\T::Framework_DateTimePicker_selectDate();?>',
						locale: '<?=\T::getCurrentLanguage();?>',
						startOfTheWeek: 1,
						dateFormats: {
							LL: 'yyyy-MM-dd HH:mm:ss',
						},
						format: 'LL'
					},
				});

				pickerStart.dates.clear();
				const startDate = pickerStart.dates.parseInput(new window.Date(<?=($this->maintenance['start_time']??0)*1000;?>));
				pickerStart.dates.setValue(startDate, 0);

				pickerFinish.dates.clear();
				<?php if ($this->maintenance['finish_time']??0) { ?>
					const finishDate = pickerFinish.dates.parseInput(new window.Date(<?=($this->maintenance['finish_time']??0)*1000;?>));
					pickerFinish.dates.setValue(finishDate, 0);
				<?php } ?>

				$('form').on('submit', function(event) {
					$('form').addClass("was-validated");
				});
			});
		</script>
		<?php
	}
}
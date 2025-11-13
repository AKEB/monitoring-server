<?php

namespace App\Maintenances;

class Show extends \Routing_Parent implements \Routing_Interface {

	private bool $can_read_global = false;
	private bool $can_delete_global = false;
	private bool $can_create = false;

	private array $maintenances = [];

	public function Run($action='list', $maintenance_id=null) {
		$this->check_auth();
		$this->check_permissions();

		if ($action == 'delete' && $maintenance_id) {
			$this->processDeleteAction($maintenance_id);
		} elseif ($action == 'copy' && $maintenance_id) {
			$this->processCopyAction($maintenance_id);
		}

		$this->get_data();

		$this->template = new \Template('Maintenances');
		$this->print_header();
		$this->print_table();
		$this->print_modal();
		$this->print_javascript();
	}

	private function check_permissions() {
		$this->can_read_global = \Sessions::checkPermission(\Maintenances::PERMISSION_ACCESS, -1, READ);
		$this->can_delete_global = \Sessions::checkPermission(\Maintenances::PERMISSION_ACCESS, -1, DELETE);
		$this->can_create = \Sessions::checkPermission(\Maintenances::PERMISSION_CREATE, 0, WRITE);

		if (!$this->can_read_global && !$this->can_create) {
			e403();
		}
	}

	private function processDeleteAction(int $maintenance_id) {
		if (!isset($maintenance_id)) return;
		if (!$this->can_delete_global) {
			$this->error = \T::Maintenances_Delete_PermissionDenied();
			return;
		}
		if (!$maintenance_id) {
			$this->error = \T::Maintenances_Delete_NotFound();
			return;
		}

		if (!\Sessions::checkPermission(\Maintenances::PERMISSION_ACCESS, $maintenance_id, DELETE)) {
			$this->error = \T::Maintenances_Delete_PermissionDenied();
			return;
		}
		$maintenance = \Maintenances::get(['id' => $maintenance_id]);
		if (!$maintenance) {
			$this->error = \T::Maintenances_Delete_NotFound();
			return;
		}
		$jobs = \MonitoringMaintenances::data(['maintenance_id' => $maintenance_id]);
		foreach($jobs as $job) {
			$log_id = \Logs::delete_log(\MonitoringMaintenances::LOGS_OBJECT, $job['id'], $job);
			if ($log_id) {
				\Logs::add_tag($log_id, \Monitors::LOGS_OBJECT, $job['monitor_id']);
				\Logs::add_tag($log_id, \Maintenances::LOGS_OBJECT, $job['maintenance_id']);
			}
			\MonitoringMaintenances::delete(['id' => $job['id']]);
		}

		\Maintenances::delete(['id' => $maintenance_id]);
		\Logs::delete_log(\Maintenances::LOGS_OBJECT, $maintenance_id, $maintenance);
		common_redirect('/maintenances/');
	}

	private function processCopyAction(int $maintenance_id) {
		if (!isset($maintenance_id)) return;
		if (!$this->can_read_global) {
			$this->error = \T::Maintenances_Copy_PermissionDenied();
			return;
		}
		if (!$maintenance_id) {
			$this->error = \T::Maintenances_Copy_NotFound();
			return;
		}
		if (!\Sessions::checkPermission(\Maintenances::PERMISSION_CREATE, $maintenance_id, WRITE)) {
			$this->error = \T::Maintenances_Copy_PermissionDenied();
			return;
		}
		if (!\Sessions::checkPermission(\Maintenances::PERMISSION_ACCESS, $maintenance_id, READ)) {
			$this->error = \T::Maintenances_Copy_PermissionDenied();
			return;
		}
		$maintenance = \Maintenances::get(['id' => $maintenance_id]);
		if (!$maintenance) {
			$this->error = \T::Maintenances_Copy_NotFound();
			return;
		}
		$newMaintenance = $maintenance;
		unset($newMaintenance['id']);
		$newMaintenance['title'] .= ' (copy)';
		$newMaintenance['create_time'] = time();
		$newMaintenance['update_time'] = time();
		$newMaintenance['id'] = \Maintenances::save($newMaintenance);
		if ($newMaintenance['id']) {
			\Logs::create_log(\Maintenances::LOGS_OBJECT, $newMaintenance['id'], $newMaintenance);

			if (!\Sessions::in_group(\Groups::ADMIN_GROUP_ID, intval(\Sessions::currentUser()['id']))) {
				$permissions = \Maintenances::permissions_hash();
				foreach($permissions as $permission => $_) {
					$ObjectPermissions = [
						'object' => 'user',
						'object_id' => intval(\Sessions::currentUser()['id']),
						'subject' => $permission,
						'subject_id' => $newMaintenance['id'],
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
					\Logs::add_tag($log_id, \Maintenances::LOGS_OBJECT, $newMaintenance['id']);
				}
			}

			$monitors = \MonitoringMaintenances::data(['maintenance_id'=>$maintenance_id]);
			foreach($monitors as $monitor) {
				unset($monitor['id']);
				$monitor['maintenance_id'] = $newMaintenance['id'];
				$monitor['create_time'] = time();
				$monitor['update_time'] = time();
				$monitor['id'] = \MonitoringMaintenances::save($monitor);
				$log_id = \Logs::create_log(\MonitoringMaintenances::LOGS_OBJECT, $monitor['id'], $monitor);
				\Logs::add_tag($log_id, \Maintenances::LOGS_OBJECT, $newMaintenance['id']);
			}
			common_redirect('/maintenances/edit/'.$newMaintenance['id'].'/');
		}
	}

	private function get_data() {
		$this->maintenances = [];
		$data = \Maintenances::data();
		foreach($data as $item) {
			if (!\Sessions::checkPermission(\Maintenances::PERMISSION_ACCESS, $item['id'], READ)) continue;
			$this->maintenances[$item['id']] = $item;
		}
	}

	private function print_header() {
		?>
		<div class="float-start"><h2><i class="bi bi-hammer"></i> <?=\T::Maintenances_PageTitle();?></h2></div>
		<?php if ($this->can_create) {
			?>
			<div class="float-end">
				<h3 class="pointer text-info">
					<i class="bi bi-plus-circle createActionButton"> <?=\T::Framework_Common_Create();?></i>
				</h3>
			</div>
			<?php
		}
		?>
		<div class="clearfix"></div>
		<?php
	}

	private function print_table() {
		?>
		<div class="row d-flex justify-content-center">
			<table class="table table-transparent table-responsive" id="Table">
				<thead class="">
					<tr>
						<th scope="col" class="align-middle" data-priority="1">ID</th>
						<th scope="col" class="align-middle" data-priority="2"><?=\T::Maintenances_Table_Title();?></th>
						<th scope="col" class="align-middle text-center" data-priority="4"><?=\T::Maintenances_Table_StartTime();?></th>
						<th scope="col" class="align-middle text-center" data-priority="5"><?=\T::Maintenances_Table_FinishTime();?></th>
						<th scope="col" class="align-middle text-center" data-priority="7"><?=\T::Maintenances_Table_CreateTime();?></th>
						<th scope="col" class="align-middle text-center" data-priority="6"><?=\T::Maintenances_Table_UpdateTime();?></th>
						<th scope="col" class="align-middle text-center" data-priority="3"><?=\T::Maintenances_Table_Actions();?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ($this->maintenances as $item) {
						$item['start_time'] ??=0;
						$item['finish_time'] ??=0;
						$item['create_time'] ??=0;
						$item['update_time'] ??=0;

						$params = [
							'id' => intval($item['id'] ?? 0),
							'title' => htmlspecialchars(trim($item['title'] ?? '')),
							'start_time' => $item['start_time'] > 0 ? date("Y-m-d H:i:s", $item['start_time']) : '',
							'finish_time' => $item['finish_time'] > 0 ? date("Y-m-d H:i:s", $item['finish_time']) : '',
							'create_time' => $item['create_time'] > 0 ? date("Y-m-d H:i:s", $item['create_time']) : '',
							'update_time' => $item['update_time'] > 0 ? date("Y-m-d H:i:s", $item['update_time']) : '',
						];

						$can_read = \Sessions::checkPermission(\Maintenances::PERMISSION_ACCESS, $params['id'], READ);
						if (!$can_read) continue;
						$can_write = \Sessions::checkPermission(\Maintenances::PERMISSION_ACCESS, $params['id'], WRITE);
						$can_delete = \Sessions::checkPermission(\Maintenances::PERMISSION_ACCESS, $params['id'], DELETE);
						$class = '';
						if (time() >= $item['start_time'] && time() <= $item['finish_time']) {
							$class = 'text-primary';
						} elseif (time() < $item['start_time']) {
							$class = 'text-warning';
						} elseif (time() > $item['finish_time']) {
							$class = 'text-secondary';
						}
						?>
						<tr>
							<th scope="row" class="align-middle"><?=$params['id'];?></th>
							<td class="align-middle <?=$class;?>"><span class="d-inline"><?=$params['title'];?></span></td>
							<td class="align-middle text-center"><?=$params['start_time'];?></td>
							<td class="align-middle text-center"><?=$params['finish_time'];?></td>
							<td class="align-middle text-center"><?=$params['create_time'];?></td>
							<td class="align-middle text-center"><?=$params['update_time'];?></td>
							<td class="align-middle text-center">
								<?php if ($can_write) {
									?><i class="bi bi-pencil-square me-2 fs-4 text-info editActionButton pointer" title="<?=\T::Maintenances_Table_Edit();?>" data-object-id="<?=$params['id'];?>"></i><?php
								} elseif ($can_read) {
									?><i class="bi bi-eye fs-4 me-2 text-info editActionButton pointer" title="<?=\T::Maintenances_Table_Edit();?>" data-object-id="<?=$params['id'];?>"></i><?php
								} else {
									?><i class="bi bi-pencil-square me-2 fs-4 text-secondary" title="<?=\T::Maintenances_Table_Edit();?>"></i><?php
								} ?>
								<i class="bi bi-copy fs-4 me-2 text-info pointer copyActionButton" title="<?=\T::Monitor_Table_Copy();?>" data-object-id="<?=$params['id'];?>"></i>
								<?php if ($this->can_delete_global) {?>
									<?php
									if ($can_delete) {
										?>
										<i class="bi bi-trash fs-4 me-2 text-danger pointer deleteActionButton"
											data-object-id = "<?=$params['id'];?>"
											data-object-title = "<?=addslashes($params['title']);?>"
											title="<?=\T::Maintenances_Table_Delete();?>"
										></i>
										<?php
									} else {
										?><i class="bi bi-trash fs-4 text-secondary" title="<?=\T::Maintenances_Table_Delete();?>"></i><?php
									}
									?>
								<?php } ?>
							</td>
						</tr>
						<?php
					}
					?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private function print_modal() {
		?>
		<div class="modal" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel">
			<div class="modal-dialog">
				<div class="modal-content">
				<div class="modal-header border-secondary">
					<h5 class="modal-title" id="deleteModalLabel"><?=\T::Maintenances_Delete_Title();?></h5>
					<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body" id="deleteModalBody"></div>
				<div class="modal-footer border-secondary">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?=\T::Framework_Common_Cancel();?></button>
					<button type="button" class="btn btn-danger" id="confirmDeleteBtn"><?=\T::Framework_Common_Delete();?></button>
				</div>
				</div>
			</div>
		</div>
		<?php
	}

	private function print_javascript() {
		?>
		<script nonce="<?=\CSP::nonceRandom();?>">
			$(document).ready(function(){
				new DataTable('#Table');

				$('.createActionButton').on('click', function() {
					window.location.href = '/maintenances/edit/';
				});

				$('.editActionButton').on('click', function() {
					window.location.href = '/maintenances/edit/' + $(this).data('object-id') + '/';
				});

				$('.copyActionButton').on('click', function() {
					window.location.href = '/maintenances/copy/' + $(this).data('object-id') + '/';
				});

				$('.deleteActionButton').on('click', function() {
					showDeleteModal($(this).data('object-id'), $(this).data('object-title'));
				});

			});
			function showDeleteModal(objectId, objectTitle) {
				const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
				const modalBody = document.getElementById('deleteModalBody');
				const confirmBtn = document.getElementById('confirmDeleteBtn');

				modalBody.textContent = '<?=\T::Maintenances_Delete_Confirmation();?>'.replace('{title}', objectTitle);

				confirmBtn.onclick = function() {
					window.location.href = '/maintenances/delete/' + objectId + '/';
				};

				modal.show();

				const modalElement = document.getElementById('deleteModal');
				modalElement.addEventListener('hide.bs.modal', event => {
					const focusedElement = document.activeElement;
					if (modalElement.contains(focusedElement)) {
						focusedElement.blur();
					}
				});
			}
		</script>
		<?php
	}
}

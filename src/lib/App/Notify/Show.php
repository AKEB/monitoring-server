<?php

namespace App\Notify;

class Show extends \Routing_Parent implements \Routing_Interface {

	private bool $can_read_global = false;
	private bool $can_delete_global = false;
	private bool $can_create = false;

	private array $notify = [];

	public function Run($action='list', $notify_id=null) {
		$this->check_auth();
		$this->check_permissions();

		if ($action == 'delete' && $notify_id) {
			$this->processDeleteAction($notify_id);
		}

		$this->get_data();

		$this->template = new \Template('Notify');
		$this->print_header();
		$this->print_table();
		$this->print_modal();
		$this->print_javascript();
	}

	private function check_permissions() {
		$this->can_read_global = \Sessions::checkPermission(\Notify::PERMISSION_ACCESS, -1, READ);
		$this->can_delete_global = \Sessions::checkPermission(\Notify::PERMISSION_ACCESS, -1, DELETE);
		$this->can_create = \Sessions::checkPermission(\Notify::PERMISSION_CREATE, 0, WRITE);

		if (!$this->can_read_global && !$this->can_create) {
			e403();
		}
	}

	private function processDeleteAction(int $notify_id) {
		if (!isset($notify_id)) return;
		if (!$this->can_delete_global) {
			$this->error = \T::Notify_Delete_PermissionDenied();
			return;
		}
		if (!$notify_id) {
			$this->error = \T::Notify_Delete_NotFound();
			return;
		}

		if (!\Sessions::checkPermission(\Notify::PERMISSION_ACCESS, $notify_id, DELETE)) {
			$this->error = \T::Notify_Delete_PermissionDenied();
			return;
		}
		$notify = \Notify::get(['id' => $notify_id]);
		if (!$notify) {
			$this->error = \T::Notify_Delete_NotFound();
			return;
		}
		$jobs = \MonitoringNotify::data(['notify_id' => $notify_id]);
		foreach($jobs as $job) {
			$log_id = \Logs::delete_log(\MonitoringNotify::LOGS_OBJECT, $job['id'], $job);
			if ($log_id) {
				\Logs::add_tag($log_id, \Monitors::LOGS_OBJECT, $job['monitor_id']);
				\Logs::add_tag($log_id, \Notify::LOGS_OBJECT, $job['notify_id']);
			}
			\MonitoringNotify::delete(['id' => $job['id']]);
		}

		\Notify::delete(['id' => $notify_id]);
		\Logs::delete_log(\Notify::LOGS_OBJECT, $notify_id, $notify);
		common_redirect('/notify/');
	}

	private function get_data() {
		$this->notify = [];
		$data = \Notify::data();
		foreach($data as $item) {
			if (!\Sessions::checkPermission(\Notify::PERMISSION_ACCESS, $item['id'], READ)) continue;
			$this->notify[$item['id']] = $item;
		}
	}

	private function print_header() {
		?>
		<div class="float-start"><h2><i class="bi bi-bell"></i> <?=\T::Notify_PageTitle();?></h2></div>
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
						<th scope="col" class="align-middle" data-priority="2"><?=\T::Notify_Table_Title();?></th>
						<th scope="col" class="align-middle" data-priority="4"><?=\T::Notify_Table_Status();?></th>
						<th scope="col" class="align-middle" data-priority="5"><?=\T::Notify_Table_Type();?></th>
						<th scope="col" class="align-middle" data-priority="6"><?=\T::Notify_Table_Receiver();?></th>
						<th scope="col" class="align-middle text-center" data-priority="8"><?=\T::Notify_Table_CreateTime();?></th>
						<th scope="col" class="align-middle text-center" data-priority="7"><?=\T::Notify_Table_UpdateTime();?></th>
						<th scope="col" class="align-middle text-center" data-priority="3"><?=\T::Notify_Table_Actions();?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ($this->notify as $item) {
						$params = [
							'id' => intval($item['id'] ?? 0),
							'title' => htmlspecialchars(trim($item['title'] ?? '')),
							'status' => intval($item['status'] ?? 0) == 1 ? '<i class="bi bi-check-lg fs-4 text-success"></i>':'<i class="bi bi-x-lg fs-4 text-danger"></i>',
							'type' => \Notify::type_hash()[intval($item['type'] ?? 0)],
							'receiver' => htmlspecialchars(trim($item['receiver'] ?? '')),
							'create_time' => isset($item['create_time']) && $item['create_time'] > 0 ? date("Y-m-d H:i:s", $item['create_time']) : '',
							'update_time' => isset($item['update_time']) && $item['update_time'] > 0 ? date("Y-m-d H:i:s", $item['update_time']) : '',
						];

						$can_read = \Sessions::checkPermission(\Notify::PERMISSION_ACCESS, $params['id'], READ);
						if (!$can_read) continue;
						$can_write = \Sessions::checkPermission(\Notify::PERMISSION_ACCESS, $params['id'], WRITE);
						$can_delete = \Sessions::checkPermission(\Notify::PERMISSION_ACCESS, $params['id'], DELETE);
						?>
						<tr>
							<th scope="row" class="align-middle"><?=$params['id'];?></th>
							<td class="align-middle"><span class="d-inline"><?=$params['title'];?></span></td>
							<td class="align-middle text-center"><?=$params['status'];?></td>
							<td class="align-middle text-center"><?=$params['type'];?></td>
							<td class="align-middle text-center"><?=$params['receiver'];?></td>
							<td class="align-middle text-center"><?=$params['create_time'];?></td>
							<td class="align-middle text-center"><?=$params['update_time'];?></td>
							<td class="align-middle text-center">
								<?php if ($can_write) {
									?><i class="bi bi-pencil-square me-2 fs-4 text-info editActionButton pointer" title="<?=\T::Notify_Table_Edit();?>" data-object-id="<?=$params['id'];?>"></i><?php
								} elseif ($can_read) {
									?><i class="bi bi-eye fs-4 me-2 text-info editActionButton pointer" title="<?=\T::Notify_Table_Edit();?>" data-object-id="<?=$params['id'];?>"></i><?php
								} else {
									?><i class="bi bi-pencil-square me-2 fs-4 text-secondary" title="<?=\T::Notify_Table_Edit();?>"></i><?php
								} ?>
								<?php if ($this->can_delete_global) {?>
									<?php
									if ($can_delete) {
										?>
										<i class="bi bi-trash fs-4 me-2 text-danger pointer deleteActionButton"
											data-object-id = "<?=$params['id'];?>"
											data-object-title = "<?=addslashes($params['title']);?>"
											title="<?=\T::Notify_Table_Delete();?>"
										></i>
										<?php
									} else {
										?><i class="bi bi-trash fs-4 text-secondary" title="<?=\T::Notify_Table_Delete();?>"></i><?php
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
					<h5 class="modal-title" id="deleteModalLabel"><?=\T::Notify_Delete_Title();?></h5>
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
					window.location.href = '/notify/edit/';
				});

				$('.editActionButton').on('click', function() {
					window.location.href = '/notify/edit/' + $(this).data('object-id') + '/';
				});

				$('.deleteActionButton').on('click', function() {
					showDeleteModal($(this).data('object-id'), $(this).data('object-title'));
				});

			});
			function showDeleteModal(objectId, objectTitle) {
				const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
				const modalBody = document.getElementById('deleteModalBody');
				const confirmBtn = document.getElementById('confirmDeleteBtn');

				modalBody.textContent = '<?=\T::Notify_Delete_Confirmation();?>'.replace('{title}', objectTitle);

				confirmBtn.onclick = function() {
					window.location.href = '/notify/delete/' + objectId + '/';
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

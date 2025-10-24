<?php

namespace App\Workers;

class Show extends \Routing_Parent implements \Routing_Interface {

	private bool $can_read_global = false;
	private bool $can_delete_global = false;
	private bool $can_create = false;

	private array $workers = [];

	public function Run($action='list', $worker_id=null) {
		$this->check_auth();
		$this->check_permissions();

		if ($action == 'delete' && $worker_id) {
			$this->processDeleteAction($worker_id);
		}

		$this->get_data();

		$this->template = new \Template();
		$this->print_header();
		$this->print_table();
		$this->print_modal();
		$this->print_javascript();
	}

	private function check_permissions() {
		$this->can_read_global = \Sessions::checkPermission(\Workers::PERMISSION_ACCESS, -1, READ);
		$this->can_delete_global = \Sessions::checkPermission(\Workers::PERMISSION_ACCESS, -1, DELETE);
		$this->can_create = \Sessions::checkPermission(\Workers::PERMISSION_CREATE, 0, WRITE);

		if (!$this->can_read_global && !$this->can_create) {
			e403();
		}
	}

	private function processDeleteAction(int $workerId) {
		if (!isset($workerId)) return;
		if (!$this->can_delete_global) {
			$this->error = \T::Worker_Delete_PermissionDenied();
			return;
		}
		if (!$workerId) {
			$this->error = \T::Worker_Delete_WorkerNotFound();
			return;
		}

		if (!\Sessions::checkPermission(\Workers::PERMISSION_ACCESS, $workerId, DELETE)) {
			$this->error = \T::Worker_Delete_PermissionDenied();
			return;
		}
		$worker = \Workers::get(['id' => $workerId]);
		if (!$worker) {
			$this->error = \T::Worker_Delete_WorkerNotFound();
			return;
		}
		\Workers::delete(['id' => $workerId]);
		\Logs::delete_log(\Workers::LOGS_OBJECT, $workerId, $worker);
		common_redirect('/workers/');
	}

	private function get_data() {
		$this->workers = [];
		$data = \Workers::data();
		foreach($data as $item) {
			if (!\Sessions::checkPermission(\Workers::PERMISSION_ACCESS, $item['id'], READ)) continue;
			$this->workers[$item['id']] = $item;
		}
	}

	private function print_header() {
		?>
		<div class="float-start"><h2><i class="bi bi-hdd-network"></i> <?=\T::Worker_PageTitle();?></h2></div>
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
						<th scope="col" class="align-middle" data-priority="2"><?=\T::Worker_Table_Title();?></th>
						<th scope="col" class="align-middle" data-priority="4"><?=\T::Worker_Table_LastActiveTime();?></th>
						<th scope="col" class="align-middle text-center" data-priority="6"><?=\T::Worker_Table_CreateTime();?></th>
						<th scope="col" class="align-middle text-center" data-priority="5"><?=\T::Worker_Table_UpdateTime();?></th>
						<th scope="col" class="align-middle text-center" data-priority="3"><?=\T::Worker_Table_Actions();?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ($this->workers as $item) {
						$online = false;
						if (isset($item['last_active_time']) && $item['last_active_time'] + 3*60 > time()) {
							$online = true;
						}
						$params = [
							'id' => intval($item['id'] ?? 0),
							'title' => htmlspecialchars(trim($item['title'] ?? '')),
							'last_active_time' => isset($item['last_active_time']) && $item['last_active_time'] > 0 ? date("Y-m-d H:i:s", $item['last_active_time']) : '',
							'create_time' => isset($item['create_time']) && $item['create_time'] > 0 ? date("Y-m-d H:i:s", $item['create_time']) : '',
							'update_time' => isset($item['update_time']) && $item['update_time'] > 0 ? date("Y-m-d H:i:s", $item['update_time']) : '',
						];

						$can_read = \Sessions::checkPermission(\Workers::PERMISSION_ACCESS, $params['id'], READ);
						if (!$can_read) continue;
						$can_write = \Sessions::checkPermission(\Workers::PERMISSION_ACCESS, $params['id'], WRITE);
						$can_delete = \Sessions::checkPermission(\Workers::PERMISSION_ACCESS, $params['id'], DELETE);
						?>
						<tr>
							<th scope="row" class="align-middle"><?=$params['id'];?></th>
							<td class="align-middle">
								<?php
								if ($can_write) {
									?>
									<span class="d-inline pointer text-info editActionButton" data-object-id="<?=$params['id'];?>">
										<?=$params['title'];?>
									</span>
									<?php
								} else {
									?>
									<span class="d-inline">
										<?=$params['title'];?>
									</span>
									<?php
								}
								?>
							</td>
							<td class="align-middle text-center"><?=($online ? '<span class="text-success">Online</span>: ':'<span class="text-danger">Offline</span>: ');?><?=$params['last_active_time'];?></td>
							<td class="align-middle text-center"><?=$params['create_time'];?></td>
							<td class="align-middle text-center"><?=$params['update_time'];?></td>
							<td class="align-middle text-center">
								<?php if ($this->can_delete_global) {?>
									<?php
									if ($can_delete) {
										?>
										<i class="bi bi-trash fs-4 text-danger pointer deleteActionButton"
											data-object-id = "<?=$params['id'];?>"
											data-object-title = "<?=addslashes($params['title']);?>"
											title="<?=\T::Worker_Table_Delete();?>"
										></i>
										<?php
									} else {
										?><i class="bi bi-trash fs-4 text-secondary" title="<?=\T::Worker_Table_Delete();?>"></i><?php
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
					<h5 class="modal-title" id="deleteModalLabel"><?=\T::Worker_Delete_Title();?></h5>
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
					window.location.href = '/workers/edit/';
				});

				$('.editActionButton').on('click', function() {
					window.location.href = '/workers/edit/' + $(this).data('object-id') + '/';
				});

				$('.deleteActionButton').on('click', function() {
					showDeleteModal($(this).data('object-id'), $(this).data('object-title'));
				});

			});
			function showDeleteModal(objectId, objectTitle) {
				const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
				const modalBody = document.getElementById('deleteModalBody');
				const confirmBtn = document.getElementById('confirmDeleteBtn');

				modalBody.textContent = '<?=\T::Worker_Delete_Confirmation();?>'.replace('{title}', objectTitle);

				confirmBtn.onclick = function() {
					window.location.href = '/workers/delete/' + objectId + '/';
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

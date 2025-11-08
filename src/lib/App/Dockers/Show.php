<?php

namespace App\Dockers;

class Show extends \Routing_Parent implements \Routing_Interface {

	private bool $can_read_global = false;
	private bool $can_delete_global = false;
	private bool $can_create = false;

	private array $dockers = [];

	public function Run($action='list', $docker_id=null) {
		$this->check_auth();
		$this->check_permissions();

		if ($action == 'delete' && $docker_id) {
			$this->processDeleteAction($docker_id);
		}

		$this->get_data();

		$this->template = new \Template('Dockers');
		$this->print_header();
		$this->print_table();
		$this->print_modal();
		$this->print_javascript();
	}

	private function check_permissions() {
		$this->can_read_global = \Sessions::checkPermission(\Dockers::PERMISSION_ACCESS, -1, READ);
		$this->can_delete_global = \Sessions::checkPermission(\Dockers::PERMISSION_ACCESS, -1, DELETE);
		$this->can_create = \Sessions::checkPermission(\Dockers::PERMISSION_CREATE, 0, WRITE);

		if (!$this->can_read_global && !$this->can_create) {
			e403();
		}
	}

	private function processDeleteAction(int $docker_id) {
		if (!isset($docker_id)) return;
		if (!$this->can_delete_global) {
			$this->error = \T::Docker_Delete_PermissionDenied();
			return;
		}
		if (!$docker_id) {
			$this->error = \T::Docker_Delete_NotFound();
			return;
		}

		if (!\Sessions::checkPermission(\Dockers::PERMISSION_ACCESS, $docker_id, DELETE)) {
			$this->error = \T::Docker_Delete_PermissionDenied();
			return;
		}
		$docker = \Dockers::get(['id' => $docker_id]);
		if (!$docker) {
			$this->error = \T::Docker_Delete_NotFound();
			return;
		}
		\Dockers::delete(['id' => $docker_id]);
		\Logs::delete_log(\Dockers::LOGS_OBJECT, $docker_id, $docker);
		common_redirect('/dockers/');
	}

	private function get_data() {
		$this->dockers = [];
		$data = \Dockers::data();
		foreach($data as $item) {
			if (!\Sessions::checkPermission(\Dockers::PERMISSION_ACCESS, $item['id'], READ)) continue;
			$this->dockers[$item['id']] = $item;
		}
	}

	private function print_header() {
		?>
		<div class="float-start"><h2><i class="bi bi-box"></i> <?=\T::Docker_PageTitle();?></h2></div>
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
						<th scope="col" class="align-middle" data-priority="2"><?=\T::Docker_Table_Title();?></th>
						<th scope="col" class="align-middle" data-priority="4"><?=\T::Docker_Table_Status();?></th>
						<th scope="col" class="align-middle" data-priority="5"><?=\T::Docker_Table_Containers();?></th>
						<th scope="col" class="align-middle" data-priority="7"><?=\T::Docker_Table_Host();?></th>
						<th scope="col" class="align-middle" data-priority="8"><?=\T::Docker_Table_Port();?></th>
						<th scope="col" class="align-middle" data-priority="9"><?=\T::Docker_Table_Tls();?></th>
						<th scope="col" class="align-middle text-center" data-priority="10"><?=\T::Docker_Table_CreateTime();?></th>
						<th scope="col" class="align-middle text-center" data-priority="6"><?=\T::Docker_Table_UpdateTime();?></th>
						<th scope="col" class="align-middle text-center" data-priority="3"><?=\T::Docker_Table_Actions();?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ($this->dockers as $item) {
						$params = [
							'id' => intval($item['id'] ?? 0),
							'title' => htmlspecialchars(trim($item['title'] ?? '')),
							'status' => intval($item['status'] ?? 0) == 1 ? '<i class="bi bi-check-lg fs-4 text-success"></i>':'<i class="bi bi-x-lg fs-4 text-danger"></i>',
							'tls' => intval($item['tls'] ?? 0) == 1 ? '<i class="bi bi-check-lg fs-4 text-success"></i>':'<i class="bi bi-x-lg fs-4 text-danger"></i>',
							'host' => htmlspecialchars(trim($item['host'] ?? '')),
							'port' => intval($item['port'] ?? 0),
							'create_time' => isset($item['create_time']) && $item['create_time'] > 0 ? date("Y-m-d H:i:s", $item['create_time']) : '',
							'update_time' => isset($item['update_time']) && $item['update_time'] > 0 ? date("Y-m-d H:i:s", $item['update_time']) : '',
							'containers' => '',
						];
						if (isset($item['container_states']) && $item['container_states']) {
							$item['container_states'] = @json_decode($item['container_states']??'[]', true);
							$item['container_states'] ??= [];
							$all_count = 0;
							$running_count = 0;
							foreach($item['container_states'] as $v) {
								$all_count++;
								if ($v['state'] == 'running') {
									$running_count++;
								}
							}
							$params['containers'] = $running_count.'/'.$all_count;
						}

						$can_read = \Sessions::checkPermission(\Dockers::PERMISSION_ACCESS, $params['id'], READ);
						if (!$can_read) continue;
						$can_write = \Sessions::checkPermission(\Dockers::PERMISSION_ACCESS, $params['id'], WRITE);
						$can_delete = \Sessions::checkPermission(\Dockers::PERMISSION_ACCESS, $params['id'], DELETE);
						?>
						<tr>
							<th scope="row" class="align-middle"><?=$params['id'];?></th>
							<td class="align-middle"><span class="d-inline"><?=$params['title'];?></span></td>
							<td class="align-middle text-center"><?=$params['status'];?></td>
							<td class="align-middle text-center"><?=$params['containers'];?></td>
							<td class="align-middle text-center"><?=$params['host'];?></td>
							<td class="align-middle text-center"><?=$params['port'];?></td>
							<td class="align-middle text-center"><?=$params['tls'];?></td>
							<td class="align-middle text-center"><?=$params['create_time'];?></td>
							<td class="align-middle text-center"><?=$params['update_time'];?></td>
							<td class="align-middle text-center">
								<?php if ($can_write) {
									?><i class="bi bi-pencil-square me-2 fs-4 text-info editActionButton pointer" title="<?=\T::Docker_Table_Edit();?>" data-object-id="<?=$params['id'];?>"></i><?php
								} elseif ($can_read) {
									?><i class="bi bi-eye fs-4 me-2 text-info editActionButton pointer" title="<?=\T::Docker_Table_Edit();?>" data-object-id="<?=$params['id'];?>"></i><?php
								} else {
									?><i class="bi bi-pencil-square me-2 fs-4 text-secondary" title="<?=\T::Docker_Table_Edit();?>"></i><?php
								} ?>
								<?php if ($this->can_delete_global) {?>
									<?php
									if ($can_delete) {
										?>
										<i class="bi bi-trash fs-4 me-2 text-danger pointer deleteActionButton"
											data-object-id = "<?=$params['id'];?>"
											data-object-title = "<?=addslashes($params['title']);?>"
											title="<?=\T::Docker_Table_Delete();?>"
										></i>
										<?php
									} else {
										?><i class="bi bi-trash fs-4 text-secondary" title="<?=\T::Docker_Table_Delete();?>"></i><?php
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
					<h5 class="modal-title" id="deleteModalLabel"><?=\T::Docker_Delete_Title();?></h5>
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
					window.location.href = '/dockers/edit/';
				});

				$('.editActionButton').on('click', function() {
					window.location.href = '/dockers/edit/' + $(this).data('object-id') + '/';
				});

				$('.deleteActionButton').on('click', function() {
					showDeleteModal($(this).data('object-id'), $(this).data('object-title'));
				});

			});
			function showDeleteModal(objectId, objectTitle) {
				const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
				const modalBody = document.getElementById('deleteModalBody');
				const confirmBtn = document.getElementById('confirmDeleteBtn');

				modalBody.textContent = '<?=\T::Docker_Delete_Confirmation();?>'.replace('{title}', objectTitle);

				confirmBtn.onclick = function() {
					window.location.href = '/dockers/delete/' + objectId + '/';
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

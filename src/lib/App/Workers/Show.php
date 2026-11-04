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
		$this->processRequest();

		if ($action == 'delete' && $worker_id) {
			\Csrf::requirePost();
			$this->processDeleteAction($worker_id);
		}

		$this->get_data();

		$this->template = new \Template('Workers');
		$this->print_header();
		$this->print_table();
		$this->print_modal();
		$this->print_desired_version_modal();
		$this->print_javascript();
	}

	protected function handlePostData(array $data) {
		if (!$data) {
			return;
		}
		if (!empty($data['saveGlobalDesiredWorkerVersion'])) {
			$this->saveGlobalDesiredWorkerVersion($data);
		}
	}

	/** @param array<string,mixed> $data */
	private function saveGlobalDesiredWorkerVersion(array $data): void {
		if (!$this->can_create && !\Sessions::in_group(\Groups::ADMIN_GROUP_ID, intval(\Sessions::currentUser()['id'] ?? 0))) {
			$this->error = \T::Worker_Edit_PermissionDenied();
			return;
		}
		$version = \AppOptions::normalizeVersion(strval($data['desired_worker_version'] ?? ''));
		\AppOptions::setDesiredWorkerVersion($version);
		foreach (\Workers::data() ?: [] as $row) {
			$id = intval($row['id'] ?? 0);
			if ($id < 1) {
				continue;
			}
			\Workers::save([
				'id' => $id,
				'update_requested_at' => 0,
				'update_time' => time(),
				'_mode' => \DB\Common::CSMODE_UPDATE,
			]);
		}
		$this->success = \T::Worker_Update_GlobalSaved();
	}

	private function print_desired_version_modal() {
		$canManage = $this->can_create || \Sessions::in_group(\Groups::ADMIN_GROUP_ID, intval(\Sessions::currentUser()['id'] ?? 0));
		$current = htmlspecialchars(\AppOptions::getDesiredWorkerVersion(), ENT_QUOTES, 'UTF-8');
		?>
		<div class="modal" id="desiredWorkerVersionModal" tabindex="-1" aria-labelledby="desiredWorkerVersionModalLabel">
			<div class="modal-dialog">
				<div class="modal-content">
					<div class="modal-header border-secondary">
						<h5 class="modal-title" id="desiredWorkerVersionModalLabel"><?=\T::Worker_Update_GlobalTitle();?></h5>
						<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
					</div>
					<div class="modal-body">
						<p class="text-muted"><?=\T::Worker_Update_GlobalHint();?></p>
						<?php if ($canManage) { ?>
							<form method="post" id="desiredWorkerVersionForm">
								<?= \Csrf::field(); ?>
								<label class="form-label" for="desired_worker_version"><?=\T::Worker_Update_DesiredVersion();?></label>
								<input type="text" class="form-control" id="desired_worker_version" name="desired_worker_version" value="<?=$current;?>" placeholder="v2.2.9" pattern="v?[0-9].*" />
							</form>
						<?php } else { ?>
							<p class="mb-0"><strong><?=\T::Worker_Update_DesiredVersion();?>:</strong> <?=($current !== '' ? $current : '—');?></p>
						<?php } ?>
					</div>
					<div class="modal-footer border-secondary">
						<button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?=\T::Framework_Common_Cancel();?></button>
						<?php if ($canManage) { ?>
							<button type="submit" class="btn btn-primary" form="desiredWorkerVersionForm" name="saveGlobalDesiredWorkerVersion" value="true"><?=\T::Worker_Update_SaveGlobal();?></button>
						<?php } ?>
					</div>
				</div>
			</div>
		</div>
		<?php
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
		$jobs = \MonitoringJobs::data(['worker_id' => $workerId]);
		foreach($jobs as $job) {
			$log_id = \Logs::delete_log(\MonitoringJobs::LOGS_OBJECT, $job['id'], $job);
			if ($log_id) {
				\Logs::add_tag($log_id, \Monitors::LOGS_OBJECT, $job['monitor_id']);
				\Logs::add_tag($log_id, \Workers::LOGS_OBJECT, $job['worker_id']);
			}
			\MonitoringJobs::delete(['id' => $job['id']]);
		}
		$jobs = \MonitoringDockers::data(['worker_id' => $workerId]);
		foreach($jobs as $job) {
			$log_id = \Logs::delete_log(\MonitoringDockers::LOGS_OBJECT, $job['id'], $job);
			if ($log_id) {
				\Logs::add_tag($log_id, \Workers::LOGS_OBJECT, $job['worker_id']);
				\Logs::add_tag($log_id, \Dockers::LOGS_OBJECT, $job['docker_id']);
			}
			\MonitoringDockers::delete(['id' => $job['id']]);
		}
		\MonitorsEvents::delete(['worker_id' => $workerId]);
		\MonitoringLogs::delete(['worker_id' => $workerId]);
		\MonitoringLogsAggregate::delete(['worker_id' => $workerId]);
		\Workers::delete(['id' => $workerId]);
		$log_id = \Logs::delete_log(\Workers::LOGS_OBJECT, $workerId, $worker);
		common_redirect('/admin/workers/');
	}

	private function get_data() {
		$this->workers = [];
		$data = \Workers::data();
		$worker_ids = array_map(static fn($item) => intval($item['id'] ?? 0), $data);
		$jobs_count_hash = \MonitoringJobs::countMonitorsByWorkerIds($worker_ids);
		$dockers_count_hash = \MonitoringDockers::countMonitorsByWorkerIds($worker_ids);
		foreach($data as $item) {
			if (!\Sessions::checkPermission(\Workers::PERMISSION_ACCESS, $item['id'], READ)) continue;
			$item['monitors_count'] = intval($jobs_count_hash[$item['id']] ?? 0) + intval($dockers_count_hash[$item['id']] ?? 0);
			$this->workers[$item['id']] = $item;
		}
	}

	private function print_header() {
		$current = \AppOptions::getDesiredWorkerVersion();
		$currentLabel = $current !== '' ? $current : '—';
		?>
		<div class="float-start"><h2><i class="bi bi-hdd-network"></i> <?=\T::Worker_PageTitle();?></h2></div>
		<div class="float-end d-flex align-items-center gap-3">
			<button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#desiredWorkerVersionModal" title="<?=\T::Worker_Update_GlobalTitle();?>">
				<i class="bi bi-tags"></i> <?=\T::Worker_Update_OpenButton();?>
				<span class="badge text-bg-secondary ms-1"><?=htmlspecialchars($currentLabel, ENT_QUOTES, 'UTF-8');?></span>
			</button>
			<?php if ($this->can_create) { ?>
				<h3 class="pointer text-info mb-0">
					<i class="bi bi-plus-circle createActionButton"> <?=\T::Framework_Common_Create();?></i>
				</h3>
			<?php } ?>
		</div>
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
						<th scope="col" class="align-middle text-center" data-priority="4"><?=\T::Monitor_PageTitle();?></th>
						<th scope="col" class="align-middle" data-priority="4"><?=\T::Worker_Table_LastActiveTime();?></th>

						<th scope="col" class="align-middle text-center" data-priority="6"><?=\T::Worker_Table_ProtocolVersion();?></th>
						<th scope="col" class="align-middle text-center" data-priority="5"><?=\T::Worker_Table_WorkerVersion();?></th>

						<th scope="col" class="align-middle text-center" data-priority="8"><?=\T::Worker_Table_CreateTime();?></th>
						<th scope="col" class="align-middle text-center" data-priority="7"><?=\T::Worker_Table_UpdateTime();?></th>
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
						$currentVer = trim(strval($item['worker_version'] ?? ''));
						$desiredVer = \Workers::effectiveDesiredWorkerVersion($item);
						$needsUpdate = \Workers::workerNeedsUpdate($item);
						$updateError = trim(strval($item['update_error'] ?? ''));
						$versionLabel = htmlspecialchars($currentVer !== '' ? $currentVer : '—', ENT_QUOTES, 'UTF-8');
						if ($desiredVer !== '' && $needsUpdate) {
							$versionLabel .= ' <span class="badge text-bg-warning">→ '.htmlspecialchars($desiredVer, ENT_QUOTES, 'UTF-8').'</span>';
						} elseif ($desiredVer !== '' && !$needsUpdate) {
							$versionLabel .= ' <span class="badge text-bg-success">ok</span>';
						}
						if ($updateError !== '') {
							$versionLabel .= ' <i class="bi bi-exclamation-triangle text-danger" title="'.htmlspecialchars($updateError, ENT_QUOTES, 'UTF-8').'"></i>';
						}
						$params = [
							'id' => intval($item['id'] ?? 0),
							'title' => htmlspecialchars(trim($item['title'] ?? '')),
							'protocol_version' => htmlspecialchars(trim($item['protocol_version'] ?? '')),
							'worker_version' => $versionLabel,
							'monitors_count' => intval($item['monitors_count'] ?? 0),
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
							<td class="align-middle text-center"><?=$params['monitors_count'];?></td>
							<td class="align-middle text-center"><?=($online ? '<span class="text-success">Online</span>: ':'<span class="text-danger">Offline</span>: ');?><?=$params['last_active_time'];?></td>
							<td class="align-middle text-center"><?=$params['protocol_version'];?></td>
							<td class="align-middle text-center"><?=$params['worker_version'];?></td>
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
					window.location.href = '/admin/workers/edit/';
				});

				$('.editActionButton').on('click', function() {
					window.location.href = '/admin/workers/edit/' + $(this).data('object-id') + '/';
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
					<?=\Csrf::jsPostToUrl("'/admin/workers/delete/' + objectId + '/'");?>
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

<?php

namespace App\Servers;

class Show extends \Routing_Parent implements \Routing_Interface {

	private bool $can_read_global = false;
	private bool $can_delete_global = false;
	private bool $can_create = false;

	/** @var array<int,array<string,mixed>> */
	private array $servers = [];

	public function Run($action = 'list', $server_id = null) {
		$this->check_auth();
		$this->check_permissions();
		$this->processRequest();

		if ($action == 'delete' && $server_id) {
			\Csrf::requirePost();
			$this->processDeleteAction(intval($server_id));
		}

		$this->get_data();

		$this->template = new \Template('Servers');
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
		if (!empty($data['saveGlobalDesiredAgentVersion'])) {
			$this->saveGlobalDesiredAgentVersion($data);
		}
	}

	/** @param array<string,mixed> $data */
	private function saveGlobalDesiredAgentVersion(array $data): void {
		if (!$this->can_create && !\Sessions::in_group(\Groups::ADMIN_GROUP_ID, intval(\Sessions::currentUser()['id'] ?? 0))) {
			$this->error = \T::Server_Edit_PermissionDenied();
			return;
		}
		$version = \AppOptions::normalizeVersion(strval($data['desired_agent_version'] ?? ''));
		\AppOptions::setDesiredAgentVersion($version);
		// Reset cooldown so next reports pick up the new target.
		foreach (\Servers::data() ?: [] as $row) {
			$id = intval($row['id'] ?? 0);
			if ($id < 1) {
				continue;
			}
			\Servers::save([
				'id' => $id,
				'update_requested_at' => 0,
				'update_time' => time(),
				'_mode' => \DB\Common::CSMODE_UPDATE,
			]);
		}
		$this->success = \T::Server_Update_GlobalSaved();
	}

	private function check_permissions() {
		$this->can_read_global = \Sessions::checkPermission(\Servers::PERMISSION_ACCESS, -1, READ);
		$this->can_delete_global = \Sessions::checkPermission(\Servers::PERMISSION_ACCESS, -1, DELETE);
		$this->can_create = \Sessions::checkPermission(\Servers::PERMISSION_CREATE, 0, WRITE);

		if (!$this->can_read_global && !$this->can_create) {
			e403();
		}
	}

	private function processDeleteAction(int $server_id) {
		if (!$this->can_delete_global) {
			$this->error = \T::Server_Delete_PermissionDenied();
			return;
		}
		if (!$server_id) {
			$this->error = \T::Server_Delete_NotFound();
			return;
		}
		if (!\Sessions::checkPermission(\Servers::PERMISSION_ACCESS, $server_id, DELETE)) {
			$this->error = \T::Server_Delete_PermissionDenied();
			return;
		}
		$server = \Servers::get(['id' => $server_id]);
		if (!$server) {
			$this->error = \T::Server_Delete_NotFound();
			return;
		}
		foreach (\Monitors::data(['server' => $server_id]) ?: [] as $monitor) {
			$mid = intval($monitor['id']);
			foreach (\MonitoringJobs::data(['monitor_id' => $mid]) ?: [] as $job) {
				\MonitoringJobs::delete(['id' => intval($job['id'])]);
			}
			foreach (\MonitoringNotify::data(['monitor_id' => $mid]) ?: [] as $job) {
				\MonitoringNotify::delete(['id' => intval($job['id'])]);
			}
			\MonitorsEvents::delete(['monitor_id' => $mid]);
			\MonitoringLogs::delete(['monitor_id' => $mid]);
			\MonitoringSubscribe::delete(['monitor_id' => $mid]);
			\Monitors::delete(['id' => $mid]);
			\Logs::delete_log(\Monitors::LOGS_OBJECT, $mid, $monitor);
		}
		\ServerMetrics::delete(['server_id' => $server_id]);
		\ServerAuthEvents::delete(['server_id' => $server_id]);
		\Servers::delete(['id' => $server_id]);
		\Logs::delete_log(\Servers::LOGS_OBJECT, $server_id, $server);
		common_redirect('/admin/servers/');
	}

	private function get_data() {
		$this->servers = [];
		foreach (\Servers::data() ?: [] as $item) {
			if (!\Sessions::checkPermission(\Servers::PERMISSION_ACCESS, $item['id'], READ)) {
				continue;
			}
			$this->servers[intval($item['id'])] = $item;
		}
	}

	private function print_header() {
		$current = \AppOptions::getDesiredAgentVersion();
		$currentLabel = $current !== '' ? $current : '—';
		?>
		<div class="float-start"><h2><i class="bi bi-hdd-rack"></i> <?=\T::Server_PageTitle();?></h2></div>
		<div class="float-end d-flex align-items-center gap-3">
			<button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#desiredAgentVersionModal" title="<?=\T::Server_Update_GlobalTitle();?>">
				<i class="bi bi-tags"></i> <?=\T::Server_Update_OpenButton();?>
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

	private function print_desired_version_modal() {
		$canManage = $this->can_create || \Sessions::in_group(\Groups::ADMIN_GROUP_ID, intval(\Sessions::currentUser()['id'] ?? 0));
		$current = htmlspecialchars(\AppOptions::getDesiredAgentVersion(), ENT_QUOTES, 'UTF-8');
		?>
		<div class="modal" id="desiredAgentVersionModal" tabindex="-1" aria-labelledby="desiredAgentVersionModalLabel">
			<div class="modal-dialog">
				<div class="modal-content">
					<div class="modal-header border-secondary">
						<h5 class="modal-title" id="desiredAgentVersionModalLabel"><?=\T::Server_Update_GlobalTitle();?></h5>
						<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
					</div>
					<div class="modal-body">
						<p class="text-muted"><?=\T::Server_Update_GlobalHint();?></p>
						<?php if ($canManage) { ?>
							<form method="post" id="desiredAgentVersionForm">
								<?= \Csrf::field(); ?>
								<label class="form-label" for="desired_agent_version"><?=\T::Server_Update_DesiredVersion();?></label>
								<input type="text" class="form-control" id="desired_agent_version" name="desired_agent_version" value="<?=$current;?>" placeholder="v1.0.1" pattern="v?[0-9].*" />
							</form>
						<?php } else { ?>
							<p class="mb-0"><strong><?=\T::Server_Update_DesiredVersion();?>:</strong> <?=($current !== '' ? $current : '—');?></p>
						<?php } ?>
					</div>
					<div class="modal-footer border-secondary">
						<button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?=\T::Framework_Common_Cancel();?></button>
						<?php if ($canManage) { ?>
							<button type="submit" class="btn btn-primary" form="desiredAgentVersionForm" name="saveGlobalDesiredAgentVersion" value="true"><?=\T::Server_Update_SaveGlobal();?></button>
						<?php } ?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	private function print_table() {
		?>
		<div class="row d-flex justify-content-center">
			<table class="table table-transparent table-responsive" id="Table">
				<thead>
					<tr>
						<th scope="col" class="align-middle" data-priority="1">ID</th>
						<th scope="col" class="align-middle" data-priority="2"><?=\T::Server_Table_Title();?></th>
						<th scope="col" class="align-middle" data-priority="4"><?=\T::Server_Table_Online();?></th>
						<th scope="col" class="align-middle" data-priority="5"><?=\T::Server_Table_Hostname();?></th>
						<th scope="col" class="align-middle" data-priority="6">CPU</th>
						<th scope="col" class="align-middle" data-priority="7">RAM</th>
						<th scope="col" class="align-middle" data-priority="8">Disk</th>
						<th scope="col" class="align-middle" data-priority="9"><?=\T::Server_Table_Load();?></th>
						<th scope="col" class="align-middle" data-priority="10"><?=\T::Server_Table_Ips();?></th>
						<th scope="col" class="align-middle" data-priority="11"><?=\T::Server_Table_AgentVersion();?></th>
						<th scope="col" class="align-middle text-center" data-priority="12"><?=\T::Server_Table_LastSeen();?></th>
						<th scope="col" class="align-middle text-center" data-priority="3"><?=\T::Server_Table_Actions();?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ($this->servers as $item) {
					$id = intval($item['id']);
					$online = \Servers::isOnline($item);
					$ips = [];
					if (!empty($item['ips'])) {
						$decoded = @json_decode(strval($item['ips']), true);
						if (is_array($decoded)) {
							$ips = $decoded;
						}
					}
					$can_write = \Sessions::checkPermission(\Servers::PERMISSION_ACCESS, $id, WRITE);
					$can_delete = \Sessions::checkPermission(\Servers::PERMISSION_ACCESS, $id, DELETE);
					$title = htmlspecialchars(trim(strval($item['title'] ?? '')));
					$currentVer = trim(strval($item['agent_version'] ?? ''));
					$desiredVer = \Servers::effectiveDesiredAgentVersion($item);
					$needsUpdate = \Servers::agentNeedsUpdate($item);
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
					?>
					<tr>
						<th scope="row" class="align-middle"><?=$id;?></th>
						<td class="align-middle"><?=$title;?></td>
						<td class="align-middle text-center" data-order="<?=$online ? '1' : '0';?>">
							<?=$online
								? '<i class="bi bi-circle-fill text-success" title="online"></i>'
								: '<i class="bi bi-circle-fill text-danger" title="offline"></i>';?>
						</td>
						<td class="align-middle"><?=htmlspecialchars(strval($item['hostname'] ?? ''));?></td>
						<td class="align-middle text-center"><?=number_format(floatval($item['cpu_usage_pct'] ?? 0), 1);?>%</td>
						<td class="align-middle text-center"><?=number_format(floatval($item['mem_used_pct'] ?? 0), 1);?>%</td>
						<td class="align-middle text-center"><?=number_format(floatval($item['disk_used_pct_max'] ?? 0), 1);?>%</td>
						<td class="align-middle text-center"><?=number_format(floatval($item['load1'] ?? 0), 2);?></td>
						<td class="align-middle"><small><?=htmlspecialchars(implode(', ', array_slice($ips, 0, 3)));?></small></td>
						<td class="align-middle text-center"><?=$versionLabel;?></td>
						<td class="align-middle text-center"><?= !empty($item['last_seen_time']) ? \UserTime::format(intval($item['last_seen_time'])) : '';?></td>
						<td class="align-middle text-center">
							<?php if ($can_write) { ?>
								<i class="bi bi-pencil-square me-2 fs-4 text-info editActionButton pointer" data-object-id="<?=$id;?>" title="<?=\T::Server_Table_Edit();?>"></i>
							<?php } else { ?>
								<i class="bi bi-eye me-2 fs-4 text-info editActionButton pointer" data-object-id="<?=$id;?>" title="<?=\T::Server_Table_Edit();?>"></i>
							<?php } ?>
							<?php if ($this->can_delete_global && $can_delete) { ?>
								<i class="bi bi-trash fs-4 me-2 text-danger pointer deleteActionButton"
									data-object-id="<?=$id;?>"
									data-object-title="<?=addslashes($title);?>"
									title="<?=\T::Server_Table_Delete();?>"></i>
							<?php } ?>
						</td>
					</tr>
				<?php } ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private function print_modal() {
		?>
		<div class="modal" id="deleteModal" tabindex="-1">
			<div class="modal-dialog">
				<div class="modal-content">
					<div class="modal-header border-secondary">
						<h5 class="modal-title"><?=\T::Server_Delete_Title();?></h5>
						<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
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
					window.location.href = '/admin/servers/edit/';
				});
				$('.editActionButton').on('click', function() {
					window.location.href = '/admin/servers/edit/' + $(this).data('object-id') + '/';
				});
				$('.deleteActionButton').on('click', function() {
					const objectId = $(this).data('object-id');
					const objectTitle = $(this).data('object-title');
					const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
					document.getElementById('deleteModalBody').textContent =
						'<?=\T::Server_Delete_Confirmation();?>'.replace('{title}', objectTitle);
					document.getElementById('confirmDeleteBtn').onclick = function() {
						<?=\Csrf::jsPostToUrl("'/admin/servers/delete/' + objectId + '/'");?>
					};
					modal.show();
				});
			});
		</script>
		<?php
	}
}

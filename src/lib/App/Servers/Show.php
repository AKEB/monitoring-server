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

		if ($action == 'delete' && $server_id) {
			\Csrf::requirePost();
			$this->processDeleteAction(intval($server_id));
		}

		$this->get_data();

		$this->template = new \Template('Servers');
		$this->print_header();
		$this->print_table();
		$this->print_modal();
		$this->print_javascript();
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
		?>
		<div class="float-start"><h2><i class="bi bi-hdd-rack"></i> <?=\T::Server_PageTitle();?></h2></div>
		<?php if ($this->can_create) { ?>
			<div class="float-end">
				<h3 class="pointer text-info">
					<i class="bi bi-plus-circle createActionButton"> <?=\T::Framework_Common_Create();?></i>
				</h3>
			</div>
		<?php } ?>
		<div class="clearfix"></div>
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
					?>
					<tr>
						<th scope="row" class="align-middle"><?=$id;?></th>
						<td class="align-middle"><?=$title;?></td>
						<td class="align-middle text-center">
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
						<td class="align-middle text-center"><?=htmlspecialchars(strval($item['agent_version'] ?? ''));?></td>
						<td class="align-middle text-center"><?= !empty($item['last_seen_time']) ? date('Y-m-d H:i:s', intval($item['last_seen_time'])) : '';?></td>
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

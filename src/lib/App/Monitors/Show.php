<?php

namespace App\Monitors;

class Show extends \Routing_Parent implements \Routing_Interface {

	private bool $can_read_global = false;
	private bool $can_delete_global = false;
	private bool $can_create = false;

	private int $parent_id = 0;
	private array $monitors = [];
	private array $breadcrumb = [];

	public function Run($action='list', $monitor_id=null, $parent_id=null) {
		$this->check_auth();
		$this->check_permissions();

		$this->parent_id = intval($parent_id);
		// if (!isset($parent_id)) {
		// 	common_redirect('/monitors/0/');
		// }

		if ($action == 'delete' && $monitor_id) {
			$this->processDeleteAction($monitor_id);
		} elseif ($action == 'copy' && $monitor_id) {
			$this->processCopyAction($monitor_id);
		}

		$this->get_data();
		$this->get_breadcrumb();

		$this->template = new \Template();
		$this->print_header();
		$this->print_breadcrumb();
		$this->print_table();
		$this->print_modal();
		$this->print_javascript();
	}

	private function check_permissions() {
		$this->can_read_global = \Sessions::checkPermission(\Monitors::PERMISSION_ACCESS, -1, READ);
		$this->can_delete_global = \Sessions::checkPermission(\Monitors::PERMISSION_ACCESS, -1, DELETE);
		$this->can_create = \Sessions::checkPermission(\Monitors::PERMISSION_CREATE, 0, WRITE);

		if (!$this->can_read_global && !$this->can_create) {
			e403();
		}
	}

	private function processCopyAction(int $monitorId) {
		if (!isset($monitorId)) return;
		if (!$this->can_read_global) {
			$this->error = \T::Monitor_Copy_PermissionDenied();
			return;
		}
		if (!$monitorId) {
			$this->error = \T::Monitor_Copy_MonitorNotFound();
			return;
		}

		if (!\Sessions::checkPermission(\Monitors::PERMISSION_ACCESS, $monitorId, READ)) {
			$this->error = \T::Monitor_Copy_PermissionDenied();
			return;
		}
		$monitor = \Monitors::get(['id' => $monitorId]);
		if (!$monitor) {
			$this->error = \T::Monitor_Copy_MonitorNotFound();
			return;
		}
		$newMonitor = $monitor;
		unset($newMonitor['id']);
		$newMonitor['title'] .= ' (copy)';
		$newMonitor['create_time'] = time();
		$newMonitor['update_time'] = time();
		$newMonitor['id'] = \Monitors::save($newMonitor);
		if ($newMonitor['id']) {
			\Logs::create_log(\Monitors::LOGS_OBJECT, $newMonitor['id'], $newMonitor);
			$jobs = \Jobs::data(['monitor_id'=>$monitorId]);
			foreach($jobs as $job) {
				unset($job['id']);
				$job['monitor_id'] = $newMonitor['id'];
				$job['create_time'] = time();
				$job['update_time'] = time();
				$job['id'] = \Jobs::save($job);
				$log_id = \Logs::create_log(\Jobs::LOGS_OBJECT, $job['id'], $job);
				\Logs::add_tag($log_id, \Monitors::LOGS_OBJECT, $newMonitor['id']);
			}
			common_redirect('/monitors/'.($this->parent_id ? $this->parent_id.'/':'').'edit/'.$newMonitor['id'].'/');
		}
	}

	private function processDeleteAction(int $monitorId) {
		if (!isset($monitorId)) return;
		if (!$this->can_delete_global) {
			$this->error = \T::Monitor_Delete_PermissionDenied();
			return;
		}
		if (!$monitorId) {
			$this->error = \T::Monitor_Delete_MonitorNotFound();
			return;
		}

		if (!\Sessions::checkPermission(\Monitors::PERMISSION_ACCESS, $monitorId, DELETE)) {
			$this->error = \T::Monitor_Delete_PermissionDenied();
			return;
		}
		$monitor = \Monitors::get(['id' => $monitorId]);
		if (!$monitor) {
			$this->error = \T::Monitor_Delete_MonitorNotFound();
			return;
		}
		if ($monitor['type'] == \Monitors::TYPE_FOLDER) {
			$parent_id = intval($monitor['parent_id']);
			$children = \Monitors::data(false, sql_pholder(' AND parent_id = ? ', $monitor['id']));
			foreach($children as $child) {
				$new_child = [
					'id' => $child['id'],
					'parent_id' => $parent_id,
					'update_time' => time(),
				];
				\Monitors::save($new_child);
				\Logs::update_log(\Monitors::LOGS_OBJECT, $child['id'], $child, \array_merge($child, $new_child));
			}
			// $child_count = \Monitors::count(false, sql_pholder(' AND parent_id = ? ', $monitor['id']));
			// if ($child_count) {
			// 	$this->error = \T::Monitor_Delete_MonitorFolderNotEmpty();
			// 	return;
			// }
		}

		\Monitors::delete(['id' => $monitorId]);
		\Logs::delete_log(\Monitors::LOGS_OBJECT, $monitorId, $monitor);
		common_redirect('/monitors/'.($this->parent_id ? $this->parent_id.'/':''));
	}

	private function get_data() {
		$this->monitors = [];
		$sql = sql_pholder(' AND parent_id=?', $this->parent_id);
		$data = \Monitors::data(false, $sql);
		foreach($data as $item) {
			if ($item['type'] != \Monitors::TYPE_FOLDER && !\Sessions::checkPermission(\Monitors::PERMISSION_ACCESS, $item['id'], READ)) continue;
			$this->monitors[$item['id']] = $item;
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

	private function print_breadcrumb() {
		?>
		<nav aria-label="breadcrumb">
			<ol class="breadcrumb">
				<?php
				$last_index = count($this->breadcrumb) - 1;
				if ($last_index > 0) {
					foreach($this->breadcrumb as $k=>$item) {
						echo '<li class="breadcrumb-item'.($k == $last_index ? ' active" aria-current="page':'').'">'.($k != $last_index ? '<a href="/monitors/'.($item['id'] ? $item['id'].'/':'').'">' : '').htmlspecialchars($item['title']).($k != $last_index ? '</a>' : '').'</li>';
					}
				}
				?>
			</ol>
			</nav>
		<?php
	}

	private function print_table() {
		?>
		<div class="row d-flex justify-content-center">
			<table class="table table-transparent table-responsive" id="Table">
				<thead class="">
					<tr>
						<th scope="col" class="align-middle" data-priority="1">ID</th>
						<th scope="col" class="align-middle" data-priority="2"><?=\T::Monitor_Table_Title();?></th>
						<th scope="col" class="align-middle" data-priority="4"><?=\T::Monitor_Table_Status();?></th>
						<th scope="col" class="align-middle" data-priority="5"><?=\T::Monitor_Table_Url();?></th>
						<th scope="col" class="align-middle text-center" data-priority="7"><?=\T::Monitor_Table_CreateTime();?></th>
						<th scope="col" class="align-middle text-center" data-priority="6"><?=\T::Monitor_Table_UpdateTime();?></th>
						<th scope="col" class="align-middle text-center" data-priority="3"><?=\T::Monitor_Table_Actions();?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ($this->monitors as $item) {
						$params = [
							'id' => intval($item['id'] ?? 0),
							'title' => htmlspecialchars(trim($item['title'] ?? '')),
							'type' => intval($item['type']),
							'status' => intval($item['status'] ?? 0) == 1 ? '<i class="bi bi-check-lg fs-4 text-success"></i>':'<i class="bi bi-x-lg fs-4 text-danger"></i>',
							'url' => strval($item['url']),
							'create_time' => isset($item['create_time']) && $item['create_time'] > 0 ? date("Y-m-d H:i:s", $item['create_time']) : '',
							'update_time' => isset($item['update_time']) && $item['update_time'] > 0 ? date("Y-m-d H:i:s", $item['update_time']) : '',
						];

						$can_read = \Sessions::checkPermission(\Monitors::PERMISSION_ACCESS, $params['id'], READ);
						if (!$can_read) continue;
						$can_write = \Sessions::checkPermission(\Monitors::PERMISSION_ACCESS, $params['id'], WRITE);
						$can_delete = \Sessions::checkPermission(\Monitors::PERMISSION_ACCESS, $params['id'], DELETE);
						?>
						<tr>
							<th scope="row" class="align-middle"><?=$params['id'];?></th>
							<td class="align-middle">
								<?php
								if ($params['type'] == \Monitors::TYPE_FOLDER) {
									?>
									<i class="bi bi-folder fs-4 folderActionButton pointer" data-object-id="<?=$params['id'];?>"></i>
									<?php
								}
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
							<td class="align-middle text-center"><?=$params['status'];?></td>
							<td class="align-middle"><?=$params['url'];?></td>
							<td class="align-middle text-center"><?=$params['create_time'];?></td>
							<td class="align-middle text-center"><?=$params['update_time'];?></td>
							<td class="align-middle text-center">
								<i class="bi bi-copy fs-4 text-info pointer copyActionButton" data-object-id="<?=$params['id'];?>"></i>

								<?php if ($this->can_delete_global) {?>
									<?php
									if ($can_delete) {
										?>
										<i class="bi bi-trash fs-4 text-danger pointer deleteActionButton"
											data-object-id = "<?=$params['id'];?>"
											data-object-title = "<?=addslashes($params['title']);?>"
											title="<?=\T::Monitor_Table_Delete();?>"
										></i>
										<?php
									} else {
										?><i class="bi bi-trash fs-4 text-secondary" title="<?=\T::Monitor_Table_Delete();?>"></i><?php
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
					<h5 class="modal-title" id="deleteModalLabel"><?=\T::Monitor_Delete_Title();?></h5>
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
					window.location.href = '/monitors/<?=$this->parent_id;?>/edit/';
				});

				$('.folderActionButton').on('click', function() {
					window.location.href = '/monitors/' + $(this).data('object-id') + '/';
				});

				$('.editActionButton').on('click', function() {
					window.location.href = '/monitors/<?=$this->parent_id;?>/edit/' + $(this).data('object-id') + '/';
				});

				$('.copyActionButton').on('click', function() {
					window.location.href = '/monitors/<?=$this->parent_id;?>/copy/' + $(this).data('object-id') + '/';
				});

				$('.deleteActionButton').on('click', function() {
					showDeleteModal($(this).data('object-id'), $(this).data('object-title'));
				});

			});
			function showDeleteModal(objectId, objectTitle) {
				const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
				const modalBody = document.getElementById('deleteModalBody');
				const confirmBtn = document.getElementById('confirmDeleteBtn');

				modalBody.textContent = '<?=\T::Monitor_Delete_Confirmation();?>'.replace('{title}', objectTitle);

				confirmBtn.onclick = function() {
					window.location.href = '/monitors/<?=$this->parent_id;?>/delete/' + objectId + '/';
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

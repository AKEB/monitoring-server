<?php

namespace App\Tags;

class Show extends \Routing_Parent implements \Routing_Interface {

	private bool $can_read_global = false;
	private bool $can_delete_global = false;
	private bool $can_create = false;

	/** @var array<int,array> */
	private array $tags = [];

	public function Run($action = 'list', $tag_id = null) {
		$this->check_auth();
		$this->check_permissions();

		if ($action == 'delete' && $tag_id) {
			\Csrf::requirePost();
			$this->processDeleteAction(intval($tag_id));
		}

		$this->get_data();

		$this->template = new \Template('Tags');
		$this->print_header();
		$this->print_table();
		$this->print_modal();
		$this->print_javascript();
	}

	private function check_permissions() {
		$this->can_read_global = \Sessions::checkPermission(\MonitorTags::PERMISSION_ACCESS, -1, READ);
		$this->can_delete_global = \Sessions::checkPermission(\MonitorTags::PERMISSION_ACCESS, -1, DELETE);
		$this->can_create = \Sessions::checkPermission(\MonitorTags::PERMISSION_CREATE, 0, WRITE);

		if (!$this->can_read_global && !$this->can_create) {
			e403();
		}
	}

	private function processDeleteAction(int $tag_id) {
		if (!$this->can_delete_global) {
			$this->error = \T::MonitorTags_Delete_PermissionDenied();
			return;
		}
		if ($tag_id < 1) {
			$this->error = \T::MonitorTags_Delete_NotFound();
			return;
		}
		if (!\Sessions::checkPermission(\MonitorTags::PERMISSION_ACCESS, $tag_id, DELETE)) {
			$this->error = \T::MonitorTags_Delete_PermissionDenied();
			return;
		}
		$tag = \MonitorTags::get(['id' => $tag_id]);
		if (!$tag) {
			$this->error = \T::MonitorTags_Delete_NotFound();
			return;
		}
		$links = \MonitoringMonitorTags::data(['tag_id' => $tag_id]) ?: [];
		foreach ($links as $link) {
			\MonitoringMonitorTags::delete(intval($link['id']));
		}
		\MonitorTags::delete(['id' => $tag_id]);
		\Logs::delete_log(\MonitorTags::LOGS_OBJECT, $tag_id, $tag);
		common_redirect('/admin/tags/');
	}

	private function get_data() {
		$this->tags = [];
		foreach (\MonitorTags::data(false, ' ORDER BY `title` ASC') ?: [] as $item) {
			$id = intval($item['id'] ?? 0);
			if ($id < 1 || !\Sessions::checkPermission(\MonitorTags::PERMISSION_ACCESS, $id, READ)) {
				continue;
			}
			$this->tags[$id] = $item;
		}
	}

	private function print_header() {
		\Ui::print_section_title('bi bi-tags', \T::MonitorTags_PageTitle());
		if ($this->can_create) {
			?>
			<div class="float-end">
				<h3 class="pointer text-info">
					<a class="text-info text-decoration-none" href="/admin/tags/edit/">
						<i class="bi bi-plus-circle"></i> <?=\T::Framework_Common_Create();?>
					</a>
				</h3>
			</div>
			<?php
		}
		echo '<div class="clearfix"></div>';
	}

	private function print_table() {
		?>
		<div class="row d-flex justify-content-center">
			<table class="table table-transparent table-responsive" id="Table">
				<thead>
					<tr>
						<th scope="col" class="align-middle">ID</th>
						<th scope="col" class="align-middle"><?=\T::MonitorTags_Table_Title();?></th>
						<th scope="col" class="align-middle"><?=\T::MonitorTags_Table_Color();?></th>
						<th scope="col" class="align-middle text-center"><?=\T::MonitorTags_Table_Actions();?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ($this->tags as $tag) {
					$id = intval($tag['id']);
					$color = \MonitorTags::normalizeColor(strval($tag['color'] ?? ''));
					$canEdit = \Sessions::checkPermission(\MonitorTags::PERMISSION_ACCESS, $id, WRITE);
					$canDelete = \Sessions::checkPermission(\MonitorTags::PERMISSION_ACCESS, $id, DELETE);
					?>
					<tr>
						<th scope="row"><?=$id;?></th>
						<td>
							<span class="badge rounded-pill text-bg-light border" style="border-color:<?=htmlspecialchars($color, ENT_QUOTES, 'UTF-8');?>!important;color:<?=htmlspecialchars($color, ENT_QUOTES, 'UTF-8');?>;">
								<?=htmlspecialchars(strval($tag['title']), ENT_QUOTES, 'UTF-8');?>
							</span>
						</td>
						<td><code><?=htmlspecialchars($color, ENT_QUOTES, 'UTF-8');?></code>
							<span class="badge rounded-pill ms-1" style="background-color:<?=htmlspecialchars($color, ENT_QUOTES, 'UTF-8');?>;">&nbsp;</span>
						</td>
						<td class="text-center">
							<?php if ($canEdit) { ?>
								<a class="btn btn-sm btn-outline-primary" href="/admin/tags/edit/<?=$id;?>/"><i class="bi bi-pencil"></i></a>
							<?php } ?>
							<?php if ($canDelete) { ?>
								<button type="button" class="btn btn-sm btn-outline-danger deleteActionButton"
									data-id="<?=$id;?>"
									data-title="<?=htmlspecialchars(strval($tag['title']), ENT_QUOTES, 'UTF-8');?>">
									<i class="bi bi-trash"></i>
								</button>
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
		<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
			<div class="modal-dialog">
				<form method="post" id="deleteForm" class="modal-content">
					<?= \Csrf::field(); ?>
					<div class="modal-header">
						<h5 class="modal-title"><?=\T::MonitorTags_Delete_Title();?></h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
					</div>
					<div class="modal-body">
						<p id="deleteConfirmText"></p>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?=\T::Framework_Common_Cancel();?></button>
						<button type="submit" class="btn btn-danger"><?=\T::Framework_Common_Delete();?></button>
					</div>
				</form>
			</div>
		</div>
		<?php
	}

	private function print_javascript() {
		?>
		<script nonce="<?=\CSP::nonceRandom();?>">
		$(function(){
			$('.deleteActionButton').on('click', function(){
				const id = $(this).data('id');
				const title = $(this).data('title');
				$('#deleteForm').attr('action', '/admin/tags/delete/' + id + '/');
				$('#deleteConfirmText').text(<?=json_encode(\T::MonitorTags_Delete_Confirmation(), JSON_UNESCAPED_UNICODE);?>.replace('{title}', title));
				new bootstrap.Modal('#deleteModal').show();
			});
		});
		</script>
		<?php
	}
}

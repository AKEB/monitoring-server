<?php

namespace App\Tags;

class Edit extends \Routing_Parent implements \Routing_Interface {
	private const FIELD_CLASS2 = 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6';

	private int $tag_id = 0;
	private array $tag = [];
	private bool $can_read = false;
	private bool $can_write = false;
	private bool $can_create = false;

	public function Run($tag_id = 0) {
		$this->tag_id = intval($tag_id ?? 0);
		$this->check_auth();
		$this->check_permissions();

		$this->processRequest();
		$this->get_data();
		$this->template = new \Template('Tag edit');
		$this->print_header();
		$this->print_forms();
	}

	protected function handlePostData(array $data) {
		if (!$data) {
			return;
		}
		$this->save_tag($data);
	}

	private function save_tag(array $data) {
		$params = [];
		do {
			if (isset($data['id']) && trim($data['id']) != '') {
				if ($this->tag_id != intval($data['id'])) {
					$this->error = \T::MonitorTags_Edit_NotFound();
					break;
				}
				$params['id'] = intval($data['id']);
			}
			$title = \MonitorTags::normalizeTitle(strval($data['title'] ?? ''));
			if (!\MonitorTags::isTitleValid($title)) {
				$this->error = \T::MonitorTags_Edit_NameLengthError();
				break;
			}
			$params['title'] = $title;
			$params['color'] = \MonitorTags::normalizeColor(strval($data['color'] ?? \MonitorTags::DEFAULT_COLOR));

			$old = [];
			if (!empty($params['id'])) {
				if (!\Sessions::checkPermission(\MonitorTags::PERMISSION_ACCESS, $params['id'], WRITE)) {
					$this->error = \T::MonitorTags_Edit_PermissionDenied();
					break;
				}
				$old = \MonitorTags::get(['id' => $params['id']]);
				if (!$old) {
					$this->error = \T::MonitorTags_Edit_NotFound();
					break;
				}
				$dup = \MonitorTags::get(['title' => $params['title']]);
				if ($dup && intval($dup['id']) !== intval($params['id'])) {
					$this->error = \T::MonitorTags_Edit_DuplicateError();
					break;
				}
				foreach ($params as $k => $v) {
					if (($old[$k] ?? null) == $v) {
						unset($params[$k]);
					}
				}
				if (!$params) {
					$this->success = \T::MonitorTags_Edit_NotingChanged();
					break;
				}
				$params['id'] = $old['id'];
			} else {
				if (!\Sessions::checkPermission(\MonitorTags::PERMISSION_CREATE, 0, WRITE)) {
					$this->error = \T::MonitorTags_Create_PermissionDenied();
					break;
				}
				if (\MonitorTags::get(['title' => $params['title']])) {
					$this->error = \T::MonitorTags_Edit_DuplicateError();
					break;
				}
				$params['create_time'] = time();
			}
			$params['update_time'] = time();

			if (!empty($params['id'])) {
				$params['_mode'] = \DB\Common::CSMODE_UPDATE;
				$id = \MonitorTags::save($params);
				$new = \MonitorTags::get(['id' => $params['id']]);
				\Logs::update_log(\MonitorTags::LOGS_OBJECT, $params['id'], $old, $new, [
					'_save_fields' => ['id', 'title'],
				]);
			} else {
				$params['_mode'] = \DB\Common::CSMODE_INSERT;
				$id = \MonitorTags::save($params);
				if ($id) {
					$new = \MonitorTags::get(['id' => $id]);
					\Logs::create_log(\MonitorTags::LOGS_OBJECT, $id, $new);
					\MonitorTags::grantCreatePermissions($id, intval(\Sessions::currentUser()['id'] ?? 0));
				}
			}
			if (!empty($id)) {
				common_redirect('/admin/tags/edit/'.$id.'/');
			}
			common_redirect('/admin/tags/');
		} while (false);
		foreach ($data as $key => $value) {
			$this->tag[$key] = $value;
		}
	}

	private function check_permissions() {
		if ($this->tag_id) {
			$this->tag = \MonitorTags::get(['id' => $this->tag_id]) ?: [];
			if (!$this->tag) {
				common_redirect('/admin/tags/');
			}
			$this->can_read = \Sessions::checkPermission(\MonitorTags::PERMISSION_ACCESS, $this->tag_id, READ);
			$this->can_write = \Sessions::checkPermission(\MonitorTags::PERMISSION_ACCESS, $this->tag_id, WRITE);
			if (!$this->can_read) {
				e403();
			}
		} else {
			$this->can_read = \Sessions::checkPermission(\MonitorTags::PERMISSION_ACCESS, -1, READ);
			$this->can_create = \Sessions::checkPermission(\MonitorTags::PERMISSION_CREATE, 0, WRITE);
			if (!$this->can_read && !$this->can_create) {
				e403();
			}
		}
	}

	private function get_data() {
		$this->tag['id'] ??= '';
		$this->tag['title'] ??= '';
		$this->tag['color'] ??= \MonitorTags::DEFAULT_COLOR;
		$this->tag['color'] = \MonitorTags::normalizeColor(strval($this->tag['color']));
	}

	private function print_header() {
		\Ui::print_section_title('bi bi-tags', \T::MonitorTags_PageTitle());
		echo '<div class="clearfix"></div>';
	}

	private function print_forms() {
		$readonly = !($this->can_write || $this->can_create);
		$fieldClass = ['class2' => self::FIELD_CLASS2, 'readonly' => $readonly];
		$cardTitle = !empty($this->tag['id'])
			? \T::MonitorTags_Edit_EditTitle(
				htmlspecialchars(strval($this->tag['title'] ?? ''), ENT_QUOTES, 'UTF-8'),
				intval($this->tag['id'])
			)
			: \T::MonitorTags_Edit_CreateTitle();
		?>
		<div class="row d-flex justify-content-center">
			<div class="card bg-transparent col-xl-10 p-4 mt-2 mb-3">
				<div class="card-header bg-transparent"><h3><?=$cardTitle;?></h3></div>
				<?php if (!empty($this->error)) { ?>
					<div class="alert alert-danger mt-3 mb-0"><?=htmlspecialchars($this->error, ENT_QUOTES, 'UTF-8');?></div>
				<?php } ?>
				<?php if (!empty($this->success)) { ?>
					<div class="alert alert-success mt-3 mb-0"><?=htmlspecialchars($this->success, ENT_QUOTES, 'UTF-8');?></div>
				<?php } ?>
				<form class="card-body needs-validation" method="post" novalidate>
					<?= \Csrf::field(); ?>
					<input type="hidden" name="id" value="<?=htmlspecialchars(strval($this->tag['id'] ?? ''), ENT_QUOTES, 'UTF-8');?>"/>
					<?php
					echo $this->template->html_input('title', $this->tag['title'] ?? '', \T::MonitorTags_Edit_Title(), true, $fieldClass);
					echo $this->template->html_input('color', $this->tag['color'] ?? \MonitorTags::DEFAULT_COLOR, \T::MonitorTags_Edit_Color(), false, $fieldClass + [
						'type' => 'color',
					]);
					?>
					<div class="mb-3 row">
						<div class="<?=self::FIELD_CLASS2;?> offset-md-3">
							<small class="text-muted"><?=\T::MonitorTags_Edit_ColorHint();?></small>
							<div class="mt-1">
								<?php foreach (\MonitorTags::color_presets() as $hex => $label) { ?>
									<button type="button" class="btn btn-sm rounded-pill border me-1 mb-1 tag-color-preset"
										data-color="<?=htmlspecialchars($hex, ENT_QUOTES, 'UTF-8');?>"
										style="background-color:<?=htmlspecialchars($hex, ENT_QUOTES, 'UTF-8');?>;color:#fff;"
										<?=$readonly ? 'disabled' : '';?>>
										<?=htmlspecialchars($label, ENT_QUOTES, 'UTF-8');?>
									</button>
								<?php } ?>
							</div>
						</div>
					</div>
					<div class="d-flex flex-row-reverse gap-2">
						<?php if ($this->can_write || $this->can_create) { ?>
							<button type="submit" class="btn btn-primary"><?=!empty($this->tag['id']) ? \T::MonitorTags_Edit_ChangeButton() : \T::MonitorTags_Edit_CreateButton();?></button>
						<?php } ?>
						<a class="btn btn-secondary" href="/admin/tags/"><?=\T::Framework_Common_Cancel();?></a>
					</div>
				</form>
			</div>
		</div>
		<script nonce="<?=\CSP::nonceRandom();?>">
		$(function(){
			$('.tag-color-preset').on('click', function(){
				$('input[name="color"]').val($(this).data('color'));
			});
		});
		</script>
		<?php
	}
}

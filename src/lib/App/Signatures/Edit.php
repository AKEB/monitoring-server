<?php
namespace App\Signatures;

class Edit extends \Routing_Parent implements \Routing_Interface {
	private int $signature_id = 0;
	private array $signature = [];
	private bool $can_read = false;
	private bool $can_write = false;
	private bool $can_create = false;

	public function Run($signature_id=0) {
		$this->signature_id = intval($signature_id ?? 0);
		$this->check_auth();
		$this->check_permissions();

		$this->processRequest();
		$this->get_data();
		$this->template = new \Template('Signature edit');
		$this->print_header();

		$this->print_forms();
		$this->print_javascript();
	}

	protected function handlePostData(array $data) {
		if (!isset($data)) return;
		$this->save_signature($data);
	}

	private function save_signature(array $data) {
		if (!isset($data)) return;
		if (!$data) return;

		$params = [];
		do {
			if (isset($data['id']) && trim($data['id']) != '') {
				$data['id'] = trim($data['id']);
				if ($this->signature_id != intval($data['id'])) {
					$this->error = \T::Signature_Edit_NotFound();
					break;
				}
				$params['id'] = intval($data['id']);
			}
			if (isset($data['project'])) {
				$params['project'] = trim($data['project']);
				if (mb_strlen($params['project']) > 255) {
					$this->error = \T::Signature_Edit_ProjectLengthError();
					break;
				}
			}
			if (isset($data['title']) && trim($data['title']) != '') {
				$data['title'] = trim($data['title']);
				if (mb_strlen($data['title']) < 1 || mb_strlen($data['title']) > 255) {
					$this->error = \T::Signature_Edit_NameLengthError();
					break;
				}
				$params['title'] = $data['title'];
			} else {
				$this->error = \T::Signature_Edit_NameLengthError();
				break;
			}
			if (isset($data['text'])) {
				$params['text'] = trim($data['text']);
			} else {
				$params['text'] = '';
			}
			if (isset($data['status']) && trim($data['status']) != '') {
				$data['status'] = trim($data['status']);
				if ($data['status'] == 'true') {
					$data['status'] = 1;
				} else {
					$data['status'] = 0;
				}
				$params['status'] = intval($data['status']);
			} else {
				$data['status'] = 0;
				$params['status'] = 0;
			}

			$old_signature = [];
			if (isset($params['id']) && $params['id']) {
				if (!\Sessions::checkPermission(\Signatures::PERMISSION_ACCESS, $params['id'], WRITE)) {
					$this->error = \T::Signature_Edit_PermissionDenied();
					break;
				}
				$old_signature = \Signatures::get(['id' => $params['id']]);
				if (!$old_signature) {
					$this->error = \T::Signature_Edit_NotFound();
					break;
				}

				$dup = \Signatures::get([
					'project' => $params['project'] ?? ($old_signature['project'] ?? ''),
					'title' => $params['title'],
				]);
				if ($dup && intval($dup['id']) != intval($params['id'])) {
					$this->error = \T::Signature_Edit_DuplicateError();
					break;
				}

				foreach ($params as $k => $v) {
					if (($old_signature[$k] ?? null) == $v) {
						unset($params[$k]);
					}
				}
				if (!$params) {
					$this->success = \T::Signature_Edit_NotingChanged();
					break;
				}
				$params['id'] = $old_signature['id'];
			} else {
				if (!\Sessions::checkPermission(\Signatures::PERMISSION_CREATE, 0, WRITE)) {
					$this->error = \T::Signature_Create_PermissionDenied();
					break;
				}
				$params['project'] ??= '';
				$dup = \Signatures::get([
					'project' => $params['project'],
					'title' => $params['title'],
				]);
				if ($dup) {
					$this->error = \T::Signature_Edit_DuplicateError();
					break;
				}
				$params['create_time'] = time();
			}
			$params['update_time'] = time();

			$signature_id = 0;
			if (isset($params['id']) && $params['id']) {
				$params['_mode'] = \DB\Common::CSMODE_UPDATE;
				$signature_id = \Signatures::save($params);
				$new_signature = \Signatures::get(['id' => $params['id']]);
				\Logs::update_log(\Signatures::LOGS_OBJECT, $params['id'], $old_signature, $new_signature, [
					'_save_fields' => ['id', 'project', 'title'],
				]);
			} else {
				$params['_mode'] = \DB\Common::CSMODE_INSERT;
				$signature_id = \Signatures::save($params);
				if ($signature_id) {
					$new_signature = \Signatures::get(['id' => $signature_id]);
					\Logs::create_log(\Signatures::LOGS_OBJECT, $signature_id, $new_signature);
					\Signatures::grantCreatePermissions(
						$signature_id,
						intval(\Sessions::currentUser()['id'] ?? 0)
					);
				}
			}
			if ($signature_id) {
				common_redirect('/admin/signatures/edit/' . $signature_id . '/');
			} else {
				common_redirect('/admin/signatures/');
			}
		} while (false);
		foreach ($data as $key => $value) {
			$this->signature[$key] = $value;
		}
	}

	private function check_permissions() {
		if ($this->signature_id) {
			$this->signature = \Signatures::get(['id' => $this->signature_id]);
			if (!$this->signature) {
				common_redirect('/admin/signatures/');
			}
			$this->can_read = \Sessions::checkPermission(\Signatures::PERMISSION_ACCESS, $this->signature_id, READ);
			$this->can_write = \Sessions::checkPermission(\Signatures::PERMISSION_ACCESS, $this->signature_id, WRITE);
			if (!$this->can_read) {
				e403();
			}
		} else {
			$this->can_read = \Sessions::checkPermission(\Signatures::PERMISSION_ACCESS, -1, READ);
			$this->can_create = \Sessions::checkPermission(\Signatures::PERMISSION_CREATE, 0, WRITE);
			if (!$this->can_read && !$this->can_create) {
				e403();
			}
		}
	}

	private function get_data() {
		if (!isset($this->signature['id'])) $this->signature['id'] = '';

		if ($this->signature['id']) {
			$this->signature['create_date'] = (
				isset($this->signature['create_time']) && $this->signature['create_time'] > 0 ?
				date('Y-m-d H:i:s', $this->signature['create_time']) : ''
			);
			$this->signature['update_date'] = (
				isset($this->signature['update_time']) && $this->signature['update_time'] > 0 ?
				date('Y-m-d H:i:s', $this->signature['update_time']) : ''
			);
		} else {
			$this->signature['status'] ??= 1;
			$this->signature['project'] ??= '';
			$this->signature['title'] ??= '';
			$this->signature['text'] ??= '';
		}
	}

	private function print_header() {
		?>
		<div class="float-start"><h2><i class="bi bi-pen"></i> <?=\T::Signature_PageTitle();?></h2></div>
		<div class="clearfix"></div>
		<?php
	}

	private function print_forms() {
		?>
		<div class="row d-flex justify-content-center">
			<div class="card bg-transparent col-xl-10 p-4 mt-2 mb-3">
				<div class="card-header bg-transparent"><h3>
					<?php
					if ($this->signature['id']) {
						echo \T::Signature_Edit_EditTitle(htmlspecialchars($this->signature['title'] ?? '', ENT_QUOTES, 'UTF-8'), $this->signature['id']);
					} else {
						echo \T::Signature_Edit_CreateTitle();
					}
					?>
				</h3></div>
				<form class="card-body needs-validation" method="post" novalidate>
					<input type="hidden" name="id" value="<?=$this->signature['id'];?>"/>
					<?php
					echo $this->template->html_input("project", $this->signature['project']??'', \T::Signature_Edit_Project(), false, [
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'readonly' => !($this->can_write || $this->can_create),
					]);
					$projects = \Signatures::projects_hash(false);
					?>
					<datalist id="signature-projects-list">
						<?php foreach ($projects as $project) { ?>
							<option value="<?=htmlspecialchars($project);?>"></option>
						<?php } ?>
					</datalist>
					<?php
					echo $this->template->html_input("title", htmlspecialchars($this->signature['title'] ?? '', ENT_QUOTES, 'UTF-8'), \T::Signature_Edit_Title(), true, [
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'readonly' => !($this->can_write || $this->can_create),
					]);
					echo $this->template->html_switch("status", intval($this->signature['status']??0), \T::Signature_Edit_Status(), false,[
						'readonly' => !($this->can_write || $this->can_create),
					]);
					echo $this->template->html_input("text", $this->signature['text']??'', \T::Signature_Edit_Text(), true, [
						'type' => 'textarea',
						'rows' => '8',
						'cols' => '60',
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'readonly' => !($this->can_write || $this->can_create),
					]);

					if ($this->signature['id']) {
						echo $this->template->html_input("create_date", $this->signature['create_date']??'', \T::Signature_Edit_CreateTime(), false, ['readonly' => true]);
						echo $this->template->html_input("update_date", $this->signature['update_date']??'', \T::Signature_Edit_UpdateTime(), false, ['readonly' => true]);
					}
					?>
					<?php if ($this->can_write || $this->can_create) { ?>
						<div class="d-flex flex-row-reverse">
							<button type="submit" class="btn btn-primary" name="<?=$this->signature['id'] ? 'editSignature' : 'createSignature';?>" value="true"><?=($this->signature['id'] ? \T::Signature_Edit_ChangeButton() : \T::Signature_Edit_CreateButton());?></button>
						</div>
					<?php } ?>
				</form>
			</div>
		</div>
		<?php
	}

	private function print_javascript() {
		?>
		<script nonce="<?=\CSP::nonceRandom();?>">
			$(document).ready(function(){
				$('#project').attr('list', 'signature-projects-list');
				$('form').on('submit', function(event) {
					$('form').addClass("was-validated");
				});
			});
		</script>
		<?php
	}
}

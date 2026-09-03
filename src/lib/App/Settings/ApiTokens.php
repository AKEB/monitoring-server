<?php

namespace App\Settings;

class ApiTokens extends \Routing_Parent implements \Routing_Interface {

	private array $currentUser = [];
	private array $tokens = [];
	private string $createdPlaintext = '';

	public function Run() {
		$this->check_auth();
		$this->currentUser = \Sessions::currentUser();
		$this->processRequest();
		$this->tokens = \UserApiTokens::listActiveForUser(intval($this->currentUser['id']));
		$this->template = new \Template('API tokens');
		$this->print_header();
		$this->print_created();
		$this->print_create_form();
		$this->print_table();
		$this->print_javascript();
	}

	protected function handlePostData(array $data) {
		if (!isset($data) || !$data) {
			return;
		}
		if (isset($data['createToken'])) {
			$this->createToken($data);
			return;
		}
		if (isset($data['revokeToken'])) {
			$this->revokeToken($data);
		}
	}

	private function createToken(array $data): void {
		$title = trim(strval($data['title'] ?? ''));
		if (mb_strlen($title) < 2 || mb_strlen($title) > 255) {
			$this->error = \T::Api_Settings_NameLengthError();
			return;
		}
		$row = \UserApiTokens::createForUser(intval($this->currentUser['id']), $title);
		if (!$row || empty($row['plaintext'])) {
			$this->error = \T::Api_Settings_CreateError();
			return;
		}
		$this->createdPlaintext = strval($row['plaintext']);
		$this->success = \T::Api_Settings_CreateSuccess();
	}

	private function revokeToken(array $data): void {
		$token_id = intval($data['token_id'] ?? 0);
		if ($token_id < 1) {
			$this->error = \T::Api_Settings_NotFound();
			return;
		}
		if (!\UserApiTokens::revokeForUser(intval($this->currentUser['id']), $token_id)) {
			$this->error = \T::Api_Settings_NotFound();
			return;
		}
		$this->success = \T::Api_Settings_RevokeSuccess();
	}

	private function print_header() {
		?>
		<div class="float-start"><h2><i class="bi bi-key"></i> <?=\T::Api_Settings_PageTitle();?></h2></div>
		<div class="clearfix"></div>
		<p class="text-body-secondary"><?=\T::Api_Settings_DocsHint();?></p>
		<?php
	}

	private function print_created() {
		if ($this->createdPlaintext === '') {
			return;
		}
		?>
		<div class="alert alert-warning" role="alert">
			<div class="mb-2"><?=\T::Api_Settings_CreatedOnce();?></div>
			<div class="input-group">
				<input type="text" class="form-control font-monospace" id="createdApiToken" readonly value="<?=htmlspecialchars($this->createdPlaintext);?>">
				<button type="button" class="btn btn-outline-secondary" id="copyApiToken"><?=\T::Api_Settings_Copy();?></button>
			</div>
		</div>
		<?php
	}

	private function print_create_form() {
		?>
		<div class="row d-flex justify-content-center">
			<div class="card bg-transparent col-xl-10 p-4 mt-2 mb-3">
				<div class="card-header bg-transparent"><h3><?=\T::Api_Settings_CreateTitle();?></h3></div>
				<form class="card-body needs-validation" method="post" novalidate>
					<?php
					echo $this->template->html_input('title', '', \T::Api_Settings_Title(), true, [
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
					]);
					?>
					<div class="d-flex flex-row-reverse">
						<button type="submit" class="btn btn-primary" name="createToken" value="true"><?=\T::Api_Settings_CreateButton();?></button>
					</div>
				</form>
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
						<th scope="col" class="align-middle" data-priority="2"><?=\T::Api_Settings_Title();?></th>
						<th scope="col" class="align-middle text-center" data-priority="4"><?=\T::Api_Settings_Prefix();?></th>
						<th scope="col" class="align-middle text-center" data-priority="5"><?=\T::Api_Settings_LastUsedTime();?></th>
						<th scope="col" class="align-middle text-center" data-priority="6"><?=\T::Api_Settings_CreateTime();?></th>
						<th scope="col" class="align-middle text-center" data-priority="3"><?=\T::Api_Settings_Actions();?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ($this->tokens as $item) {
						$id = intval($item['id'] ?? 0);
						$title = htmlspecialchars(trim($item['title'] ?? ''));
						$prefix = htmlspecialchars(trim($item['token_prefix'] ?? ''));
						$last_used = intval($item['last_used_time'] ?? 0);
						$create_time = intval($item['create_time'] ?? 0);
						?>
						<tr>
							<th scope="row" class="align-middle"><?=$id;?></th>
							<td class="align-middle"><?=$title;?></td>
							<td class="align-middle text-center font-monospace"><?=$prefix;?>…</td>
							<td class="align-middle text-center"><?=$last_used > 0 ? date('Y-m-d H:i:s', $last_used) : '—';?></td>
							<td class="align-middle text-center"><?=$create_time > 0 ? date('Y-m-d H:i:s', $create_time) : '';?></td>
							<td class="align-middle text-center">
								<form method="post" class="d-inline" onsubmit="return confirm('<?=\T::Api_Settings_RevokeConfirmation();?>'.replace('{title}', '<?=addslashes($title);?>'));">
									<input type="hidden" name="token_id" value="<?=$id;?>">
									<button type="submit" class="btn btn-sm btn-outline-danger" name="revokeToken" value="true"><?=\T::Api_Settings_Revoke();?></button>
								</form>
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

	private function print_javascript() {
		?>
		<script nonce="<?=\CSP::nonceRandom();?>">
			$(document).ready(function(){
				if (document.getElementById('Table')) {
					new DataTable('#Table');
				}
				$('#copyApiToken').on('click', function() {
					const input = document.getElementById('createdApiToken');
					if (!input) return;
					navigator.clipboard.writeText(input.value).then(function() {
						showSuccessToast('<?=\T::Api_Settings_Copied();?>');
					});
				});
			});
		</script>
		<?php
	}
}

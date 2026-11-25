<?php

namespace App\StatusPages;

class Edit extends \Routing_Parent implements \Routing_Interface {

	private int $status_page_id = 0;
	private array $page = [];
	private bool $can_write = false;
	private bool $can_create = false;
	/** @var list<array<string,mixed>> */
	private array $incidents = [];

	public function Run($status_page_id = 0) {
		$this->status_page_id = intval($status_page_id ?? 0);
		$this->check_auth();
		$this->check_permissions();
		$this->processRequest();
		$this->get_data();
		$this->template = new \Template('Status page edit');
		$this->print_header();
		$this->print_forms();
	}

	protected function handlePostData(array $data) {
		if (($data['form'] ?? '') === 'incident') {
			$this->save_incident($data);
			return;
		}
		$this->save_page($data);
	}

	private function check_permissions() {
		$this->can_create = \Sessions::checkPermission(\StatusPages::PERMISSION_CREATE, 0, WRITE);
		if ($this->status_page_id > 0) {
			$this->can_write = \Sessions::checkPermission(\StatusPages::PERMISSION_ACCESS, $this->status_page_id, WRITE);
			if (!$this->can_write && !\Sessions::checkPermission(\StatusPages::PERMISSION_ACCESS, $this->status_page_id, READ)) {
				e403();
			}
		} elseif (!$this->can_create) {
			e403();
		} else {
			$this->can_write = true;
		}
	}

	private function get_data() {
		if ($this->status_page_id > 0) {
			$row = \StatusPages::get(['id' => $this->status_page_id]);
			if (!$row) {
				e404();
			}
			$this->page = \Api\V2\StatusPagesService::publicRow($row);
			$this->incidents = \StatusPageIncidents::data(
				['status_page_id' => $this->status_page_id],
				' ORDER BY `id` DESC'
			) ?: [];
		} else {
			$this->page = [
				'title' => '',
				'slug' => '',
				'status' => 1,
				'folder_ids' => [],
				'monitor_ids' => [],
			];
		}
	}

	private function save_page(array $data) {
		if (!$this->can_write && !$this->can_create) {
			$this->error = \T::StatusPages_Edit_PermissionDenied();
			return;
		}
		$user = \Sessions::currentUser();
		$payload = [
			'title' => strval($data['title'] ?? ''),
			'slug' => strval($data['slug'] ?? ''),
			'status' => $data['status'] ?? 0,
			'folder_ids' => $data['folder_ids'] ?? [],
			'monitor_ids' => $data['monitor_ids'] ?? [],
		];
		if ($this->status_page_id > 0) {
			$result = \Api\V2\StatusPagesService::update($user, $this->status_page_id, $payload);
		} else {
			$result = \Api\V2\StatusPagesService::create($user, $payload);
		}
		if (($result['status'] ?? 1) !== 0) {
			$this->error = strval($result['error'] ?? 'Save failed');
			return;
		}
		$id = intval($result['data']['id'] ?? $this->status_page_id);
		common_redirect('/admin/status-pages/edit/'.$id.'/');
	}

	private function save_incident(array $data) {
		if ($this->status_page_id < 1 || !$this->can_write) {
			$this->error = \T::StatusPages_Edit_PermissionDenied();
			return;
		}
		$result = \Api\V2\StatusPagesService::createIncident(\Sessions::currentUser(), $this->status_page_id, [
			'title' => strval($data['incident_title'] ?? ''),
			'message' => strval($data['incident_message'] ?? ''),
			'state' => strval($data['incident_state'] ?? 'investigating'),
			'active' => $data['incident_active'] ?? 1,
		]);
		if (($result['status'] ?? 1) !== 0) {
			$this->error = strval($result['error'] ?? 'Save failed');
			return;
		}
		common_redirect('/admin/status-pages/edit/'.$this->status_page_id.'/');
	}

	private function print_header() {
		\Ui::print_section_title('bi bi-broadcast', \T::StatusPages_Edit_Title());
		if (!empty($this->error)) {
			echo '<div class="alert alert-danger">'.htmlspecialchars($this->error, ENT_QUOTES, 'UTF-8').'</div>';
		}
		if ($this->status_page_id > 0 && !empty($this->page['slug'])) {
			$url = '/status/'.rawurlencode(strval($this->page['slug'])).'/';
			echo '<p><a href="'.htmlspecialchars($url, ENT_QUOTES, 'UTF-8').'" target="_blank" rel="noopener">'.\T::StatusPages_Table_PublicUrl().': '.$url.'</a></p>';
		}
	}

	private function print_forms() {
		$folderOptions = \Monitors::get_folder_hash_options_hash(true);
		$monitorOptions = [];
		foreach (\Monitors::data(false, sql_pholder(' AND `type` <> ? ORDER BY `title` ASC', \Monitors::TYPE_FOLDER), '`id`, `title`') ?: [] as $m) {
			if (\Sessions::checkPermission(\Monitors::PERMISSION_ACCESS, intval($m['id']), READ)) {
				$monitorOptions[intval($m['id'])] = strval($m['title']);
			}
		}
		$readonly = !($this->can_write || ($this->status_page_id < 1 && $this->can_create));

		echo '<form method="post" class="mb-4">';
		echo \Csrf::field();
		echo $this->template->html_input('title', htmlspecialchars(strval($this->page['title'] ?? ''), ENT_QUOTES, 'UTF-8'), \T::StatusPages_Edit_Name(), false, ['readonly' => $readonly]);
		echo $this->template->html_input('slug', htmlspecialchars(strval($this->page['slug'] ?? ''), ENT_QUOTES, 'UTF-8'), \T::StatusPages_Edit_Slug(), false, ['readonly' => $readonly]);
		echo $this->template->html_switch('status', intval($this->page['status'] ?? 0), \T::StatusPages_Edit_Status(), false, ['readonly' => $readonly]);
		echo $this->template->html_select('folder_ids[]', $folderOptions, $this->page['folder_ids'] ?? [], \T::StatusPages_Edit_Folders(), false, [
			'multiple' => true,
			'readonly' => $readonly,
			'class2' => 'col-xs-12 col-md-8',
		]);
		echo $this->template->html_select('monitor_ids[]', $monitorOptions, $this->page['monitor_ids'] ?? [], \T::StatusPages_Edit_Monitors(), false, [
			'multiple' => true,
			'readonly' => $readonly,
			'class2' => 'col-xs-12 col-md-8',
		]);
		if (!$readonly) {
			echo '<div class="mb-3"><button type="submit" class="btn btn-primary">'.\T::Framework_Common_Save().'</button> ';
			echo '<a class="btn btn-secondary" href="/admin/status-pages/">'.\T::Framework_Common_Cancel().'</a></div>';
		}
		echo '</form>';

		if ($this->status_page_id > 0) {
			echo '<h3>'.\T::StatusPages_Edit_Incidents().'</h3>';
			if ($this->can_write) {
				echo '<form method="post" class="mb-3 border rounded p-3">';
				echo \Csrf::field();
				echo '<input type="hidden" name="form" value="incident">';
				echo $this->template->html_input('incident_title', '', \T::StatusPages_Edit_IncidentTitle(), false, []);
				echo $this->template->html_input('incident_message', '', \T::StatusPages_Edit_IncidentMessage(), false, ['type' => 'textarea', 'rows' => 3]);
				$states = [];
				foreach (\StatusPageMembership::INCIDENT_STATES as $st) {
					$states[$st] = $st;
				}
				echo $this->template->html_select('incident_state', $states, 'investigating', \T::StatusPages_Edit_IncidentState(), false, []);
				echo $this->template->html_switch('incident_active', 1, \T::StatusPages_Edit_IncidentActive(), false, []);
				echo '<button type="submit" class="btn btn-warning">'.\T::StatusPages_Edit_AddIncident().'</button></form>';
			}
			echo '<table class="table table-sm"><thead><tr><th>ID</th><th>'.\T::StatusPages_Edit_IncidentTitle().'</th><th>'.\T::StatusPages_Edit_IncidentState().'</th><th>Active</th></tr></thead><tbody>';
			foreach ($this->incidents as $inc) {
				echo '<tr><td>'.intval($inc['id']).'</td><td>'.htmlspecialchars(strval($inc['title'] ?? ''), ENT_QUOTES, 'UTF-8').'</td>';
				echo '<td>'.htmlspecialchars(strval($inc['state'] ?? ''), ENT_QUOTES, 'UTF-8').'</td>';
				echo '<td>'.(intval($inc['active'] ?? 0) === 1 ? 'yes' : 'no').'</td></tr>';
			}
			echo '</tbody></table>';
		}
	}
}

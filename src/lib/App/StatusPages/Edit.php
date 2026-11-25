<?php

namespace App\StatusPages;

class Edit extends \Routing_Parent implements \Routing_Interface {

	private int $status_page_id = 0;
	private array $page = [];
	private bool $can_write = false;
	private bool $can_create = false;
	/** @var list<array<string,mixed>> */
	private array $incidents = [];

	private const FIELD_CLASS2 = 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6';

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
		$form = strval($data['form'] ?? '');
		if ($form === 'incident') {
			$this->save_incident($data);
			return;
		}
		if ($form === 'incident_delete') {
			$this->delete_incident($data);
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

	private function delete_incident(array $data) {
		if ($this->status_page_id < 1 || !$this->can_write) {
			$this->error = \T::StatusPages_Edit_PermissionDenied();
			return;
		}
		$incidentId = intval($data['incident_id'] ?? 0);
		$result = \Api\V2\StatusPagesService::deleteIncident(
			\Sessions::currentUser(),
			$this->status_page_id,
			$incidentId
		);
		if (($result['status'] ?? 1) !== 0) {
			$this->error = strval($result['error'] ?? \T::StatusPages_Edit_DeleteIncidentFailed());
			return;
		}
		common_redirect('/admin/status-pages/edit/'.$this->status_page_id.'/');
	}

	private function print_header() {
		\Ui::print_section_title('bi bi-broadcast', \T::StatusPages_Edit_Title());
		echo '<div class="clearfix"></div>';
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
		$fieldClass = ['class2' => self::FIELD_CLASS2, 'readonly' => $readonly];

		echo '<div class="row d-flex justify-content-center">';
		echo '<div class="card bg-transparent col-xl-10 p-4 mt-2 mb-3">';
		echo '<div class="card-header bg-transparent d-flex flex-wrap align-items-center justify-content-between gap-2">';
		echo '<h3 class="mb-0">'.($this->status_page_id > 0
			? htmlspecialchars(strval($this->page['title'] ?? ''), ENT_QUOTES, 'UTF-8').' <small class="text-muted">#'.$this->status_page_id.'</small>'
			: \T::StatusPages_Edit_CreateTitle()).'</h3>';
		if ($this->status_page_id > 0 && !empty($this->page['slug'])) {
			$url = '/status/'.rawurlencode(strval($this->page['slug'])).'/';
			echo '<a class="btn btn-sm btn-outline-info" href="'.htmlspecialchars($url, ENT_QUOTES, 'UTF-8').'" target="_blank" rel="noopener">';
			echo '<i class="bi bi-box-arrow-up-right"></i> '.\T::StatusPages_Table_PublicUrl().'</a>';
		}
		echo '</div>';

		if (!empty($this->error)) {
			echo '<div class="alert alert-danger mt-3 mb-0">'.htmlspecialchars($this->error, ENT_QUOTES, 'UTF-8').'</div>';
		}

		echo '<form class="card-body needs-validation" method="post" novalidate>';
		echo \Csrf::field();
		echo $this->template->html_input('title', strval($this->page['title'] ?? ''), \T::StatusPages_Edit_Name(), true, $fieldClass);
		echo $this->template->html_input('slug', strval($this->page['slug'] ?? ''), \T::StatusPages_Edit_Slug(), true, $fieldClass + [
			'placeholder' => 'my-status',
		]);
		echo $this->template->html_switch('status', intval($this->page['status'] ?? 0), \T::StatusPages_Edit_Status(), false, [
			'readonly' => $readonly,
			'class2' => self::FIELD_CLASS2,
		]);
		echo $this->template->html_select('folder_ids[]', $folderOptions, $this->page['folder_ids'] ?? [], \T::StatusPages_Edit_Folders(), false, [
			'multiple' => true,
			'readonly' => $readonly,
			'id' => 'folder_ids',
			'class2' => self::FIELD_CLASS2,
		]);
		echo $this->template->html_select('monitor_ids[]', $monitorOptions, $this->page['monitor_ids'] ?? [], \T::StatusPages_Edit_Monitors(), false, [
			'multiple' => true,
			'readonly' => $readonly,
			'id' => 'monitor_ids',
			'class2' => self::FIELD_CLASS2,
		]);
		if (!$readonly) {
			echo '<div class="d-flex flex-row-reverse gap-2">';
			echo '<button type="submit" class="btn btn-primary">'.\T::Framework_Common_Save().'</button>';
			echo '<a class="btn btn-secondary" href="/admin/status-pages/">'.\T::Framework_Common_Cancel().'</a>';
			echo '</div>';
		}
		echo '</form>';
		echo '</div></div>';

		if ($this->status_page_id > 0) {
			$this->print_incidents($readonly);
		}
	}

	private function print_incidents(bool $readonly): void {
		echo '<div class="row d-flex justify-content-center">';
		echo '<div class="card bg-transparent col-xl-10 p-4 mt-2 mb-4">';
		echo '<div class="card-header bg-transparent"><h3 class="mb-0">'.\T::StatusPages_Edit_Incidents().'</h3></div>';
		echo '<div class="card-body">';

		if ($this->can_write && !$readonly) {
			$states = [
				'investigating' => \T::StatusPages_Edit_StateInvestigating(),
				'identified' => \T::StatusPages_Edit_StateIdentified(),
				'monitoring' => \T::StatusPages_Edit_StateMonitoring(),
				'resolved' => \T::StatusPages_Edit_StateResolved(),
			];
			echo '<form method="post" class="mb-4">';
			echo \Csrf::field();
			echo '<input type="hidden" name="form" value="incident">';
			echo $this->template->html_input('incident_title', '', \T::StatusPages_Edit_IncidentTitle(), true, [
				'class2' => self::FIELD_CLASS2,
			]);
			echo $this->template->html_input('incident_message', '', \T::StatusPages_Edit_IncidentMessage(), false, [
				'type' => 'textarea',
				'rows' => 3,
				'class2' => self::FIELD_CLASS2,
			]);
			echo $this->template->html_select('incident_state', $states, 'investigating', \T::StatusPages_Edit_IncidentState(), false, [
				'id' => 'incident_state',
				'class2' => self::FIELD_CLASS2,
			]);
			echo $this->template->html_switch('incident_active', 1, \T::StatusPages_Edit_IncidentActive(), false, [
				'class2' => self::FIELD_CLASS2,
			]);
			echo '<div class="d-flex flex-row-reverse">';
			echo '<button type="submit" class="btn btn-warning">'.\T::StatusPages_Edit_AddIncident().'</button>';
			echo '</div></form>';
		}

		echo '<div class="table-responsive"><table class="table table-transparent table-sm align-middle mb-0">';
		echo '<thead><tr>';
		echo '<th>ID</th>';
		echo '<th>'.\T::StatusPages_Edit_IncidentTitle().'</th>';
		echo '<th>'.\T::StatusPages_Edit_IncidentState().'</th>';
		echo '<th>'.\T::StatusPages_Edit_IncidentActive().'</th>';
		echo '<th>'.\T::StatusPages_Edit_IncidentCreated().'</th>';
		echo '<th>'.\T::StatusPages_Table_Actions().'</th>';
		echo '</tr></thead><tbody>';
		if (!$this->incidents) {
			echo '<tr><td colspan="6" class="text-muted">'.\T::StatusPages_Edit_NoIncidents().'</td></tr>';
		}
		$confirm = htmlspecialchars(
			json_encode(\T::StatusPages_Edit_DeleteIncidentConfirmation(), JSON_UNESCAPED_UNICODE),
			ENT_QUOTES,
			'UTF-8'
		);
		foreach ($this->incidents as $inc) {
			$state = strval($inc['state'] ?? '');
			$stateLabel = match ($state) {
				'investigating' => \T::StatusPages_Edit_StateInvestigating(),
				'identified' => \T::StatusPages_Edit_StateIdentified(),
				'monitoring' => \T::StatusPages_Edit_StateMonitoring(),
				'resolved' => \T::StatusPages_Edit_StateResolved(),
				default => $state,
			};
			$badge = match ($state) {
				'investigating', 'identified' => 'text-bg-danger',
				'monitoring' => 'text-bg-warning',
				'resolved' => 'text-bg-success',
				default => 'text-bg-secondary',
			};
			$created = intval($inc['create_time'] ?? 0);
			$incId = intval($inc['id'] ?? 0);
			echo '<tr>';
			echo '<td>'.$incId.'</td>';
			echo '<td>'.htmlspecialchars(strval($inc['title'] ?? ''), ENT_QUOTES, 'UTF-8').'</td>';
			echo '<td><span class="badge '.$badge.'">'.htmlspecialchars($stateLabel, ENT_QUOTES, 'UTF-8').'</span></td>';
			echo '<td>'.(intval($inc['active'] ?? 0) === 1
				? '<i class="bi bi-check-lg text-success"></i>'
				: '<i class="bi bi-x-lg text-danger"></i>').'</td>';
			echo '<td class="text-muted">'.($created > 0 ? htmlspecialchars(gmdate('Y-m-d H:i', $created).' UTC', ENT_QUOTES, 'UTF-8') : '—').'</td>';
			echo '<td class="text-end">';
			if ($this->can_write && !$readonly && $incId > 0) {
				echo '<form method="post" class="d-inline" onsubmit="return confirm('.$confirm.');">';
				echo \Csrf::field();
				echo '<input type="hidden" name="form" value="incident_delete">';
				echo '<input type="hidden" name="incident_id" value="'.$incId.'">';
				echo '<button type="submit" class="btn btn-sm btn-outline-danger">'.\T::Framework_Common_Delete().'</button>';
				echo '</form>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table></div>';
		echo '</div></div></div>';
	}
}

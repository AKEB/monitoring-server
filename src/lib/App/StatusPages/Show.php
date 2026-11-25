<?php

namespace App\StatusPages;

class Show extends \Routing_Parent implements \Routing_Interface {

	private bool $can_read_global = false;
	private bool $can_delete_global = false;
	private bool $can_create = false;
	private array $pages = [];

	public function Run($action = 'list', $status_page_id = null) {
		$this->check_auth();
		$this->check_permissions();

		if ($action == 'delete' && $status_page_id) {
			\Csrf::requirePost();
			$this->processDeleteAction(intval($status_page_id));
		}

		$this->get_data();
		$this->template = new \Template('Status pages');
		$this->print_header();
		$this->print_table();
		$this->print_javascript();
	}

	private function check_permissions() {
		$this->can_read_global = \Sessions::checkPermission(\StatusPages::PERMISSION_ACCESS, -1, READ);
		$this->can_delete_global = \Sessions::checkPermission(\StatusPages::PERMISSION_ACCESS, -1, DELETE);
		$this->can_create = \Sessions::checkPermission(\StatusPages::PERMISSION_CREATE, 0, WRITE);
		if (!$this->can_read_global && !$this->can_create) {
			e403();
		}
	}

	private function processDeleteAction(int $id) {
		if (!$this->can_delete_global || $id < 1) {
			$this->error = \T::StatusPages_Delete_PermissionDenied();
			return;
		}
		if (!\Sessions::checkPermission(\StatusPages::PERMISSION_ACCESS, $id, DELETE)) {
			$this->error = \T::StatusPages_Delete_PermissionDenied();
			return;
		}
		$result = \Api\V2\StatusPagesService::delete(\Sessions::currentUser(), $id);
		if (($result['status'] ?? 1) !== 0) {
			$this->error = strval($result['error'] ?? \T::StatusPages_Delete_NotFound());
			return;
		}
		common_redirect('/admin/status-pages/');
	}

	private function get_data() {
		$this->pages = [];
		foreach (\StatusPages::data(false, ' ORDER BY `title` ASC') ?: [] as $item) {
			if (!\Sessions::checkPermission(\StatusPages::PERMISSION_ACCESS, intval($item['id']), READ)) {
				continue;
			}
			$this->pages[intval($item['id'])] = $item;
		}
	}

	private function print_header() {
		\Ui::print_section_title('bi bi-broadcast', \T::StatusPages_PageTitle());
		if ($this->can_create) {
			echo '<div class="float-end"><h3 class="pointer text-info"><a class="text-info text-decoration-none" href="/admin/status-pages/edit/"><i class="bi bi-plus-circle"></i> '.\T::Framework_Common_Create().'</a></h3></div>';
		}
		echo '<div class="clearfix"></div>';
		if (!empty($this->error)) {
			echo '<div class="alert alert-danger">'.htmlspecialchars($this->error, ENT_QUOTES, 'UTF-8').'</div>';
		}
	}

	private function print_table() {
		echo '<div class="row d-flex justify-content-center"><table class="table table-transparent table-responsive" id="Table"><thead><tr>';
		echo '<th>ID</th><th>'.\T::StatusPages_Table_Title().'</th><th>'.\T::StatusPages_Table_Slug().'</th><th>'.\T::StatusPages_Table_Status().'</th><th>'.\T::StatusPages_Table_PublicUrl().'</th><th>'.\T::StatusPages_Table_Actions().'</th>';
		echo '</tr></thead><tbody>';
		foreach ($this->pages as $item) {
			$id = intval($item['id']);
			$slug = htmlspecialchars(strval($item['slug'] ?? ''), ENT_QUOTES, 'UTF-8');
			$title = htmlspecialchars(strval($item['title'] ?? ''), ENT_QUOTES, 'UTF-8');
			$status = intval($item['status'] ?? 0) === 1
				? '<i class="bi bi-check-lg fs-4 text-success"></i>'
				: '<i class="bi bi-x-lg fs-4 text-danger"></i>';
			$public = '/status/'.$slug.'/';
			$can_write = \Sessions::checkPermission(\StatusPages::PERMISSION_ACCESS, $id, WRITE);
			$can_delete = $this->can_delete_global
				&& \Sessions::checkPermission(\StatusPages::PERMISSION_ACCESS, $id, DELETE);
			echo '<tr><th>'.$id.'</th><td>'.$title.'</td><td><code>'.$slug.'</code></td><td class="text-center">'.$status.'</td>';
			echo '<td><a href="'.htmlspecialchars($public, ENT_QUOTES, 'UTF-8').'" target="_blank" rel="noopener">'.$public.'</a></td><td class="text-center">';
			if ($can_write) {
				echo '<a class="btn btn-sm btn-outline-primary me-1" href="/admin/status-pages/edit/'.$id.'/">'.\T::Framework_Common_Edit().'</a>';
			}
			if ($can_delete) {
				$confirm = htmlspecialchars(
					json_encode(\T::StatusPages_Delete_Confirmation(), JSON_UNESCAPED_UNICODE),
					ENT_QUOTES,
					'UTF-8'
				);
				echo '<form method="post" action="/admin/status-pages/delete/'.$id.'/" class="d-inline" onsubmit="return confirm('.$confirm.');">';
				echo \Csrf::field();
				echo '<button type="submit" class="btn btn-sm btn-outline-danger">'.\T::Framework_Common_Delete().'</button></form>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	private function print_javascript() {
		// reserved for table plugins if needed
	}
}

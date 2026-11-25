<?php

class StatusPages extends \DB\MySQLObject implements \PermissionSubject_Interface {

	static public $table = 'status_pages';

	const LOGS_OBJECT = 'StatusPages';

	const PERMISSION_ACCESS = 'status_page';
	const PERMISSION_CREATE = 'create_status_page';

	const STATUS_ENABLED = 1;
	const STATUS_DISABLED = 0;

	static public function subject_hash(): array {
		$data_hash = [];
		foreach (static::permissions_subject_hash() as $subject_id => $permissionTitle) {
			if (!\Sessions::checkPermission(static::PERMISSION_ACCESS, $subject_id, ACCESS_WRITE)) {
				continue;
			}
			$data_hash[$subject_id] = $permissionTitle;
		}
		return $data_hash;
	}

	static public function permissions_subject_hash(): array {
		$data = static::data(false, ' ORDER BY title ASC') ?: [];
		$permissions_hash = [];
		foreach ($data as $item) {
			$permissions_hash[$item['id']] = $item['title'];
		}
		return $permissions_hash;
	}

	static public function permissions_hash(): array {
		return [
			static::PERMISSION_ACCESS => \T::StatusPages_Permissions_Access(),
		];
	}

	static public function getUserPermissions(array $user): array {
		return [];
	}

	static public function getBySlug(string $slug): ?array {
		$slug = \StatusPageMembership::normalizeSlug($slug);
		if ($slug === '') {
			return null;
		}
		$row = static::get(['slug' => $slug]);
		return $row ?: null;
	}

	/**
	 * On create: READ for Default group; full access for creator.
	 */
	static public function grantCreatePermissions(int $page_id, int $creator_user_id = 0): void {
		if ($page_id < 1) {
			return;
		}
		$now = time();
		$everyone = [
			'object' => 'group',
			'object_id' => \Groups::DEFAULT_GROUP_ID,
			'subject' => static::PERMISSION_ACCESS,
			'subject_id' => $page_id,
			READ => 1,
			WRITE => 0,
			DELETE => 0,
			ACCESS_READ => 0,
			ACCESS_WRITE => 0,
			ACCESS_CHANGE => 0,
			'create_time' => $now,
			'update_time' => $now,
			'_mode' => \DB\Common::CSMODE_INSERT,
		];
		$everyone_id = \ObjectPermissions::save($everyone);
		if ($everyone_id) {
			$log_id = \Logs::create_log(\ObjectPermissions::LOGS_OBJECT, $everyone_id, $everyone);
			\Logs::add_tag($log_id, static::LOGS_OBJECT, $page_id);
		}
		if ($creator_user_id < 1) {
			return;
		}
		$creator = [
			'object' => 'user',
			'object_id' => $creator_user_id,
			'subject' => static::PERMISSION_ACCESS,
			'subject_id' => $page_id,
			READ => 1,
			WRITE => 1,
			DELETE => 1,
			ACCESS_READ => 1,
			ACCESS_WRITE => 1,
			ACCESS_CHANGE => 1,
			'create_time' => $now,
			'update_time' => $now,
			'_mode' => \DB\Common::CSMODE_INSERT,
		];
		$creator_id = \ObjectPermissions::save($creator);
		if ($creator_id) {
			$log_id = \Logs::create_log(\ObjectPermissions::LOGS_OBJECT, $creator_id, $creator);
			\Logs::add_tag($log_id, static::LOGS_OBJECT, $page_id);
		}
	}
}

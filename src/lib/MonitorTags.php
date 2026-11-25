<?php

class MonitorTags extends \DB\MySQLObject implements \PermissionSubject_Interface {

	static public $table = 'monitor_tags';

	const LOGS_OBJECT = 'MonitorTags';

	const PERMISSION_ACCESS = 'monitor_tag';
	const PERMISSION_CREATE = 'create_monitor_tag';

	const DEFAULT_COLOR = '#0d6efd';

	/** Bootstrap-inspired presets for the admin color picker. */
	static public function color_presets(): array {
		return [
			'#0d6efd' => 'Primary',
			'#6c757d' => 'Secondary',
			'#198754' => 'Success',
			'#dc3545' => 'Danger',
			'#ffc107' => 'Warning',
			'#0dcaf0' => 'Info',
			'#212529' => 'Dark',
			'#6610f2' => 'Purple',
		];
	}

	static public function normalizeTitle(string $title): string {
		$title = trim(preg_replace('/\s+/u', ' ', $title) ?? $title);
		return mb_substr($title, 0, 64);
	}

	static public function isTitleValid(string $title): bool {
		$len = mb_strlen(trim($title));
		return $len >= 1 && $len <= 64;
	}

	static public function normalizeColor(string $color): string {
		$color = trim($color);
		if (preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
			return strtolower($color);
		}
		if (preg_match('/^[0-9A-Fa-f]{6}$/', $color)) {
			return '#'.strtolower($color);
		}
		return self::DEFAULT_COLOR;
	}

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
			static::PERMISSION_ACCESS => \T::MonitorTags_Permissions_Access(),
		];
	}

	static public function getUserPermissions(array $user): array {
		return [];
	}

	/**
	 * Tags the current user can READ (for monitor multiselect).
	 * @return array<int,string> id => title
	 */
	static public function readable_hash(): array {
		$data = static::data(false, ' ORDER BY title ASC') ?: [];
		$hash = [];
		foreach ($data as $item) {
			$id = intval($item['id'] ?? 0);
			if ($id < 1) {
				continue;
			}
			if (!\Sessions::checkPermission(static::PERMISSION_ACCESS, $id, READ)) {
				continue;
			}
			$hash[$id] = strval($item['title'] ?? '');
		}
		return $hash;
	}

	/**
	 * @return list<array{id:int,title:string,color:string}>
	 */
	static public function readable_list(): array {
		$data = static::data(false, ' ORDER BY title ASC') ?: [];
		$out = [];
		foreach ($data as $item) {
			$id = intval($item['id'] ?? 0);
			if ($id < 1) {
				continue;
			}
			if (!\Sessions::checkPermission(static::PERMISSION_ACCESS, $id, READ)) {
				continue;
			}
			$out[] = [
				'id' => $id,
				'title' => strval($item['title'] ?? ''),
				'color' => static::normalizeColor(strval($item['color'] ?? self::DEFAULT_COLOR)),
			];
		}
		return $out;
	}

	static public function grantCreatePermissions(int $tag_id, int $creator_user_id = 0): void {
		if ($tag_id < 1) {
			return;
		}
		$now = time();
		$everyone = [
			'object' => 'group',
			'object_id' => \Groups::DEFAULT_GROUP_ID,
			'subject' => static::PERMISSION_ACCESS,
			'subject_id' => $tag_id,
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
			\Logs::add_tag($log_id, static::LOGS_OBJECT, $tag_id);
		}
		if ($creator_user_id > 0) {
			$owner = [
				'object' => 'user',
				'object_id' => $creator_user_id,
				'subject' => static::PERMISSION_ACCESS,
				'subject_id' => $tag_id,
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
			$owner_id = \ObjectPermissions::save($owner);
			if ($owner_id) {
				$log_id = \Logs::create_log(\ObjectPermissions::LOGS_OBJECT, $owner_id, $owner);
				\Logs::add_tag($log_id, static::LOGS_OBJECT, $tag_id);
			}
			// Drop cached ACL so create→edit redirect sees the new row immediately.
			$cache = new \Cache('getPermissionsForUser_'.$creator_user_id);
			$cache->remove();
		}
	}
}

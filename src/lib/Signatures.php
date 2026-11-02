<?php

class Signatures extends \DB\MySQLObject implements \PermissionSubject_Interface {

	static public $table = 'signatures';

	const LOGS_OBJECT = 'Signatures';

	const PERMISSION_ACCESS = 'signature';
	const PERMISSION_CREATE = 'create_signature';

	const STATUS_DISABLED = 0;
	const STATUS_ENABLED = 1;

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

	static public function status_hash(): array {
		return [
			static::STATUS_DISABLED => \T::Signature_Status_Disabled(),
			static::STATUS_ENABLED => \T::Signature_Status_Enabled(),
		];
	}

	static public function permissions_subject_hash(): array {
		$data = static::data(false, ' ORDER BY `project` ASC, `title` ASC');
		$permissions_hash = [];
		foreach ($data as $item) {
			$label = trim(($item['project'] ?? '').' / '.($item['title'] ?? ''));
			$permissions_hash[$item['id']] = $label !== ' / ' ? $label : ('#'.$item['id']);
		}
		return $permissions_hash;
	}

	static public function permissions_hash(): array {
		return [
			static::PERMISSION_ACCESS => \T::Signature_Permissions_Access(),
		];
	}

	static public function getUserPermissions(array $user): array {
		return [];
	}

	/** @return array<string,string> project => project */
	static public function projects_hash(bool $enabled_only = true): array {
		$where = $enabled_only ? sql_pholder(' AND `status` = ?', static::STATUS_ENABLED) : '';
		$data = static::data(false, $where.' ORDER BY `project` ASC', '`project`');
		$hash = [];
		foreach ($data as $item) {
			$project = (string)($item['project'] ?? '');
			$hash[$project] = $project;
		}
		return $hash;
	}

	/**
	 * Active signatures the current user can READ (for monitor select UI).
	 * @return array<int,array{id:int,project:string,title:string,text:string}>
	 */
	static public function active_list(): array {
		$data = static::data(false, sql_pholder(' AND `status` = ? ORDER BY `project` ASC, `title` ASC', static::STATUS_ENABLED));
		$result = [];
		foreach ($data as $item) {
			$id = intval($item['id'] ?? 0);
			if (!$id) continue;
			if (!\Sessions::checkPermission(static::PERMISSION_ACCESS, $id, READ)) continue;
			$result[$id] = [
				'id' => $id,
				'project' => (string)($item['project'] ?? ''),
				'title' => (string)($item['title'] ?? ''),
				'text' => (string)($item['text'] ?? ''),
			];
		}
		return $result;
	}

	static public function resolveText(int $signature_id): string {
		if ($signature_id < 1) return '';
		$item = static::get(['id' => $signature_id]);
		if (!$item) return '';
		if (intval($item['status'] ?? 0) != static::STATUS_ENABLED) return '';
		return trim((string)($item['text'] ?? ''));
	}

	/**
	 * On create: READ for Default group (everyone); full access for creator.
	 */
	static public function grantCreatePermissions(int $signature_id, int $creator_user_id = 0): void {
		if ($signature_id < 1) return;
		$now = time();

		$everyone = [
			'object' => 'group',
			'object_id' => \Groups::DEFAULT_GROUP_ID,
			'subject' => static::PERMISSION_ACCESS,
			'subject_id' => $signature_id,
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
			\Logs::add_tag($log_id, static::LOGS_OBJECT, $signature_id);
		}

		if ($creator_user_id < 1) return;

		$creator = [
			'object' => 'user',
			'object_id' => $creator_user_id,
			'subject' => static::PERMISSION_ACCESS,
			'subject_id' => $signature_id,
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
			\Logs::add_tag($log_id, static::LOGS_OBJECT, $signature_id);
		}
	}

}

<?php

class Maintenances extends \DB\MySQLObject implements \PermissionSubject_Interface {

	static public $table = 'maintenances';

	const LOGS_OBJECT = 'Maintenances';

	const PERMISSION_ACCESS = 'maintenance';
	const PERMISSION_CREATE = 'create_maintenance';

	static public function subject_hash(): array {
		$data_hash = [];
		foreach(static::permissions_subject_hash() as $subject_id=>$permissionTitle) {
			if (!\Sessions::checkPermission(static::PERMISSION_ACCESS, $subject_id, ACCESS_WRITE)) {
				continue;
			}
			$data_hash[$subject_id] = $permissionTitle;
		}
		return $data_hash;
	}

	static public function permissions_subject_hash(): array {
		$data = static::data(false, ' ORDER BY title ASC');
		$permissions_hash = [];
		foreach($data as $item) {
			$permissions_hash[$item['id']] = $item['title'];
		}
		return $permissions_hash;
	}
	static public function permissions_hash(): array {
		return [
			static::PERMISSION_ACCESS => \T::Maintenances_Permissions_Access(),
		];
	}

	static public function getUserPermissions(array $user): array {
		return [];
	}

}
<?php

class Dockers extends \DB\MySQLObject implements \PermissionSubject_Interface {

	static public $table = 'dockers';

	const LOGS_OBJECT = 'Dockers';

	const PERMISSION_ACCESS = 'docker';
	const PERMISSION_CREATE = 'create_docker';

	const STATUS_DISABLED = 0;
	const STATUS_ENABLED = 1;

	static public function subject_hash(): array {
		$data_hash = [];
		// Получаем список субъектов для которых у пользователя есть доступ к docker
		foreach(static::permissions_subject_hash() as $subject_id=>$permissionTitle) {
			if (!\Sessions::checkPermission(static::PERMISSION_ACCESS, $subject_id, ACCESS_WRITE)) {
				continue;
			}
			$data_hash[$subject_id] = $permissionTitle;
		}
		return $data_hash;
	}

	static public function status_hash(): array {
		return [
			static::STATUS_DISABLED   => \T::Docker_Status_Disabled(),
			static::STATUS_ENABLED => \T::Docker_Status_Enabled(),
		];
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
			static::PERMISSION_ACCESS => \T::Docker_Permissions_Access(),
		];
	}

	static public function getUserPermissions(array $user): array {
		return [];
	}

}
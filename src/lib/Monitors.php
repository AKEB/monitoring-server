<?php

class Monitors extends \DB\MySQLObject implements \PermissionSubject_Interface {
	static public $table = 'monitoring_monitor';

	const LOGS_OBJECT = 'Monitors';

	const PERMISSION_ACCESS = 'monitor';
	const PERMISSION_CREATE = 'create_monitor';

	const STATUS_DISABLED = 0;
	const STATUS_ENABLED = 1;

	const TYPE_CURL = 0;
	const TYPE_FOLDER = 1;

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

	static public function type_hash(): array {
		return [
			static::TYPE_CURL => \T::Monitor_Type_Curl(),
			static::TYPE_FOLDER => \T::Monitor_Type_Folder(),
		];
	}

	static public function HTTP_methods(): array {
		return [
			'GET' => 'GET',
			'HEAD' => 'HEAD',
			'POST' => 'POST',
			'PUT' => 'PUT',
			'DELETE' => 'DELETE',
			'CONNECT' => 'CONNECT',
			'OPTIONS' => 'OPTIONS',
			'TRACE' => 'TRACE',
			'PATCH' => 'PATCH',
		];
	}

	static public function permissions_subject_hash(): array {
		$data = static::data();
		$permissions_hash = [];
		foreach($data as $item) {
			$permissions_hash[$item['id']] = $item['title'];
		}
		return $permissions_hash;
	}
	static public function permissions_hash(): array {
		return [
			static::PERMISSION_ACCESS => \T::Monitor_Permissions_Monitor(),
		];
	}
}
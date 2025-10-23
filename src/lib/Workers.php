<?php

class Workers extends \DB\MySQLObject implements \PermissionSubject_Interface {

	static public $table = 'workers';

	const LOGS_OBJECT = 'Workers';

	const PERMISSION_WORKER = 'worker';
	const PERMISSION_WORKER_KEY_HASH = 'worker_key_hash';
	const PERMISSION_CREATE_WORKER = 'create_worker';
	


	static public function subject_hash(): array {
		$data_hash = [];
		foreach(static::permissions_subject_hash() as $subject_id=>$permissionTitle) {
			if (!\Sessions::checkPermission(static::PERMISSION_WORKER, $subject_id, ACCESS_WRITE)) {
				continue;
			}
			$data_hash[$subject_id] = $permissionTitle;
		}
		return $data_hash;
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
			static::PERMISSION_WORKER => \T::Worker_Permissions_Worker(),
			static::PERMISSION_WORKER_KEY_HASH => \T::Worker_Permissions_WorkerKeyHash(),
		];
	}

}
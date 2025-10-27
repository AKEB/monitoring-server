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

	static private array $monitorsForAccess = [];

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

	static private function setAccessForChild(&$data) {
		// vardump($data);
		foreach($data as $k=>$v) {
			if (isset($v['children']) && $v['children']) {
				foreach($v['children'] as $k2=>$v2) {
					$data[$k]['children'][$k2]['access'] = [
						READ => ($v2['access'][READ]??false) || ($v['access'][READ]??false),
						WRITE => ($v2['access'][WRITE]??false) || ($v['access'][WRITE]??false),
						DELETE => ($v2['access'][DELETE]??false) || ($v['access'][DELETE]??false),
						ACCESS_READ => ($v2['access'][ACCESS_READ]??false) || ($v['access'][ACCESS_READ]??false),
						ACCESS_WRITE => ($v2['access'][ACCESS_WRITE]??false) || ($v['access'][ACCESS_WRITE]??false),
						ACCESS_CHANGE => ($v2['access'][ACCESS_CHANGE]??false) || ($v['access'][ACCESS_CHANGE]??false),
					];
				}
				static::setAccessForChild($v['children']);
			}
		}
	}

	static private function setAccessForParent(&$data, $obj_id) {
		foreach($data[$obj_id]['children'] as $k=>$v) {
			if (isset($v['parent_id']) && $v['parent_id']) {
				$parent = &$data[$v['parent_id']];
				$parent['access'][READ] = ($parent['access'][READ]??false) || ($v['access'][READ]??false);
			}
			static::setAccessForParent($data, $k);
		}
	}

	static public function getMonitorsAccess() {
		if (!static::$monitorsForAccess) {
			$data = \Monitors::data(false,' ORDER BY NULL ', '`id`, `parent_id`');
			foreach($data as $item) {
				if (!isset(static::$monitorsForAccess[$item['id']])) {
					static::$monitorsForAccess[$item['id']] = [];
					static::$monitorsForAccess[$item['id']]['children'] = [];
				}
				static::$monitorsForAccess[$item['id']]['id'] = $item['id'];
				static::$monitorsForAccess[$item['id']]['parent_id'] = $item['parent_id'];
				static::$monitorsForAccess[$item['id']]['access'] = [
					READ => \Sessions::checkPermission(\Monitors::PERMISSION_ACCESS, $item['id'], READ),
					WRITE => \Sessions::checkPermission(\Monitors::PERMISSION_ACCESS, $item['id'], WRITE),
					DELETE => \Sessions::checkPermission(\Monitors::PERMISSION_ACCESS, $item['id'], DELETE),
					ACCESS_READ => \Sessions::checkPermission(\Monitors::PERMISSION_ACCESS, $item['id'], ACCESS_READ),
					ACCESS_WRITE => \Sessions::checkPermission(\Monitors::PERMISSION_ACCESS, $item['id'], ACCESS_WRITE),
					ACCESS_CHANGE => \Sessions::checkPermission(\Monitors::PERMISSION_ACCESS, $item['id'], ACCESS_CHANGE),
				];
				if (!isset(static::$monitorsForAccess[$item['parent_id']]['children'])) {
					static::$monitorsForAccess[$item['parent_id']]['children'] = [];
				}
				static::$monitorsForAccess[$item['parent_id']]['children'][$item['id']] = &static::$monitorsForAccess[$item['id']];
			}
			static::setAccessForChild(static::$monitorsForAccess);
			static::setAccessForParent(static::$monitorsForAccess, 0);
		}
		return static::$monitorsForAccess;
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
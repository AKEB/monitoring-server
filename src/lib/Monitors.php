<?php

class Monitors extends \DB\MySQLObject implements \PermissionSubject_Interface {
	static public $table = 'monitoring_monitor';

	const LOGS_OBJECT = 'Monitors';

	const PERMISSION_ACCESS = 'monitor';
	const PERMISSION_CREATE = 'create_monitor';

	const STATUS_DISABLED = 0;
	const STATUS_ENABLED = 1;

	const TYPE_HTTPS  = 0;
	const TYPE_FOLDER = 1;
	const TYPE_TCP    = 2;
	const TYPE_PING   = 3;
	const TYPE_DOCKER = 4;


	static private array $monitorsForAccess = [];

	static public function subject_hash(): array {
		$data_hash = [];
		foreach(static::permissions_subject_hash() as $subject_id=>$permissionTitle) {
			if (!\Sessions::checkPermission(\Monitors::PERMISSION_ACCESS, $subject_id, ACCESS_WRITE)) {
				continue;
			}
			$data_hash[$subject_id] = $permissionTitle;
		}
		return $data_hash;
	}

	static public function type_hash(): array {
		return [
			static::TYPE_HTTPS => \T::Monitor_Type_HTTPs(),
			static::TYPE_FOLDER => \T::Monitor_Type_Folder(),
			static::TYPE_TCP => \T::Monitor_Type_TCP(),
			static::TYPE_PING => \T::Monitor_Type_Ping(),
			static::TYPE_DOCKER => \T::Monitor_Type_Docker(),
		];
	}

	static public function proxy_type_hash(): array {
		return [
			CURLPROXY_HTTP => 'CURLPROXY_HTTP',
			CURLPROXY_HTTPS => 'CURLPROXY_HTTPS',
			CURLPROXY_HTTP_1_0 => 'CURLPROXY_HTTP_1_0',
			CURLPROXY_SOCKS4 => 'CURLPROXY_SOCKS4',
			CURLPROXY_SOCKS4A => 'CURLPROXY_SOCKS4A',
			CURLPROXY_SOCKS5 => 'CURLPROXY_SOCKS5',
			CURLPROXY_SOCKS5_HOSTNAME => 'CURLPROXY_SOCKS5_HOSTNAME',
		];
	}

	static public function response_code_hash(): array {
		$return = [
			1 => '100-199',
			2 => '200-299',
			3 => '300-399',
			4 => '400-499',
			5 => '500-599',
		];
		for($i=100; $i<=599; $i++) {
			$return[$i] = $i;
		}
		return $return;
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

	static private function getMonitorsAccess(?array $user=null) {
		if (!isset($user) || !$user) {
			$user = \Sessions::currentUser();
		}

		if (!isset(static::$monitorsForAccess[$user['id']]) || !static::$monitorsForAccess[$user['id']]) {
			$data = \Monitors::data(false,' ORDER BY NULL ', '`id`, `parent_id`, `type`, `title`');
			foreach($data as $item) {
				if (!isset(static::$monitorsForAccess[$user['id']][$item['id']])) {
					static::$monitorsForAccess[$user['id']][$item['id']] = [];
					static::$monitorsForAccess[$user['id']][$item['id']]['children'] = [];
				}
				static::$monitorsForAccess[$user['id']][$item['id']]['id'] = $item['id'];
				static::$monitorsForAccess[$user['id']][$item['id']]['parent_id'] = $item['parent_id'];
				static::$monitorsForAccess[$user['id']][$item['id']]['type'] = $item['type'];
				static::$monitorsForAccess[$user['id']][$item['id']]['title'] = $item['title'];
				static::$monitorsForAccess[$user['id']][$item['id']]['access'] = [
					READ => \Sessions::checkPermission(\Monitors::PERMISSION_ACCESS, $item['id'], READ, $user),
					WRITE => \Sessions::checkPermission(\Monitors::PERMISSION_ACCESS, $item['id'], WRITE, $user),
					DELETE => \Sessions::checkPermission(\Monitors::PERMISSION_ACCESS, $item['id'], DELETE, $user),
					ACCESS_READ => \Sessions::checkPermission(\Monitors::PERMISSION_ACCESS, $item['id'], ACCESS_READ, $user),
					ACCESS_WRITE => \Sessions::checkPermission(\Monitors::PERMISSION_ACCESS, $item['id'], ACCESS_WRITE, $user),
					ACCESS_CHANGE => \Sessions::checkPermission(\Monitors::PERMISSION_ACCESS, $item['id'], ACCESS_CHANGE, $user),
				];
				if (!isset(static::$monitorsForAccess[$user['id']][$item['parent_id']]['children'])) {
					static::$monitorsForAccess[$user['id']][$item['parent_id']]['children'] = [];
				}
				static::$monitorsForAccess[$user['id']][$item['parent_id']]['children'][$item['id']] = &static::$monitorsForAccess[$user['id']][$item['id']];
			}
			static::setAccessForChild(static::$monitorsForAccess[$user['id']]);
			// static::setAccessForParent(static::$monitorsForAccess[$user['id']], 0);
		}
		return static::$monitorsForAccess[$user['id']];
	}

	static public function getUserPermissions(array $user): array {
		$permissions = [];
		$access_hash = \Monitors::getMonitorsAccess($user);
		foreach(static::permissions_subject_hash() as $subject_id=>$_) {
			$permissions[\Monitors::PERMISSION_ACCESS][$subject_id] = [
				READ => isset($access_hash[$subject_id]['access'][READ]) && $access_hash[$subject_id]['access'][READ] ? 1 : 0,
				WRITE => isset($access_hash[$subject_id]['access'][WRITE]) && $access_hash[$subject_id]['access'][WRITE] ? 1 : 0,
				DELETE => isset($access_hash[$subject_id]['access'][DELETE]) && $access_hash[$subject_id]['access'][DELETE] ? 1 : 0,
				ACCESS_READ => isset($access_hash[$subject_id]['access'][ACCESS_READ]) && $access_hash[$subject_id]['access'][ACCESS_READ] ? 1 : 0,
				ACCESS_WRITE => isset($access_hash[$subject_id]['access'][ACCESS_WRITE]) && $access_hash[$subject_id]['access'][ACCESS_WRITE] ? 1 : 0,
				ACCESS_CHANGE => isset($access_hash[$subject_id]['access'][ACCESS_CHANGE]) && $access_hash[$subject_id]['access'][ACCESS_CHANGE] ? 1 : 0,
			];
			if (array_sum($permissions[\Monitors::PERMISSION_ACCESS][$subject_id]) == 0) {
				unset($permissions[\Monitors::PERMISSION_ACCESS][$subject_id]);
			}
		}
		return $permissions;
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
			$permissions_hash[$item['id']] = ($item['type'] == static::TYPE_FOLDER ? \T::Monitor_Permissions_Folder().': ' : \T::Monitor_Permissions_Monitor().': ') .$item['title'];
		}
		return $permissions_hash;
	}

	static public function permissions_hash(): array {
		return [
			static::PERMISSION_ACCESS => \T::Monitor_Permissions_Access(),
		];
	}

	static public function folders_hash(): array {
		$data = \Monitors::data(['type' => \Monitors::TYPE_FOLDER], '', '`id`, `parent_id`, `title`');
		$folders_hash = [];
		foreach($data as $item) {
			if (!isset($folders_hash[$item['id']])) {
				$folders_hash[$item['id']] = $item;
				$folders_hash[$item['id']]['children'] = [];
			}
			$folders_hash[$item['id']]['id'] = $item['id'];
			$folders_hash[$item['id']]['parent_id'] = $item['parent_id'];
			$folders_hash[$item['id']]['title'] = $item['title'];
			if (!isset($folders_hash[$item['parent_id']])) {
				$folders_hash[$item['parent_id']] = [];
			}
			$folders_hash[$item['parent_id']]['children'][$item['id']] = &$folders_hash[$item['id']];
		}
		foreach($folders_hash as $k=>$v) {
			if ($k == 0) continue;
			if (!\Sessions::checkPermission(\Monitors::PERMISSION_ACCESS, $k, READ)) {
				unset($folders_hash[$v['parent_id']]['children'][$v['id']]);
				$folders_hash[$k] = [];
				unset($folders_hash[$k]);
				$folders_hash[$k] = null;
				continue;
			}
		}
		return $folders_hash[0]['children'] ?? [];
	}

	static public function get_folder_titles(&$options_hash, $data, $level) {
		if (!$data) return;
		usort($data, function($a, $b){
			$titleA = $a['title'];
			$titleB = $b['title'];

			// Проверяем, начинается ли строка с русской буквы
			$isRussianA = preg_match('/^[а-яё]/iu', $titleA);
			$isRussianB = preg_match('/^[а-яё]/iu', $titleB);

			// Если одна строка начинается с русской буквы, а другая - нет,
			// русская строка должна идти первой
			if ($isRussianA && !$isRussianB) {
				return -1;
			}
			if (!$isRussianA && $isRussianB) {
				return 1;
			}

			// Если обе строки начинаются с русских букв или обе с английских,
			// сортируем регистронезависимо
			return strcasecmp($titleA, $titleB);
		});

		foreach($data as $v) {
			$options_hash[$v['id']] = str_repeat('&nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;', $level).$v['title'];
			if ($v['children']) {
				static::get_folder_titles($options_hash, $v['children'], $level+1);
			}
		}
	}

	static public function get_folder_hash_options_hash() {
		$folders_hash = \Monitors::folders_hash();
		$options_hash = [];
		$options_hash[0] = \T::Monitor_Table_Root();
		static::get_folder_titles($options_hash, $folders_hash, 1);
		return $options_hash;
	}

	static public function get_monitor_title_with_folders(?int $monitor_id): string {
		$title = '';
		if (!isset($monitor_id) || !$monitor_id) return $title;
		$monitors_hash = [];
		$cache = new \Cache('monitors_hash');
		if ($cache->isValid()) {
			$monitors_hash = json_decode($cache->get(), true);
		}
		if (!$monitors_hash) {
			$monitors = \Monitors::data(false, '', '`id`, `title`, `parent_id`');
			if ($monitors) {
				$monitors_hash = make_hash($monitors, 'id');
			}
		}
		$tmp_monitor = $monitors_hash[$monitor_id];
		if (!$tmp_monitor) return $title;
		$titles = [];
		while(true) {
			$titles[] = $tmp_monitor['title'];
			if (!$tmp_monitor['parent_id']) break;
			$tmp_monitor = $monitors_hash[$tmp_monitor['parent_id']];
		}
		return implode(' > ', array_reverse($titles));
	}
}
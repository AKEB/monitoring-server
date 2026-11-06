<?php

class Monitors extends \DB\MySQLObject implements \PermissionSubject_Interface {
	static public $table = 'monitoring_monitor';

	const LOGS_OBJECT = 'Monitors';

	const PERMISSION_ACCESS = 'monitor';
	const PERMISSION_CREATE = 'create_monitor';

	const STATUS_DISABLED = 0;
	const STATUS_ENABLED = 1;

	const TYPE_HTTPS           = 0;
	const TYPE_FOLDER          = 1;
	const TYPE_TCP             = 2;
	const TYPE_PING            = 3;
	const TYPE_DOCKER          = 4;
	const TYPE_EXPORTER_DISK   = 5;
	const TYPE_EXPORTER_MEMORY = 6;
	const TYPE_EXPORTER_CPU    = 7;
	const TYPE_HTTP_JSON       = 8;
	const TYPE_EXTERNAL        = 9;
	const TYPE_SERVER_OFFLINE    = 10;
	const TYPE_SERVER_CPU        = 11;
	const TYPE_SERVER_MEMORY     = 12;
	const TYPE_SERVER_DISK       = 13;
	const TYPE_SERVER_CONTAINER  = 14;
	const TYPE_SERVER_NET_RX     = 15;
	const TYPE_SERVER_NET_TX     = 16;
	const TYPE_SERVER_PORT       = 17;
	const TYPE_UDP               = 18;

	/** Virtual worker_id for push-based External monitors (never a real Workers row). */
	const EXTERNAL_WORKER_ID = -1;

	/** Alert when every assigned worker failed. */
	const ALERT_FAIL_MODE_ALL = 0;
	/** Alert when at least half of assigned workers failed (default). */
	const ALERT_FAIL_MODE_MAJORITY = 1;
	/** Alert when at least one assigned worker failed. */
	const ALERT_FAIL_MODE_ANY = 2;

	static private array $monitors_hash = [];


	static private array $monitorsForAccess = [];

	static public function subject_hash(): array {
		$data_hash = [];
		$permissions_hash = static::get_folder_hash_options_hash(false);
		foreach($permissions_hash as $subject_id=>$permissionTitle) {
			if (!\Sessions::checkPermission(static::PERMISSION_ACCESS, $subject_id, ACCESS_WRITE)) {
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
			static::TYPE_EXPORTER_DISK => \T::Monitor_Type_ExporterDisk(),
			static::TYPE_EXPORTER_MEMORY => \T::Monitor_Type_ExporterMemory(),
			static::TYPE_HTTP_JSON => \T::Monitor_Type_HTTPJson(),
			static::TYPE_EXTERNAL => \T::Monitor_Type_External(),
			static::TYPE_SERVER_OFFLINE => \T::Monitor_Type_ServerOffline(),
			static::TYPE_SERVER_CPU => \T::Monitor_Type_ServerCPU(),
			static::TYPE_SERVER_MEMORY => \T::Monitor_Type_ServerMemory(),
			static::TYPE_SERVER_DISK => \T::Monitor_Type_ServerDisk(),
			static::TYPE_SERVER_CONTAINER => \T::Monitor_Type_ServerContainer(),
			static::TYPE_SERVER_NET_RX => \T::Monitor_Type_ServerNetRx(),
			static::TYPE_SERVER_NET_TX => \T::Monitor_Type_ServerNetTx(),
			static::TYPE_SERVER_PORT => \T::Monitor_Type_ServerPort(),
			static::TYPE_UDP => \T::Monitor_Type_UDP(),
			// static::TYPE_EXPORTER_CPU => \T::Monitor_Type_ExporterCPU(),
		];
	}

	/** @return array<string,string> */
	static public function port_protocol_hash(): array {
		return [
			'tcp' => 'TCP',
			'udp' => 'UDP',
		];
	}

	static public function normalize_port_protocol(mixed $raw): string {
		$v = strtolower(trim(strval($raw ?? '')));
		return $v === 'udp' ? 'udp' : 'tcp';
	}

	/** @return list<int> */
	static public function server_types(): array {
		return [
			static::TYPE_SERVER_OFFLINE,
			static::TYPE_SERVER_CPU,
			static::TYPE_SERVER_MEMORY,
			static::TYPE_SERVER_DISK,
			static::TYPE_SERVER_CONTAINER,
			static::TYPE_SERVER_NET_RX,
			static::TYPE_SERVER_NET_TX,
			static::TYPE_SERVER_PORT,
		];
	}

	/** Server network types that alert on Mbps thresholds. */
	static public function is_server_net_type(int $type): bool {
		return $type === static::TYPE_SERVER_NET_RX || $type === static::TYPE_SERVER_NET_TX;
	}

	static public function is_server_type(int $type): bool {
		return in_array($type, static::server_types(), true);
	}

	/** Types that store a numeric metric in response_error (not HTTP latency). */
	static public function is_metric_value_type(int $type): bool {
		return in_array($type, [
			static::TYPE_EXPORTER_DISK,
			static::TYPE_EXPORTER_MEMORY,
			static::TYPE_EXPORTER_CPU,
			static::TYPE_SERVER_OFFLINE,
			static::TYPE_SERVER_CPU,
			static::TYPE_SERVER_MEMORY,
			static::TYPE_SERVER_DISK,
			static::TYPE_SERVER_NET_RX,
			static::TYPE_SERVER_NET_TX,
		], true);
	}

	/** Hide response-time KPIs (latency is meaningless or unused). */
	static public function hides_response_time_ui(int $type): bool {
		return $type === static::TYPE_EXTERNAL
			|| static::is_server_type($type)
			|| in_array($type, [
				static::TYPE_EXPORTER_DISK,
				static::TYPE_EXPORTER_MEMORY,
				static::TYPE_EXPORTER_CPU,
			], true);
	}

	/** Y-axis unit for metric-value charts. */
	static public function metric_chart_unit(int $type): string {
		if (static::is_server_net_type($type)) {
			return 'Mbps';
		}
		if ($type === static::TYPE_SERVER_OFFLINE) {
			return 'min';
		}
		return '%';
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

	static public function alert_fail_mode_hash(): array {
		return [
			static::ALERT_FAIL_MODE_MAJORITY => \T::Monitor_Edit_AlertFailModeMajority(),
			static::ALERT_FAIL_MODE_ALL => \T::Monitor_Edit_AlertFailModeAll(),
			static::ALERT_FAIL_MODE_ANY => \T::Monitor_Edit_AlertFailModeAny(),
		];
	}

	static public function is_alert_fail_threshold(int $mode, int $failed, int $total): bool {
		if ($total < 1 || $failed < 1) {
			return false;
		}
		return match ($mode) {
			static::ALERT_FAIL_MODE_ANY => true,
			static::ALERT_FAIL_MODE_MAJORITY => ($failed * 2) >= $total,
			default => $failed >= $total,
		};
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

	static public function ssl_cert_expire_alert_days_list(): array {
		return [15, 7, 6, 5, 4, 3, 2, 1];
	}

	static public function ssl_cert_expire_alert_days_hash(): array {
		$return = [];
		foreach (static::ssl_cert_expire_alert_days_list() as $day) {
			$return[$day] = (string)$day;
		}
		return $return;
	}

	static public function ssl_cert_expire_alert_days_default(): string {
		return implode(',', static::ssl_cert_expire_alert_days_list());
	}

	/** @return int[] */
	static public function parse_ssl_cert_expire_alert_days(?string $value): array {
		$allowed = static::ssl_cert_expire_alert_days_list();
		if ($value === null || trim($value) === '') {
			return $allowed;
		}
		$days = array_map('intval', array_map('trim', explode(',', $value)));
		$days = array_values(array_intersect($days, $allowed));
		return $days ?: $allowed;
	}

	static private function setAccessForChild(&$data) {
		// vardump($data);
		if (!isset($data) || !$data) return;
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
			$data = static::get_hash();
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
					READ => \Sessions::checkPermission(static::PERMISSION_ACCESS, $item['id'], READ, $user),
					WRITE => \Sessions::checkPermission(static::PERMISSION_ACCESS, $item['id'], WRITE, $user),
					DELETE => \Sessions::checkPermission(static::PERMISSION_ACCESS, $item['id'], DELETE, $user),
					ACCESS_READ => \Sessions::checkPermission(static::PERMISSION_ACCESS, $item['id'], ACCESS_READ, $user),
					ACCESS_WRITE => \Sessions::checkPermission(static::PERMISSION_ACCESS, $item['id'], ACCESS_WRITE, $user),
					ACCESS_CHANGE => \Sessions::checkPermission(static::PERMISSION_ACCESS, $item['id'], ACCESS_CHANGE, $user),
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
		$access_hash = static::getMonitorsAccess($user);
		foreach(static::permissions_subject_hash() as $subject_id=>$_) {
			$permissions[static::PERMISSION_ACCESS][$subject_id] = [
				READ => isset($access_hash[$subject_id]['access'][READ]) && $access_hash[$subject_id]['access'][READ] ? 1 : 0,
				WRITE => isset($access_hash[$subject_id]['access'][WRITE]) && $access_hash[$subject_id]['access'][WRITE] ? 1 : 0,
				DELETE => isset($access_hash[$subject_id]['access'][DELETE]) && $access_hash[$subject_id]['access'][DELETE] ? 1 : 0,
				ACCESS_READ => isset($access_hash[$subject_id]['access'][ACCESS_READ]) && $access_hash[$subject_id]['access'][ACCESS_READ] ? 1 : 0,
				ACCESS_WRITE => isset($access_hash[$subject_id]['access'][ACCESS_WRITE]) && $access_hash[$subject_id]['access'][ACCESS_WRITE] ? 1 : 0,
				ACCESS_CHANGE => isset($access_hash[$subject_id]['access'][ACCESS_CHANGE]) && $access_hash[$subject_id]['access'][ACCESS_CHANGE] ? 1 : 0,
			];
			if (array_sum($permissions[static::PERMISSION_ACCESS][$subject_id]) == 0) {
				unset($permissions[static::PERMISSION_ACCESS][$subject_id]);
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
		$data = static::get_hash();
		$permissions_hash = [];
		foreach($data as $item) {
			$permissions_hash[$item['id']] = \Monitors::get_monitor_title_with_folders($item['id']);//   ($item['type'] == static::TYPE_FOLDER ? \T::Monitor_Permissions_Folder().': ' : \T::Monitor_Permissions_Monitor().': ') .$item['title'];
		}
		return $permissions_hash;
	}

	static public function permissions_hash(): array {
		return [
			static::PERMISSION_ACCESS => \T::Monitor_Permissions_Access(),
		];
	}

	static public function folders_hash(?bool $onlyFolders=true): array {
		$data = static::get_hash();
		$folders_hash = [];
		foreach($data as $item) {
			if ($onlyFolders && $item['type'] != static::TYPE_FOLDER) continue;
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
			if (!\Sessions::checkPermission(static::PERMISSION_ACCESS, $k, READ)) {
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
			$title = $v['title'];
			$v['type'] ??= static::TYPE_FOLDER;
			if ($v['type'] == static::TYPE_FOLDER) {
				$title = '['.$v['title'].']';
			}
			// Real NBSP chars: html_select escapes HTML entities, so "&nbsp;" would show literally
			$indent = "\u{00A0}\u{00A0}\u{00A0}|\u{00A0}\u{00A0}\u{00A0}";
			$options_hash[$v['id']] = str_repeat($indent, $level).$title;
			if ($v['children']) {
				static::get_folder_titles($options_hash, $v['children'], $level+1);
			}
		}
	}

	static public function get_folder_hash_options_hash(?bool $onlyFolders=true) {
		$folders_hash = static::folders_hash($onlyFolders);
		$options_hash = [];
		if ($onlyFolders) {
			$options_hash[0] = \T::Monitor_Table_Root();
			static::get_folder_titles($options_hash, $folders_hash, 1);
		} else {
			static::get_folder_titles($options_hash, $folders_hash, 0);
		}
		return $options_hash;
	}

	static public function get_hash(): array {
		if (!static::$monitors_hash) {
			$monitors = static::data(false, '', '`id`, `title`, `type`, `parent_id`, `status`');
			if ($monitors) {
				static::$monitors_hash = make_hash($monitors, 'id');
			}
		}
		return static::$monitors_hash;
	}

	static public function get_monitor_title_with_folders(?int $monitor_id): string {
		$title = '';
		if (!isset($monitor_id) || !$monitor_id) return $title;
		$monitors_hash = static::get_hash();
		if (!isset($monitors_hash[$monitor_id]) || !$monitors_hash[$monitor_id]) return $title;
		$tmp_monitor = $monitors_hash[$monitor_id];
		$titles = [];
		while(true) {
			$titles[] = $tmp_monitor['title'];
			if (!$tmp_monitor['parent_id']) break;
			$tmp_monitor = $monitors_hash[$tmp_monitor['parent_id']];
		}
		return implode(' > ', array_reverse($titles));
	}

	static public function is_enable(?int $monitor_id): bool {
		if (!isset($monitor_id) || !$monitor_id) return false;
		$monitors_hash = static::get_hash();
		if (!isset($monitors_hash[$monitor_id]) || !$monitors_hash[$monitor_id]) return false;

		$enable = true;
		$tmp_monitor = $monitors_hash[$monitor_id];
		while(true) {
			if ($tmp_monitor['status'] != static::STATUS_ENABLED) {
				$enable = false;
				break;
			}
			if (!$tmp_monitor['parent_id']) break;
			$tmp_monitor = $monitors_hash[$tmp_monitor['parent_id']];
		}
		return $enable;
	}
}
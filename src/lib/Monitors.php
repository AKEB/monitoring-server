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
	const TYPE_SERVER_PORT            = 17;
	const TYPE_UDP                    = 18;
	const TYPE_SERVER_TEMP            = 19;
	const TYPE_SERVER_UPTIME          = 20;
	const TYPE_SERVER_CONTAINER_CPU   = 21;
	const TYPE_SERVER_CONTAINER_MEMORY = 22;
	const TYPE_SERVER_SYSTEMD         = 23;
	const TYPE_SERVER_SYSTEMD_CPU     = 24;
	const TYPE_SERVER_SYSTEMD_MEMORY  = 25;
	const TYPE_DOMAIN                 = 26;
	const TYPE_DNS                    = 27;
	const TYPE_HTTP_KEYWORD           = 28;
	const TYPE_SMTP                   = 29;
	const TYPE_NTP                    = 30;
	const TYPE_WEBSOCKET              = 31;
	const TYPE_MYSQL                  = 32;
	const TYPE_POSTGRES               = 33;
	const TYPE_REDIS                  = 34;
	const TYPE_MONGODB                = 35;
	const TYPE_SFTP                   = 36;
	const TYPE_SNMP                   = 37;
	const TYPE_RADIUS                 = 38;
	const TYPE_GRPC_KEYWORD           = 39;
	const TYPE_MQTT                   = 40;
	const TYPE_RABBITMQ               = 41;
	const TYPE_KAFKA_PRODUCER         = 42;

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
			static::TYPE_HTTP_KEYWORD => \T::Monitor_Type_HTTPKeyword(),
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
			static::TYPE_SERVER_TEMP => \T::Monitor_Type_ServerTemp(),
			static::TYPE_SERVER_UPTIME => \T::Monitor_Type_ServerUptime(),
			static::TYPE_SERVER_CONTAINER_CPU => \T::Monitor_Type_ServerContainerCPU(),
			static::TYPE_SERVER_CONTAINER_MEMORY => \T::Monitor_Type_ServerContainerMemory(),
			static::TYPE_SERVER_SYSTEMD => \T::Monitor_Type_ServerSystemd(),
			static::TYPE_SERVER_SYSTEMD_CPU => \T::Monitor_Type_ServerSystemdCPU(),
			static::TYPE_SERVER_SYSTEMD_MEMORY => \T::Monitor_Type_ServerSystemdMemory(),
			static::TYPE_DOMAIN => \T::Monitor_Type_Domain(),
			static::TYPE_DNS => \T::Monitor_Type_DNS(),
			static::TYPE_SMTP => \T::Monitor_Type_SMTP(),
			static::TYPE_NTP => \T::Monitor_Type_NTP(),
			static::TYPE_WEBSOCKET => \T::Monitor_Type_Websocket(),
			static::TYPE_MYSQL => \T::Monitor_Type_MySQL(),
			static::TYPE_POSTGRES => \T::Monitor_Type_PostgreSQL(),
			static::TYPE_REDIS => \T::Monitor_Type_Redis(),
			static::TYPE_MONGODB => \T::Monitor_Type_MongoDB(),
			static::TYPE_SFTP => \T::Monitor_Type_SFTP(),
			static::TYPE_SNMP => \T::Monitor_Type_SNMP(),
			static::TYPE_RADIUS => \T::Monitor_Type_RADIUS(),
			static::TYPE_GRPC_KEYWORD => \T::Monitor_Type_GRPCKeyword(),
			static::TYPE_MQTT => \T::Monitor_Type_MQTT(),
			static::TYPE_RABBITMQ => \T::Monitor_Type_RabbitMQ(),
			static::TYPE_KAFKA_PRODUCER => \T::Monitor_Type_KafkaProducer(),
			// static::TYPE_EXPORTER_CPU => \T::Monitor_Type_ExporterCPU(),
		];
	}

	/** HTTP / Keyword / Websocket share request body + auth fields. */
	static public function is_http_request_type(int $type): bool {
		return in_array($type, [
			static::TYPE_HTTPS,
			static::TYPE_HTTP_JSON,
			static::TYPE_HTTP_KEYWORD,
			static::TYPE_WEBSOCKET,
		], true);
	}

	/** @return array<string,string> */
	static public function http_body_encoding_hash(): array {
		return [
			'json' => 'JSON',
			'form' => 'Form',
			'xml' => 'XML',
			'raw' => 'Raw',
		];
	}

	static public function normalize_http_body_encoding(mixed $raw): string {
		$v = strtolower(trim(strval($raw ?? '')));
		return isset(static::http_body_encoding_hash()[$v]) ? $v : 'json';
	}

	/** @return array<string,string> */
	static public function auth_method_hash(): array {
		return [
			'none' => 'None',
			'basic' => 'Basic Auth',
			'bearer' => 'Bearer Token',
			'oauth2_cc' => 'OAuth2 Client Credentials',
			'mtls' => 'mTLS',
		];
	}

	static public function normalize_auth_method(mixed $raw): string {
		$v = strtolower(trim(strval($raw ?? '')));
		return isset(static::auth_method_hash()[$v]) ? $v : 'none';
	}

	/** @return array<string,string> */
	static public function oauth_auth_method_hash(): array {
		return [
			'client_secret_basic' => 'Client Secret Basic',
			'client_secret_post' => 'Client Secret Post',
		];
	}

	static public function normalize_oauth_auth_method(mixed $raw): string {
		$v = strtolower(trim(strval($raw ?? '')));
		return isset(static::oauth_auth_method_hash()[$v]) ? $v : 'client_secret_basic';
	}

	/** @return array<string,string> */
	static public function smtp_security_hash(): array {
		return [
			'none' => 'None',
			'starttls' => 'STARTTLS',
			'tls' => 'TLS',
		];
	}

	static public function normalize_smtp_security(mixed $raw): string {
		$v = strtolower(trim(strval($raw ?? '')));
		return isset(static::smtp_security_hash()[$v]) ? $v : 'none';
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
			static::TYPE_SERVER_TEMP,
			static::TYPE_SERVER_UPTIME,
			static::TYPE_SERVER_CONTAINER_CPU,
			static::TYPE_SERVER_CONTAINER_MEMORY,
			static::TYPE_SERVER_SYSTEMD,
			static::TYPE_SERVER_SYSTEMD_CPU,
			static::TYPE_SERVER_SYSTEMD_MEMORY,
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
			static::TYPE_SERVER_TEMP,
			static::TYPE_SERVER_UPTIME,
			static::TYPE_SERVER_CONTAINER_CPU,
			static::TYPE_SERVER_CONTAINER_MEMORY,
			static::TYPE_SERVER_SYSTEMD_CPU,
			static::TYPE_SERVER_SYSTEMD_MEMORY,
			static::TYPE_DOMAIN,
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
				static::TYPE_DOMAIN,
			], true);
	}

	/** Y-axis unit for metric-value charts. */
	static public function metric_chart_unit(int $type): string {
		if (static::is_server_net_type($type)) {
			return 'Mbps';
		}
		if ($type === static::TYPE_SERVER_OFFLINE || $type === static::TYPE_SERVER_UPTIME) {
			return 'min';
		}
		if ($type === static::TYPE_SERVER_TEMP) {
			return '°C';
		}
		if ($type === static::TYPE_SERVER_SYSTEMD_MEMORY) {
			return 'MB';
		}
		if ($type === static::TYPE_DOMAIN) {
			return 'days';
		}
		return '%';
	}

	/** Types that store a named target (sensor/container/unit) in `container`. */
	static public function uses_container_name_field(int $type): bool {
		return in_array($type, [
			static::TYPE_SERVER_CONTAINER,
			static::TYPE_SERVER_CONTAINER_CPU,
			static::TYPE_SERVER_CONTAINER_MEMORY,
			static::TYPE_SERVER_SYSTEMD,
			static::TYPE_SERVER_SYSTEMD_CPU,
			static::TYPE_SERVER_SYSTEMD_MEMORY,
		], true);
	}

	static public function is_server_container_metric_type(int $type): bool {
		return $type === static::TYPE_SERVER_CONTAINER_CPU
			|| $type === static::TYPE_SERVER_CONTAINER_MEMORY;
	}

	static public function is_server_systemd_metric_type(int $type): bool {
		return $type === static::TYPE_SERVER_SYSTEMD_CPU
			|| $type === static::TYPE_SERVER_SYSTEMD_MEMORY;
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

	/** @return list<int> */
	static public function domain_expire_alert_days_list(): array {
		return [14, 7, 6, 5, 4, 3, 2, 1, 0];
	}

	static public function domain_expire_alert_days_hash(): array {
		$return = [];
		foreach (static::domain_expire_alert_days_list() as $day) {
			$return[$day] = (string)$day;
		}
		return $return;
	}

	static public function domain_expire_alert_days_default(): string {
		return implode(',', static::domain_expire_alert_days_list());
	}

	/** @return int[] */
	static public function parse_domain_expire_alert_days(?string $value): array {
		$allowed = static::domain_expire_alert_days_list();
		if ($value === null || trim($value) === '') {
			return $allowed;
		}
		$days = array_map('intval', array_map('trim', explode(',', $value)));
		$days = array_values(array_intersect($days, $allowed));
		return $days ?: $allowed;
	}

	/** Max selected alert day (FAIL while remaining days <= this). */
	static public function domain_expire_alert_days_max(?string $value): int {
		$days = static::parse_domain_expire_alert_days($value);
		return $days ? max($days) : max(static::domain_expire_alert_days_list());
	}

	/** @return array<string,string> */
	static public function dns_record_type_hash(): array {
		return [
			'A' => 'A',
			'AAAA' => 'AAAA',
			'CNAME' => 'CNAME',
			'MX' => 'MX',
			'TXT' => 'TXT',
			'NS' => 'NS',
		];
	}

	static public function normalize_dns_record_type(mixed $raw): string {
		$v = strtoupper(trim(strval($raw ?? '')));
		return isset(static::dns_record_type_hash()[$v]) ? $v : 'A';
	}

	/** @return array<string,string> */
	static public function dns_transport_hash(): array {
		return [
			'auto' => 'Auto (UDP→TCP)',
			'udp' => 'UDP',
			'tcp' => 'TCP',
			'dot' => 'DoT (TLS)',
			'doh' => 'DoH (HTTPS)',
		];
	}

	static public function normalize_dns_transport(mixed $raw): string {
		$v = strtolower(trim(strval($raw ?? '')));
		return isset(static::dns_transport_hash()[$v]) ? $v : 'auto';
	}

	/** @return array<string,string> */
	static public function dns_match_mode_hash(): array {
		return [
			'exact' => 'Exact (one value)',
			'any' => 'Any of list',
			'all' => 'All of list present',
			'exact_set' => 'Exact set (full match)',
			'contains' => 'Contains (TXT)',
			'regex' => 'Regex (TXT)',
			'mx_host' => 'MX hostname only',
			'mx_priority_host' => 'MX priority + hostname',
			'nxdomain' => 'Must not exist (NXDOMAIN)',
		];
	}

	static public function normalize_dns_match_mode(mixed $raw): string {
		$v = strtolower(trim(strval($raw ?? '')));
		return isset(static::dns_match_mode_hash()[$v]) ? $v : 'exact_set';
	}

	/** @return array<string,string> DoH preset label => URL */
	static public function dns_doh_presets(): array {
		return [
			'Google' => 'https://dns.google/dns-query',
			'Cloudflare' => 'https://cloudflare-dns.com/dns-query',
			'Quad9' => 'https://dns.quad9.net/dns-query',
		];
	}

	/** @return array<string,string> Public DNS resolver preset label => IP */
	static public function dns_resolver_presets(): array {
		return [
			'Google (8.8.8.8)' => '8.8.8.8',
			'Google (8.8.4.4)' => '8.8.4.4',
			'Cloudflare (1.1.1.1)' => '1.1.1.1',
			'Cloudflare (1.0.0.1)' => '1.0.0.1',
			'Quad9 (9.9.9.9)' => '9.9.9.9',
			'Quad9 (149.112.112.112)' => '149.112.112.112',
			'OpenDNS (208.67.222.222)' => '208.67.222.222',
			'OpenDNS (208.67.220.220)' => '208.67.220.220',
			'AdGuard (94.140.14.14)' => '94.140.14.14',
			'AdGuard (94.140.15.15)' => '94.140.15.15',
			'Yandex (77.88.8.8)' => '77.88.8.8',
			'Yandex (77.88.8.1)' => '77.88.8.1',
		];
	}

	/** Normalize expected DNS answers: one value per line. */
	static public function normalize_dns_expected(mixed $raw): string {
		if (is_array($raw)) {
			$lines = array_map(static fn($v) => trim(strval($v)), $raw);
		} else {
			$text = str_replace(["\r\n", "\r"], "\n", strval($raw ?? ''));
			$lines = preg_split('/\n+/', $text) ?: [];
			$lines = array_map('trim', $lines);
		}
		$lines = array_values(array_filter($lines, static fn($v) => $v !== ''));
		return implode("\n", $lines);
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

	/**
	 * Non-folder monitor ids under $root_id (recursive).
	 * Includes $root_id itself when it is not a folder.
	 *
	 * @param array<int,array<string,mixed>>|null $monitors_hash
	 * @return list<int>
	 */
	static public function get_leaf_monitor_ids_under(int $root_id, ?array $monitors_hash = null): array {
		if ($root_id < 1) {
			return [];
		}
		$monitors_hash ??= static::get_hash();
		if (!isset($monitors_hash[$root_id])) {
			return [];
		}

		/** @var array<int,list<int>> $children */
		$children = [];
		foreach ($monitors_hash as $id => $monitor) {
			$parent_id = intval($monitor['parent_id'] ?? 0);
			if ($parent_id < 1) {
				continue;
			}
			$children[$parent_id][] = intval($id);
		}

		$ids = [];
		$stack = [$root_id];
		$seen = [];
		while ($stack) {
			$id = array_pop($stack);
			if (isset($seen[$id])) {
				continue;
			}
			$seen[$id] = true;
			$monitor = $monitors_hash[$id] ?? null;
			if (!$monitor) {
				continue;
			}
			$type = intval($monitor['type'] ?? -1);
			if ($type !== static::TYPE_FOLDER) {
				$ids[] = $id;
			}
			foreach ($children[$id] ?? [] as $child_id) {
				$stack[] = $child_id;
			}
		}
		return $ids;
	}
}
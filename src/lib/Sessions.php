<?php

class Sessions extends \DB\MySQLObject{
	static public $table = 'sessions';

	private static string $session_name = 'session_uid';
	private static string $sessionId = '';

	private static array $current_user = [];

	private static int $sessionLifeTime = 7*86400;

	public static function currentUser(): array {
		return static::$current_user;
	}

	static private function client_ip() {
		if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
			$ips = explode(',',$_SERVER['HTTP_X_REAL_IP']);
			$ip = trim(end($ips));
		} elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
			$ips = explode(',',$_SERVER['HTTP_CLIENT_IP']);
			$ip = trim(end($ips));
		} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$ips = explode(',',$_SERVER['HTTP_X_FORWARDED_FOR']);
			$ip = trim(end($ips));
		} else {
			$ip=$_SERVER['REMOTE_ADDR'];
		}
		return $ip;
	}

	static public function session_init(bool $WithoutRedirect=false) {
		static::$current_user = [];
		do {
			if (
				!isset($_COOKIE[static::$session_name]) ||
				!$_COOKIE[static::$session_name] ||
				strlen($_COOKIE[static::$session_name]) < 128
			) {
				static::$sessionId = hash('sha512', md5(
					static::client_ip() .
					($_SERVER['HTTP_USER_AGENT'] ?? '') .
					microtime() .
					random_int(100000000,999999999)
				));
				setcookie(static::$session_name, static::$sessionId, time() + static::$sessionLifeTime, '/');
				$_COOKIE[static::$session_name] = static::$sessionId;
			}

			static::$sessionId = $_COOKIE[static::$session_name] ?? '';
			if (!static::$sessionId) {
				break;
			}

			$session = static::get(['id' => static::$sessionId]);
			if (!$session) break;
			$userId = '';
			if (!empty($session['userId'])) {
				$userId = $session['userId'];
			}
			if (!$userId) break;
			$user = Users::get(['id' => $userId]);
			if (!isset($user) || !is_array($user) || !$user) break;
			if ($user['status'] != \Users::STATUS_ACTIVE) break;
			static::$current_user = $user;
		} while(0);

		if (!static::$current_user) {
			if ($WithoutRedirect) return false;
				common_redirect('/login.php', ['target' => urlencode($_SERVER['REQUEST_URI'])]);
				return false;
		}
		return true;
	}
}
<?php

class Config {
	public readonly string $timezone;

	public readonly string $mysql_host;
	public readonly int $mysql_port;
	public readonly string $mysql_username;
	public readonly string $mysql_password;
	public readonly string $mysql_db_name;

	public readonly bool $mysql_dont_use_slave;

	public readonly string $mysql_slave_host;
	public readonly int $mysql_slave_port;
	public readonly string $mysql_slave_username;
	public readonly string $mysql_slave_password;
	public readonly string $mysql_slave_db_name;

	public readonly string $password_salt;

	private static $_instance;

	// public function __construct() {
	// 	$this->server_url = strval($_ENV['SERVER_URL'] ?? '');
	// }

	private function __construct() {}
	private function __clone() {}
	public function __wakeup() {}

	public static function getInstance():self {
		if (self::$_instance === null) {
			self::$_instance = new self;

			self::$_instance->timezone = strval($_ENV['TZ'] ?? 'UTC');

			self::$_instance->mysql_host = strval($_ENV['MYSQL_HOST'] ?? 'localhost');
			self::$_instance->mysql_port = intval($_ENV['MYSQL_PORT'] ?? 3306);
			self::$_instance->mysql_username = strval($_ENV['MYSQL_USERNAME'] ?? 'root');
			self::$_instance->mysql_password = strval($_ENV['MYSQL_PASSWORD'] ?? '');
			self::$_instance->mysql_db_name = strval($_ENV['MYSQL_DB_NAME'] ?? 'monitoring');

			self::$_instance->mysql_dont_use_slave = boolval($_ENV['MYSQL_DONT_USE_SLAVE'] ?? true);

			self::$_instance->mysql_slave_host = strval($_ENV['MYSQL_SLAVE_HOST'] ?? self::$_instance->mysql_host);
			self::$_instance->mysql_slave_port = intval($_ENV['MYSQL_SLAVE_PORT'] ?? self::$_instance->mysql_port);
			self::$_instance->mysql_slave_username = strval($_ENV['MYSQL_SLAVE_USERNAME'] ?? self::$_instance->mysql_username);
			self::$_instance->mysql_slave_password = strval($_ENV['MYSQL_SLAVE_PASSWORD'] ?? self::$_instance->mysql_password);
			self::$_instance->mysql_slave_db_name = strval($_ENV['MYSQL_SLAVE_DB_NAME'] ?? self::$_instance->mysql_db_name);

			self::$_instance->password_salt = "bHchLzC3B99Ss2ghc2gkDdtgCG7vKtoj";
		}
		return self::$_instance;
	}

	public function toArray() {
		return [
			'timezone' => $this->timezone,

			'mysql_host' => $this->mysql_host,
			'mysql_port' => $this->mysql_port,
			'mysql_username' => $this->mysql_username,
			'mysql_password' => $this->mysql_password,
			'mysql_db_name' => $this->mysql_db_name,

			'mysql_dont_use_slave' => $this->mysql_dont_use_slave,

			'mysql_slave_host' => $this->mysql_slave_host,
			'mysql_slave_port' => $this->mysql_slave_port,
			'mysql_slave_username' => $this->mysql_slave_username,
			'mysql_slave_password' => $this->mysql_slave_password,
			'mysql_slave_db_name' => $this->mysql_slave_db_name,
		];
	}

	public function __debugInfo() {
		return $this->toArray();
	}

	public function __toString() {
		return json_encode($this->toArray());
	}
}
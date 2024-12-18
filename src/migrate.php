<?php
require_once("./autoload.php");
set_time_limit(0);

error_log("Migrates Running");

$migrate = new \DB\Initialize(
	\Config::getInstance()->mysql_host,
	\Config::getInstance()->mysql_username,
	\Config::getInstance()->mysql_password,
	\Config::getInstance()->mysql_db_name,
	\Config::getInstance()->mysql_port
);

if (isset($_SERVER['argv']) && is_array($_SERVER['argv']) && isset($_SERVER['argv'][1])) {
	$action = $_SERVER['argv'][1];
	if ($action == 'rollback') {
		\Migrate::rollback(array_slice($_SERVER['argv'], 2));
	}
} else {
	\Migrate::apply();
}

error_log("Migrates Finished");

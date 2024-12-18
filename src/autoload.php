<?php
require_once("./version.php");
require_once("./vendor/autoload.php");
require_once("./include/placeholder.php");
require_once("./include/common.php");

ini_set('zend.exception_ignore_args', 0);

error_reporting(E_ALL &~ E_NOTICE);

global $PWD;
$PWD = __DIR__;
if (!defined('SERVER_ROOT')) define("SERVER_ROOT", $PWD.'/');

\Config::getInstance();

date_default_timezone_set(\Config::getInstance()->timezone);

srand(intval(round(microtime(true)*100)));
mt_srand(intval(round(microtime(true)*100)));

new \T();

global $dbs_slaves, $dbs_masters;
global $db, $db_slave;
global $common_cache, $common_cache_active;
global $use_slave;

$db = new \DB\Database(
	(\Config::getInstance()->mysql_host).':'.(\Config::getInstance()->mysql_port),
	\Config::getInstance()->mysql_db_name,
	\Config::getInstance()->mysql_username,
	\Config::getInstance()->mysql_password,
	'UTF8'
);

$db_slave = new \DB\Database(
	(\Config::getInstance()->mysql_slave_host).':'.(\Config::getInstance()->mysql_slave_port),
	\Config::getInstance()->mysql_slave_db_name,
	\Config::getInstance()->mysql_slave_username,
	\Config::getInstance()->mysql_slave_password,
	'UTF8'
);
if (\Config::getInstance()->mysql_dont_use_slave) {
	$db_slave->do_not_check_slave_status = true;
}
$db_slave->database_master = false;
$dbs_slaves[\Config::getInstance()->mysql_db_name] = $db_slave;
$dbs_masters[\Config::getInstance()->mysql_slave_db_name] = $db;

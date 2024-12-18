<?php

namespace DB;

function Database_seconds_cmp($a, $b) {
	if ($a['seconds'] == $b['seconds']) {
		return 0;
	}
	return ($a['seconds'] < $b['seconds']) ? -1 : 1;
}

class Database {
	private $dbh;
	public $database_name = "";
	public $database_node = false;
	public $database_master = true;
	public $host_name = "";
	public $host_names = [];
	public $database_user = "";
	public $database_password = "";

	public $do_not_check_slave_status = false;

	var $mysqli = true;

	var $debug_array = [];
	var $is_debug = 0;//defined('DEVS') && DEVS ? 1 : 0;  // 0 -  нет, 1 - запросы, 2 - запросы+результаты
	var $is_file_debug = false;
	var $die_on_error = true;
	var $file_debug;
	var $connection_error='';
	var $last_error='';
	var $charset = false;

	public function __toString(): string {
		return '\DB\Database';
	}

	function connect($returnStatus=false){
		if (!$returnStatus && $this->dbh) return true;

		shuffle($this->host_names);
		$i=0;
		$dbhs = [];
		foreach ($this->host_names as $host) {
			$this->host_name = $host;

			$dbh_t = [];
			$dbh_t['host_name'] = $this->host_name;
			$host = explode(":", $this->host_name);
			if (!isset($host[1])) $host[1] = null;

			$this->dbh = mysqli_init();
			if (!$this->dbh) {
				error_log(sprintf("Can't init mysql host: %s %s",$this->host_name,$this->database_name)."; Continue");
				$i++;
				continue;
			}

			// if (!$this->dbh->options(MYSQLI_INIT_COMMAND, 'SET AUTOCOMMIT = 0')) {
			// 	die('Setting MYSQLI_INIT_COMMAND failed');
			// }

			if (!$this->dbh->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10)) {
				error_log(sprintf("Can't set connect timeout to host: %s %s",$this->host_name,$this->database_name)."; Continue");
				$i++;
				continue;
			}

			if (!$this->dbh->real_connect($host[0], $this->database_user, $this->database_password, $this->database_name,$host[1])) {
				error_log(sprintf("Can't connect to host: %s %s",$this->host_name,$this->database_name)."; Continue");
				$i++;
				continue;
			}

			global $DEBUG_MYSQL;
			if ($DEBUG_MYSQL) error_log('mysql connect '.getmypid().' '.$host[0].':'.$host[1]);

			$dbhs[$i] = $dbh_t;
			$dbhs[$i]['dbh'] = $this->dbh;

			if ($this->database_master) {
				break;
			}
			if (!$returnStatus && count($this->host_names) < 2) break;

			$result = [];
			$cache = new \Cache($this->host_name.'_slave');
			if (!$cache->isValid()) {
				$die = $this->die_on_error;
				$this->die_on_error = false;
				$this->db_GetQueryRow('SHOW SLAVE STATUS;',$result);
				$this->die_on_error = $die;
				$cache->update(serialize($result),30);
			} else {
				$result = unserialize($cache->get());
			}

			$dbhs[$i]['Slave_IO_Running'] = strval($result['Slave_IO_Running']);
			$dbhs[$i]['Slave_SQL_Running'] = strval($result['Slave_SQL_Running']);
			$dbhs[$i]['seconds'] = intval($result['Seconds_Behind_Master']);

			if ($dbhs[$i]['Slave_IO_Running'] != 'Yes' || $dbhs[$i]['Slave_SQL_Running'] != 'Yes') {
				$dbhs[$i]['seconds'] = 66666;
			}

			if (!$returnStatus && $dbhs[$i]['seconds'] < 300) break;

			$i++;

		}
		if ($returnStatus) {
			return $dbhs;
		}

		if (!count($dbhs)) {
			$this->connection_error="Can't connect to host: ".implode(", ",$this->host_names)."; die";
			$code = 0;
			if (!$this->database_master) {
				error_log('Slave is down. Try Select from master!');
				$code = 301;
			}
			throw new \Exception(date('[Y-m-d H:i:s] ').$this->connection_error, $code);
		}

		if (count($dbhs) > 1) {
			usort($dbhs,"Database_seconds_cmp");
		}

		for ($i=1;$i<count($dbhs);$i++) {
			mysqli_close($dbhs[$i]['dbh']);
		}

		$k = 0;
		if (isset($dbhs[$k]['seconds']) && $dbhs[$k]['seconds'] == 66666 && !$this->database_master) {
			error_log('Slave is down. Try Select from master!');
			$code = 301;
			throw new \Exception(date('[Y-m-d H:i:s]').' Slave is down. Try Select from master!', $code);
		}
		$this->dbh = $dbhs[$k]['dbh'];
		$this->host_name = $dbhs[$k]['host_name'];

		if ($this->charset && ($this->charset != 'UTF8' || (defined('DEVS') && constant('DEVS')))) mysqli_query($this->dbh, "SET NAMES '".$this->charset."'");
		return true;
	}

	function __construct($host_names, $database_name, $database_user, $database_password, $charset=false) {
		$this->host_names = explode(';', $host_names);

		// $index = count($host_names) <= 1 ? 0 : rand(0, count($host_names) - 1);
		// $host_name = $host_names[$index];
		// $this->host_name = $host_name;

		$this->database_name = $database_name;
		$this->database_user = $database_user;
		$this->database_password = $database_password;
		$this->charset = $charset;
		if ($this->is_file_debug) {
			$this->file_debug = fopen($_SERVER['DOCUMENT_ROOT']."/database.log", "a+");
		}

		if (defined('DEVS') && constant('DEVS')) {
			$this->is_debug = 1;
		}

		$this->init();
	}

	function close() {
		mysqli_close($this->dbh);
		$this->dbh = null;
		//fclose($this->file_debug);
	}

	function init() {}

	function error() { # get last error
		return count($this->debug_array)? $this->debug_array[count($this->debug_array)-1]->error : false;
	}

	function getmicrotime(){
		list($usec, $sec) = explode(" ",microtime());
		return ((float)$usec + (float)$sec);
	}

	/**
	 * execute sql string
	 * additional ~ "and visible=0 limit 100,10"
	 */
	function execSQL($sql, $additional = "",$count=0) {
		if (!$this->dbh && !$this->connect()) return false;

		$baseSql = $sql;
		$baseadditional = $additional;

		$_startTime = microtime(true);

		if ($this->is_debug) {
			$item = new DebugSQL();
			$item->sql = $sql . " " . $additional;
			$item->time_start = $this->getmicrotime();
		}

		if ($additional) $sql .= " " . $additional;
		$sql = '/* '.$_SERVER['PHP_SELF'].' '.getmypid().' '.$this->host_name.' */ '.$sql;
		if (!(@$result = mysqli_query($this->dbh, $sql))) {
			$is_error = true;
			$error = mysqli_error($this->dbh);
			if (!mysqli_ping($this->dbh) || strpos($error, 'server has gone away') !== false) { // если нет соединения попробовать пересоединиться и повторить
				$this->close();
				if(!$this->connect()) {
					throw new \Exception($this->connection_error);
				}
				if (@$result = mysqli_query($this->dbh, $sql)) {
					$is_error = false;
				}
			}
			if ($is_error) {
				$this->last_error = $error;
				if ($count < 10 && (strpos($this->last_error, 'try restarting transaction') !== false || strpos($this->last_error, 'server has gone away') !== false)) {
					$error_text = sprintf("[%s] #%s %s [Try again] (%s)", date('Y-m-d H:i:s'), mysqli_errno($this->dbh), $this->last_error, substr($sql,0,150));
					// error_log($error_text);
					// error_log(sprintf("Can't exec query: %s %s",$this->host_name,$this->database_user)."; Try again");
					$this->close();
					return $this->execSQL($baseSql, $baseadditional,++$count);
				}
				if ($this->is_debug) {
					$item->error = $this->last_error;
				}
				if ($this->die_on_error) {
					$error_text = sprintf("[%s] #%s %s (%s)", date('Y-m-d H:i:s'), mysqli_errno($this->dbh), $this->last_error, $sql);
					error_log($error_text);
					error_log(sprintf("Can't exec query: %s %s",$this->host_name,$this->database_user)."; die");
					throw new \Exception($error_text);
				}
			}
		}
		global $DEBUG_MYSQL;
		if ($DEBUG_MYSQL) {
			error_log(getmypid().' '.$sql);
		}

		if ($this->is_debug) {
			$item->time_end = $this->getmicrotime();
			if($this->is_debug==2 && !$item->error){
				$list = [];
				while (@$row = mysqli_fetch_array($result,MYSQLI_ASSOC)) $list[] = $row;
				@(mysqli_data_seek($result,0));
				@($item->result=$list);
			}
			$this->debug_array[] = $item;

			if ($item->time_end - $item->time_start > 0.9) {
				error_log("LONG [ ".($item->time_end - $item->time_start)." ]".($item->sql));
				error_log(sprintf("Host: %s User: %s",$this->host_name,$this->database_user));
			}
		}

		if ($this->is_file_debug) {
			$debug_info = debug_backtrace();
			$f = '';
			for ($i = count($debug_info)-1; $i >= 0; $i--) $f .= $debug_info[$i]['function'].'()->';
			fwrite($this->file_debug,
				"\n".date("H:i:s").": (".($item->time_end-$item->time_start).') '.
				$debug_info[0]['file'].':'.$debug_info[0]['line'].': '.$debug_info[1]['function'].'() ('.$f.')'."\n".
				$sql . " " . $additional."\n"
			);
		}

		global $__MysqlScriptTime;
		$__MysqlScriptTime += microtime(true) - $_startTime;

		return $result;
	}

	function getTables() {
		if (!$this->dbh && !$this->connect()) return false;
		$list = [];
		$result = [];
		$this->db_GetQueryArray('SHOW TABLES FROM '.$this->database_name, $result);
		foreach ($result as $v) {
			foreach ($v as $v2) {
				$list[] = $v2;
			}
		}
		return $list;
	}

	function getFields($table_name) {
		if (!$this->dbh && !$this->connect()) return false;

		$list = [];
		$result = [];
		$this->db_GetQueryArray('SHOW COLUMNS FROM '.$table_name, $result);
		$i = 0;
		foreach ($result as $item) {
			$field = new Field();
			$field->name = $item['Field'];
			$field->type = strtoupper($item['Type']);
			if ($item['Null'] != 'NO') $field->flags .= " NOT NULL ";
			if ($item['Key'] == 'PRI') $field->flags .= " PRIMARY KEY ";
			if ($item['Key'] == 'UNI') $field->flags .= " UNIQUE KEY ";
			if ($item['Key'] == 'MUL') $field->index = $field->name;
			if ($item['Extra'] == 'auto_increment') $field->flags .= " AUTO_INCREMENT ";
			$field->def = $item['Default'];
			$list[] = $field;
			$i++;
		}
		return $list;
	}

	function TableExists($table_name) {
		$t = $this->die_on_error;
		$this->die_on_error = false;
		$status = ($this->execSQL("SELECT COUNT(*) FROM $table_name LIMIT 1"));
		$this->die_on_error = $t;
		if ($status === false) {
			return false;
		}
		return true;
	}

	function FieldExists($table_name, $field_name) {
		if (!($this->execSQL("SELECT COUNT($field_name) FROM $table_name LIMIT 1"))) {
			return false;
		}
		return true;
	}

# ******************************************************************************

	function db_GetQueryArray($sql, &$result)  {
		$result = [];
		if (!($db_result = $this->execSQL($sql))) return false;
		while (is_array($row = mysqli_fetch_array($db_result, MYSQLI_ASSOC))) $result[] = $row;
		mysqli_free_result($db_result);
		return true;
	}

	function db_GetQueryRow($sql, &$result)  {
		$result = [];
		if (!($db_result = $this->execSQL($sql))) return false;
		if (is_array($row = mysqli_fetch_array($db_result, MYSQLI_ASSOC))) $result = $row;
		mysqli_free_result($db_result);
		return true;
	}

	function db_GetQueryCol($sql, &$result)  {
		$result = [];
		if (!($db_result = $this->execSQL($sql))) return false;
		while (is_array($row = mysqli_fetch_array($db_result))) $result[] = $row[0];
		mysqli_free_result($db_result);
		return true;
	}

	function db_GetQueryHash($sql, &$result)  {
		$result = [];
		if (!($db_result = $this->execSQL($sql))) return false;
		while (is_array($row = mysqli_fetch_array($db_result))) $result[$row[0]] = $row[1];
		mysqli_free_result($db_result);
		return true;
	}

	function db_GetQueryVal($sql, &$result, $default='')  {
		$result = $default;
		if (!($db_result = $this->execSQL($sql))) return false;
		$row = mysqli_fetch_array($db_result);
		mysqli_free_result($db_result);
		if (!$row) return false;
		$result = $row[0];
		return true;
	}

	function db_ExecQuery($sql)  {
		if (!($this->execSQL($sql))) return false;
		$res = mysqli_affected_rows($this->dbh);
		if (!$res) $res=-1;
		return $res;
	}

	function insert_id() {
		return mysqli_insert_id($this->dbh);
	}

	function affected_rows() {
		return mysqli_affected_rows($this->dbh);
	}

	function db_NextRow(&$result) {
		return mysqli_fetch_array($result, MYSQLI_ASSOC);
	}


}

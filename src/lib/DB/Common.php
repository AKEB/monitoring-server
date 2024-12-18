<?php

namespace DB;

class Common {

	const CSMODE_INSERT = 1;
	const CSMODE_UPDATE = 2;
	const CSMODE_REPLACE = 3;

	public function __toString(): string {
		return '\DB\Common';
	}

	private static function &find_dbobj(\DB\Database &$db_obj, string $table_name, ?bool $from_slave = false) {
		global $dbs_slaves;
		$db_name = ($db_obj->database_node ? 'node_'.($db_obj->database_node).'_':'').$db_obj->database_name;
		if ($db_obj && $from_slave && isset($dbs_slaves[$db_name])) {
			$db_obj = $dbs_slaves[$db_name];
		}
		return $db_obj;
	}

	private static function &get_params(array $item, mixed $keys, bool $skip_null=false, bool $skip_empty=false) {
		if (!is_array($keys)) $keys = preg_split("/[\s,;]+/", $keys);
		$arr = [];
		foreach ($keys as $k) {
			if ($skip_null && !isset($item[$k])) continue;
			if ($skip_empty && empty($item[$k])) continue;
			$arr[$k] = $item[$k];
		}
		return $arr;
	}

	public static function get(\DB\Database &$db_obj, string $table_name, mixed $ref=false, string $add='', string $ref_name='id', bool $no_sql_cache=false, bool $no_local_cache=false, array $params=[]) {
		global $common_cache, $common_cache_active, $common_cache_get_active, $dbs_masters, $use_slave;

		$from_slave = false;
		if (isset($params['from_slave'])) {
			$from_slave = $params['from_slave'];
		} else {
			$from_slave = $use_slave;
		}
		$db_obj = &static::find_dbobj($db_obj, $table_name, $from_slave);
		if (!$ref && !$add) {
			if ($from_slave && !empty($dbs_masters[($db_obj->database_node ? 'node_'.($db_obj->database_node).'_':'').$db_obj->database_name])) {
				$db_obj = $dbs_masters[($db_obj->database_node ? 'node_'.($db_obj->database_node).'_':'').$db_obj->database_name];
			}
			return false;
		}
		$index_add = isset($params['_index_hint']) ? ' '.$params['_index_hint'] : '';
		$query = 'SELECT ' . ($no_sql_cache ? 'SQL_NO_CACHE ' : '').'* FROM `'.$table_name.'` AS t '.$index_add.' WHERE 1 ';
		if ($ref) {
			if (!is_array($ref)) $ref = [$ref_name => $ref];
			foreach ($ref as $k=>$v) {
				$k = str_replace('`', '', $k); /* ` */
				$query .= is_array($v) ?
					sql_pholder(' AND `'.$k.'` IN ( ?@ )', $v ) :
					( isset($v) ?
						sql_pholder(' AND `'.$k.'`=?', $v) :
						' AND `'.$k.'` IS NULL'
					);
			}
		}
		$query .= $add." LIMIT 1";
		$ckey = md5($query);
		$raw_data = [];
		if (($common_cache_active || $common_cache_get_active) && !$no_local_cache && isset($common_cache[($db_obj->database_node ? 'node_'.($db_obj->database_node).'_':'').$db_obj->database_name.'.'.$table_name]['get'][$ckey])) {
			$raw_data = $common_cache[($from_slave ? 'slave' : 'master').($db_obj->database_node ? 'node_'.($db_obj->database_node).'_':'').$db_obj->database_name.'.'.$table_name]['get'][$ckey];
		} else {
			try {
				pf_inc('mysql.get.'.$table_name);
				$db_obj->db_GetQueryRow($query,$raw_data);
			} catch(\Exception $ex) {
				if ($ex->getCode() == 301) {
					$params['from_slave'] = false;
					$db_obj = $dbs_masters[($db_obj->database_node ? 'node_'.($db_obj->database_node).'_':'').$db_obj->database_name];
					return static::get($db_obj, $table_name, $ref, $add, $ref_name, $no_sql_cache, $no_local_cache, $params);
				} else {
					error_log('\DB\Common::get error: '.$ex->getMessage() . "\n" . $ex->getTraceAsString());
					die('DB Query error');
				}
			}
		}
		if (!$no_local_cache && ($common_cache_active || $common_cache_get_active)) {
			$common_cache[($from_slave ? 'slave' : 'master').($db_obj->database_node ? 'node_'.($db_obj->database_node).'_':'').$db_obj->database_name.'.'.$table_name]['get'][$ckey] = &$raw_data;
		}
		$row = $raw_data;
		if ($from_slave && !empty($dbs_masters[($db_obj->database_node ? 'node_'.($db_obj->database_node).'_':'').$db_obj->database_name])) $db_obj = $dbs_masters[($db_obj->database_node ? 'node_'.($db_obj->database_node).'_':'').$db_obj->database_name];
		return $row;
	}

	public static function list(\DB\Database &$db_obj, string $table_name, mixed $ref=false, string $add='', string $field_list='*', bool $no_sql_cache=false, bool $no_local_cache=false, array $params=[]) {
		global $common_cache, $common_cache_active, $dbs_masters, $use_slave;

		$debug = intval($params['debug'] ?? 0);

		$from_slave = false;
		if (isset($params['from_slave'])) {
			$from_slave = $params['from_slave'];
		} else {
			$from_slave = $use_slave;
		}
		$db_obj = &static::find_dbobj($db_obj, $table_name, $from_slave);

		$index_add = isset($params['_index_hint']) ? ' '.$params['_index_hint'] : '';
		$query = 'SELECT '.($no_sql_cache ? 'SQL_NO_CACHE ' : '').$field_list.' FROM `'.$table_name.'` AS t '.$index_add;
		if ($params['_join'] ?? false) $query .= ' '. $params['_join'];
		$query .= ' WHERE 1 ';
		if ($ref) {
			if (!is_array($ref)) {
				if ($from_slave && !empty($dbs_masters[($db_obj->database_node ? 'node_'.($db_obj->database_node).'_':'').$db_obj->database_name])) {
					$db_obj = $dbs_masters[($db_obj->database_node ? 'node_'.($db_obj->database_node).'_':'').$db_obj->database_name];
				}
				return false;
			}
			foreach ($ref as $k=>$v) {
				$k = str_replace('`', '', $k);
				$query .= is_array($v) ? sql_pholder(" AND `".$k."` IN ( ?@ )",$v): (isset($v) ? sql_pholder(" AND `".$k."`=?",$v): " AND `".$k."` IS NULL");
			}
		}
		$query .= $add;
		$st_time = microtime(true);
		$ckey = $query;
		$raw_data = [];
		if ($common_cache_active && !$no_local_cache && isset($common_cache[($db_obj->database_node ? 'node_'.($db_obj->database_node).'_':'').$db_obj->database_name.'.'.$table_name]['list'][$ckey])) {
			$raw_data = $common_cache[($from_slave ? 'slave' : 'master').($db_obj->database_node ? 'node_'.($db_obj->database_node).'_':'').$db_obj->database_name.'.'.$table_name]['list'][$ckey];
		} else {
			if ($db_obj == NULL) {
				error_log('PHP Fatal Error: db_obj is NULL [query:'.$query.']');
				die('PHP Fatal Error: db_obj is NULL [query:'.$query.']');
			}
			try {
				pf_inc('mysql.list.'.$table_name);
				$db_obj->db_GetQueryArray($query, $raw_data);
			} catch(\Exception $ex) {
				if ($ex->getCode() == 301) {
					$params['from_slave'] = false;
					$db_obj = $dbs_masters[($db_obj->database_node ? 'node_'.($db_obj->database_node).'_':'').$db_obj->database_name];
					return static::list($db_obj, $table_name, $ref, $add, $field_list, $no_sql_cache, $no_local_cache, $params);
				} else {
					error_log('\DB\Common::list error: '.$ex->getMessage() . "\n" . $ex->getTraceAsString());
					die('DB Query error');
				}
			}
		}
		if ($debug) {
			error_log('List '.($db_obj->database_master ? 'master':'slave').' '.($db_obj->database_node ? 'Node:'.$db_obj->database_node.' ':'').$table_name.' '.round(microtime(true)-$st_time,4));
		}
		if ($common_cache_active && !$no_local_cache) {
			$common_cache[($from_slave ? 'slave' : 'master').($db_obj->database_node ? 'node_'.($db_obj->database_node).'_':'').$db_obj->database_name.'.'.$table_name]['list'][$ckey] = &$raw_data;
		}
		$data = $raw_data;
		if ($from_slave && !empty($dbs_masters[($db_obj->database_node ? 'node_'.($db_obj->database_node).'_':'').$db_obj->database_name])) $db_obj = $dbs_masters[($db_obj->database_node ? 'node_'.($db_obj->database_node).'_':'').$db_obj->database_name];
		return $data;
	}

	public static function count(\DB\Database &$db_obj, string $table_name, mixed $ref=false, string $add='', bool $no_sql_cache=false, array $params=[]) {
		global $request_statistic, $dbs_masters, $use_slave,$request_statistic;
		$debug = intval($params['debug']??0);

		$from_slave = false;
		if (isset($params['from_slave'])) {
			$from_slave = $params['from_slave'];
		} else {
			$from_slave = $use_slave;
		}

		$db_obj = &static::find_dbobj($db_obj, $table_name, $from_slave);

		$index_add = isset($params['_index_hint']) ? ' '.$params['_index_hint'] : '';
		$query = 'SELECT '.($no_sql_cache ? 'SQL_NO_CACHE ' : '').'count(*) FROM `'.$table_name.'` AS t '.$index_add.' WHERE 1 ';
		if ($ref) {
			if (!is_array($ref)) {
				if ($from_slave && !empty($dbs_masters[($db_obj->database_node ? 'node_'.($db_obj->database_node).'_':'').$db_obj->database_name])) $db_obj = $dbs_masters[($db_obj->database_node ? 'node_'.($db_obj->database_node).'_':'').$db_obj->database_name];
				return false;
			}
			foreach ($ref as $k=>$v) {
				$k = str_replace('`', '', $k);
				$query .= is_array($v) ? sql_pholder( " AND `".$k."` IN ( ?@ ) ", $v ) : ( isset($v) ? sql_pholder(' AND `' . $k . '`=?', $v) : ' AND `' . $k . '` IS NULL' );
			}
		}
		$query .= $add;
		$val = 0;
		$st_time = microtime(true);
		try {
			pf_inc('mysql.count.'.$table_name);
			$db_obj->db_GetQueryVal($query,$val);
		} catch(\Exception $ex) {
			if ($ex->getCode() == 301) {
				$params['from_slave'] = false;
				$db_obj = $dbs_masters[($db_obj->database_node ? 'node_'.($db_obj->database_node).'_':'').$db_obj->database_name];
				return static::count($db_obj, $table_name, $ref, $add, $no_sql_cache, $params);
			} else {
				error_log('\DB\Common::count error: '.$ex->getMessage() . "\n" . $ex->getTraceAsString());
				die('\DB\Common::count error: '.$ex->getMessage());
			}
		}

		if ($debug) {
			error_log('Count '.($db_obj->database_master ? 'master':'slave').' '.($db_obj->database_node ? 'Node:'.$db_obj->database_node.' ':'').$table_name.' '.round(microtime(true)-$st_time,4));
		}
		// mrgsLogs_log('Count '.($db_obj->database_master ? 'master':'slave').' '.($db_obj->database_node ? 'Node:'.$db_obj->database_node.' ':'').$table_name.' '.round(microtime(true)-$st_time,4));
		if ($from_slave && !empty($dbs_masters[($db_obj->database_node ? 'node_'.($db_obj->database_node).'_':'').$db_obj->database_name])) $db_obj = $dbs_masters[($db_obj->database_node ? 'node_'.($db_obj->database_node).'_':'').$db_obj->database_name];

		return intval($val);
	}

	public static function save(\DB\Database &$db_obj, string $table_name, array $param, string $table_fields='', string $ref_name='id' , string $add='') {
		global $common_cache,$request_statistic;
		$db_obj = &static::find_dbobj($db_obj, $table_name);

		$param[$ref_name] = $param[$ref_name] ?? null;

		$ref_id = $param[$ref_name];
		$set = '';
		$cnt = false;
		$on_duplicate = false;
		$mode = false;
		$ignore = false;
		if (isset($param['_mode'])) { $mode = $param['_mode']; unset($param['_mode']); }
		if (isset($param['_set'])) { $set .= $param['_set']; unset($param['_set']); }
		if (isset($param['_add'])) { $add .= $param['_add']; unset($param['_add']); }
		if (isset($param['_cnt'])) { $cnt = true; unset($param['_cnt']); }
		if (isset($param['_ignore'])) { $ignore = true; unset($param['_ignore']); }
		if (isset($param['_on_duplicate'])) { $on_duplicate = $param['_on_duplicate']; unset($param['_on_duplicate']);}
		if ($set && !$ref_id && !$add) return false;	// защита!
		if (!$mode) $mode =	$set || $add || $ref_id ? static::CSMODE_UPDATE : static::CSMODE_INSERT;
		$res = false;
		$die_on_error = $db_obj->die_on_error;
		if (isset($param['_noerr'])) { $db_obj->die_on_error = false; unset($param['_noerr']); }
		$index_add = isset($param['_index_hint']) ? ' '.$param['_index_hint'] : '';
		if ($table_fields) $param = static::get_params($param,$table_fields,true);
		try {
			if ($mode == static::CSMODE_INSERT) {	// INSERT
				$query = sql_pholder('INSERT '.($ignore ? 'IGNORE': '').' INTO `'.$table_name.'` (`'.implode('`,`', array_keys($param)).'`) values ( ?@ )',array_values($param));
				if ($on_duplicate) $query .= ' ON DUPLICATE KEY UPDATE '.$on_duplicate;
				pf_inc('mysql.insert.'.$table_name);
				$res = $db_obj->execSQL($query);
				$ref_id = $res && ($db_obj->affected_rows() > 0) ? ($param[$ref_name] ? $param[$ref_name] : $db_obj->insert_id()) : false;
				// mrgsLogs_log('Save insert '.($db_obj->database_node ? 'Node:'.$db_obj->database_node.' ':'').$table_name.' '.round(microtime(true)-$st_time,4));
			} elseif ($mode == static::CSMODE_UPDATE) {	// UPDATE
				$query = 'UPDATE `'.$table_name.'` '.$index_add;
				$t = [];
				if ($param[$ref_name]) unset($param[$ref_name]);

				if ($param) $t[] = sql_pholder("?%",$param);
				if ($set) $t[] = $set;
				$query .= ' SET '.implode(', ',$t).' WHERE 1 ';
				if ($ref_id) $query .= (is_array($ref_id) ? sql_pholder(" AND `".$ref_name."` IN ( ?@ ) ",$ref_id) : sql_pholder(" AND `".$ref_name."`=?",$ref_id));
				$query .= $add;
				pf_inc('mysql.update.'.$table_name);
				$res = $db_obj->execSQL($query);
				// mrgsLogs_log('Save update '.($db_obj->database_node ? 'Node:'.$db_obj->database_node.' ':'').$table_name.' '.round(microtime(true)-$st_time,4));
			} elseif ($mode == static::CSMODE_REPLACE)  {	// REPLACE
				$query = sql_pholder('REPLACE INTO `'.$table_name.'` SET ?%',$param);
				pf_inc('mysql.replace.'.$table_name);
				$res = $db_obj->execSQL($query);
				$ref_id = $res && ($db_obj->affected_rows() > 0) ? $db_obj->insert_id(): false;
			}
		} catch(\Exception $ex) {
			error_log('\DB\Common::save error: '.$ex->getMessage() . "\n" . $ex->getTraceAsString());
			die('\DB\Common::save error: '.$ex->getMessage());
		}

		$db_obj->die_on_error = $die_on_error;
		if (!$res) return false;
		// Сбрасывание кэша
		unset($common_cache['master'.($db_obj->database_node ? 'node_'.($db_obj->database_node).'_':'').$db_obj->database_name.'.'.$table_name]);
		return $cnt ? $db_obj->affected_rows() : $ref_id;
	}

	public static function delete(\DB\Database &$db_obj, string $table_name, mixed $ref=false, string $add='', string $ref_name='id') {
		global $common_cache;
		$db_obj = &static::find_dbobj($db_obj, $table_name);
		if (!$ref && !$add) return false;
		$query = "DELETE FROM `".$table_name."` WHERE 1 ";
		if ($ref) {
			if (!is_array($ref)) {
				$ref = [$ref_name => $ref];
			}
			foreach ($ref as $k=>$v) {
				$query .= is_array($v) ? sql_pholder(" AND `".$k."` IN ( ?@ )",$v): (isset($v) ? sql_pholder(" AND `".$k."`=?",$v): " AND `".$k."` IS NULL");
			}
		}
		$query .= $add;
		pf_inc('mysql.delete.'.$table_name);
		$db_obj->execSQL($query);
		unset($common_cache['master'.($db_obj->database_node ? 'node_'.($db_obj->database_node).'_':'').$db_obj->database_name.'.'.$table_name]);
		return $db_obj->affected_rows();
	}

	public static function truncate(\DB\Database &$db_obj, string $table_name) {
		$db_obj = &static::find_dbobj($db_obj, $table_name);
		$query = 'TRUNCATE `'.$table_name.'` ';
		pf_inc('mysql.truncate.'.$table_name);
		$db_obj->execSQL($query);

		return true;
	}

	public static function multi_update(\DB\Database &$db_obj, string $table_name, array $data, array $data_fields=[], array $update_fields=[], array $params=[]) {
		if (!$data_fields) $data_fields = array_keys(reset($data));
		if (!$data || !$data_fields) return false;
		$db_obj = &static::find_dbobj($db_obj, $table_name);

		//готовим данные для подстановки в запрос
		$rows = [];
		foreach($data as $data_row) {
			$row = [];
			foreach($data_fields as $field) {
				if (isset($data_row[$field])) {
					$row[$field] = "'".addslashes($data_row[$field])."'";
				} else {
					$row[$field] = 'NULL';
				}
			}
			$rows[] = '('.implode(',', $row).')';
		}

		if ($params['_mode'] == static::CSMODE_REPLACE) {
			$sql = 'REPLACE INTO `'.$table_name.'`';
		} else {
			$sql = 'INSERT '.($params['_delayed'] ? 'DELAYED ' : '').'INTO `'.$table_name.'`';
		}

		$sql .= ' ('.implode(',', $data_fields).') values '.implode(',', $rows);

		if ($update_fields) {
			//готовим поля для апдейта если нужно
			$update_pieces = [];
			foreach($update_fields as $field) {
				if (!is_array($field)) {
					$update_pieces[] = $field.'=values('.$field.')';
				} else {
					$update_pieces[] = $field['field'].'='.$field['value'];
				}
			}

			$sql .= ' ON DUPLICATE KEY UPDATE '.implode(',', $update_pieces);
		}
		pf_inc('mysql.multiUpdate.'.$table_name);
		$db_obj->execSQL($sql);
		return $db_obj->affected_rows();
	}

	public static function exec_sql(\DB\Database &$db_obj, string $query, string $table_name='', array $params=[]) {
		if ($table_name) {
			$from_slave = !empty($params['from_slave']);
			$db_obj = &static::find_dbobj($db_obj, $table_name, $from_slave);
		}
		if (!$params['debug_action']) $params['debug_action'] = 'exec';
		$die_on_error = $db_obj->die_on_error;
		if (isset($params['_noerr'])) { $db_obj->die_on_error = false; unset($params['_noerr']); }
		$res = $db_obj->execSQL($query);
		$db_obj->die_on_error = $die_on_error;
		return $res;
	}

	public static function exec_sql_with_result(\DB\Database &$db_obj, string $query, string $table_name='', array $params=[]) {
		if ($table_name) {
			$from_slave = !empty($params['from_slave']);
			$db_obj = &static::find_dbobj($db_obj, $table_name, $from_slave);
		}
		if (!$params['debug_action']) $params['debug_action'] = 'exec';
		if (isset($params['_noerr'])) { $db_obj->die_on_error = false; unset($params['_noerr']); }
		$raw_data = [];
		try {
			pf_inc('mysql.list.'.$table_name);
			$db_obj->db_GetQueryArray($query, $raw_data);
		} catch(\Exception $ex) {
			error_log("common_exec_sql_with_result SQL request exception: " . $ex->getMessage()."\n". $ex->getTraceAsString());
			die('common_exec_sql_with_result SQL request exception: ' . $ex->getMessage());
		}
		return $raw_data;
	}

	public static function bulk_update(\DB\Database &$db_obj, string $table_name, mixed $ids, string $field, string $value='', ?string $value_old=null, string $add='') {
		global $common_cache;
		$db_obj = &static::find_dbobj($db_obj, $table_name);

		if (!$ids && !isset($value_old) && !$add) return false;
		$query = sql_pholder("UPDATE ".$table_name." SET `".$field."`=? WHERE 1",$value);
		if ($ids) {
			if (!is_array($ids)) $ids = [$ids];
			$query .= sql_pholder(" AND id IN ( ?@ )",$ids);
		}
		if (isset($value_old)) $query .= sql_pholder(" AND `".$field."`=?",$value_old);
		$query .= $add;
		pf_inc('mysql.bulkUpdate.'.$table_name);
		$db_obj->execSQL($query);

		unset($common_cache['master'.($db_obj->database_node ? 'node_'.($db_obj->database_node).'_':'').$db_obj->database_name.'.'.$table_name]);
		$affected_rows = $db_obj->affected_rows();
		return ($affected_rows > 0);

	}

	private static function iconnect(\DB\Database $db) {
		if (!$db) return false;

		shuffle($db->host_names);
		$i=0;
		$dbhs = [];
		foreach ($db->host_names as $host) {

			list($h,$p) = explode(":", $host);
			$dbh_t = [];
			$dbh_t['host_name'] = $host;
			$dbhs[$i] = $dbh_t;


			if (!($link = mysqli_connect($h, $db->database_user, $db->database_password, $db->database_name,$p ? $p:3306))) {
				$i++;
				continue;
			}
			if (mysqli_connect_errno()) {
				$i++;
				continue;
			}
			global $DEBUG_MYSQL;
			if ($DEBUG_MYSQL) error_log('mysql connect '.getmypid().' '.$h.':'.$p);
			$dbhs[$i]['dbh'] = $link;

			if ($db->database_master) break;
			if (count($db->host_names) < 2) break;

			$result = [];
			$die = $db->die_on_error;
			$db->die_on_error = false;

			if ($result = mysqli_query($link, "SHOW SLAVE STATUS;")) {
				$row = mysqli_fetch_array($result, MYSQLI_ASSOC);
				mysqli_free_result($result);
			}
			$db->die_on_error = $die;
			$sec = intval($row['Seconds_Behind_Master']);

			$dbhs[$i]['seconds'] = $sec;

			if ($sec < 300) break;

			$i++;
		}

		if (!count($dbhs)) {
			$db->connection_error="Can't connect to host: ".implode(", ",$db->host_names);
			error_log(date('[Y-m-d H:i:s] ').$db->connection_error);
			die(date('[Y-m-d H:i:s] ').$db->connection_error."\n");
		}

		if (count($dbhs) > 1) {
			usort($dbhs,"Database_seconds_cmp");
		}
		$link = false;
		$link = $dbhs[0]['dbh'];

		for ($i=1;$i<count($dbhs);$i++) {
			mysqli_close($dbhs[$i]['dbh']);
		}

		if ($db->charset && ($db->charset != 'UTF8' || (defined('DEVS') && constant('DEVS')))) {
			mysqli_query($link,"SET NAMES '".$db->charset."'");
		}
		return $link;

	}

	public static function ilist(array $shards, string $table_name, mixed $ref=false, string $add='', string $field_list='*', bool $no_sql_cache=false, bool $no_local_cache=false, array $params=[]) {
		global $use_slave;
		shuffle($shards);
		if (!defined('MYSQLI_ASYNC') || !function_exists('mysqli_poll')) {
			$data = [];
			foreach ($shards as $k=>$shard) {
				$data = array_merge($data, static::list($shard['master'], $table_name, $ref, $add, $field_list, $no_sql_cache, $no_local_cache, $params));
			}
			return $data;
		}

		$tbegin = microtime(true);
		$from_slave = false;
		if (!defined('PROHIBITION_USE_SLAVE')) {
			$from_slave = ($params['from_slave'] || $use_slave);
		}

		$index_add = isset($params['_index_hint']) ? ' '.$params['_index_hint'] : '';
		$query = 'SELECT '.($no_sql_cache ? 'SQL_NO_CACHE ' : '').$field_list.' FROM `'.$table_name.'` AS t '.$index_add;
		if ($params['_join']) $query .= ' '. $params['_join'];
		$query .= ' WHERE 1 ';
		if ($ref) {
			if (!is_array($ref)) {
				return false;
			}
			foreach ($ref as $k=>$v) {
				$k = str_replace('`', '', $k);
				$query .= is_array($v) ? sql_pholder(" AND `".$k."` IN ( ?@ )",$v): (isset($v) ? sql_pholder(" AND `".$k."`=?",$v): " AND `".$k."` IS NULL");
			}
		}
		$query .= $add;
		$db_shards_links = [];
		foreach ($shards as $db_obj) {
			$db_obj = &static::find_dbobj($db_obj['master'], $table_name, $from_slave);
			$db_shards_links[] = static::iconnect($db_obj);
		}
		$flag = defined('MYSQLI_ASYNC') ? MYSQLI_ASYNC : MYSQLI_USE_RESULT;
		$query = '/* '.$_SERVER['PHP_SELF'].' '.getmypid().'  */ '.$query;
		foreach ($db_shards_links as $link) {
			pf_inc('mysql.list.'.$table_name);
			mysqli_query($link, $query, $flag);
		}
		$processed = 0;
		$i = 0;
		$data = [];
		do {
			$links = $errors = $reject = [];
			foreach ($db_shards_links as $i=>$link) {
				if ($link) $links[$i] = $errors[$i] = $reject[$i] = $link;
			}
			if ($links && !mysqli_poll($links, $errors, $reject, 0, 10)) {
				continue;
			}

			foreach ($links as $i=>$link) {
				if ($result = $link->reap_async_query()) {
					$data = array_merge($data,mysqli_fetch_all($result,MYSQLI_ASSOC));
					if (is_object($result)) mysqli_free_result($result);
				} else {
					$errorMessage = sprintf("Ошибка MySQLi: %s", mysqli_error($link));
					error_log($errorMessage);
					die($errorMessage);
				}
				$processed++;
			}
		} while ($processed < count($db_shards_links));
		//echo "\n";
		foreach ($db_shards_links as $link) {
			mysqli_close($link);
		}
		global $__MysqlScriptTime;
		$__MysqlScriptTime += microtime(true)-$tbegin;
		return $data;
	}

	public static function icount(array $shards, string $table_name, mixed $ref=false, string $add='', bool $no_sql_cache=false, array $params=[]) {
		global $use_slave;
		shuffle($shards);
		if (!defined('MYSQLI_ASYNC') || !function_exists('mysqli_poll')) {
			$count = 0;
			foreach ($shards as $k=>$shard) {
				$count += static::count($shard['master'], $table_name, $ref, $add, $no_sql_cache, $params);
			}
			return $count;
		}

		$tbegin = microtime(true);
		$from_slave = false;
		if (!defined('PROHIBITION_USE_SLAVE')) {
			$from_slave = ($params['from_slave'] || $use_slave);
		}

		$index_add = isset($params['_index_hint']) ? ' '.$params['_index_hint'] : '';
		$query = 'SELECT '.($no_sql_cache ? 'SQL_NO_CACHE ' : '').'count(*) as cnt '.' FROM `'.$table_name.'` AS t '.$index_add.' WHERE 1 ';
		if ($ref) {
			if (!is_array($ref)) {
				return false;
			}
			foreach ($ref as $k=>$v) {
				$k = str_replace('`', '', $k);
				$query .= is_array($v) ? sql_pholder(" AND `".$k."` IN ( ?@ )",$v): (isset($v) ? sql_pholder(" AND `".$k."`=?",$v): " AND `".$k."` IS NULL");
			}
		}
		$query .= $add;
		$db_shards_links = [];
		$t1 = microtime(true);
		foreach ($shards as $db_obj) {
			$db_obj = &static::find_dbobj($db_obj['master'], $table_name, $from_slave);
			$db_shards_links[] = static::iconnect($db_obj);
		}
		$flag = defined('MYSQLI_ASYNC') ? MYSQLI_ASYNC : MYSQLI_USE_RESULT;
		$query = '/* '.$_SERVER['PHP_SELF'].' '.getmypid().'  */ '.$query;
		foreach ($db_shards_links as $link) {
			pf_inc('mysql.count.'.$table_name);
			mysqli_query($link,$query, $flag);
		}
		$processed = 0;
		$i = 0;
		$count = 0;
		do {
			$links = $errors = $reject = [];
			foreach ($db_shards_links as $i=>$link) {
				$links[$i] = $errors[$i] = $reject[$i] = $link;
			}
			if (!mysqli_poll($links, $errors, $reject, 0, 10)) {
				continue;
			}

			foreach ($links as $i=>$link) {
				if ($result = $link->reap_async_query()) {
					$data = mysqli_fetch_all($result,MYSQLI_ASSOC);
					$count += intval($data[0]['cnt']);
					if (is_object($result)) mysqli_free_result($result);
				} else {
					$errorMessage = sprintf("Ошибка MySQLi: %s", mysqli_error($link));
					error_log($errorMessage);
					die($errorMessage);
				}
				$processed++;
			}
		} while ($processed < count($db_shards_links));
		foreach ($db_shards_links as $link) {
			mysqli_close($link);
		}
		global $__MysqlScriptTime;
		$__MysqlScriptTime += microtime(true)-$tbegin;
		return $count;
	}

}
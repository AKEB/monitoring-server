<?php
/*
 * Creator: AKEB
 * Date: 27.05.2014 11:21:01
 * Encoding: UTF-8
 *
 */
namespace DB;

class MySQLObject {

	static public $database;
	static public $table;
	static public $shard = false;
	static public $shardReplication = false;
	static public $shardKey = 'id';

	public function __construct() {
		$this->database = static::getDatabase();
		$this->table = static::getTable();
		$this->shard = static::getShard();
		$this->shardKey = static::getShardKey();
	}

	/**
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	static public function getNodeNum($param): int {
		return 0;
	}

	static public function &getDatabase(): \DB\Database|array {
		global $db;
		static::$database = $db;
		return static::$database;
	}

	static public function getTable() {
		return static::$table;
	}

	static public function getShard() {
		return static::$shard;
	}

	static public function getShardReplication() {
		// Таблица Одинаковая на ВСЕХ шардах!
		return static::$shardReplication;
	}

	static public function getShardKey(): array|string {
		return static::$shardKey;
	}

	static public function data($ref = false, $add = '', $field_list = '*', $no_sql_cache=false, $no_local_cache=false, $params=[]) {
		if (static::getShard()) {
			if (static::getShardReplication()) {
				$nodes = static::getDatabase();
				shuffle($nodes);
				foreach ($nodes as $v) {
					return \DB\Common::list($v['master'], static::getTable(), $ref, $add, $field_list, $no_sql_cache, $no_local_cache, $params);
				}
				return false;
			}
			$node = 0;
			if (is_array($ref)) {
				if (is_array(static::getShardKey())) {
					$shardKeys = [];
					foreach(static::getShardKey() as $key) {
						if ($ref[$key] && !is_array($ref[$key])) {
							$shardKeys[$key] = $ref[$key];
						}
					}
					if (count(static::getShardKey()) == count($shardKeys)) {
						$node = static::getNodeNum($shardKeys);
					}
				} elseif ($ref[static::getShardKey()] && !is_array($ref[static::getShardKey()])) {
					$node = static::getNodeNum($ref[static::getShardKey()]);
				}
			} elseif($ref && !is_array(static::getShardKey())) $node = static::getNodeNum($ref);
			if ($params['node']) {
				$node = intval($params['node']);
				unset($params['node']);
			}
			if (!$node || !static::getDatabase()[$node]['master']) {
				return \DB\Common::ilist(static::getDatabase(), static::getTable(), $ref, $add, $field_list, $no_sql_cache, $no_local_cache, $params);
			} else {
				return \DB\Common::list(static::getDatabase()[$node]['master'], static::getTable(), $ref, $add, $field_list, $no_sql_cache, $no_local_cache, $params);
			}
		} else {
			return \DB\Common::list(static::getDatabase(), static::getTable(), $ref, $add, $field_list, $no_sql_cache, $no_local_cache, $params);
		}
	}

	static public function get($ref = false, $add = '', $ref_name='id', $no_sql_cache=false, $no_local_cache=false, $params=[]) {
		if (static::getShard()) {
			if (static::getShardReplication()) {
				$nodes = static::getDatabase();
				shuffle($nodes);
				foreach ($nodes as $v) {
					return \DB\Common::get($v['master'], static::getTable(), $ref, $add, $ref_name, $no_sql_cache, $no_local_cache, $params);
				}
				return false;
			}
			$node = 0;
			if (is_array($ref)) {
				if (is_array(static::getShardKey())) {
					$shardKeys = [];
					foreach (static::getShardKey() as $key) {
						if ($ref[$key] && !is_array($ref[$key])) {
							$shardKeys[$key] = $ref[$key];
						}
					}
					if (count(static::getShardKey()) == count($shardKeys)) {
						$node = static::getNodeNum($shardKeys);
					}
				} elseif ($ref[static::getShardKey()] && !is_array($ref[static::getShardKey()])) {
					$node = static::getNodeNum($ref[static::getShardKey()]);
				}
			} elseif($ref && !is_array(static::getShardKey())) $node = static::getNodeNum($ref);
			if ($params['node']) {
				$node = intval($params['node']);
				unset($params['node']);
			}
			if (!$node || !static::getDatabase()[$node]['master']) {
				$nodes = static::getDatabase();
				shuffle($nodes);
				foreach ($nodes as $v) {
					$data = \DB\Common::get($v['master'], static::getTable(), $ref, $add, $ref_name, $no_sql_cache, $no_local_cache, $params);
					if ($data) return $data;
				}
				return [];
			} else {
				return \DB\Common::get(static::getDatabase()[$node]['master'], static::getTable(), $ref, $add, $ref_name, $no_sql_cache, $no_local_cache, $params);
			}
		} else {
			return \DB\Common::get(static::getDatabase(), static::getTable(), $ref, $add, $ref_name, $no_sql_cache, $no_local_cache, $params);
		}
	}

	static public function count($ref = false, $add = '', $no_sql_cache=false, $params=[]) {
		if (static::getShard()) {
			if (static::getShardReplication()) {
				$nodes = static::getDatabase();
				shuffle($nodes);
				foreach ($nodes as $v) {
					return \DB\Common::count($v['master'], static::getTable(), $ref, $add, $no_sql_cache, $params);
				}
				return 0;
			}

			$node = 0;
			if (is_array($ref)) {
				if (is_array(static::getShardKey())) {
					$shardKeys = [];
					foreach (static::getShardKey() as $key) {
						if ($ref[$key] && !is_array($ref[$key])) {
							$shardKeys[$key] = $ref[$key];
						}
					}
					if (count(static::getShardKey()) == count($shardKeys)) {
						$node = static::getNodeNum($shardKeys);
					}
				} elseif ($ref[static::getShardKey()] && !is_array($ref[static::getShardKey()])) {
					$node = static::getNodeNum($ref[static::getShardKey()]);
				}
			} elseif($ref && !is_array(static::getShardKey())) $node = static::getNodeNum($ref);
			if (!$node || !static::getDatabase()[$node]['master']) {
				return \DB\Common::icount(static::getDatabase(), static::getTable(), $ref, $add, $no_sql_cache, $params);
			} else {
				return \DB\Common::count(static::getDatabase()[$node]['master'], static::getTable(), $ref, $add, $no_sql_cache, $params);
			}
		} else {
			return \DB\Common::count(static::getDatabase(), static::getTable(), $ref, $add, $no_sql_cache, $params);
		}
	}

	static public function save($param, $table_fields='', $ref_name='id', $add='') {
		if (static::getShard()) {
			if (static::getShardReplication()) {
				$nodes = static::getDatabase();
				shuffle($nodes);
				foreach ($nodes as $v) {
					\DB\Common::save($v['master'], static::getTable(), $param, $table_fields, $ref_name, $add);
				}
				return true;
			}

			if (is_array(static::getShardKey())) {
				$shardKeys = [];
				foreach (static::getShardKey() as $key) {
					if ($param[$key] && !is_array($param[$key])) {
						$shardKeys[$key] = $param[$key];
					}
				}
				if (count(static::getShardKey()) == count($shardKeys)) {
					$node = static::getNodeNum($shardKeys);
				} else return false;
			} elseif ($param[static::getShardKey()] && !is_array($param[static::getShardKey()])) {
				$node = static::getNodeNum($param[static::getShardKey()]);
			} else return false;
			if (!$node || !static::getDatabase()[$node]['master']) return false;
			return \DB\Common::save(static::getDatabase()[$node]['master'], static::getTable(), $param, $table_fields, $ref_name, $add);
		} else {
			return \DB\Common::save(static::getDatabase(), static::getTable(), $param, $table_fields, $ref_name, $add);
		}
	}

	static public function delete($ref=false, $add='', $ref_name='id') {
		if (!$ref && !$add) return false;

		if (static::getShard()) {
			if (static::getShardReplication()) {
				$nodes = static::getDatabase();
				shuffle($nodes);
				foreach ($nodes as $v) {
					\DB\Common::delete($v['master'], static::getTable(), $ref, $add, $ref_name);
				}
				return true;
			}
			$node = 0;
			if (is_array($ref)) {
				if (is_array(static::getShardKey())) {
					$shardKeys = [];
					foreach (static::getShardKey() as $key) {
						if ($ref[$key] && !is_array($ref[$key])) {
							$shardKeys[$key] = $ref[$key];
						}
					}
					if (count(static::getShardKey()) == count($shardKeys)) {
						$node = static::getNodeNum($shardKeys);
					}
				} elseif ($ref[static::getShardKey()] && !is_array($ref[static::getShardKey()])) {
					$node = static::getNodeNum($ref[static::getShardKey()]);
				}
			} elseif($ref && !is_array(static::getShardKey())) $node = static::getNodeNum($ref);

			if (!$node || !static::getDatabase()[$node]['master']) {
				$nodes = static::getDatabase();
				shuffle($nodes);
				foreach ($nodes as $v) {
					\DB\Common::delete($v['master'], static::getTable(), $ref, $add, $ref_name);
				}
				return true;
			} else {
				return \DB\Common::delete(static::getDatabase()[$node]['master'], static::getTable(), $ref, $add, $ref_name);
			}
		} else {
			return \DB\Common::delete(static::getDatabase(), static::getTable(), $ref, $add, $ref_name);
		}

	}

	static public function exec($query='', $params=[]) {
		if (!$query) return false;
		if (static::getShard()) {
			$nodes = static::getDatabase();
			shuffle($nodes);
			foreach ($nodes as $v) {
				\DB\Common::exec_sql($v['master'], $query, static::getTable(), $params);
			}
			return true;
		} else {
			return \DB\Common::exec_sql(static::getDatabase(), $query, static::getTable(), $params);
		}
	}

	static public function start_transaction() {
		if (static::getShard()) {
			$nodes = static::getDatabase();
			shuffle($nodes);
			foreach ($nodes as $v) {
				\DB\Common::exec_sql($v['master'], "START TRANSACTION", static::getTable());
			}
			return true;
		} else {
			return \DB\Common::exec_sql(static::getDatabase(), "START TRANSACTION", static::getTable());
		}
	}

	static public function commit_transaction() {
		if (static::getShard()) {
			$nodes = static::getDatabase();
			shuffle($nodes);
			foreach ($nodes as $v) {
				\DB\Common::exec_sql($v['master'], "COMMIT", static::getTable());
			}
			return true;
		} else {
			return \DB\Common::exec_sql(static::getDatabase(), "COMMIT", static::getTable());
		}
	}

	static public function rollback_transaction() {
		if (static::getShard()) {
			$nodes = static::getDatabase();
			shuffle($nodes);
			foreach ($nodes as $v) {
				\DB\Common::exec_sql($v['master'], "ROLLBACK", static::getTable());
			}
			return true;
		} else {
			return \DB\Common::exec_sql(static::getDatabase(), "ROLLBACK", static::getTable());
		}
	}

	static public function multi_update($data, $update_fields=[], $params=[], $node=0) {
		if (!$data) return false;
		if (static::getShard()) {
			return \DB\Common::multi_update(static::getDatabase()[$node]['master'], static::getTable(), $data, [], $update_fields, $params);
		} else {
			return \DB\Common::multi_update(static::getDatabase(), static::getTable(), $data, [], $update_fields, $params);
		}
	}

	static public function bulk_update($ids, $field, $value = '', $value_old = null, $add = '', $node = 0) {
		if (!$field) return false;
		if (static::getShard()) {
			return \DB\Common::bulk_update(static::getDatabase()[$node]['master'], static::getTable(), $ids, $field, $value, $value_old, $add);
		} else {
			return \DB\Common::bulk_update(static::getDatabase(), static::getTable(), $ids, $field, $value, $value_old, $add);
		}
	}

	public static function fieldsToRequest(array $fields) {
		if (count($fields) == 0) {
			return  "";
		}
		$result = "";
		$currentNum = 0;
		foreach ($fields as $k => $f) {
			if ($currentNum === $k ) {
				$result .= ', ' . $f;
				$currentNum++;
			} else {
				$result .= ', ' . $f . ' as ' . $k;
			}
		}
		$result .= ' ';
		return substr($result, 1);
	}
}
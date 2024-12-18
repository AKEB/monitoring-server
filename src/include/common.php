<?php

function &get_hash(array $arr, string $key_name='id', string $val_name='title'): array {
	if (!is_array($arr)) return [];
	if (!$arr) return [];
	$ret = [];
	foreach ($arr as $item) {
		$ret[$item[$key_name]] = $item[$val_name];
	}
	return $ret;
}

function &get_hash_grp(array $arr, string $grp_key_name='type_id', string $key_name='id', string $val_name='title'): array {
	if (!is_array($arr)) return [];
	$ret = [];
	foreach ($arr as $item) {
		if ($key_name)
			$ret[$item[$grp_key_name]][$item[$key_name]] = $item[$val_name];
		else
			$ret[$item[$grp_key_name]][] = $item[$val_name];
	}
	return $ret;
}

function &make_hash(array $arr, string|array $key_name='id', bool $group=false) {
	if (!is_array($arr)) return [];
	$key = is_array($key_name) ? array_shift($key_name) : $key_name;
	$ret = [];
	foreach ($arr as $item) {
		if ($group) $ret[$item[$key]][] = $item;
		else $ret[$item[$key]] = $item;
	}
	if (is_array($key_name) && count($key_name) > 0 && $group) {
		foreach ($ret as $idx => $_) {
			$ret[$idx] = make_hash($ret[$idx], $key_name, true);
		}
	}
	return $ret;
}
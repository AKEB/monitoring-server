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

function common_html_hidden(array $arr, ?string $arr_name=null): string {
	$html = "";
	foreach ($arr as $k=>$v) {
		if ($arr_name) $k = $arr_name.'['.$k.']';
		$html .= !is_array($v) ? "<input type=\"hidden\" name=\"".$k."\" value=\"".htmlspecialchars($v)."\">\n": common_html_hidden($v,$k);
	}
	return $html;
}

function common_build_request(string|array $param, string $arr_name='', bool $encode=true): string {
	if (!is_array($param)) return $encode ? urlencode($param): $param;
	$t = [];
	foreach ($param as $k=>$v) {
		if ($encode) $k = urlencode($k);
		if ($arr_name) $k = $arr_name.'['.$k.']';
		$t[] = !is_array($v) ? $k.'='.($encode ? urlencode($v): $v): common_build_request($v,$k);
	}
	return implode('&',$t);
}

function common_redirect(string $url='', array $param=[], bool $post=false) {
	global $error;
	$err = $error ? $error : $_GET['error'];
	if ((!isset($param['error']) || !$param['error']) && $err) $param['error'] = $err;
	if (!$post) {
		if ($param) $url .= (strpos($url, '?') !== false ? '&' : '?') . common_build_request($param);
		header("Location: ".$url);
	} else {
		$html = '<html><body>';
		$html .= '<script type="text/javascript" nonce="'.$GLOBALS['nonceRandom'].'">';
		$html .= '$(document).ready(function(){ frm.submit(); });';
		$html .= '</script>';
		$html .= '<form name="frm" method="post" action="'.$url.'">';
		if ($param && is_array($param)) $html .= common_html_hidden($param);
		$html .= '</form>';
		$html .= '</body></html>';
		print $html;
	}
	exit;
}
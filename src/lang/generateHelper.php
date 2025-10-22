<?php
$dirname = dirname(__FILE__) . '/';
$files = $dirname . '*.yml';

global $PWD;
$PWD = __DIR__;
if (!defined('SERVER_ROOT')) define("SERVER_ROOT", $PWD);


require_once($dirname . "/../vendor/autoload.php");

if (!function_exists('glob_recursive')) {
	// Does not support flag GLOB_BRACE
	function glob_recursive($pattern, $flags = 0) {
		$files = glob($pattern, $flags);
		$dirs = glob(dirname($pattern) . '/*', GLOB_ONLYDIR);
		if ($dirs) {
			foreach ($dirs as $dir) {
				if (!is_array($files)) $files = [];
				$files = array_merge($files, glob_recursive($dir . '/' . basename($pattern), $flags));
			}
		}
		return $files;
	}
}

$file_names = glob_recursive($files);
$configs = [];
foreach ($file_names as $file_name) {
	$config = \Spyc::YAMLLoad($file_name);
	$configs = array_merge_recursive($configs, $config);
}

function compile($config, $prefix = '') {
	$return = [];
	foreach ($config as $key => $value) {
		if (is_array($value) && count($value) >= 1 && count($value) <= 2 && isset($value[0])) {
			$fullName = $prefix . $key;
			$return[$fullName] = $value;
		} elseif (is_array($value)) {
			$return = array_merge($return, compile($value, $prefix . $key . '_'));
		} else {
			$fullName = $prefix . $key;
			if (!preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $fullName)) {
				continue;
			}
			$return[$fullName][0] = $value;
		}
	}
	return $return;
}

$data = compile($configs);

$code = "<?php\n";
$code .= "class T {\n";

foreach($data as $k=>$v) {

	$code .= "\t/**\n";
	$code .= "\t * $k\n";
	$code .= "\t * \n";
	$code .= "\t * @return string \"".str_replace(["\n", "\t"],["\\n", "\\t"],addslashes($v[0]))."\"\n";
	$code .= "\t * @return string \"". str_replace(["\n", "\t"], ["\\n", "\\t"], addslashes($v[1]))."\"\n";
	$code .= "\t */\n";
	$code .= "\tpublic static function $k(...\$argv): string { return ''; }\n\n";
}

$code .= "}\n";

$fileName = dirname(__FILE__).'/T_autocomplete_tmp.php';

file_put_contents($fileName, $code);
echo date("Y-m-d H:i:s");

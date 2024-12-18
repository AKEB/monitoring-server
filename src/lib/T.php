<?php

class T {
	private static $__i18n = [];
	private static $__currentLanguage = NULL;

	private static $__languages = ['ru', 'en'];
	private static $__fallbackLang = 'en';

	private $sectionSeparator = '_';

	protected function __getConfig($languageFile, $language) {
		$language = strtolower($language);
		if (is_array($languageFile)) {
			if (isset($languageFile[$language])) {
				if (is_array($languageFile[$language])) return $languageFile[$language];
				else $languageFile = $languageFile[$language];
			} else {
				return [];
			}
		}
		if (!$languageFile) $languageFile = 'lang_{LANGUAGE}.yml';
		$languageFile = str_replace(SERVER_ROOT, '', $languageFile);
		$languageFile = str_replace('lang/', '', $languageFile);
		$languageFile = SERVER_ROOT . 'lang/' . $languageFile;
		$languageFile = str_replace('{LANGUAGE}', $language, $languageFile);
		if (file_exists($languageFile)) {
			return $this->__load($languageFile);
		}
		return [];
	}

	public function __construct($languageFileOrArray= '') {
		if (isset($_GET['lang']) && $_GET['lang'] != '') {
			setcookie('lang', strtolower($_GET['lang']), time() + 2*365*86400);
			$_COOKIE['lang'] = strtolower($_GET['lang']);
		}
		foreach(static::$__languages as $__lang) {
			$config = $this->__getConfig($languageFileOrArray, $__lang);
			if (!isset(static::$__i18n[strtolower($__lang)]) || !static::$__i18n[strtolower($__lang)]) static::$__i18n[strtolower($__lang)] = [];
			static::$__i18n[strtolower($__lang)] = array_replace_recursive(static::$__i18n[strtolower($__lang)], $this->__compile($config));
		}
		if (!static::$__currentLanguage) static::$__currentLanguage = $this->__getUserLangs()[0];
	}

	protected function __load($filename) {
		$ext = substr(strrchr($filename, '.'), 1);
		switch ($ext) {
			case 'properties':
			case 'ini':
				$config = parse_ini_file($filename, true);
				break;
			case 'yml':
				$config = \Spyc::YAMLLoad($filename);
				break;
			case 'json':
				$config = json_decode(file_get_contents($filename), true);
				break;
			default:
				throw new InvalidArgumentException($ext . " is not a valid extension!");
		}
		return $config;
	}

	protected function __compile($config, $prefix = '') {
		$return = [];
		foreach ($config as $key => $value) {
			if (is_array($value)) {
				$return = array_merge($return, $this->__compile($value, $prefix . $key . $this->sectionSeparator));
			} else {
				$fullName = $prefix . $key;
				if (!preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $fullName)) {
					throw new InvalidArgumentException(__CLASS__ . ": Cannot compile translation key " . $fullName . " because it is not a valid PHP identifier.");
				}
				$return[$fullName] = $value;
			}
		}
		return $return;
	}

	public function __getUserLangs() {
		$userLangs = array();

		if (isset($_GET['lang']) && is_string($_GET['lang'])) {
			$userLangs[] = $_GET['lang'];
		}

		if (isset($_COOKIE['lang']) && is_string($_COOKIE['lang'])) {
			$userLangs[] = $_COOKIE['lang'];
		}

		if (isset($_SESSION['lang']) && is_string($_SESSION['lang'])) {
			$userLangs[] = $_SESSION['lang'];
		}

		if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
			foreach (explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']) as $part) {
				$userLangs[] = strtolower(substr($part, 0, 2));
			}
		}

		$userLangs[] = static::$__fallbackLang;
		$userLangs = array_unique($userLangs);
		$userLangs2 = array();
		foreach ($userLangs as $key => $value) {
			if (!in_array(strtolower($value), static::$__languages)) continue;
			// only allow a-z, A-Z and 0-9 and _ and -
			if (preg_match('/^[a-zA-Z0-9_-]*$/', $value) === 1)
				$userLangs2[$key] = $value;
		}
		return $userLangs2;
	}

	public static function __getI18n() {
		return static::$__i18n;
	}

	public static function getCurrentLanguage() {
		return static::$__currentLanguage;
	}

	public static function setCurrentLanguage(string $lang) {
		return static::$__currentLanguage = strtolower($lang);
	}

	public static function __callStatic($string, $args) {
		if (!isset(static::$__i18n[static::$__currentLanguage])) static::setCurrentLanguage(static::$__fallbackLang);
		$string = isset(static::$__i18n[static::$__currentLanguage][$string]) ? static::$__i18n[static::$__currentLanguage][$string] : (
			isset(static::$__i18n[static::$__fallbackLang][$string]) ? static::$__i18n[static::$__fallbackLang][$string] : 'T::'.$string);
		return vsprintf($string, $args);
	}

	public static function __returnPhraseCounts($string) {
		if (!isset(static::$__i18n[static::$__currentLanguage])) static::setCurrentLanguage(static::$__fallbackLang);
		$data = isset(static::$__i18n[static::$__currentLanguage]) ? static::$__i18n[static::$__currentLanguage] : (isset(static::$__i18n[static::$__fallbackLang]) ? static::$__i18n[static::$__fallbackLang] : []);
		$count = 0;
		foreach($data as $k=>$v) {
			if (strpos($k, $string) !== false) {
				$count++;
			}
		}
		return intval($count);
	}
}
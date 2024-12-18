<?php

class Migrate {
	static private $doneMigrations = [];
	static private $depends = [];

	static private function _migrate($migrationName) {
		if (isset(static::$doneMigrations[$migrationName]) && static::$doneMigrations[$migrationName]) return true;

		$className = ucfirst($migrationName);
		if (property_exists($className, 'previous') && $className::$previous) {
			if (!is_array($className::$previous)) $className::$previous = [$className::$previous];
			foreach($className::$previous as $previous) {
				static::_migrate($previous);
			}
		}

		$className::install();
		\Models\Migrations::save([
			'migration_name' => $migrationName,
			'stime' => time(),
			'_mode' => \DB\Common::CSMODE_INSERT,
		], '', 'migration_name');
		echo $migrationName." install\n";
		static::$doneMigrations[$migrationName] = time();
	}

	static private function _rollback($migrationName) {
		if (!static::$doneMigrations[$migrationName]) return true;
		if (isset(static::$depends[$migrationName]) && static::$depends[$migrationName]) {
			foreach(static::$depends[$migrationName] as $next) {
				static::_rollback($next);
			}
		}
		$className = ucfirst($migrationName);
		$className::uninstall();
		\Models\Migrations::delete(['migration_name' => $migrationName],'','migration_name');
		echo $migrationName." uninstall\n";
		unset(static::$doneMigrations[$migrationName]);
	}

	static public function apply() {
		static::$doneMigrations = get_hash(\Models\Migrations::data(), 'migration_name', 'stime');
		$allFiles = glob(SERVER_ROOT.'migrations/migration_*.php');
		$migrations = [];
		foreach($allFiles as $file) {
			$fileName = basename($file);
			$migrationName = str_replace('.php','', $fileName);
			if (isset(static::$doneMigrations[$migrationName]) && static::$doneMigrations[$migrationName]) continue;
			include($file);
			$migrations[$migrationName] = $migrationName;
		}

		while($migrations && count($migrations) > 0) {
			foreach($migrations as $migrationName => $item) {
				if (!isset(static::$doneMigrations[$migrationName]) || !static::$doneMigrations[$migrationName]) {
					static::_migrate($migrationName);
				}
				unset($migrations[$migrationName]);
				continue;
			}
		}
	}

	static public function rollback($migrations) {
		static::$doneMigrations = get_hash(\Models\Migrations::data(), 'migration_name', 'stime');
		$allFiles = glob(SERVER_ROOT.'migrations/migration_*.php');

		static::$depends = [];
		foreach($allFiles as $file) {
			$fileName = basename($file);
			$migrationName = str_replace('.php','', $fileName);
			if (!static::$doneMigrations[$migrationName]) continue;
			include($file);
			$className = ucfirst($migrationName);
			if (property_exists($className, 'previous') && $className::$previous) {
				if (!is_array($className::$previous)) $className::$previous = [$className::$previous];
				foreach($className::$previous as $previous) {
					static::$depends[$previous][] = $migrationName;
				}
			}
		}

		foreach($migrations as $migration) {
			static::_rollback($migration);
		}
	}
}
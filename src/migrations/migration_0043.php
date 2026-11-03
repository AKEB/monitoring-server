<?php
class Migration_0043 {
	static public $previous = [
		'migration_0042',
	];

	// No-op: MySQL here rejects DEFAULT on TEXT (error #1101).
	// Create paths must set external_log explicitly (see Monitors\Edit).
	static function install() {
	}

	static function uninstall() {
	}
}

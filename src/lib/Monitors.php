<?php

class Monitors extends \DB\MySQLObject {
	static public $table = 'monitoring_monitor';

	const STATUS_DISABLED = 0;
	const STATUS_ENABLED = 1;

	const TYPE_CURL = 0;
	const TYPE_FOLDER = 1;


}
<?php

class Users extends \DB\MySQLObject{
	static public $table = 'users';

	const STATUS_ACTIVE               =  1;
	const STATUS_INACTIVE             =  2;

}
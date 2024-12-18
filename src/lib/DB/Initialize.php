<?php

namespace DB;

class Initialize {
	private $db;

	public function __construct(string $host_name, string $database_user, string $database_password, string $database_name, int $database_port=3306) {
		$this->db = mysqli_init();
		if (!$this->db) {
			error_log("Can't init mysql database");
			exit;
		}
		if (!$this->db->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10)) {
			error_log("Can't set connection timeout to mysql");
			exit;
		}

		if (!$this->db->real_connect(
			$host_name,
			$database_user,
			$database_password,
			null,
			$database_port
		)) {
			error_log("Can't connect to mysql");
			exit;
		}

		if ($this->create_database($database_name)) {
			$this->create_migrate_table();
		}
	}

	public function __destruct() {
		mysqli_close($this->db);
		$this->db = null;
	}

	public function create_database(string $database_name): bool {
		$result = mysqli_query($this->db, "CREATE DATABASE IF NOT EXISTS `{$database_name}`");
		if (!$result) {
			error_log("Can't create database: ". mysqli_error($this->db));
			return false;
		}
		mysqli_select_db($this->db, $database_name);
		return true;
	}

	public function create_migrate_table(): bool {
		$query = "CREATE TABLE IF NOT EXISTS `migrations` (
			`migration_name` varchar(255) NOT NULL,
			`stime` int NOT NULL,
			PRIMARY KEY (`migration_name`)
		)";
		$result = mysqli_query($this->db, $query);
		if (!$result) {
			error_log("Can't create table: ". mysqli_error($this->db));
			return false;
		}
		return true;
	}

}
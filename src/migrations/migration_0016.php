<?php
class Migration_0016 {
	static public $previous = [
		'migration_0015',
	];

	static function install() {
		global $db;
		$db->execSQL("
			CREATE TABLE IF NOT EXISTS `monitoring_dockers` (
				`id` bigint NOT NULL AUTO_INCREMENT,
				`worker_id` int DEFAULT 0,
				`docker_id` int DEFAULT 0,
				`create_time` int DEFAULT 0,
				`update_time` int DEFAULT 0,
				PRIMARY KEY (`id`),
				UNIQUE (`worker_id`, `docker_id`)
			) ENGINE=InnoDB
		;");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("DROP TABLE IF EXISTS `monitoring_dockers`;");
	}
}

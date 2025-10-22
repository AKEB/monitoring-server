<?php
class Migration_0003 {
	static public $previous = [

	];

	static function install() {
		global $db;
		$db->execSQL("
			INSERT INTO `permissions`
				(`id`, `permission`)
			VALUES
				(11, '".\Workers::PERMISSION_CREATE_WORKER."');
		");
		$db->execSQL("
			INSERT INTO `translates`
				(`id`, `table`, `field`, `field_id`, `language`, `value`)
			VALUES
				(NULL, 'permissions', 'title', 11, 'ru', 'Создание обработчика'),
				(NULL, 'permissions', 'title', 11, 'en', 'Create worker')
		");
	}

	static function uninstall() {
		global $db;
		$db->execSQL("DELETE FROM `translates` WHERE `table` = 'permissions' AND `field_id`=11;");
		$db->execSQL("DELETE FROM `permissions` WHERE `id`=11;");
	}
}


<?php

class Notify extends \DB\MySQLObject implements \PermissionSubject_Interface {

	static public $table = 'notify';

	const LOGS_OBJECT = 'Notify';

	const PERMISSION_ACCESS = 'notify';
	const PERMISSION_SECRETS = 'notify_secrets';
	const PERMISSION_CREATE = 'create_notify';

	const TYPE_TELEGRAM = 0;
	const TYPE_MATTERMOST = 1;

	const STATUS_DISABLED = 0;
	const STATUS_ENABLED = 1;

	static public function subject_hash(): array {
		$data_hash = [];
		foreach(static::permissions_subject_hash() as $subject_id=>$permissionTitle) {
			if (!\Sessions::checkPermission(static::PERMISSION_ACCESS, $subject_id, ACCESS_WRITE)) {
				continue;
			}
			$data_hash[$subject_id] = $permissionTitle;
		}
		return $data_hash;
	}

	static public function type_hash(): array {
		return [
			static::TYPE_TELEGRAM   => \T::Notify_Type_Telegram(),
			static::TYPE_MATTERMOST => \T::Notify_Type_Mattermost(),
		];
	}

	static public function status_hash(): array {
		return [
			static::STATUS_DISABLED   => \T::Notify_Status_Disabled(),
			static::STATUS_ENABLED => \T::Notify_Status_Enabled(),
		];
	}

	static public function permissions_subject_hash(): array {
		$data = static::data(false, ' ORDER BY title ASC');
		$permissions_hash = [];
		foreach($data as $item) {
			$permissions_hash[$item['id']] = $item['title'];
		}
		return $permissions_hash;
	}
	static public function permissions_hash(): array {
		return [
			static::PERMISSION_ACCESS => \T::Notify_Permissions_Access(),
			static::PERMISSION_SECRETS => \T::Notify_Permissions_Secrets(),
		];
	}

	static public function getUserPermissions(array $user): array {
		return [];
	}

}
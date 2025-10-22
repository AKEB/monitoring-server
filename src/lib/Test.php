<?php

class Test extends \Websocket_Parent {
	public function Run(): mixed {
		if ($this->action == 'test') {
			return 'Test response message';
		} else if ($this->action == 'notification_test') {
			\Notifications::sendNotification('Test Message', 'This is a test message from the server');
			return true;
		} else if ($this->action == 'mattermost_test') {
			$user = \Sessions::currentUser();
			if (!$user) {
				return false;
			}
			$mattermost = new \Mattermost(\Config::getInstance()->mattermost_url, \Config::getInstance()->mattermost_token);
			if (
				$mattermost->sendMessageByEmail($user['email'], 'This is a test message from the server')
			) {
				return true;
			} else {
				return false;
			}
		}
		return false;
	}
}
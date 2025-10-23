<?php

class MonitorsWebsocket extends \Websocket_Parent {
	public function Run(): mixed {
		if ($this->action == 'monitors_update') {
			$user = \Sessions::currentUser();
			if (!$user) {
				return false;
			}
			$data = \MonitoringLogs::getMonitorsWithLogs($this->params['count']??10);
			return $data;
		}
		return false;
	}
}
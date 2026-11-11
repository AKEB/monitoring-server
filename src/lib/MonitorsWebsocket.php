<?php

class MonitorsWebsocket extends \Websocket_Parent {
	public function Run(): mixed {
		if ($this->action == 'monitors_update') {
			if (!$this->get_currentUser()) {
				return false;
			}
			$data = \MonitoringLogs::getMonitorsWithData($this->params['count']??10);
			return $data;
		} elseif ($this->action == 'monitor_show_data') {
			if (!$this->get_currentUser()) {
				return false;
			}
			$data = \MonitoringLogs::getMonitorData($this->params['monitor_id']??0, floatval($this->params['period']??1.0));
			return $data;
		} elseif ($this->action == 'monitors_dirty') {
			if (!$this->get_currentUser()) {
				return false;
			}
			return \MonitoringLogs::getDashboardDirtyVersion();
		}
		return false;
	}

	private function get_currentUser(): array|bool {
		$user = \Sessions::currentUser();
		if (!$user) {
			return false;
		}
		return $user;
	}
}
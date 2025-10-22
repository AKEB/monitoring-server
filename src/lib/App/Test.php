<?php

namespace App;

class Test extends \Routing_Parent implements \Routing_Interface {

	public function Run() {
		$this->check_auth();
		$this->check_permissions();

		$this->template = new \Template();
		$this->print_header();

		$this->testPermissions();
		$this->testWebSocket();
		$this->testNotifications();
		$this->testMattermost();
	}

	private function check_permissions() {
		// \Sessions::requestPermission('test_page', 0, READ);

		// $can_read = \Sessions::checkPermission('test_page', 0, READ);
		// if (!$can_read) {
		// 	e403();
		// }
	}

	private function print_header() {
		?>
		<div class="float-start"><h2><i class="bi bi-code-square"></i> <?=\T::TestPage();?></h2></div>
		<div class="clearfix"></div>
		<?php
	}

	private function testPermissions() {
		?>
		<h2 class="mt-3">Testing Permissions</h2>
		<?php
		$worker_objects = [
			'1' => 'worker',
			'2' => 'worker',
			'3' => 'worker',
			'4' => 'worker',
			'5' => 'worker',
		];
		echo '<ul>';
		foreach($worker_objects as $worker_object=>$worker_permission) {
			echo '<li> <i class="bi '.(\Sessions::checkPermission($worker_permission, $worker_object, READ) ? 'bi-check-circle text-success':'bi-dash-circle text-danger').'"></i> '.ucfirst($worker_permission).' '.$worker_object.'</li>';
		}
		echo '</ul>';
	}

	private function testWebSocket() {
		?>
		<h2 class="mt-3">Testing WebSocket</h2>
		<button class="btn btn-warning websocket_getUserName">Get user name Button</button>
		<button class="btn btn-warning websocket_test">Test message Button</button>

		<script nonce="<?=\CSP::nonceRandom();?>">
			$(document).ready(function() {
				$('.websocket_getUserName').on('click', function(){
					wss.send('getUserName',{},(response)=>{
						showSuccessToast(response.message, true);
					})
				});
				$('.websocket_test').on('click', function(){
					wss.send('test',null,(response)=>{
						showSuccessToast(response.message, false, 2000);
					})
				});
			});
		</script>
		<?php
	}

	private function testNotifications() {
		?>
		<h2 class="mt-3">Testing Notifications</h2>
		<button class="btn btn-warning notification_test">Send test notification</button>
		<script nonce="<?=\CSP::nonceRandom();?>">
			$(document).ready(function() {
				$('.notification_test').on('click', function(){
					wss.send('notification_test',null,(response)=>{
						// showSuccessToast(response.message, false, 2000);
					})
				});
			});
		</script>
		<?php
	}

	private function testMattermost() {
		?>
		<h2 class="mt-3">Testing Mattermost</h2>
		<button class="btn btn-warning mattermost_test">Send test message</button>
		<script nonce="<?=\CSP::nonceRandom();?>">
			$(document).ready(function() {
				$('.mattermost_test').on('click', function(){
					wss.send('mattermost_test',null,(response)=>{
						if (response.message == false) {
							showErrorToast('Error sending message', false, 5000);
						} else {
							showSuccessToast('Message sent successfully', false, 5000);
						}
					})
				});
			});
		</script>
		<?php
	}
}

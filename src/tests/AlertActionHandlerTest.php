<?php

error_reporting(E_ALL);

class AlertActionHandlerTest extends PHPUnit\Framework\TestCase {

	function test_is_valid_message_id_common_rejects() {
		$this->assertFalse(\AlertActionHandler::isValidMessageId(\AlertActions::CHANNEL_TELEGRAM, ''));
		$this->assertFalse(\AlertActionHandler::isValidMessageId(\AlertActions::CHANNEL_TELEGRAM, 'x'));
		$this->assertFalse(\AlertActionHandler::isValidMessageId(\AlertActions::CHANNEL_TELEGRAM, '0'));
	}

	function test_telegram_message_id() {
		$this->assertTrue(\AlertActionHandler::isValidMessageId(\AlertActions::CHANNEL_TELEGRAM, '123'));
		$this->assertFalse(\AlertActionHandler::isValidMessageId(\AlertActions::CHANNEL_TELEGRAM, '12a'));
	}

	function test_mattermost_message_id() {
		$ok = str_repeat('a', 26);
		$this->assertTrue(\AlertActionHandler::isValidMessageId(\AlertActions::CHANNEL_MATTERMOST, $ok));
		$this->assertFalse(\AlertActionHandler::isValidMessageId(\AlertActions::CHANNEL_MATTERMOST, strtoupper($ok)));
		$this->assertFalse(\AlertActionHandler::isValidMessageId(\AlertActions::CHANNEL_MATTERMOST, str_repeat('a', 25)));
	}

	function test_max_message_id() {
		$this->assertTrue(\AlertActionHandler::isValidMessageId(\AlertActions::CHANNEL_MAX, 'abc'));
		$this->assertFalse(\AlertActionHandler::isValidMessageId(\AlertActions::CHANNEL_MAX, 'ab'));
		$this->assertFalse(\AlertActionHandler::isValidMessageId('unknown', 'abcdef'));
	}
}

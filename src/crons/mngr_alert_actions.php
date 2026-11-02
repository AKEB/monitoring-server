<?php
require_once("../autoload.php");
$mngr = new \MNGR(2, '128M');

// Telegram webhook often cannot reach RU hosts (Connection timed out).
// Poll getUpdates through the same proxy used for sendMessage.
$processed = \AlertActionHandler::pollTelegramUpdates();
error_log('alert_actions telegram_callbacks='.$processed);

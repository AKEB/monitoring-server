<?php
require_once("../autoload.php");
$mngr = new \MNGR(60, '128M');

$written = \ExternalMonitors::heartbeatAll();
error_log('external heartbeat written='.$written);

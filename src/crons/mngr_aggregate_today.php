<?php
require_once("../autoload.php");
$mngr = new \MNGR(300, '512M');

\MonitoringLogsAggregate::refreshTodayFromRaw();

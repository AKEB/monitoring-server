<?php
require_once("../autoload.php");
$mngr = new \MNGR(60, '128M');

\ServerAgent::evaluateOfflineAll();
$purgedMetrics = \ServerMetrics::purgeOld();
$purgedAuth = \ServerAuthEvents::purgeOld();
error_log('servers offline-check done; purged metrics='.$purgedMetrics.' auth='.$purgedAuth);

<?php
require_once("../autoload.php");
$mngr = new \MNGR(3600, '32M');

\MonitoringSubscribe::delete(false, sql_pholder(' AND `expire_time` < ? ', time()));

\MonitorsEvents::delete(false, sql_pholder(' AND `event_time` < ? ', time() - 365 * 86400));

\MonitoringLogsAggregate::delete(false, sql_pholder(' AND `time` < ? ', time() - 365 * 86400));

\Logs::delete(false, sql_pholder(' AND `time` < ? ', time() - 2 * 365 * 86400));

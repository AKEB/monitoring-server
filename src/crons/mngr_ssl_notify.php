<?php
require_once("../autoload.php");
$mngr = new \MNGR(10, '128M');

/*
SELECT monitor_id, cert_expire, FROM_UNIXTIME(cert_expire) FROM (
	SELECT monitor_id, cert_expire, ROW_NUMBER() OVER (PARTITION BY `monitor_id` ORDER BY `update_time` DESC) as rn FROM `monitoring_logs` WHERE 1 AND `update_time` >= UNIX_TIMESTAMP() - 3600
) as sub WHERE sub.rn=1 AND cert_expire > 0 AND cert_expire < UNIX_TIMESTAMP()+60*86400;
*/

$alert_send_days = [14, 7, 5, 4, 3, 2, 1];

$fields = '`monitor_id`, `cert_expire`';
$sub_sql = sql_pholder(' AND `update_time` >= ? ', time() - 3600);
$sub_query_sql = "SELECT ".$fields.", ROW_NUMBER() OVER (PARTITION BY `monitor_id` ORDER BY `update_time` DESC) as rn FROM ".\MonitoringLogs::$table." WHERE 1 $sub_sql";
$sql_query = "SELECT ".$fields." FROM ($sub_query_sql) as sub WHERE sub.rn = 1 ";
$sql_query .= sql_pholder(' AND `cert_expire` > 0 AND `cert_expire` <= ? ', time() + (max($alert_send_days)+1) * 86400);
$logs = [];
\MonitoringLogs::getDatabase()->db_GetQueryArray($sql_query, $logs);
foreach($logs as $log) {
	$monitor_id = intval($log['monitor_id']??0);
	$cert_expire = intval($log['cert_expire']??0);
	if (!$monitor_id || !$cert_expire) continue;

	$days = intval(($cert_expire - time()) / 86400);
	if (!in_array($days, $alert_send_days) && $days > 0) continue;

	$monitor = \Monitors::get($monitor_id);
	if (!$monitor) continue;
	if (!\Monitors::is_enable($monitor_id)) continue;

	$monitor['type'] ??= 0;
	$monitor['ssl_cert_expire_alert'] ??= 0;
	$monitor['last_send_ssl_cert_expire_alert'] ??= 0;

	if ($monitor['type'] != \Monitors::TYPE_HTTPS) continue;
	if (!$monitor['ssl_cert_expire_alert']) continue;
	if (date("Y-m-d",$monitor['last_send_ssl_cert_expire_alert']) == date("Y-m-d", time())) continue;

	$title = \T::Monitor_Expire_Title();
	$text = \T::Monitor_Expire_Text($monitor['title'], $days, (\T::declension($days, \T::Monitor_Expire_Day(), \T::Monitor_Expire_Days(), \T::Monitor_Expire_Days5())));

	if ($monitor['additional_text']) {
		$text .= "\n\n" . $monitor['additional_text'];
	}

	\MonitoringNotify::sendNotification($monitor_id, $text, $title, false);
	$subs = \MonitoringSubscribe::data(false, sql_pholder(' AND `monitor_id` = ? AND `expire_time` >= ? ', $monitor_id, time()), '`user_id`');
	if (is_array($subs) && $subs) {
		foreach($subs as $sub) {
			if (!$sub['user_id']) continue;
			\Notifications::sendNotification($title, $text, 0, $sub['user_id']);
		}
	}
	\Monitors::save([
		'id' => $monitor_id,
		'last_send_ssl_cert_expire_alert' => time(),
		'update_time' => time(),
		'_mode' => \DB\Common::CSMODE_UPDATE,
	]);

	error_log('monitor_id='.$monitor_id.' cert_expire='.date("Y-m-d H:i:s",$cert_expire).' days='.$days);
}

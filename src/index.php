<?php
require_once("./autoload.php");

// Content Security Policy
\ContentSecurityPolicy::init();

// Add another Content Security Policy style-src
// \ContentSecurityPolicy::set_style_src([]);
// \ContentSecurityPolicy::add_style_src("'self'");
// \ContentSecurityPolicy::add_style_src("'unsafe-inline'");

// Add another Content Security Policy media-src
// \ContentSecurityPolicy::add_media_src('https://fonts.ninja/');

// Print Content Security Policy Header
\ContentSecurityPolicy::print_header();

\Permissions::add_permission(\Workers::PERMISSION_CREATE, \T::Worker_Permissions_Create());
\Permissions::add_permission(\Monitors::PERMISSION_CREATE, \T::Monitor_Permissions_Create());
\Permissions::add_permission(\Notify::PERMISSION_CREATE, \T::Notify_Permissions_Create());
\Permissions::add_permission(\Maintenances::PERMISSION_CREATE, \T::Maintenances_Permissions_Create());
\Permissions::add_permission(\Dockers::PERMISSION_CREATE, \T::Docker_Permissions_Create());
\Permissions::add_permission(\Signatures::PERMISSION_CREATE, \T::Signature_Permissions_Create());

// Add another permissions subject types
\Permissions::set_subject_type('worker', '\\Workers', 'Workers');
\Permissions::set_subject_type('monitor', '\\Monitors', 'Monitors');
\Permissions::set_subject_type('notify', '\\Notify', 'Notify');
\Permissions::set_subject_type('maintenance', '\\Maintenances', 'Maintenances');
\Permissions::set_subject_type('docker', '\\Dockers', 'Docker');
\Permissions::set_subject_type('signature', '\\Signatures', 'Signatures');

\Sessions::session_init(true); // This need to check Permissions and user language

\Logs::add_params_hash_for_print('worker_key_hash', \Logs::PARAM_HASH_SECRET);
\Logs::add_params_hash_for_print('token_hash', \Logs::PARAM_HASH_SECRET);
\Logs::add_params_hash_for_print('plaintext', \Logs::PARAM_HASH_SECRET);
\Logs::add_params_hash_for_print('Authorization', \Logs::PARAM_HASH_SECRET);
\Logs::add_params_hash_for_print('X-Api-Token', \Logs::PARAM_HASH_SECRET);
\Logs::add_object(\Workers::LOGS_OBJECT, \Workers::LOGS_OBJECT);
\Logs::add_object(\UserApiTokens::LOGS_OBJECT, \UserApiTokens::LOGS_OBJECT);
\Logs::add_object(\Monitors::LOGS_OBJECT, \Monitors::LOGS_OBJECT);
\Logs::add_object(\MonitoringJobs::LOGS_OBJECT, \MonitoringJobs::LOGS_OBJECT);
\Logs::add_object(\MonitoringSubscribe::LOGS_OBJECT, \MonitoringSubscribe::LOGS_OBJECT);
\Logs::add_object(\Dockers::LOGS_OBJECT, \Dockers::LOGS_OBJECT);
\Logs::add_object(\MonitoringDockers::LOGS_OBJECT, \MonitoringDockers::LOGS_OBJECT);
\Logs::add_object(\Maintenances::LOGS_OBJECT, \Maintenances::LOGS_OBJECT);
\Logs::add_object(\MonitoringMaintenances::LOGS_OBJECT, \MonitoringMaintenances::LOGS_OBJECT);
\Logs::add_object(\Notify::LOGS_OBJECT, \Notify::LOGS_OBJECT);
\Logs::add_object(\MonitoringNotify::LOGS_OBJECT, \MonitoringNotify::LOGS_OBJECT);
\Logs::add_object(\Signatures::LOGS_OBJECT, \Signatures::LOGS_OBJECT);

// Set Application Settings
\Template::setProjectName("Monitoring");
\Template::setTheme('auto');

\Template::addHeadMeta(['content' => "#212529",'name' => 'msapplication-TileColor']);
\Template::addHeadMeta(['name' => 'theme-color','content' => "#212529"]);

// Add another css files
\Template::addCSSFile('/css/main.css');

// Add another js files
\Template::addJSFile('/js/echarts.min.js');
\Template::addJSFile('/js/locale_'.\T::getCurrentLanguage().'.js');
\Template::addJSFile('/js/main.js');

\Template::addMenuItem(new \MenuItem('', \T::Menu_Home(), '/', null, null));
\Template::addMenuItem(new \MenuItem('bi bi-key', \T::Menu_ApiTokens(), '/settings/api/', null, null));
\Template::addMenuItem(new \MenuItem('bi bi-hammer', \T::Maintenances_Menu(), '/maintenances/', null, null));
\Template::addMenuAdminItem(new \MenuItem('bi bi-hdd-network', \T::Worker_Menu(), '/admin/workers/', null, [
	new \MenuPermissionItem(\Workers::PERMISSION_ACCESS, -1, READ),
	new \MenuPermissionItem(\Workers::PERMISSION_CREATE, 0, WRITE),
]));
\Template::addMenuAdminItem(new \MenuItem('bi bi-question-octagon', \T::Monitor_Menu(), '/admin/monitors/', null, [
	new \MenuPermissionItem(\Monitors::PERMISSION_ACCESS, -1, READ),
	new \MenuPermissionItem(\Monitors::PERMISSION_CREATE, 0, WRITE),
]));
\Template::addMenuAdminItem(new \MenuItem('bi bi-bell', \T::Notify_Menu(), '/admin/notify/', null, [
	new \MenuPermissionItem(\Notify::PERMISSION_ACCESS, -1, READ),
	new \MenuPermissionItem(\Notify::PERMISSION_CREATE, 0, WRITE),
]));
\Template::addMenuAdminItem(new \MenuItem('bi bi-pen', \T::Signature_Menu(), '/admin/signatures/', null, [
	new \MenuPermissionItem(\Signatures::PERMISSION_ACCESS, -1, READ),
	new \MenuPermissionItem(\Signatures::PERMISSION_CREATE, 0, WRITE),
]));
\Template::addMenuAdminItem(new \MenuItem('bi bi-box', \T::Docker_Menu(), '/admin/dockers/', null, [
	new \MenuPermissionItem(\Dockers::PERMISSION_ACCESS, -1, READ),
	new \MenuPermissionItem(\Dockers::PERMISSION_CREATE, 0, WRITE),
]));

// Add another Websocket item
\Websocket::addAction('monitors_update', '\\MonitorsWebsocket');
\Websocket::addAction('monitor_show_data', '\\MonitorsWebsocket');

// ADD Another Routes
// API/MCP first: framework matches routes as URI suffixes (`preg_match('~'.$route.'$~')`),
// so `/maintenances/` would otherwise steal `/api/v2/maintenances/`.
\Routes::addRoute('/api/v1/status/', '\\App\\Api\\V1\\Status');
\Routes::addRoute('/api/v2/(?P<resource>[a-z]+)/(?P<id>\d+)/(?P<action>permissions)/', '\\App\\Api\\V2\\Gateway');
\Routes::addRoute('/api/v2/(?P<resource>[a-z]+)/(?P<id>\d+)/', '\\App\\Api\\V2\\Gateway');
\Routes::addRoute('/api/v2/(?P<resource>[a-z]+)/', '\\App\\Api\\V2\\Gateway');
\Routes::addRoute('/api/docs/openapi.json', '\\App\\Api\\OpenApi');
\Routes::addRoute('/api/docs/', '\\App\\Api\\Docs');
\Routes::addRoute('/mcp/', '\\App\\Mcp\\Endpoint');
\Routes::addRoute('/mcp', '\\App\\Mcp\\Endpoint');
\Routes::addRoute('/api/external/(?P<hash>[a-fA-F0-9]{64})/', '\\App\\Api\\External');
\Routes::addRoute('/api/alert-action/(?P<channel>telegram|mattermost|max)/(?P<token>[a-fA-F0-9]{32})/', '\\App\\Api\\AlertAction');
\Routes::addRoute('/settings/api/', '\\App\\Settings\\ApiTokens');

\Routes::addRoute('/admin/workers/edit/(?P<worker_id>\d+)/', '\\App\\Workers\\Edit');
\Routes::addRoute('/admin/workers/edit/', '\\App\\Workers\\Edit');
\Routes::addRoute('/admin/workers/(?P<action>[^/]+)/(?P<worker_id>\d+)/', '\\App\\Workers\\Show');
\Routes::addRoute('/admin/workers/', '\\App\\Workers\\Show');

\Routes::addRoute('/admin/monitors/(?P<parent_id>\d+)/edit/(?P<monitor_id>\d+)/', '\\App\\Monitors\\Edit');
\Routes::addRoute('/admin/monitors/(?P<parent_id>\d+)/edit/', '\\App\\Monitors\\Edit');
\Routes::addRoute('/admin/monitors/(?P<parent_id>\d+)/(?P<action>[^/]+)/(?P<monitor_id>\d+)/', '\\App\\Monitors\\Show');
\Routes::addRoute('/admin/monitors/(?P<parent_id>\d+)/', '\\App\\Monitors\\Show');
\Routes::addRoute('/admin/monitors/', '\\App\\Monitors\\Show');

\Routes::addRoute('/monitor/(?P<monitor_id>\d+)/', '\\App\\Main');

\Routes::addRoute('/admin/notify/edit/(?P<notify_id>\d+)/', '\\App\\Notify\\Edit');
\Routes::addRoute('/admin/notify/edit/', '\\App\\Notify\\Edit');
\Routes::addRoute('/admin/notify/(?P<action>[^/]+)/(?P<notify_id>\d+)/', '\\App\\Notify\\Show');
\Routes::addRoute('/admin/notify/', '\\App\\Notify\\Show');

\Routes::addRoute('/admin/signatures/edit/(?P<signature_id>\d+)/', '\\App\\Signatures\\Edit');
\Routes::addRoute('/admin/signatures/edit/', '\\App\\Signatures\\Edit');
\Routes::addRoute('/admin/signatures/(?P<action>[^/]+)/(?P<signature_id>\d+)/', '\\App\\Signatures\\Show');
\Routes::addRoute('/admin/signatures/', '\\App\\Signatures\\Show');

\Routes::addRoute('/maintenances/edit/(?P<maintenance_id>\d+)/', '\\App\\Maintenances\\Edit');
\Routes::addRoute('/maintenances/edit/', '\\App\\Maintenances\\Edit');
\Routes::addRoute('/maintenances/(?P<action>[^/]+)/(?P<maintenance_id>\d+)/', '\\App\\Maintenances\\Show');
\Routes::addRoute('/maintenances/', '\\App\\Maintenances\\Show');

\Routes::addRoute('/admin/dockers/edit/(?P<docker_id>\d+)/', '\\App\\Dockers\\Edit');
\Routes::addRoute('/admin/dockers/edit/', '\\App\\Dockers\\Edit');
\Routes::addRoute('/admin/dockers/(?P<action>[^/]+)/(?P<docker_id>\d+)/', '\\App\\Dockers\\Show');
\Routes::addRoute('/admin/dockers/', '\\App\\Dockers\\Show');

\MonitoringSubscribe::init();

new \Routing();

// Main Page
$app = new \App\Main();
$app->Run();

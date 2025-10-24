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

\Permissions::add_permission(\Workers::PERMISSION_CREATE_WORKER, \T::Worker_Permissions_CreateWorker());
\Permissions::add_permission(\Monitors::PERMISSION_CREATE_MONITOR, \T::Monitor_Permissions_CreateMonitor());

// Add another permissions subject types
\Permissions::set_subject_type('worker', '\\Workers', 'Workers');
\Permissions::set_subject_type('monitor', '\\Monitors', 'Monitors');

\Sessions::session_init(true); // This need to check Permissions and user language

// Set Application Settings
\Template::setProjectName("Monitoring");
\Template::setTheme('auto');

\Template::addHeadMeta(['content' => "#212529",'name' => 'msapplication-TileColor']);
\Template::addHeadMeta(['name' => 'theme-color','content' => "#212529"]);

// Add another css files
\Template::addCSSFile('/css/main.css');

// Add another js files
\Template::addJSFile('/js/locale_'.\T::getCurrentLanguage().'.js');
\Template::addJSFile('/js/main.js');

\Template::addMenuItem(new \MenuItem('', \T::Menu_Home(), '/', null, null));
\Template::addMenuAdminItem(new \MenuItem('bi bi-code-square', \T::Worker_Menu(), '/workers/', null, new \MenuPermissionItem(\Workers::PERMISSION_WORKER, -1, READ)));

// Add another Websocket item
\Websocket::addAction('monitors_update', '\\MonitorsWebsocket');

// ADD Another Routes
\Routes::addRoute('/workers/edit/(?P<worker_id>\d+)/', '\\App\\Workers\\Edit');
\Routes::addRoute('/workers/edit/', '\\App\\Workers\\Edit');
\Routes::addRoute('/workers/(?P<action>[^/]+)/(?P<worker_id>\d+)/', '\\App\\Workers\\Show');
\Routes::addRoute('/workers/', '\\App\\Workers\\Show');

new \Routing();

// Main Page
$app = new \App\Main();
$app->Run();

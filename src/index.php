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

new \T([
	'en' => 'lang/worker_en.yml',
	'ru' => 'lang/worker_ru.yml',
]);


// Add another permissions subject types
\Permissions::set_subject_type('worker', '\\Workers', 'Workers');

\Sessions::session_init(true); // This need to check Permissions and user language

// Set Application Settings
\Template::setProjectName("Monitoring");
\Template::setTheme('auto');

// Add another css files
\Template::addCSSFile('/css/main.css');

// Add another js files
\Template::addJSFile('/js/locale_'.\T::getCurrentLanguage().'.js');
\Template::addJSFile('/js/main.js');

// Add another Menu items
\Template::setMenuItems([
	[
		'title' => \T::Menu_Home(),
		'link'=>'/',
	],
	[
		'title' => \T::Menu_Test(),
		'link' => '/test/',
		'icon' => "bi bi-code-square"
		// 'permission' => \Sessions::checkPermission(\Permissions::ADMIN, 0, READ),
	],
	[
		'title' => \T::Worker_Menu(),
		'link' => '/workers/',
		'icon' => "bi bi-hdd-network",
		'permission' => \Sessions::checkPermission(\Workers::PERMISSION_WORKER, -1, READ),
	],
]);

// Add another Websocket item
\Websocket::addAction('test', '\\Test');
\Websocket::addAction('notification_test', '\\Test');
\Websocket::addAction('mattermost_test', '\\Test');

// ADD Another Routes
// \Routes::addRoute('/test/', '\\App\\Test');
\Routes::addRoute('/workers/edit/(?P<worker_id>\d+)/', '\\App\\Workers\\Edit');
\Routes::addRoute('/workers/edit/', '\\App\\Workers\\Edit');
\Routes::addRoute('/workers/(?P<action>[^/]+)/(?P<worker_id>\d+)/', '\\App\\Workers\\Show');
\Routes::addRoute('/workers/', '\\App\\Workers\\Show');

new \Routing();

// Main Page
$app = new \App\Main();
$app->Run();

<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->setDefaultNamespace('App\\Controllers');
$routes->setDefaultController('HomeController');
$routes->setDefaultMethod('index');
$routes->setTranslateURIDashes(false);
$routes->set404Override();
$routes->setAutoRoute(false);

$routes->get('/', 'HomeController::index');
$routes->get('health', 'HealthController::index');
$routes->get('track', 'TrackingController::index');
$routes->post('track/status', 'TrackingController::status');

$routes->get('login', 'AuthController::login', ['filter' => 'guest']);
$routes->post('login', 'AuthController::attempt', ['filter' => 'guest']);
$routes->post('logout', 'AuthController::logout', ['filter' => 'auth']);

$routes->get('dashboard', 'DashboardController::index', ['filter' => 'auth']);

$routes->group('receiving', ['filter' => 'auth'], static function (RouteCollection $routes): void {
    $routes->get('/', 'ReceivingController::index', ['filter' => 'permission:Receiving,Receiving,VIEW']);
    $routes->get('new', 'ReceivingController::new', ['filter' => 'permission:Receiving,Receiving,CREATE']);
    $routes->post('/', 'ReceivingController::create', ['filter' => 'permission:Receiving,Receiving,CREATE']);
    $routes->get('(:segment)/edit', 'ReceivingController::edit/$1', ['filter' => 'permission:Receiving,Receiving,UPDATE']);
    $routes->post('(:segment)', 'ReceivingController::update/$1', ['filter' => 'permission:Receiving,Receiving,UPDATE']);
    $routes->get('(:segment)/attachments/(:segment)', 'ReceivingController::attachment/$1/$2', ['filter' => 'permission:Receiving,Receiving,VIEW']);
    $routes->get('(:segment)/client-tracking-qr', 'ReceivingController::clientTrackingQr/$1', ['filter' => 'permission:Receiving,Receiving,VIEW']);
    $routes->get('(:segment)', 'ReceivingController::show/$1', ['filter' => 'permission:Receiving,Receiving,VIEW']);
});

$routes->group('', ['filter' => 'auth'], static function (RouteCollection $routes): void {
    $routes->get('inbox', 'GeneralInboxController::index', ['filter' => 'permission:General Inbox,Inbox,VIEW']);
    $routes->get('inbox/events', 'GeneralInboxController::events', ['filter' => 'permission:General Inbox,Inbox,VIEW']);
    $routes->get('monitoring', 'MonitoringController::index', ['filter' => 'permission:Monitoring,Monitoring,VIEW']);
    $routes->get('archive', 'ArchiveController::index', ['filter' => 'permission:Document Archive,Archive,VIEW']);
    $routes->get('reports', 'ReportsController::index', ['filter' => 'permission:Reports,Reports,VIEW']);
    $routes->get('reports/export/csv', 'ReportsController::csv', ['filter' => 'permission:Reports,Reports,EXPORT']);
    $routes->get('reports/export/excel', 'ReportsController::excel', ['filter' => 'permission:Reports,Reports,EXPORT']);
    $routes->get('reports/print', 'ReportsController::print', ['filter' => 'permission:Reports,Reports,EXPORT']);
});

$routes->group('documents', ['filter' => 'auth'], static function (RouteCollection $routes): void {
    $routes->get('scan/(:segment)', 'DocumentController::scan/$1', ['filter' => 'permission:Document Details,Document Details,VIEW']);
    // Engagement endpoints enforce live document assignment server-side. They
    // intentionally do not require Document Details permission because Confirm
    // is also an action directly available from General Inbox.
    $routes->get('(:segment)/engagement', 'DocumentController::engagement/$1');
    $routes->post('(:segment)/confirm', 'DocumentController::confirm/$1');
    $routes->post('(:segment)/heartbeat', 'DocumentController::heartbeat/$1');
    $routes->get('(:segment)/qr', 'DocumentController::qr/$1', ['filter' => 'permission:Document Details,Document Details,VIEW']);
    $routes->get('(:segment)/qr/print', 'DocumentController::printQr/$1', ['filter' => 'permission:Document Details,Document Details,VIEW']);
    $routes->get('(:segment)/attachments/(:segment)', 'DocumentController::attachment/$1/$2', ['filter' => 'permission:Document Details,Document Details,VIEW']);
    // Reassignment correction has stricter document-state/role checks in the
    // service and is intentionally independent of the general ROUTE permission.
    $routes->post('(:segment)/reassign', 'DocumentController::reassign/$1');
    // Recall is a narrow exception for the immediately previous sender/Section
    // Head and is allowed only while that outgoing route is still the latest event.
    $routes->post('(:segment)/recall', 'DocumentController::recall/$1');
    $routes->post('(:segment)/route', 'DocumentController::route/$1', ['filter' => 'permission:Document Routing,Routing,ROUTE']);
    $routes->get('(:segment)', 'DocumentController::show/$1', ['filter' => 'permission:Document Details,Document Details,VIEW']);
});

// Compatibility redirects for Step 6 URLs generated before the BRD terminology was reconciled.
$routes->get('routing', static fn () => redirect()->to(site_url('inbox')), ['filter' => 'auth']);
$routes->get('routing/(:segment)', static fn (string $id) => redirect()->to(site_url('documents/' . $id)), ['filter' => 'auth']);

$routes->group('admin', ['filter' => 'auth'], static function (RouteCollection $routes): void {
    $routes->get('users', 'Admin\\UsersController::index', ['filter' => 'permission:User Management,Users,VIEW']);
    $routes->get('users/new', 'Admin\\UsersController::new', ['filter' => 'permission:User Management,Users,CREATE']);
    $routes->post('users', 'Admin\\UsersController::create', ['filter' => 'permission:User Management,Users,CREATE']);
    $routes->get('users/(:segment)/edit', 'Admin\\UsersController::edit/$1', ['filter' => 'permission:User Management,Users,UPDATE']);
    $routes->post('users/(:segment)', 'Admin\\UsersController::update/$1', ['filter' => 'permission:User Management,Users,UPDATE']);
    $routes->post('users/(:segment)/deactivate', 'Admin\\UsersController::deactivate/$1', ['filter' => 'permission:User Management,Users,DELETE']);
    $routes->post('users/(:segment)/status', 'Admin\\UsersController::status/$1', ['filter' => 'permission:User Management,Users,DELETE']);
    $routes->get('users/(:segment)', 'Admin\\UsersController::show/$1', ['filter' => 'permission:User Management,Users,VIEW']);
    $routes->get('roles', 'Admin\\RolesController::index', ['filter' => 'permission:Roles & Permissions,Roles,VIEW']);
    $routes->get('roles/new', 'Admin\\RolesController::new', ['filter' => 'permission:Roles & Permissions,Roles,MANAGE']);
    $routes->post('roles', 'Admin\\RolesController::create', ['filter' => 'permission:Roles & Permissions,Roles,MANAGE']);
    $routes->get('roles/(:segment)/edit', 'Admin\\RolesController::edit/$1', ['filter' => 'permission:Roles & Permissions,Roles,MANAGE']);
    $routes->post('roles/(:segment)', 'Admin\\RolesController::update/$1', ['filter' => 'permission:Roles & Permissions,Roles,MANAGE']);
    $routes->post('roles/(:segment)/delete', 'Admin\\RolesController::delete/$1', ['filter' => 'permission:Roles & Permissions,Roles,MANAGE']);
    $routes->get('roles/(:segment)', 'Admin\\RolesController::show/$1', ['filter' => 'permission:Roles & Permissions,Roles,VIEW']);

    $routes->get('organization', 'Admin\\OrganizationController::landing');
    $routes->get('organization/offices', 'Admin\\OrganizationController::offices', ['filter' => 'permission:Organization,Offices,MANAGE']);
    $routes->get('organization/offices/new', 'Admin\\OrganizationController::newOffice', ['filter' => 'permission:Organization,Offices,MANAGE']);
    $routes->post('organization/offices', 'Admin\\OrganizationController::createOffice', ['filter' => 'permission:Organization,Offices,MANAGE']);
    $routes->get('organization/offices/(:segment)/edit', 'Admin\\OrganizationController::editOffice/$1', ['filter' => 'permission:Organization,Offices,MANAGE']);
    $routes->post('organization/offices/(:segment)', 'Admin\\OrganizationController::updateOffice/$1', ['filter' => 'permission:Organization,Offices,MANAGE']);
    $routes->post('organization/offices/(:segment)/status', 'Admin\\OrganizationController::officeStatus/$1', ['filter' => 'permission:Organization,Offices,MANAGE']);
    $routes->get('organization/offices/(:segment)', 'Admin\\OrganizationController::showOffice/$1', ['filter' => 'permission:Organization,Offices,MANAGE']);

    $routes->get('organization/departments', 'Admin\\OrganizationController::departments', ['filter' => 'permission:Organization,Departments,MANAGE']);
    $routes->get('organization/departments/new', 'Admin\\OrganizationController::newDepartment', ['filter' => 'permission:Organization,Departments,MANAGE']);
    $routes->post('organization/departments', 'Admin\\OrganizationController::createDepartment', ['filter' => 'permission:Organization,Departments,MANAGE']);
    $routes->get('organization/departments/(:segment)/edit', 'Admin\\OrganizationController::editDepartment/$1', ['filter' => 'permission:Organization,Departments,MANAGE']);
    $routes->post('organization/departments/(:segment)', 'Admin\\OrganizationController::updateDepartment/$1', ['filter' => 'permission:Organization,Departments,MANAGE']);
    $routes->post('organization/departments/(:segment)/status', 'Admin\\OrganizationController::departmentStatus/$1', ['filter' => 'permission:Organization,Departments,MANAGE']);
    $routes->get('organization/departments/(:segment)', 'Admin\\OrganizationController::showDepartment/$1', ['filter' => 'permission:Organization,Departments,MANAGE']);

    $routes->get('organization/sections', 'Admin\\OrganizationController::sections', ['filter' => 'permission:Organization,Sections,MANAGE']);
    $routes->get('organization/sections/new', 'Admin\\OrganizationController::newSection', ['filter' => 'permission:Organization,Sections,MANAGE']);
    $routes->post('organization/sections', 'Admin\\OrganizationController::createSection', ['filter' => 'permission:Organization,Sections,MANAGE']);
    $routes->get('organization/sections/(:segment)/edit', 'Admin\\OrganizationController::editSection/$1', ['filter' => 'permission:Organization,Sections,MANAGE']);
    $routes->post('organization/sections/(:segment)', 'Admin\\OrganizationController::updateSection/$1', ['filter' => 'permission:Organization,Sections,MANAGE']);
    $routes->post('organization/sections/(:segment)/status', 'Admin\\OrganizationController::sectionStatus/$1', ['filter' => 'permission:Organization,Sections,MANAGE']);
    $routes->get('organization/sections/(:segment)', 'Admin\\OrganizationController::showSection/$1', ['filter' => 'permission:Organization,Sections,MANAGE']);
    $routes->get('organization/sections/(:segment)/assignments', 'Admin\\OrganizationController::assignments/$1', ['filter' => 'permission:Organization,Sections,MANAGE']);
    $routes->post('organization/sections/(:segment)/assignments', 'Admin\\OrganizationController::saveAssignments/$1', ['filter' => 'permission:Organization,Sections,MANAGE']);

    $routes->get('document-types', 'Admin\\DocumentTypesController::index', ['filter' => 'permission:Document Types,Document Types,VIEW']);
    $routes->get('document-types/new', 'Admin\\DocumentTypesController::new', ['filter' => 'permission:Document Types,Document Types,MANAGE']);
    $routes->post('document-types', 'Admin\\DocumentTypesController::create', ['filter' => 'permission:Document Types,Document Types,MANAGE']);
    $routes->get('document-types/(:segment)/edit', 'Admin\\DocumentTypesController::edit/$1', ['filter' => 'permission:Document Types,Document Types,MANAGE']);
    $routes->post('document-types/(:segment)', 'Admin\\DocumentTypesController::update/$1', ['filter' => 'permission:Document Types,Document Types,MANAGE']);
    $routes->post('document-types/(:segment)/status', 'Admin\\DocumentTypesController::status/$1', ['filter' => 'permission:Document Types,Document Types,MANAGE']);
    $routes->get('document-types/(:segment)', 'Admin\\DocumentTypesController::show/$1', ['filter' => 'permission:Document Types,Document Types,VIEW']);

    $routes->get('routing-actions', 'Admin\\RoutingActionsController::index', ['filter' => 'permission:Routing Actions,Routing Actions,VIEW']);
    $routes->get('routing-actions/new', 'Admin\\RoutingActionsController::new', ['filter' => 'permission:Routing Actions,Routing Actions,MANAGE']);
    $routes->post('routing-actions', 'Admin\\RoutingActionsController::create', ['filter' => 'permission:Routing Actions,Routing Actions,MANAGE']);
    $routes->get('routing-actions/(:segment)/edit', 'Admin\\RoutingActionsController::edit/$1', ['filter' => 'permission:Routing Actions,Routing Actions,MANAGE']);
    $routes->post('routing-actions/(:segment)', 'Admin\\RoutingActionsController::update/$1', ['filter' => 'permission:Routing Actions,Routing Actions,MANAGE']);
    $routes->post('routing-actions/(:segment)/status', 'Admin\\RoutingActionsController::status/$1', ['filter' => 'permission:Routing Actions,Routing Actions,MANAGE']);
    $routes->get('routing-actions/(:segment)', 'Admin\\RoutingActionsController::show/$1', ['filter' => 'permission:Routing Actions,Routing Actions,VIEW']);

    $routes->get('email-settings', 'Admin\\EmailSettingsController::index', ['filter' => 'permission:Email Configuration,Email Settings,MANAGE']);
    $routes->post('email-settings', 'Admin\\EmailSettingsController::update', ['filter' => 'permission:Email Configuration,Email Settings,MANAGE']);
    $routes->post('email-settings/test', 'Admin\\EmailSettingsController::test', ['filter' => 'permission:Email Configuration,Email Settings,MANAGE']);

    $routes->get('telegram-settings', 'Admin\\TelegramSettingsController::index', ['filter' => 'permission:Telegram Configuration,Telegram Settings,MANAGE']);
    $routes->post('telegram-settings', 'Admin\\TelegramSettingsController::update', ['filter' => 'permission:Telegram Configuration,Telegram Settings,MANAGE']);
    $routes->post('telegram-settings/test', 'Admin\\TelegramSettingsController::test', ['filter' => 'permission:Telegram Configuration,Telegram Settings,MANAGE']);

    $routes->get('audit', 'Admin\\AuditLogController::index', ['filter' => 'permission:Audit Log,Audit Log,VIEW']);
    $routes->get('audit/export/csv', 'Admin\\AuditLogController::csv', ['filter' => 'permission:Audit Log,Audit Log,VIEW']);
});

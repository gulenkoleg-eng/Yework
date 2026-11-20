<?php
declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\CabinetController;
use App\Controllers\SiteController;
use App\Controllers\Admin\AdminController;

/** @var App\Core\Router $router */

// Публічні сторінки
$router->get('/', [SiteController::class, 'home']);
$router->get('/jobs', [SiteController::class, 'jobs']);
$router->get('/jobs/{id}', [SiteController::class, 'vacancy']);
$router->post('/jobs/{id}/apply', [SiteController::class, 'apply'], []);
$router->get('/rules', [SiteController::class, 'rules']);

// Вхід і реєстрація
$router->get('/login', [AuthController::class, 'loginForm']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);
$router->get('/register', [AuthController::class, 'registerForm']);
$router->post('/register', [AuthController::class, 'register']);
$router->get('/recover', [AuthController::class, 'recoverForm']);
$router->post('/recover', [AuthController::class, 'recover']);

// Кабінет
$router->get('/cabinet', [CabinetController::class, 'index'], []);
$router->post('/cabinet/profile', [CabinetController::class, 'profile'], []);
$router->post('/cabinet/password', [CabinetController::class, 'password'], []);
$router->get('/cabinet/employer', [CabinetController::class, 'employerForm'], []);
$router->post('/cabinet/employer', [CabinetController::class, 'employerSave'], []);
$router->get('/cabinet/vacancies', [CabinetController::class, 'vacancies'], []);
$router->get('/cabinet/vacancies/new', [CabinetController::class, 'vacancyForm'], []);
$router->post('/cabinet/vacancies/new', [CabinetController::class, 'vacancySave'], []);
$router->get('/cabinet/vacancies/{id}', [CabinetController::class, 'vacancyView'], []);
$router->get('/cabinet/vacancies/{id}/edit', [CabinetController::class, 'vacancyForm'], []);
$router->post('/cabinet/vacancies/{id}/edit', [CabinetController::class, 'vacancySave'], []);
$router->post('/cabinet/vacancies/{id}/archive', [CabinetController::class, 'vacancyArchive'], []);
$router->get('/cabinet/applications', [CabinetController::class, 'applications'], []);

// Адмін-панель (права перевіряються і тут, і в контролері)
$router->get('/admin', [AdminController::class, 'dashboard'], []);
$router->get('/admin/moderation', [AdminController::class, 'moderation'], ['jobs.listing:moderate']);
$router->post('/admin/moderation/{id}', [AdminController::class, 'moderate'], ['jobs.listing:moderate']);
$router->get('/admin/users', [AdminController::class, 'users'], ['identity.user:view']);
$router->get('/admin/users/{id}', [AdminController::class, 'user'], ['identity.user:view']);
$router->post('/admin/users/{id}/status', [AdminController::class, 'userStatus'], ['identity.user:update']);
$router->post('/admin/users/{id}/roles', [AdminController::class, 'userRoles'], ['access.role:manage_settings']);
$router->get('/admin/roles', [AdminController::class, 'roles'], ['access.role:view']);
$router->get('/admin/audit', [AdminController::class, 'audit'], ['audit.log:view']);
$router->get('/admin/themes', [AdminController::class, 'themes'], ['theming.theme:view']);
$router->post('/admin/themes', [AdminController::class, 'themePublish'], ['theming.theme:publish']);
$router->get('/admin/settings', [AdminController::class, 'settings'], ['settings.system:manage_settings']);
$router->post('/admin/settings', [AdminController::class, 'settingsSave'], ['settings.system:manage_settings']);
$router->get('/admin/outbox', [AdminController::class, 'outbox'], ['settings.system:manage_settings']);
$router->post('/admin/upgrade', [AdminController::class, 'upgrade'], ['settings.system:manage_settings']);

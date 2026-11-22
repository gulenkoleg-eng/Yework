<?php
declare(strict_types=1);

use App\Controllers\AdsController;
use App\Controllers\AuthController;
use App\Controllers\CabinetController;
use App\Controllers\ResumeController;
use App\Controllers\SiteController;
use App\Controllers\Admin\AdminController;
use App\Controllers\Admin\ResumeModerationController;
use App\Controllers\Admin\TelegramAdminController;
use App\Controllers\TelegramController;

/** @var App\Core\Router $router */

// Публічні сторінки
$router->get('/', [SiteController::class, 'home']);
$router->get('/jobs', [SiteController::class, 'jobs']);
$router->get('/jobs/{id}', [SiteController::class, 'vacancy']);
$router->post('/jobs/{id}/apply', [SiteController::class, 'apply'], []);
$router->get('/rules', [SiteController::class, 'rules']);
$router->get('/about', [SiteController::class, 'about']);
$router->get('/contacts', [SiteController::class, 'contacts']);
$router->get('/media/{kind}/{file}', [SiteController::class, 'media']);
$router->get('/go/{id}', [SiteController::class, 'promoClick']);

// Резюме: пошук для роботодавців, сторінка резюме, PDF, запрошення, фото профілю
$router->get('/resumes', [ResumeController::class, 'index']);
$router->get('/resumes/{id}', [ResumeController::class, 'show']);
$router->get('/resumes/{id}/pdf', [ResumeController::class, 'pdf']);
$router->post('/resumes/{id}/phone', [ResumeController::class, 'phone']);
$router->post('/resumes/{id}/invite', [ResumeController::class, 'invite'], []);
$router->get('/photo/{id}', [ResumeController::class, 'photo']);

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
// GET потрібен, щоб при помилці в датах сторінка вакансії показалася знову з підсвіченими полями.
$router->get('/cabinet/vacancies/{id}/restore', [CabinetController::class, 'vacancyView'], []);
$router->post('/cabinet/vacancies/{id}/restore', [CabinetController::class, 'vacancyRestore'], []);
$router->get('/cabinet/applications', [CabinetController::class, 'applications'], []);
$router->get('/cabinet/resume', [ResumeController::class, 'form'], []);
$router->post('/cabinet/resume', [ResumeController::class, 'save'], []);
$router->get('/cabinet/resume/pdf', [ResumeController::class, 'myPdf'], []);
$router->post('/cabinet/resume/visibility', [ResumeController::class, 'visibility'], []);
$router->post('/cabinet/resume/delete', [ResumeController::class, 'delete'], []);
$router->get('/cabinet/person', [ResumeController::class, 'form'], []);
$router->post('/cabinet/person', [ResumeController::class, 'personSave'], []);
$router->post('/cabinet/employer/logo-delete', [CabinetController::class, 'logoDelete'], []);

// Реклама й послуги роботодавця
$router->get('/cabinet/ads', [AdsController::class, 'index'], []);
$router->post('/cabinet/ads', [AdsController::class, 'order'], []);
$router->get('/cabinet/ads/{id}', [AdsController::class, 'view'], []);
$router->post('/cabinet/ads/{id}/cancel', [AdsController::class, 'cancel'], []);
$router->get('/cabinet/ads/{id}/invoice', [AdsController::class, 'invoice'], []);

// Адмін-панель (права перевіряються і тут, і в контролері)
$router->get('/admin', [AdminController::class, 'dashboard'], []);
$router->get('/admin/moderation', [AdminController::class, 'moderation'], ['jobs.listing:moderate']);
$router->post('/admin/moderation/{id}', [AdminController::class, 'moderate'], ['jobs.listing:moderate']);
$router->get('/admin/resumes', [ResumeModerationController::class, 'index'], ['jobs.listing:moderate']);
$router->post('/admin/resumes/{id}', [ResumeModerationController::class, 'decide'], ['jobs.listing:moderate']);
$router->get('/admin/banners', [AdminController::class, 'banners'], ['ads.banner:moderate']);
$router->post('/admin/banners/{id}', [AdminController::class, 'bannerModerate'], ['ads.banner:moderate']);
$router->get('/admin/billing', [AdminController::class, 'billing'], ['billing.plan:view']);
$router->post('/admin/billing', [AdminController::class, 'billingSave'], ['billing.plan:view']);
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
$router->get('/admin/pages', [AdminController::class, 'pages'], ['content.page:update']);
$router->get('/admin/pages/{slug}', [AdminController::class, 'pageForm'], ['content.page:update']);
$router->post('/admin/pages/{slug}', [AdminController::class, 'pageSave'], ['content.page:update']);
$router->get('/admin/outbox', [AdminController::class, 'outbox'], ['settings.system:manage_settings']);
$router->post('/admin/upgrade', [AdminController::class, 'upgrade'], ['settings.system:manage_settings']);

// Оновлення сайту з GitHub, резервні копії, відкат (головний і старший адміністратор)
$router->get('/admin/updates', [AdminController::class, 'updates'], ['system.update:run']);
$router->post('/admin/updates/check', [AdminController::class, 'updatesCheck'], ['system.update:run']);
$router->post('/admin/updates/install', [AdminController::class, 'updatesInstall'], ['system.update:run']);
$router->get('/admin/updates/finish', [AdminController::class, 'updatesFinish'], ['system.update:run']);
$router->post('/admin/updates/backup', [AdminController::class, 'updatesBackup'], ['system.update:run']);
$router->post('/admin/updates/restore', [AdminController::class, 'updatesRestore'], ['system.update:run']);
$router->post('/admin/updates/delete', [AdminController::class, 'updatesDelete'], ['system.update:run']);
$router->post('/admin/updates/settings', [AdminController::class, 'updatesSettings'], ['settings.system:manage_settings']);

// Telegram-бот: прив'язка для персоналу, налаштування для адміністраторів, вебхук для Telegram
$router->get('/admin/telegram', [TelegramAdminController::class, 'index'], []);
$router->post('/admin/telegram/me', [TelegramAdminController::class, 'me'], []);
$router->post('/admin/telegram/manage', [TelegramAdminController::class, 'manage'], ['settings.system:manage_settings']);
$router->post('/telegram/webhook', [TelegramController::class, 'webhook']);

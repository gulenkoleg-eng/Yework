<?php
declare(strict_types=1);

use App\Controllers\AdsController;
use App\Controllers\AuthController;
use App\Controllers\CabinetController;
use App\Controllers\ResumeController;
use App\Controllers\ServicesController;
use App\Controllers\Admin\ServicesAdminController;
use App\Controllers\SiteController;
use App\Controllers\Admin\AdminController;
use App\Controllers\Admin\ListingEditController;
use App\Controllers\Admin\MasterEditController;
use App\Controllers\Admin\PeopleController;
use App\Controllers\Admin\ResumeModerationController;
use App\Controllers\Admin\TelegramAdminController;
use App\Controllers\TelegramController;

/** @var App\Core\Router $router */

// Публічні сторінки
$router->get('/', [SiteController::class, 'home']);
$router->get('/jobs', [SiteController::class, 'jobs']);
$router->get('/employers', [SiteController::class, 'employers']);
$router->get('/jobs/{id}', [SiteController::class, 'vacancy']);
$router->post('/jobs/{id}/apply', [SiteController::class, 'apply'], []);
$router->get('/rules', [SiteController::class, 'rules']);
$router->get('/about', [SiteController::class, 'about']);
$router->get('/contacts', [SiteController::class, 'contacts']);
$router->get('/media/{kind}/{file}', [SiteController::class, 'media']);
$router->get('/site.webmanifest', [SiteController::class, 'manifest']);
$router->get('/go/{id}', [SiteController::class, 'promoClick']);
$router->get('/promo/seen', [SiteController::class, 'promoSeen']);
$router->post('/sale/catch', [SiteController::class, 'saleCatch']);
$router->post('/sale/show', [SiteController::class, 'saleShow']);

// Резюме: пошук для роботодавців, сторінка резюме, PDF, запрошення, фото профілю
$router->get('/resumes', [ResumeController::class, 'index']);
$router->get('/resumes/{id}', [ResumeController::class, 'show']);
$router->get('/resumes/{id}/pdf', [ResumeController::class, 'pdf']);
$router->post('/resumes/{id}/phone', [ResumeController::class, 'phone']);
$router->post('/resumes/{id}/invite', [ResumeController::class, 'invite'], []);
$router->get('/photo/{id}', [ResumeController::class, 'photo']);

// Послуги майстрів (0.5.0): каталог, сторінка майстра, замовлення (POST на ту саму адресу — помилки показуються у формі)
$router->get('/services', [ServicesController::class, 'index']);
$router->get('/services/{id}', [ServicesController::class, 'show']);
$router->post('/services/{id}', [ServicesController::class, 'order']);
$router->post('/services/{id}/phone', [ServicesController::class, 'phone']);

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
$router->get('/cabinet/reward', [CabinetController::class, 'reward'], []);
$router->get('/cabinet/applications', [CabinetController::class, 'applications'], []);
$router->post('/cabinet/notices/{id}', [CabinetController::class, 'noticeRead'], []);
$router->get('/cabinet/resume', [ResumeController::class, 'form'], []);
$router->post('/cabinet/resume', [ResumeController::class, 'save'], []);
$router->get('/cabinet/resume/pdf', [ResumeController::class, 'myPdf'], []);
$router->post('/cabinet/resume/visibility', [ResumeController::class, 'visibility'], []);
$router->post('/cabinet/resume/delete', [ResumeController::class, 'delete'], []);
$router->get('/cabinet/person', [ResumeController::class, 'form'], []);
$router->post('/cabinet/person', [ResumeController::class, 'personSave'], []);
$router->post('/cabinet/employer/logo-delete', [CabinetController::class, 'logoDelete'], []);

// Кабінет майстра й замовлення послуг
$router->get('/cabinet/master', [ServicesController::class, 'masterForm'], []);
$router->post('/cabinet/master', [ServicesController::class, 'masterSave'], []);
$router->get('/cabinet/master/works/new', [ServicesController::class, 'workForm'], []);
$router->post('/cabinet/master/works/new', [ServicesController::class, 'workSave'], []);
$router->get('/cabinet/master/works/{id}', [ServicesController::class, 'workForm'], []);
$router->post('/cabinet/master/works/{id}', [ServicesController::class, 'workSave'], []);
$router->post('/cabinet/master/works/{id}/delete', [ServicesController::class, 'workDelete'], []);
$router->get('/cabinet/orders', [ServicesController::class, 'orders'], []);
$router->post('/cabinet/orders', [ServicesController::class, 'orderAction'], []);

// Реклама й послуги роботодавця
$router->get('/cabinet/ads', [AdsController::class, 'index'], []);
$router->post('/cabinet/ads', [AdsController::class, 'order'], []);
$router->get('/cabinet/ads/{id}', [AdsController::class, 'view'], []);
$router->post('/cabinet/ads/{id}/cancel', [AdsController::class, 'cancel'], []);
$router->get('/cabinet/ads/{id}/edit', [AdsController::class, 'edit'], []);
$router->post('/cabinet/ads/{id}/edit', [AdsController::class, 'update'], []);
$router->post('/cabinet/ads/{id}/delete', [AdsController::class, 'delete'], []);
$router->get('/cabinet/ads/{id}/invoice', [AdsController::class, 'invoice'], []);

// Адмін-панель (права перевіряються і тут, і в контролері)
$router->get('/admin', [AdminController::class, 'dashboard'], []);
$router->get('/admin/moderation', [AdminController::class, 'moderation'], ['jobs.listing:moderate']);
$router->get('/admin/applications', [AdminController::class, 'applications'], ['identity.user:view']);
$router->get('/admin/visitors', [AdminController::class, 'visitors'], ['stats.dashboard:view']);
$router->post('/admin/moderation/{id}', [AdminController::class, 'moderate'], ['jobs.listing:moderate']);
// Адміністрація виправляє чужу вакансію (0.5.3), власник отримує сповіщення
$router->get('/admin/vacancies/{id}/edit', [ListingEditController::class, 'vacancyForm'], ['jobs.listing:update']);
$router->post('/admin/vacancies/{id}/edit', [ListingEditController::class, 'vacancySave'], ['jobs.listing:update']);
$router->get('/admin/resumes', [ResumeModerationController::class, 'index'], ['jobs.listing:moderate']);
$router->post('/admin/resumes/{id}', [ResumeModerationController::class, 'decide'], ['jobs.listing:moderate']);
$router->get('/admin/masters', [ServicesAdminController::class, 'index'], ['services.listing:moderate']);
$router->post('/admin/demo', [ServicesAdminController::class, 'demo'], ['services.listing:moderate']);
$router->post('/admin/masters/{id}', [ServicesAdminController::class, 'decide'], ['services.listing:moderate']);
$router->post('/admin/reviews/{id}', [ServicesAdminController::class, 'review'], ['services.listing:moderate']);
// Правка анкети майстра адміністрацією (0.5.25.1): профіль, прайс, галерея робіт.
$router->get('/admin/masters/{id}/edit', [MasterEditController::class, 'form'], [MasterEditController::PERM]);
$router->post('/admin/masters/{id}/edit', [MasterEditController::class, 'save'], [MasterEditController::PERM]);
$router->get('/admin/masters/{id}/works/new', [MasterEditController::class, 'workForm'], [MasterEditController::PERM]);
$router->post('/admin/masters/{id}/works/new', [MasterEditController::class, 'workSave'], [MasterEditController::PERM]);
$router->get('/admin/masters/{id}/works/{wid}', [MasterEditController::class, 'workForm'], [MasterEditController::PERM]);
$router->post('/admin/masters/{id}/works/{wid}', [MasterEditController::class, 'workSave'], [MasterEditController::PERM]);
$router->post('/admin/masters/{id}/works/{wid}/delete', [MasterEditController::class, 'workDelete'], [MasterEditController::PERM]);
$router->get('/admin/service-categories', [ServicesAdminController::class, 'categories'], ['services.listing:update']);
$router->post('/admin/service-categories', [ServicesAdminController::class, 'categoriesSave'], ['services.listing:update']);
$router->get('/admin/banners', [AdminController::class, 'banners'], ['ads.banner:moderate']);
$router->get('/admin/banners/new', [AdminController::class, 'bannerNew'], ['ads.banner:moderate']);
$router->post('/admin/banners/new', [AdminController::class, 'bannerCreate'], ['ads.banner:moderate']);
$router->get('/admin/banners/templates', [AdminController::class, 'bannerTemplates'], ['ads.banner:moderate']);
$router->post('/admin/banners/templates', [AdminController::class, 'bannerTemplatesSave'], ['ads.banner:moderate']);
$router->get('/admin/banners/{id}/edit', [AdminController::class, 'bannerEdit'], ['ads.banner:moderate']);
$router->post('/admin/banners/{id}/edit', [AdminController::class, 'bannerUpdate'], ['ads.banner:moderate']);
$router->post('/admin/banners/{id}/delete', [AdminController::class, 'bannerDelete'], ['ads.banner:moderate']);
$router->post('/admin/banners/{id}', [AdminController::class, 'bannerModerate'], ['ads.banner:moderate']);
$router->get('/admin/billing', [AdminController::class, 'billing'], ['billing.plan:view']);
$router->post('/admin/billing', [AdminController::class, 'billingSave'], ['billing.plan:view']);
// Люди (0.5.16): клієнти окремо від команди; адміністратор виправляє картку клієнта (ім’я, профіль роботодавця, логотип, фото майстра)
$router->get('/admin/clients', [PeopleController::class, 'clients'], ['identity.user:view']);
$router->get('/admin/team', [PeopleController::class, 'team'], ['identity.user:view']);
// 0.5.23: «Перевірений роботодавець» одним натисканням (список клієнтів, картка клієнта, модерація)
$router->post('/admin/employers/{id}/verified', [PeopleController::class, 'verifyToggle'], ['identity.user:update']);
$router->get('/admin/users', [PeopleController::class, 'legacy'], ['identity.user:view']);
$router->get('/admin/users/{id}', [AdminController::class, 'user'], ['identity.user:view']);
$router->get('/admin/users/{id}/edit', [PeopleController::class, 'editForm'], ['identity.user:update']);
$router->post('/admin/users/{id}/edit', [PeopleController::class, 'editSave'], ['identity.user:update']);
$router->post('/admin/users/{id}/status', [AdminController::class, 'userStatus'], ['identity.user:update']);
$router->post('/admin/users/{id}/rewards', [PeopleController::class, 'rewardAdd'], ['identity.user:view']);
$router->post('/admin/users/{id}/rewards/{rid}/delete', [PeopleController::class, 'rewardDelete'], ['identity.user:view']);
$router->post('/admin/users/{id}/roles', [AdminController::class, 'userRoles'], ['access.role:manage_settings']);
$router->get('/admin/roles', [AdminController::class, 'roles'], ['access.role:view']);
$router->post('/admin/roles/{id}', [AdminController::class, 'rolePermissions'], ['access.role:manage_settings']);
$router->get('/admin/audit', [AdminController::class, 'audit'], ['audit.log:view']);
$router->get('/admin/themes', [AdminController::class, 'themes'], ['theming.theme:view']);
$router->post('/admin/themes', [AdminController::class, 'themePublish'], ['theming.theme:publish']);
$router->post('/admin/themes/festive', [AdminController::class, 'themeFestive'], ['theming.theme:publish']);
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
$router->post('/admin/updates/clean', [AdminController::class, 'updatesClean'], ['system.update:run']);
$router->post('/admin/updates/keep', [AdminController::class, 'updatesKeep'], ['system.update:run']);
$router->get('/admin/updates/download', [AdminController::class, 'updatesDownload'], ['settings.system:manage_settings']);
$router->post('/admin/updates/settings', [AdminController::class, 'updatesSettings'], ['settings.system:manage_settings']);

// Telegram-бот: прив'язка для персоналу, налаштування для адміністраторів, вебхук для Telegram
$router->get('/admin/telegram', [TelegramAdminController::class, 'index'], []);
$router->post('/admin/telegram/me', [TelegramAdminController::class, 'me'], []);
$router->post('/admin/telegram/manage', [TelegramAdminController::class, 'manage'], ['settings.system:manage_settings']);
$router->post('/telegram/webhook', [TelegramController::class, 'webhook']);

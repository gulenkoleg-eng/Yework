<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Ads;
use App\Core\App;
use App\Core\Audit;
use App\Core\Crypto;
use App\Core\Db;
use App\Core\HttpException;
use App\Core\Installer;
use App\Core\Messaging;
use App\Core\Pages;
use App\Core\Request;
use App\Core\Response;
use App\Core\Stats;
use App\Core\Updater;
use App\Core\ValidationException;
use App\Core\View;
use App\Permissions;

final class AdminController
{

    private function render(string $view, string $nav, array $data = []): Response
    {
        return new Response(View::render('admin/' . $view, [...$data, 'nav' => $nav], 'admin'));
    }

    private function staff(): array
    {
        $u = App::auth()->user();
        if (!$u || !$u['is_staff']) throw new HttpException(403, 'Адмін-панель доступна лише персоналу.');
        return $u;
    }

    public static function pendingCount(): int
    {
        return (int) App::db()->value("SELECT COUNT(*) FROM {p}vacancies WHERE status = 'pending' AND deleted_at IS NULL");
    }

    public function dashboard(Request $req): Response
    {
        $me = $this->staff();
        $db = App::db();
        $one = static fn (string $sql, array $p = []) => (int) $db->value($sql, $p);
        $k = [
            'users' => $one('SELECT COUNT(*) FROM {p}users WHERE deleted_at IS NULL'),
            'usersToday' => $one('SELECT COUNT(*) FROM {p}users WHERE created_at >= ?', [Stats::kyivDayStartUtc()]),
            'pending' => self::pendingCount(),
            'published' => $one("SELECT COUNT(*) FROM {p}vacancies WHERE status = 'published' AND deleted_at IS NULL"),
            'employers' => $one('SELECT COUNT(*) FROM {p}employers WHERE deleted_at IS NULL'),
            'applications' => $one('SELECT COUNT(*) FROM {p}applications'),
            'failedMessages' => $one("SELECT COUNT(*) FROM {p}outbox WHERE status = 'failed' AND created_at > UTC_TIMESTAMP() - INTERVAL 1 DAY"),
        ];
        $pub = Stats::public();
        $recent = App::auth()->can('audit.log:view') ? $db->all('SELECT a.*, u.display_name FROM {p}audit_log a LEFT JOIN {p}users u ON u.id = a.actor_id ORDER BY a.id DESC LIMIT 8') : [];
        $mfa = (bool) App::setting('security.admin_2fa_required', true);
        return $this->render('dashboard', 'dashboard', ['title' => 'Огляд', 'k' => $k, 'pub' => $pub, 'recent' => $recent, 'me' => $me, 'mfa' => $mfa]);
    }

    /* ---------------- модерація ---------------- */

    public function moderation(Request $req): Response
    {
        $status = in_array($req->query['status'] ?? 'pending', ['pending', 'published', 'rejected'], true) ? (string) ($req->query['status'] ?? 'pending') : 'pending';
        $rows = App::db()->all("SELECT v.*, e.name AS employer, e.is_verified, e.edrpou, s.name AS settlement, c.name AS category, u.display_name AS author,
            (SELECT COUNT(*) FROM {p}vacancies v2 WHERE v2.author_id = v.author_id AND v2.status = 'rejected') AS author_rejected
            FROM {p}vacancies v JOIN {p}employers e ON e.id = v.employer_id JOIN {p}settlements s ON s.id = v.settlement_id JOIN {p}job_categories c ON c.id = v.category_id JOIN {p}users u ON u.id = v.author_id
            WHERE v.status = ? AND v.deleted_at IS NULL ORDER BY " . ($status === 'pending' ? 'v.submitted_at ASC' : 'v.moderated_at DESC') . ' LIMIT 100', [$status]);
        return $this->render('moderation', 'moderation', ['title' => 'Модерація вакансій', 'rows' => $rows, 'status' => $status, 'me' => App::auth()->user()]);
    }

    public function moderate(Request $req): Response
    {
        $me = App::auth()->user();
        $id = (int) $req->params['id'];
        $decision = $req->str('decision', 10);
        $db = App::db();
        $v = $db->one('SELECT * FROM {p}vacancies WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$v) throw new HttpException(404, 'Вакансію не знайдено.');
        if ((int) $v['author_id'] === (int) $me['id']) throw new HttpException(403, 'Не можна модерувати власну вакансію.');
        if ($decision === 'approve') {
            if ($v['status'] !== 'pending') throw new HttpException(409, 'Вакансію вже оброблено.');
            $st = \App\Core\Vacancies::goLive($id, ['moderated_by' => $me['id'], 'moderated_at' => Db::now()]);
            Audit::log('jobs.vacancy_approved', 'vacancy', $id);
            flash('ok', $st === 'scheduled' ? 'Вакансію схвалено. Вона з’явиться на сайті з ' . date('d.m', strtotime((string) $db->value('SELECT show_from FROM {p}vacancies WHERE id = ?', [$id]))) . '.' : 'Вакансію опубліковано.');
        } elseif ($decision === 'reject') {
            $reason = $req->str('reason', 500);
            if (mb_strlen($reason) < 5) { flash('bad', 'Вкажіть причину відхилення — її побачить роботодавець.'); return Response::redirect(url('/admin/moderation')); }
            if (!in_array($v['status'], ['pending', 'published'], true)) throw new HttpException(409, 'Вакансію вже оброблено.');
            $db->update('vacancies', ['status' => 'rejected', 'moderated_by' => $me['id'], 'moderated_at' => Db::now(), 'reject_reason' => $reason], 'id = ?', [$id]);
            Audit::log('jobs.vacancy_rejected', 'vacancy', $id, ['reason' => $reason]);
            flash('ok', 'Вакансію відхилено. Роботодавець побачить причину в кабінеті.');
        } elseif ($decision === 'verify' && App::auth()->can('identity.user:update')) {
            $db->update('employers', ['is_verified' => 1], 'id = ?', [$v['employer_id']]);
            Audit::log('jobs.employer_verified', 'employer', $v['employer_id']);
            flash('ok', 'Роботодавця позначено як перевіреного.');
        }
        Stats::forget();
        return Response::redirect(url('/admin/moderation', ['status' => $req->str('back', 12) ?: null]));
    }

    /* ---------------- банерна реклама ---------------- */

    public static function pendingBanners(): int
    {
        try { return (int) App::db()->value("SELECT COUNT(*) FROM {p}banners WHERE status = 'pending'"); } catch (\PDOException) { return 0; }
    }

    public function banners(Request $req): Response
    {
        $status = in_array($req->query['status'] ?? 'pending', ['pending', 'approved', 'rejected', 'cancelled'], true) ? (string) ($req->query['status'] ?? 'pending') : 'pending';
        $rows = App::db()->all("SELECT b.*, e.name AS employer, e.legal_form, e.logo, e.is_verified, e.owner_id, u.display_name AS author,
            (SELECT COUNT(*) FROM {p}vacancies v WHERE v.employer_id = b.employer_id AND v.status = 'published' AND v.deleted_at IS NULL) AS vacancies,
            (SELECT COALESCE(SUM(impressions),0) FROM {p}banner_stats s WHERE s.banner_id = b.id) AS impressions,
            (SELECT COALESCE(SUM(clicks),0) FROM {p}banner_stats s WHERE s.banner_id = b.id) AS clicks
            FROM {p}banners b JOIN {p}employers e ON e.id = b.employer_id JOIN {p}users u ON u.id = b.created_by
            WHERE b.status = ? ORDER BY " . ($status === 'pending' ? 'b.id ASC' : 'b.id DESC') . ' LIMIT 100', [$status]);
        return $this->render('banners', 'banners', ['title' => 'Банери', 'rows' => $rows, 'status' => $status, 'me' => App::auth()->user(), 'canPay' => App::auth()->can('billing.plan:update')]);
    }

    public function bannerModerate(Request $req): Response
    {
        $me = App::auth()->user();
        $db = App::db();
        $b = $db->one('SELECT b.*, e.owner_id FROM {p}banners b JOIN {p}employers e ON e.id = b.employer_id WHERE b.id = ?', [(int) $req->params['id']]);
        if (!$b) throw new HttpException(404, 'Банер не знайдено.');
        $decision = $req->str('decision', 10);
        $back = url('/admin/banners', ['status' => $req->str('back', 12) ?: null]);
        if ($decision === 'paid') {
            if (!App::auth()->can('billing.plan:update')) throw new HttpException(403, 'Позначати оплату може фінансовий адміністратор.');
            $db->update('banners', ['paid_at' => Db::now(), 'updated_at' => Db::now()], 'id = ?', [$b['id']]);
            Audit::log('ads.banner_paid', 'banner', $b['id'], ['price' => (int) $b['price']]);
            flash('ok', 'Оплату позначено.');
            return Response::redirect($back);
        }
        if ((int) $b['owner_id'] === (int) $me['id'] || (int) $b['created_by'] === (int) $me['id']) throw new HttpException(403, 'Не можна модерувати власний банер.');
        if ($decision === 'approve') {
            if ($b['status'] !== 'pending') throw new HttpException(409, 'Банер уже оброблено.');
            $db->update('banners', ['status' => 'approved', 'moderated_by' => $me['id'], 'moderated_at' => Db::now(), 'reject_reason' => null, 'updated_at' => Db::now()], 'id = ?', [$b['id']]);
            Audit::log('ads.banner_approved', 'banner', $b['id']);
            flash('ok', 'Банер схвалено. Він показуватиметься в обрані дні.');
        } elseif ($decision === 'reject') {
            $reason = $req->str('reason', 500);
            if (mb_strlen($reason) < 5) { flash('bad', 'Вкажіть причину — її побачить роботодавець.'); return Response::redirect($back); }
            if (!in_array($b['status'], ['pending', 'approved'], true)) throw new HttpException(409, 'Банер уже оброблено.');
            $db->update('banners', ['status' => 'rejected', 'moderated_by' => $me['id'], 'moderated_at' => Db::now(), 'reject_reason' => $reason, 'updated_at' => Db::now()], 'id = ?', [$b['id']]);
            Audit::log('ads.banner_rejected', 'banner', $b['id'], ['reason' => $reason]);
            flash('ok', $b['status'] === 'approved' ? 'Банер знято з показу.' : 'Банер відхилено.');
        }
        return Response::redirect($back);
    }

    /* ---------------- монетизація ---------------- */

    public function billing(Request $req): Response
    {
        $a = App::auth();
        $db = App::db();
        $t = Ads::today();
        $k = [
            'live' => (int) $db->value('SELECT COUNT(*) FROM {p}banners b WHERE ' . Ads::LIVE_SQL, [$t, $t]),
            'pending' => self::pendingBanners(),
            'unpaid' => (int) $db->value("SELECT COUNT(*) FROM {p}banners WHERE status = 'approved' AND price > 0 AND paid_at IS NULL AND ends_on >= ?", [$t]),
            'withLogo' => (int) $db->value('SELECT COUNT(*) FROM {p}employers WHERE logo IS NOT NULL AND deleted_at IS NULL'),
            'employers' => (int) $db->value('SELECT COUNT(*) FROM {p}employers WHERE deleted_at IS NULL'),
        ];
        return $this->render('billing', 'billing', ['title' => 'Монетизація', 'on' => Ads::monetizationOn(), 'prices' => Ads::prices(), 'slots' => Ads::slots(),
            'req' => (array) App::setting('billing.requisites', []), 'k' => $k, 'svcLimits' => \App\Core\Services::limits(),
            'canToggle' => $a->can('billing.monetization:manage_settings'), 'canEdit' => $a->can('billing.plan:update')]);
    }

    public function billingSave(Request $req): Response
    {
        $a = App::auth();
        $me = $a->id();
        $section = $req->str('section', 20);
        if ($section === 'toggle') {
            // Критичне право: видає лише головний адміністратор.
            if (!$a->can('billing.monetization:manage_settings')) throw new HttpException(403, 'Вмикати монетизацію може лише той, кому головний адміністратор видав це право.');
            $on = $req->str('enabled', 1) === '1';
            App::setSetting('monetization.enabled', $on, $me);
            Audit::log('billing.monetization_' . ($on ? 'enabled' : 'disabled'), 'settings', 'monetization.enabled');
            flash('ok', $on ? 'Монетизацію ввімкнено. Нові банери тепер платні, а роботодавці бачать ціни.' : 'Монетизацію вимкнено. Усе знову безкоштовно.');
        } elseif ($section === 'prices') {
            if (!$a->can('billing.plan:update')) throw new HttpException(403, 'Недостатньо прав для зміни цін.');
            $prices = [];
            foreach (Ads::DEFAULT_PRICES as $key => $def) {
                $v = preg_replace('/\D/', '', (string) ($req->post[$key] ?? ''));
                $prices[$key] = $v === '' ? $def : min(str_starts_with($key, 'discount') ? 90 : 1_000_000, (int) $v);
            }
            App::setSetting('billing.prices', $prices, $me);
            App::setSetting('ads.slots', max(1, min(10, (int) $req->input('slots', Ads::DEFAULT_SLOTS))), $me);
            $lim = \App\Core\Services::limits();
            $svc = ['works' => max(0, min(50, (int) $req->input('svc_works', $lim['works']))), 'photos' => max(1, min(20, (int) $req->input('svc_photos', $lim['photos'])))];
            if ($svc !== $lim) Audit::log('services.limits_updated', 'settings', 'services.limits', $svc);
            App::setSetting('services.limits', $svc, $me);
            App::setSetting('billing.requisites', ['name' => $req->str('req_name', 200), 'code' => preg_replace('/\D/', '', $req->str('req_code', 12)), 'iban' => strtoupper(preg_replace('/\s/', '', $req->str('req_iban', 40))),
                'bank' => $req->str('req_bank', 120), 'note' => $req->str('req_note', 300)], $me);
            Audit::log('billing.prices_updated', 'settings', 'billing.prices', $prices);
            flash('ok', 'Ціни збережено.');
        }
        return Response::redirect(url('/admin/billing'));
    }

    /* ---------------- користувачі ---------------- */

    public function users(Request $req): Response
    {
        $db = App::db();
        $q = mb_substr(trim((string) ($req->query['q'] ?? '')), 0, 100);
        $filter = (string) ($req->query['filter'] ?? '');
        $w = ['u.deleted_at IS NULL'];
        $p = [];
        if ($q !== '') {
            $c = Crypto::parseContact($q);
            if ($c) { $w[] = ($c['kind'] === 'phone' ? 'u.phone_hash' : 'u.email_hash') . ' = ?'; $p[] = Crypto::hmac($c['value']); }
            else { $w[] = '(u.display_name LIKE ? OR u.login LIKE ?)'; $like = '%' . addcslashes($q, '%_\\') . '%'; array_push($p, $like, $like); }
        }
        if ($filter === 'blocked') $w[] = "u.status = 'blocked'";
        if ($filter === 'staff') $w[] = '(u.owner_flag = 1 OR EXISTS (SELECT 1 FROM {p}user_roles ur WHERE ur.user_id = u.id))';
        $where = ' WHERE ' . implode(' AND ', $w);
        $total = (int) $db->value('SELECT COUNT(*) FROM {p}users u' . $where, $p);
        $page = max(1, (int) ($req->query['page'] ?? 1));
        $rows = $db->all('SELECT u.id, u.login, u.display_name, u.status, u.owner_flag, u.phone_hash, u.email_hash, u.created_at, u.last_login_at,
            (SELECT GROUP_CONCAT(r.name SEPARATOR ", ") FROM {p}user_roles ur JOIN {p}roles r ON r.id = ur.role_id WHERE ur.user_id = u.id) AS roles
            FROM {p}users u' . $where . ' ORDER BY u.id DESC LIMIT 30 OFFSET ' . (($page - 1) * 30), $p);
        return $this->render('users', 'users', ['title' => 'Користувачі', 'rows' => $rows, 'total' => $total, 'page' => $page, 'q' => $q, 'filter' => $filter]);
    }

    public function user(Request $req): Response
    {
        $db = App::db();
        $id = (int) $req->params['id'];
        $u = $db->one('SELECT * FROM {p}users WHERE id = ?', [$id]);
        if (!$u) throw new HttpException(404, 'Користувача не знайдено.');
        $full = App::auth()->can('identity.user:view_contacts');
        $phone = Crypto::decrypt($u['phone_enc']);
        $email = Crypto::decrypt($u['email_enc']);
        $roles = App::auth()->rolesOf($id);
        $allRoles = $db->all("SELECT slug, name, description FROM {p}roles WHERE deleted_at IS NULL AND slug <> 'owner' ORDER BY id");
        $audit = $db->all("SELECT * FROM {p}audit_log WHERE actor_id = ? OR (entity_type = 'user' AND entity_id = ?) ORDER BY id DESC LIMIT 20", [$id, (string) $id]);
        $employers = $db->all('SELECT id, name, is_verified FROM {p}employers WHERE owner_id = ?', [$id]);
        $stats = ['vacancies' => (int) $db->value('SELECT COUNT(*) FROM {p}vacancies WHERE author_id = ?', [$id]), 'applications' => (int) $db->value('SELECT COUNT(*) FROM {p}applications WHERE user_id = ?', [$id])];
        return $this->render('user', 'users', ['title' => $u['display_name'], 'u' => $u, 'phone' => $full ? $phone : mask_contact($phone), 'email' => $full ? $email : mask_contact($email), 'masked' => !$full,
            'roles' => $roles, 'allRoles' => $allRoles, 'audit' => $audit, 'employers' => $employers, 'stats' => $stats, 'me' => App::auth()->user()]);
    }

    public function userStatus(Request $req): Response
    {
        $me = App::auth()->user();
        $id = (int) $req->params['id'];
        $status = $req->str('status', 10);
        $reason = $req->str('reason', 300);
        if (!in_array($status, ['active', 'blocked'], true)) throw new HttpException(400, 'Невідомий статус.');
        if ($id === (int) $me['id']) throw new HttpException(403, 'Не можна змінити статус власного акаунта.');
        $u = App::db()->one('SELECT * FROM {p}users WHERE id = ?', [$id]);
        if (!$u) throw new HttpException(404, 'Користувача не знайдено.');
        if ((int) $u['owner_flag'] === 1) throw new HttpException(403, 'Головного адміністратора заблокувати не можна.');
        if (!$me['is_owner'] && App::auth()->rolesOf($id)) throw new HttpException(403, 'Блокувати персонал може лише головний адміністратор.');
        if (mb_strlen($reason) < 3) { flash('bad', 'Вкажіть причину.'); return Response::redirect(url('/admin/users/' . $id)); }
        App::db()->update('users', ['status' => $status, 'updated_at' => Db::now()], 'id = ?', [$id]);
        if ($status === 'blocked') App::auth()->revokeAll($id);
        Audit::log($status === 'blocked' ? 'user.blocked' : 'user.unblocked', 'user', $id, ['reason' => $reason]);
        flash('ok', $status === 'blocked' ? 'Користувача заблоковано, усі його сесії завершено.' : 'Користувача розблоковано.');
        return Response::redirect(url('/admin/users/' . $id));
    }

    /** Видача / зняття ролі. Критичне право; не можна змінювати ролі собі; роль власника не передається. */
    public function userRoles(Request $req): Response
    {
        $me = App::auth()->user();
        $id = (int) $req->params['id'];
        $slug = $req->str('role', 64);
        $op = $req->str('op', 6);
        if ($id === (int) $me['id']) throw new HttpException(403, 'Не можна змінювати ролі самому собі.');
        if ($slug === 'owner') throw new HttpException(403, 'Роль головного адміністратора не передається.');
        $db = App::db();
        $role = $db->one('SELECT * FROM {p}roles WHERE slug = ? AND deleted_at IS NULL', [$slug]);
        $u = $db->one('SELECT * FROM {p}users WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$role || !$u) throw new HttpException(404, 'Не знайдено.');
        if ($op === 'grant') {
            if ($u['status'] !== 'active') { flash('bad', 'Спочатку розблокуйте користувача.'); return Response::redirect(url('/admin/users/' . $id)); }
            $db->run('INSERT IGNORE INTO {p}user_roles (user_id, role_id, granted_by) VALUES (?, ?, ?)', [$id, $role['id'], $me['id']]);
            Audit::log('access.role_granted', 'user', $id, ['role' => $slug]);
            flash('ok', 'Роль «' . $role['name'] . '» видано. Користувач має увійти заново.');
        } else {
            $db->run('DELETE FROM {p}user_roles WHERE user_id = ? AND role_id = ?', [$id, $role['id']]);
            Audit::log('access.role_revoked', 'user', $id, ['role' => $slug]);
            flash('ok', 'Роль «' . $role['name'] . '» знято.');
        }
        App::auth()->revokeAll($id); // персонал отримує короткі сесії, тож просимо увійти заново
        return Response::redirect(url('/admin/users/' . $id));
    }

    public function roles(Request $req): Response
    {
        $db = App::db();
        $roles = $db->all('SELECT r.*, (SELECT COUNT(*) FROM {p}user_roles ur WHERE ur.role_id = r.id) AS members FROM {p}roles r WHERE r.deleted_at IS NULL ORDER BY r.id');
        $rp = [];
        foreach ($db->all('SELECT role_id, permission_code FROM {p}role_permissions') as $x) $rp[$x['role_id']][] = $x['permission_code'];
        return $this->render('roles', 'roles', ['title' => 'Ролі та права', 'roles' => $roles, 'rp' => $rp, 'perms' => Permissions::all()]);
    }

    public function audit(Request $req): Response
    {
        $page = max(1, (int) ($req->query['page'] ?? 1));
        $action = preg_replace('/[^a-z_.]/', '', (string) ($req->query['action'] ?? ''));
        $p = [];
        $w = '';
        if ($action !== '') { $w = ' WHERE a.action LIKE ?'; $p[] = $action . '%'; }
        $rows = App::db()->all('SELECT a.*, u.display_name, u.login FROM {p}audit_log a LEFT JOIN {p}users u ON u.id = a.actor_id' . $w . ' ORDER BY a.id DESC LIMIT 50 OFFSET ' . (($page - 1) * 50), $p);
        return $this->render('audit', 'audit', ['title' => 'Журнал дій', 'rows' => $rows, 'page' => $page, 'action' => $action]);
    }

    /* ---------------- теми ---------------- */

    public function themes(Request $req): Response
    {
        $rows = App::db()->all('SELECT t.*, tv.id AS vid, tv.tokens, tv.version, (SELECT active_flag FROM {p}theme_publications p WHERE p.theme_version_id = tv.id AND p.active_flag = 1) AS active
            FROM {p}themes t JOIN {p}theme_versions tv ON tv.theme_id = t.id AND tv.version = (SELECT MAX(version) FROM {p}theme_versions x WHERE x.theme_id = t.id)
            WHERE t.deleted_at IS NULL ORDER BY t.id');
        $history = App::db()->all('SELECT p.*, t.name, u.display_name FROM {p}theme_publications p JOIN {p}theme_versions tv ON tv.id = p.theme_version_id JOIN {p}themes t ON t.id = tv.theme_id LEFT JOIN {p}users u ON u.id = p.published_by ORDER BY p.id DESC LIMIT 10');
        return $this->render('themes', 'themes', ['title' => 'Теми оформлення', 'rows' => $rows, 'history' => $history]);
    }

    public function themePublish(Request $req): Response
    {
        $vid = (int) $req->input('version_id');
        $db = App::db();
        $v = $db->one('SELECT tv.id, t.name FROM {p}theme_versions tv JOIN {p}themes t ON t.id = tv.theme_id WHERE tv.id = ?', [$vid]);
        if (!$v) throw new HttpException(404, 'Тему не знайдено.');
        $db->tx(function (Db $db) use ($vid) {
            $db->run('UPDATE {p}theme_publications SET active_flag = NULL WHERE active_flag = 1');
            $db->insert('theme_publications', ['theme_version_id' => $vid, 'published_by' => App::auth()->id(), 'active_flag' => 1]);
        });
        Audit::log('theming.theme_published', 'theme_version', $vid, ['name' => $v['name']]);
        flash('ok', 'Тему «' . $v['name'] . '» застосовано до всього сайту.');
        return Response::redirect(url('/admin/themes'));
    }

    /* ---------------- налаштування ---------------- */

    public function settings(Request $req): Response
    {
        $email = (array) App::setting('messaging.email', ['driver' => 'mail']);
        return $this->render('settings', 'settings', ['title' => 'Налаштування', 'email' => $email,
            'site' => ['name' => App::setting('site.name'), 'slogan' => App::setting('site.slogan'), 'url' => App::setting('site.url')],
            'pre' => (array) App::setting('moderation.premoderation', ['jobs' => true]), 'version' => YW_VERSION, 'logo' => logo_settings(),
            'favicon' => ['custom' => \App\Core\Favicon::custom() !== null, 'preview' => \App\Core\Favicon::url(192)],
            'pendingMigrations' => $this->pendingMigrations()]);
    }

    private function pendingMigrations(): array
    {
        $done = array_column(App::db()->all('SELECT name FROM {p}schema_migrations'), 'name');
        return array_values(array_diff(array_map('basename', glob(YW_ROOT . '/database/migrations/*.sql') ?: []), $done));
    }

    public function settingsSave(Request $req): Response
    {
        $section = $req->str('section', 20);
        $me = App::auth()->id();
        if ($section === 'site') {
            $name = $req->str('name', 60);
            if (mb_strlen($name) < 2) throw new ValidationException(['name' => 'Вкажіть назву']);
            App::setSetting('site.name', $name, $me);
            App::setSetting('site.slogan', $req->str('slogan', 80), $me);
            App::setSetting('site.url', rtrim($req->str('url', 200), '/'), $me);
            App::setSetting('moderation.premoderation', ['jobs' => !empty($req->post['pre_jobs']), 'gigs' => true, 'services' => !empty($req->post['pre_services'])], $me);
        } elseif ($section === 'logo') {
            App::setSetting('employers.logo', ['hint' => !empty($req->post['hint']), 'hintText' => $req->str('hintText', 300) ?: logo_settings()['hintText'],
                'perk' => !empty($req->post['perk']), 'perkText' => $req->str('perkText', 200)], $me);
        } elseif ($section === 'favicon') {
            if (!empty($req->post['reset'])) {
                \App\Core\Favicon::reset($me);
                Audit::log('settings.updated', 'settings', 'site.favicon', ['reset' => true]);
                flash('ok', 'Повернуто стандартну іконку сайту.');
                return Response::redirect(url('/admin/settings'));
            }
            if (!\App\Core\Favicon::store('icon', $me)) throw new ValidationException(['icon' => 'Оберіть картинку']);
            Audit::log('settings.updated', 'settings', 'site.favicon');
            flash('ok', 'Іконку сайту оновлено. Якщо у вкладці ще стара, оновіть сторінку (Ctrl+F5): браузери запам’ятовують іконки.');
            return Response::redirect(url('/admin/settings'));
        } elseif ($section === 'email') {
            $driver = $req->str('driver', 10);
            $prev = (array) App::setting('messaging.email', []);
            $v = match ($driver) {
                'smtp' => ['driver' => 'smtp', 'host' => $req->str('host', 255), 'port' => (int) $req->str('port', 5) ?: 587, 'secure' => in_array($req->str('secure', 4), ['ssl', 'tls', 'none'], true) ? $req->str('secure', 4) : 'tls',
                    'user' => $req->str('user', 254), 'from' => $req->str('from', 254),
                    'passEnc' => ($req->post['pass'] ?? '') !== '' ? Crypto::encrypt((string) $req->post['pass']) : ($prev['passEnc'] ?? null)],
                'log' => ['driver' => 'log'],
                default => ['driver' => 'mail', 'from' => $req->str('from', 254)],
            };
            if ($driver === 'smtp' && ($v['host'] === '' || !filter_var($v['from'], FILTER_VALIDATE_EMAIL))) throw new ValidationException(['host' => $v['host'] === '' ? 'Вкажіть SMTP-сервер' : null, 'from' => 'Вкажіть адресу відправника']);
            App::setSetting('messaging.email', $v, $me);
            if (($t = $req->str('test_to', 254)) !== '') {
                $r = Messaging::send('email', $t, 'Якщо ви бачите цей лист, пошта на сайті налаштована правильно.', 'test', 'Тестовий лист');
                flash($r['ok'] ? 'ok' : 'bad', $r['ok'] ? "Тестовий лист надіслано на $t." : 'Лист не надіслано. Перевірте налаштування (деталі — у розділі «Повідомлення»).');
                Audit::log('settings.updated', 'settings', 'messaging.email', ['driver' => $v['driver']]);
                return Response::redirect(url('/admin/settings'));
            }
        }
        Audit::log('settings.updated', 'settings', $section);
        flash('ok', 'Налаштування збережено.');
        return Response::redirect(url('/admin/settings'));
    }

    public function pages(Request $req): Response
    {
        $list = array_map([Pages::class, 'get'], array_keys(Pages::LIST));
        return $this->render('pages', 'pages', ['title' => 'Сторінки', 'list' => $list]);
    }

    public function pageForm(Request $req): Response
    {
        $slug = (string) ($req->params['slug'] ?? '');
        if (!Pages::exists($slug)) throw new HttpException(404, 'Такої сторінки немає.');
        return $this->render('page_form', 'pages', ['title' => 'Сторінка «' . Pages::get($slug)['title'] . '»', 'page' => Pages::get($slug)]);
    }

    public function pageSave(Request $req): Response
    {
        $slug = (string) ($req->params['slug'] ?? '');
        if (!Pages::exists($slug)) throw new HttpException(404, 'Такої сторінки немає.');
        if (($req->post['action'] ?? '') === 'reset') {
            Pages::reset($slug, App::auth()->id());
            Audit::log('content.page_reset', 'page', $slug);
            flash('ok', 'Повернуто текст за замовчуванням.');
            return Response::redirect(url('/admin/pages/' . $slug));
        }
        $title = $req->str('title', 80);
        $body = $req->str('body', 30000);
        $err = [];
        if (mb_strlen($title) < 2) $err['title'] = 'Вкажіть назву сторінки';
        if (mb_strlen($body) < 2) $err['body'] = 'Сторінка не може бути порожньою';
        if ($err) throw new ValidationException($err);
        $visible = !empty($req->post['visible']);
        Pages::save($slug, $title, $body, $visible, App::auth()->id());
        Audit::log('content.page_updated', 'page', $slug, ['visible' => $visible, 'length' => mb_strlen($body)]);
        flash('ok', $visible ? 'Збережено. Зміни вже на сайті.' : 'Збережено. Сторінку приховано від відвідувачів.');
        return Response::redirect(url('/admin/pages/' . $slug));
    }

    public function outbox(Request $req): Response
    {
        $rows = App::db()->all('SELECT * FROM {p}outbox ORDER BY id DESC LIMIT 50');
        foreach ($rows as &$r) { $r['to'] = Crypto::decrypt($r['to_enc']); $r['body'] = Crypto::decrypt($r['body_enc']); unset($r['to_enc'], $r['body_enc']); }
        return $this->render('outbox', 'outbox', ['title' => 'Повідомлення', 'rows' => $rows]);
    }

    public function upgrade(Request $req): Response
    {
        $w = Installer::upgrade(App::db());
        Audit::log('system.upgraded', null, null, ['warnings' => $w]);
        flash('ok', 'Базу даних оновлено.' . ($w ? ' Попередження: ' . implode('; ', $w) : ''));
        return Response::redirect(url('/admin/settings'));
    }

    // ---------- Оновлення сайту ----------

    public function updates(Request $req): Response
    {
        $info = Updater::check(!empty($req->query['refresh']));
        $local = Updater::localChangelog();
        $users = [];
        $backups = Updater::backups();
        $ids = array_values(array_unique(array_filter(array_column($backups, 'by'))));
        if ($ids) foreach (App::db()->all('SELECT id, display_name FROM {p}users WHERE id IN (' . implode(',', array_map('intval', $ids)) . ')') as $u) $users[(int) $u['id']] = $u['display_name'];
        $history = App::db()->all("SELECT a.*, u.display_name FROM {p}audit_log a LEFT JOIN {p}users u ON u.id = a.actor_id
            WHERE a.action IN ('system.updated','system.update_failed','system.restored','system.upgraded') ORDER BY a.id DESC LIMIT 10");
        return $this->render('updates', 'updates', ['title' => 'Оновлення', 'info' => $info, 'version' => YW_VERSION, 'local' => $local,
            'backups' => $backups, 'users' => $users, 'history' => $history, 'problems' => Updater::serverProblems(),
            'cfg' => Updater::config(), 'canConfigure' => App::auth()->can('settings.system:manage_settings'), 'pending' => Updater::pending()]);
    }

    public function updatesCheck(Request $req): Response
    {
        $info = Updater::check(true);
        if ($info['error']) flash('bad', $info['error']);
        elseif ($info['latest'] && version_compare($info['latest'], YW_VERSION, '>')) flash('ok', 'Є нова версія ' . $info['latest'] . '.');
        else flash('ok', 'У вас найновіша версія ' . YW_VERSION . '.');
        return Response::redirect(url('/admin/updates'));
    }

    public function updatesInstall(Request $req): Response
    {
        $version = $req->str('version', 40);
        try {
            Updater::install($version, App::auth()->id());
        } catch (\Throwable $e) {
            error_log('[YEWORK] update failed: ' . $e->getMessage());
            Audit::log('system.update_failed', 'version', $version, ['from' => YW_VERSION, 'to' => $version, 'error' => mb_substr($e->getMessage(), 0, 500)]);
            flash('bad', 'Оновлення не встановлено: ' . $e->getMessage());
            return Response::redirect(url('/admin/updates'));
        }
        // Міграції запускає вже новий код — окремим запитом.
        return Response::redirect(url('/admin/updates/finish'));
    }

    public function updatesFinish(Request $req): Response
    {
        $r = Updater::finish(App::auth()->id());
        if (!empty($r['nothing'])) return Response::redirect(url('/admin/updates'));
        if ($r['ok']) {
            flash('ok', 'Готово! Сайт оновлено з версії ' . $r['from'] . ' до ' . $r['to'] . '.'
                . ($r['migrations'] ? ' Оновлено структуру бази (' . count($r['migrations']) . ').' : '')
                . ' Якщо щось працює не так, внизу є кнопка «Повернути цю копію».');
        } else {
            flash('bad', 'Оновлення до ' . $r['to'] . ' не вдалося на кроці оновлення бази (' . $r['error'] . '). Сайт автоматично повернуто до версії ' . $r['from'] . ', нічого не втрачено.');
        }
        return Response::redirect(url('/admin/updates'));
    }

    public function updatesBackup(Request $req): Response
    {
        try {
            $id = Updater::createBackup('manual', $req->str('note', 120) ?: 'Створено вручну', App::auth()->id());
            Audit::log('system.backup_created', 'backup', $id);
            flash('ok', 'Резервну копію створено.');
        } catch (\Throwable $e) {
            flash('bad', $e->getMessage());
        }
        return Response::redirect(url('/admin/updates'));
    }

    public function updatesRestore(Request $req): Response
    {
        $id = $req->str('id', 80);
        if (($req->post['confirm'] ?? '') !== '1') {
            flash('bad', 'Поставте галочку підтвердження, щоб відновити копію.');
            return Response::redirect(url('/admin/updates'));
        }
        try {
            $r = Updater::restore($id, !empty($req->post['with_db']), App::auth()->id());
            flash('ok', 'Сайт повернуто до версії ' . $r['version'] . '. Стан перед відновленням теж збережено копією, тож це можна скасувати.');
        } catch (\Throwable $e) {
            error_log('[YEWORK] restore failed: ' . $e->getMessage());
            flash('bad', 'Не вдалося відновити: ' . $e->getMessage());
        }
        return Response::redirect(url('/admin/updates'));
    }

    public function updatesDelete(Request $req): Response
    {
        $id = $req->str('id', 80);
        try {
            Updater::deleteBackup($id);
            Audit::log('system.backup_deleted', 'backup', $id);
            flash('ok', 'Копію видалено.');
        } catch (\Throwable $e) {
            flash('bad', $e->getMessage());
        }
        return Response::redirect(url('/admin/updates'));
    }

    public function updatesSettings(Request $req): Response
    {
        $token = isset($req->post['token']) ? (string) $req->post['token'] : null;
        try {
            Updater::saveConfig($req->str('repo', 200), $req->str('branch', 100), $token, !empty($req->post['clear_token']), App::auth()->id());
        } catch (ValidationException $e) {
            flash('bad', implode(' ', array_filter($e->errors)));
            return Response::redirect(url('/admin/updates'));
        }
        Audit::log('system.update_settings', 'settings', 'updates.github', ['repo' => Updater::config()['repo'], 'tokenChanged' => ($token !== null && trim($token) !== '') || !empty($req->post['clear_token'])]);
        $info = Updater::check(true);
        flash($info['error'] ? 'bad' : 'ok', $info['error'] ? 'Збережено, але перевірка не вдалася: ' . $info['error'] : 'Збережено. Зв’язок з GitHub працює.');
        return Response::redirect(url('/admin/updates'));
    }
}

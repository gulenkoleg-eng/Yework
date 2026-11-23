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

    /* ---------------- відгуки і відвідувачі (0.5.20) ---------------- */

    /** Список відгуків на вакансії: куди веде картка «відгуків» на «Огляді». Текст відгуку й контакти не показуються. */
    public function applications(Request $req): Response
    {
        $this->staff();
        $db = App::db();
        $per = 50;
        $total = (int) $db->value('SELECT COUNT(*) FROM {p}applications');
        $page = max(1, min((int) ($req->query['page'] ?? 1), max(1, (int) ceil($total / $per))));
        $rows = $db->all('SELECT a.id, a.status, a.created_at, a.user_id, v.id AS vacancy_id, v.title, v.status AS v_status, e.name AS employer, u.display_name
            FROM {p}applications a JOIN {p}vacancies v ON v.id = a.vacancy_id JOIN {p}employers e ON e.id = v.employer_id JOIN {p}users u ON u.id = a.user_id
            ORDER BY a.id DESC LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per));
        $today = (int) $db->value('SELECT COUNT(*) FROM {p}applications WHERE created_at >= ?', [Stats::kyivDayStartUtc()]);
        return $this->render('applications', 'dashboard', ['title' => 'Відгуки', 'rows' => $rows, 'total' => $total, 'today' => $today, 'page' => $page, 'per' => $per]);
    }

    /** Відвідувачі по днях за останні 30 днів (лише кількість, без персональних даних). */
    public function visitors(Request $req): Response
    {
        $this->staff();
        $db = App::db();
        $from = gmdate('Y-m-d', time() - 29 * 86400);
        $by = [];
        foreach ($db->all('SELECT day, COUNT(*) AS n FROM {p}daily_visitors WHERE day >= ? GROUP BY day', [$from]) as $r) $by[(string) $r['day']] = (int) $r['n'];
        $days = [];
        for ($i = 0; $i < 30; $i++) { $d = gmdate('Y-m-d', time() - $i * 86400); $days[$d] = $by[$d] ?? 0; }
        return $this->render('visitors', 'dashboard', ['title' => 'Відвідувачі', 'days' => $days, 'pub' => Stats::public(),
            'total30' => (int) $db->value('SELECT COUNT(DISTINCT visitor_hash) FROM {p}daily_visitors WHERE day >= ?', [$from])]);
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
            (SELECT tv.title FROM {p}vacancies tv WHERE tv.id = b.vacancy_id AND tv.employer_id = b.employer_id AND tv.status = 'published' AND tv.deleted_at IS NULL) AS target_title,
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
        // Банер, який адміністратор створив сам (одразу схвалений), він може й зняти з показу.
        $adminMade = $b['status'] === 'approved' && $decision === 'reject' && (int) $b['moderated_by'] === (int) $b['created_by'] && (int) $b['created_by'] === (int) $me['id'];
        if (!$adminMade && ((int) $b['owner_id'] === (int) $me['id'] || (int) $b['created_by'] === (int) $me['id'])) throw new HttpException(403, 'Не можна модерувати власний банер.');
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

    /** Активні роботодавці з кількістю опублікованих вакансій — для банера від адміністрації. */
    private function bannerEmployers(): array
    {
        return App::db()->all("SELECT e.id, e.name, e.legal_form, e.logo,
            (SELECT COUNT(*) FROM {p}vacancies v WHERE v.employer_id = e.id AND v.status = 'published' AND v.deleted_at IS NULL) AS vacancies
            FROM {p}employers e WHERE e.status = 'active' AND e.deleted_at IS NULL ORDER BY vacancies DESC, e.name LIMIT 500");
    }

    /** Адміністратор сам створює банер для роботодавця: він одразу схвалений і безкоштовний (0.5.12). */
    public function bannerNew(Request $req): Response
    {
        $employers = $this->bannerEmployers();
        return $this->render('banner_new', 'banners', ['title' => 'Новий банер', 'employers' => $employers, 'today' => Ads::today(),
            'pick' => (int) ($req->query['employer'] ?? 0), 'targets' => Ads::targetVacancies(array_column($employers, 'id'))]);
    }

    public function bannerCreate(Request $req): Response
    {
        $db = App::db();
        $me = App::auth()->user();
        $emp = $db->one("SELECT e.*, (SELECT COUNT(*) FROM {p}vacancies v WHERE v.employer_id = e.id AND v.status = 'published' AND v.deleted_at IS NULL) AS vacancies
            FROM {p}employers e WHERE e.id = ? AND e.status = 'active' AND e.deleted_at IS NULL", [(int) $req->input('employer_id', 0)]);
        $d = Ads::bannerInput($req, array_keys(Ads::TEMPLATES));
        $err = Ads::bannerErrors($d, true);
        if (!$emp) $err['employer_id'] = 'Оберіть роботодавця, на чиї вакансії вестиме банер';
        [$d['vacancy_id'], $terr] = Ads::targetInput($req, (int) ($emp['id'] ?? 0));
        if ($terr && $emp) $err['target'] = $terr;
        if ($err) throw new ValidationException($err);
        $d['ends_on'] = date('Y-m-d', strtotime($d['starts_on'] . ' +' . ($d['days'] - 1) . ' days'));
        $image = \App\Core\Media::store('banner', 'image', 'e' . $emp['id']);
        if ($d['template'] === 'photo' && !$image) throw new ValidationException(['image' => 'Для шаблону «Фото» додайте картинку або оберіть інший шаблон']);
        $id = $db->insert('banners', [...$d, 'employer_id' => $emp['id'], 'created_by' => $me['id'], 'image' => $image, 'price' => 0, 'status' => 'approved',
            'moderated_by' => $me['id'], 'moderated_at' => Db::now()]);
        Audit::log('ads.banner_created_by_admin', 'banner', $id, ['employer' => (int) $emp['id'], 'days' => $d['days'], 'placement' => $d['placement'], 'template' => $d['template']]);
        $full = Ads::firstFullDay($d['placement'], $d['starts_on'], $d['ends_on'], $id);
        flash('ok', 'Банер створено й схвалено. Він показуватиметься з ' . date('d.m', strtotime($d['starts_on'])) . ' по ' . date('d.m', strtotime($d['ends_on'])) . '.'
            . ($full ? ' Зверніть увагу: на ' . date('d.m', strtotime($full)) . ' це місце вже було зайняте, тож банери в ці дні показуватимуться по черзі.' : ''));
        return Response::redirect(url('/admin/banners', ['status' => 'approved']));
    }

    private function bannerRow(int $id): array
    {
        $b = App::db()->one('SELECT b.*, e.name AS employer, e.legal_form, e.logo FROM {p}banners b JOIN {p}employers e ON e.id = b.employer_id WHERE b.id = ?', [$id]);
        if (!$b) throw new HttpException(404, 'Банер не знайдено.');
        return $b;
    }

    /** Адміністратор редагує будь-який банер (0.5.15): текст, вигляд, вакансію, дати. Статус банера не змінюється. */
    public function bannerEdit(Request $req): Response
    {
        $b = $this->bannerRow((int) $req->params['id']);
        if (!App::$old) App::$old = Ads::formValues($b);
        return $this->render('banner_edit', 'banners', ['title' => 'Редагувати банер', 'b' => $b, 'targets' => Ads::targetVacancies([$b['employer_id']]),
            'back' => $b['status'] === 'pending' ? null : $b['status']]);
    }

    public function bannerUpdate(Request $req): Response
    {
        $b = $this->bannerRow((int) $req->params['id']);
        $d = Ads::bannerInput($req, array_keys(Ads::TEMPLATES));
        $err = Ads::bannerErrors($d, true);
        if ($d['starts_on'] === $b['starts_on']) unset($err['starts_on']); // банер, що вже йде, можна редагувати без зміни дати
        [$d['vacancy_id'], $terr] = Ads::targetInput($req, (int) $b['employer_id']);
        if ($terr) $err['target'] = $terr;
        if ($err) throw new ValidationException($err);
        $d['ends_on'] = date('Y-m-d', strtotime($d['starts_on'] . ' +' . ($d['days'] - 1) . ' days'));
        $d['image'] = Ads::editImage($req, $b);
        if ($d['template'] === 'photo' && !$d['image']) throw new ValidationException(['image' => 'Для шаблону «Фото» додайте картинку або оберіть інший шаблон']);
        App::db()->update('banners', [...$d, 'updated_at' => Db::now()], 'id = ?', [$b['id']]);
        Audit::log('ads.banner_edited_by_admin', 'banner', $b['id'], ['vacancy' => $d['vacancy_id'], 'starts_on' => $d['starts_on'], 'days' => $d['days']]);
        flash('ok', 'Банер «' . $d['title'] . '» збережено.');
        return Response::redirect(url('/admin/banners', ['status' => $b['status'] === 'pending' ? null : $b['status']]));
    }

    public function bannerDelete(Request $req): Response
    {
        $b = $this->bannerRow((int) $req->params['id']);
        Ads::delete($b);
        Audit::log('ads.banner_deleted_by_admin', 'banner', $b['id'], ['title' => $b['title'], 'employer' => (int) $b['employer_id'], 'status' => $b['status']]);
        flash('ok', 'Банер «' . $b['title'] . '» видалено.');
        return Response::redirect(url('/admin/banners', ['status' => $b['status'] === 'pending' ? null : $b['status']]));
    }

    /** Шаблони банерів: які доступні роботодавцям і який обрано за замовчуванням (0.5.12). */
    public function bannerTemplates(Request $req): Response
    {
        $db = App::db();
        $used = [];
        try {
            foreach ($db->all("SELECT template, COUNT(*) AS n FROM {p}banners WHERE status IN ('pending','approved') AND ends_on >= ? GROUP BY template", [Ads::today()]) as $r) $used[$r['template']] = (int) $r['n'];
        } catch (\PDOException) {}
        return $this->render('banner_templates', 'banners', ['title' => 'Шаблони банерів', 'set' => Ads::templateSettings(), 'used' => $used]);
    }

    public function bannerTemplatesSave(Request $req): Response
    {
        $on = array_values(array_intersect(array_keys(Ads::TEMPLATES), (array) ($req->post['on'] ?? [])));
        if (!$on) { flash('bad', 'Залиште ввімкненим хоча б один шаблон.'); return Response::redirect(url('/admin/banners/templates')); }
        $def = $req->str('default', 12);
        if (!in_array($def, $on, true)) $def = $on[0];
        $val = ['off' => array_values(array_diff(array_keys(Ads::TEMPLATES), $on)), 'default' => $def];
        App::setSetting('ads.templates', $val, App::auth()->id());
        Audit::log('ads.templates_updated', 'settings', 'ads.templates', $val);
        flash('ok', 'Шаблони збережено. Роботодавці бачать лише ввімкнені шаблони.');
        return Response::redirect(url('/admin/banners/templates'));
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
        return $this->render('billing', 'billing', ['title' => 'Монетизація', 'on' => Ads::monetizationOn(), 'prices' => Ads::prices(), 'slots' => Ads::slots(), 'sideSlots' => Ads::sideSlots(), 'occupancy' => Ads::occupancy(),
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
            $slots = ['main' => max(1, min(10, (int) $req->input('slots', Ads::slots()))), 'side' => max(1, min(Ads::MAX_SIDE_SLOTS, (int) $req->input('slots_side', Ads::sideSlots())))];
            if ($slots !== ['main' => Ads::slots(), 'side' => Ads::sideSlots()]) Audit::log('ads.slots_updated', 'settings', 'ads.slots', $slots);
            App::setSetting('ads.slots_main', $slots['main'], $me);
            App::setSetting('ads.slots_side', $slots['side'], $me);
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
        $employers = $db->all('SELECT id, name, legal_form, logo, is_verified FROM {p}employers WHERE owner_id = ? AND deleted_at IS NULL', [$id]);
        try { $master = \App\Core\Services::ofUser($id); } catch (\PDOException) { $master = null; }
        $isStaff = (int) $u['owner_flag'] === 1 || (bool) $roles;
        $me = App::auth()->user();
        $canEdit = App::auth()->can('identity.user:update') && $u['deleted_at'] === null && (!$isStaff || $me['is_owner'] || $id === (int) $me['id']);
        $stats = ['vacancies' => (int) $db->value('SELECT COUNT(*) FROM {p}vacancies WHERE author_id = ?', [$id]), 'applications' => (int) $db->value('SELECT COUNT(*) FROM {p}applications WHERE user_id = ?', [$id])];
        return $this->render('user', $isStaff ? 'team' : 'clients', ['title' => $u['display_name'], 'u' => $u, 'phone' => $full ? $phone : mask_contact($phone), 'email' => $full ? $email : mask_contact($email), 'masked' => !$full,
            'roles' => $roles, 'allRoles' => $allRoles, 'audit' => $audit, 'employers' => $employers, 'master' => $master, 'stats' => $stats, 'me' => $me, 'isStaff' => $isStaff, 'canEdit' => $canEdit]);
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
        return $this->render('roles', 'roles', ['title' => 'Ролі та права', 'roles' => $roles, 'rp' => $rp, 'perms' => Permissions::all(),
            'canManage' => App::auth()->can('access.role:manage_settings')]);
    }

    /** Галочки прав ролі (0.5.3). Критичні права й роль власника тут не змінюються. Вимкнене право не повертається після оновлення сайту. */
    public function rolePermissions(Request $req): Response
    {
        $db = App::db();
        $role = $db->one('SELECT * FROM {p}roles WHERE id = ? AND deleted_at IS NULL', [(int) $req->params['id']]);
        if (!$role || $role['slug'] === 'owner') throw new HttpException(404, 'Роль не знайдено.');
        $allowed = Permissions::codes();
        $want = array_values(array_intersect($allowed, array_map('strval', (array) ($req->post['perms'] ?? []))));
        $have = array_column($db->all('SELECT permission_code FROM {p}role_permissions WHERE role_id = ?', [$role['id']]), 'permission_code');
        $have = array_values(array_intersect($allowed, $have));
        $add = array_values(array_diff($want, $have));
        $del = array_values(array_diff($have, $want));
        if (!$add && !$del) {
            flash('info', 'Права ролі «' . $role['name'] . '» не змінилися.');
            return Response::redirect(url('/admin/roles', ['open' => $role['id']]));
        }
        $db->tx(function (Db $db) use ($role, $add, $del) {
            foreach ($add as $c) {
                $db->run('INSERT IGNORE INTO {p}role_permissions (role_id, permission_code) VALUES (?, ?)', [$role['id'], $c]);
                $db->run('DELETE FROM {p}role_permission_removed WHERE role_id = ? AND permission_code = ?', [$role['id'], $c]);
            }
            foreach ($del as $c) {
                $db->run('DELETE FROM {p}role_permissions WHERE role_id = ? AND permission_code = ?', [$role['id'], $c]);
                $db->run('INSERT IGNORE INTO {p}role_permission_removed (role_id, permission_code) VALUES (?, ?)', [$role['id'], $c]);
            }
        });
        Audit::log('access.role_permissions', 'role', (int) $role['id'], ['role' => $role['slug'], 'added' => $add, 'removed' => $del]);
        flash('ok', 'Права ролі «' . $role['name'] . '» збережено. Діють одразу.');
        return Response::redirect(url('/admin/roles', ['open' => $role['id']]));
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
        $published = '';
        foreach ($rows as $r) if ($r['active']) $published = $r['name'];
        return $this->render('themes', 'themes', ['title' => 'Теми оформлення', 'rows' => $rows, 'history' => $history, 'festive' => \App\Core\Festive::config(), 'published' => $published]);
    }

    /** Новорічне оформлення (0.5.4): режим, дати й ефекти. */
    public function themeFestive(Request $req): Response
    {
        $F = \App\Core\Festive::class;
        $mode = (string) $req->input('mode', 'off');
        if (!isset($F::MODES[$mode])) $mode = 'off';
        $from = $F::parseHuman((string) $req->input('from', ''));
        $to = $F::parseHuman((string) $req->input('to', ''));
        $err = [];
        if (!$from) $err['from'] = 'Вкажіть дату як 15.12';
        if (!$to) $err['to'] = 'Вкажіть дату як 15.01';
        if ($err) throw new ValidationException($err);
        $c = ['mode' => $mode, 'from' => $from, 'to' => $to];
        foreach (['snow', 'garland', 'logo', 'theme'] as $k) $c[$k] = (bool) $req->input($k);
        App::setSetting($F::KEY, $c, App::auth()->id());
        Audit::log('theming.festive_updated', 'settings', $F::KEY, $c);
        $now = $F::isOn($c) && in_array(true, [$c['snow'], $c['garland'], $c['logo'], $c['theme']], true);
        flash('ok', $now ? 'Збережено. Новорічне оформлення вже на сайті.' : 'Збережено. Зараз новорічне оформлення на сайті не показується.');
        return Response::redirect(url('/admin/themes'));
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

    /** Повернення на «Налаштування» з відкритим блоком, який щойно зберегли (і позначкою «Збережено»). */
    private function settingsUrl(string $block): string
    {
        $block = preg_replace('/[^a-z]/', '', $block);
        return url('/admin/settings', ['saved' => $block]) . ($block !== '' ? '#set-' . $block : '');
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
        } elseif ($section === 'moderation') {
            App::setSetting('moderation.premoderation', ['jobs' => !empty($req->post['pre_jobs']), 'gigs' => true, 'services' => !empty($req->post['pre_services'])], $me);
        } elseif ($section === 'logo') {
            App::setSetting('employers.logo', ['hint' => !empty($req->post['hint']), 'hintText' => $req->str('hintText', 300) ?: logo_settings()['hintText'],
                'perk' => !empty($req->post['perk']), 'perkText' => $req->str('perkText', 200)], $me);
        } elseif ($section === 'favicon') {
            if (!empty($req->post['reset'])) {
                \App\Core\Favicon::reset($me);
                Audit::log('settings.updated', 'settings', 'site.favicon', ['reset' => true]);
                flash('ok', 'Повернуто стандартну іконку сайту.');
                return Response::redirect($this->settingsUrl('favicon'));
            }
            if (!\App\Core\Favicon::store('icon', $me)) throw new ValidationException(['icon' => 'Оберіть картинку']);
            Audit::log('settings.updated', 'settings', 'site.favicon');
            flash('ok', 'Іконку сайту оновлено. Якщо у вкладці ще стара, оновіть сторінку (Ctrl+F5): браузери запам’ятовують іконки.');
            return Response::redirect($this->settingsUrl('favicon'));
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
                return Response::redirect($this->settingsUrl('email'));
            }
        }
        Audit::log('settings.updated', 'settings', $section);
        flash('ok', 'Налаштування збережено.');
        return Response::redirect($this->settingsUrl($section));
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
        return Response::redirect($this->settingsUrl('db'));
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
            WHERE a.action IN ('" . implode("','", Audit::UPDATE_ACTIONS) . "') ORDER BY a.id DESC LIMIT 10");
        return $this->render('updates', 'updates', ['title' => 'Оновлення', 'info' => $info, 'version' => YW_VERSION, 'local' => $local,
            'backups' => $backups, 'users' => $users, 'history' => $history, 'problems' => Updater::serverProblems(),
            'cfg' => Updater::config(), 'canConfigure' => App::auth()->can('settings.system:manage_settings'), 'pending' => Updater::pending(),
            'vpage' => max(1, (int) ($req->query['vpage'] ?? 1))]);
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
            // Файли вже почали замінюватися й сайт повернуто з копії — «не вдалося»; інакше сайт навіть не змінювався.
            $restored = $e->getCode() === Updater::ERR_RESTORED;
            Audit::log($restored ? 'system.update_failed' : 'system.update_aborted', 'version', $version,
                ['from' => YW_VERSION, 'to' => $version, 'stage' => $restored ? 'files' : 'download', 'error' => mb_substr($e->getMessage(), 0, 500)]);
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
            flash('bad', 'Оновлення до ' . $r['to'] . ' не вдалося на кроці оновлення бази (' . $r['error'] . '). '
                . (empty($r['restoreError']) ? 'Сайт автоматично повернуто до версії ' . $r['from'] . ', нічого не втрачено.'
                    : 'Автоматично повернути попередню версію не вдалося (' . $r['restoreError'] . '). Відновіть останню резервну копію внизу сторінки.'));
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
            $b = Updater::deleteBackup($id, ($req->post['confirm_latest'] ?? '') === '1');
            Audit::log('system.backup_deleted', 'backup', $id, ['version' => $b['version'] ?? null, 'size' => $b['size'] ?? null]);
            flash('ok', 'Копію версії ' . ($b['version'] ?? '?') . ' видалено, звільнено ' . Updater::humanSize((int) ($b['size'] ?? 0)) . '.');
        } catch (\Throwable $e) {
            flash('bad', $e->getMessage());
        }
        return Response::redirect(url('/admin/updates') . '#backups');
    }

    public function updatesClean(Request $req): Response
    {
        $keep = max(1, (int) ($req->post['keep'] ?? 1));
        $r = Updater::deleteOldBackups($keep);
        if ($r['count']) {
            Audit::log('system.backups_cleaned', 'backup', null, ['keep' => $keep, 'deleted' => $r['count'], 'freed' => $r['freed']]);
            flash('ok', 'Видалено ' . plural($r['count'], 'стару копію', 'старі копії', 'старих копій') . ', звільнено ' . Updater::humanSize($r['freed']) . '. Залишилось ' . plural(count(Updater::backups()), 'копія', 'копії', 'копій') . '.');
        } else {
            flash('ok', 'Видаляти нічого: копій і так не більше ' . $keep . '.');
        }
        return Response::redirect(url('/admin/updates') . '#backups');
    }

    public function updatesKeep(Request $req): Response
    {
        try {
            $n = (int) ($req->post['keep'] ?? 0);
            $r = Updater::setKeepCount($n, App::auth()->id());
            Audit::log('system.backups_keep', 'settings', 'updates.backups_keep', ['keep' => $n, 'deleted' => $r['count']]);
            flash('ok', 'Тепер зберігаються останні ' . plural($n, 'копія', 'копії', 'копій') . '.' . ($r['count'] ? ' Зайві видалено: ' . $r['count'] . ', звільнено ' . Updater::humanSize($r['freed']) . '.' : ''));
        } catch (\Throwable $e) {
            flash('bad', $e->getMessage());
        }
        return Response::redirect(url('/admin/updates') . '#backups');
    }

    public function updatesDownload(Request $req): Response
    {
        $id = (string) ($req->query['id'] ?? '');
        $part = ($req->query['part'] ?? '') === 'db' ? 'db' : 'files';
        $path = Updater::backupPart($id, $part);
        if (!$path) {
            flash('bad', 'Резервну копію не знайдено.');
            return Response::redirect(url('/admin/updates') . '#backups');
        }
        Audit::log('system.backup_downloaded', 'backup', $id, ['part' => $part]);
        return Response::download($path, 'yework-backup-' . $id . ($part === 'db' ? '-baza.sql' : '-faily.zip'), $part === 'db' ? 'application/sql' : 'application/zip');
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

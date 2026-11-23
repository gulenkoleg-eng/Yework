<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\App;
use App\Core\Audit;
use App\Core\Crypto;
use App\Core\Db;
use App\Core\Demo;
use App\Core\HttpException;
use App\Core\Media;
use App\Core\Notices;
use App\Core\Request;
use App\Core\Response;
use App\Core\Services;
use App\Core\Stats;
use App\Core\ValidationException;
use App\Core\View;

/**
 * Люди в адмінці (0.5.16): «Клієнти» (роботодавці, шукачі, майстри, приклади) окремо від «Команди» (персонал із ролями).
 * Адміністратор може виправити картку клієнта: ім’я, профіль роботодавця з логотипом, назву й фото майстра.
 * Власник картки отримує сповіщення в кабінеті та лист (або SMS), як і при виправленні вакансії.
 */
final class PeopleController
{
    public const PER_PAGE = 30;

    /** Хто належить до персоналу: головний адміністратор або хтось із роллю. */
    public const STAFF_SQL = '(COALESCE(u.owner_flag, 0) = 1 OR EXISTS (SELECT 1 FROM {p}user_roles ur WHERE ur.user_id = u.id))';

    /** Вкладки «Клієнтів»: ключ => [підпис, умова SQL]. */
    public static function types(): array
    {
        $demo = Demo::content()['users'];
        $demoSql = $demo ? 'u.id IN (' . implode(',', array_map('intval', $demo)) . ')' : '0 = 1';
        return [
            '' => ['Усі', '1 = 1'],
            'employers' => ['Роботодавці', 'EXISTS (SELECT 1 FROM {p}employers e WHERE e.owner_id = u.id AND e.deleted_at IS NULL)'],
            'seekers' => ['Шукачі роботи', '(EXISTS (SELECT 1 FROM {p}resumes r WHERE r.user_id = u.id AND r.deleted_at IS NULL) OR EXISTS (SELECT 1 FROM {p}applications a WHERE a.user_id = u.id))'],
            'masters' => ['Майстри', 'EXISTS (SELECT 1 FROM {p}masters m WHERE m.user_id = u.id AND m.deleted_at IS NULL)'],
            'demo' => ['Приклади', $demoSql],
            'blocked' => ['Заблоковані', "u.status = 'blocked'"],
        ];
    }

    private function render(string $view, string $nav, array $data): Response
    {
        return new Response(View::render('admin/' . $view, [...$data, 'nav' => $nav], 'admin'));
    }

    /** Пошук за ім’ям, логіном, телефоном чи email — однаковий для обох списків. */
    private static function search(string $q, array &$w, array &$p): void
    {
        if ($q === '') return;
        $c = Crypto::parseContact($q);
        if ($c) { $w[] = ($c['kind'] === 'phone' ? 'u.phone_hash' : 'u.email_hash') . ' = ?'; $p[] = Crypto::hmac($c['value']); return; }
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $w[] = '(u.display_name LIKE ? OR u.login LIKE ? OR EXISTS (SELECT 1 FROM {p}employers e2 WHERE e2.owner_id = u.id AND e2.deleted_at IS NULL AND e2.name LIKE ?)'
            . ' OR EXISTS (SELECT 1 FROM {p}masters m2 WHERE m2.user_id = u.id AND m2.deleted_at IS NULL AND m2.name LIKE ?))';
        array_push($p, $like, $like, $like, $like);
    }

    /** Старе посилання «Користувачі» веде до клієнтів (персонал — у «Команді»). */
    public function legacy(Request $req): Response
    {
        $f = (string) ($req->query['filter'] ?? '');
        $q = (string) ($req->query['q'] ?? '');
        if ($f === 'staff') return Response::redirect(url('/admin/team', ['q' => $q]));
        return Response::redirect(url('/admin/clients', ['q' => $q, 'type' => $f === 'blocked' ? 'blocked' : '']));
    }

    public function clients(Request $req): Response
    {
        $db = App::db();
        $q = mb_substr(trim((string) ($req->query['q'] ?? '')), 0, 100);
        $types = self::types();
        $type = (string) ($req->query['type'] ?? '');
        if (!isset($types[$type])) $type = '';

        $base = ['u.deleted_at IS NULL', 'NOT ' . self::STAFF_SQL];
        $p = [];
        self::search($q, $base, $p);
        $counts = [];
        foreach ($types as $k => [, $sql]) {
            $counts[$k] = (int) $db->value('SELECT COUNT(*) FROM {p}users u WHERE ' . implode(' AND ', [...$base, $sql]), $p);
        }
        $where = ' WHERE ' . implode(' AND ', [...$base, $types[$type][1]]);
        $total = $counts[$type];
        $page = max(1, (int) ($req->query['page'] ?? 1));
        $rows = $db->all('SELECT u.id, u.login, u.display_name, u.status, u.phone_hash, u.email_hash, u.created_at, u.last_login_at,
            (SELECT e.id FROM {p}employers e WHERE e.owner_id = u.id AND e.deleted_at IS NULL ORDER BY e.id LIMIT 1) AS emp_id,
            (SELECT e.name FROM {p}employers e WHERE e.owner_id = u.id AND e.deleted_at IS NULL ORDER BY e.id LIMIT 1) AS emp_name,
            (SELECT e.legal_form FROM {p}employers e WHERE e.owner_id = u.id AND e.deleted_at IS NULL ORDER BY e.id LIMIT 1) AS emp_form,
            (SELECT e.logo FROM {p}employers e WHERE e.owner_id = u.id AND e.deleted_at IS NULL ORDER BY e.id LIMIT 1) AS emp_logo,
            (SELECT e.is_verified FROM {p}employers e WHERE e.owner_id = u.id AND e.deleted_at IS NULL ORDER BY e.id LIMIT 1) AS emp_verified,
            (SELECT m.name FROM {p}masters m WHERE m.user_id = u.id AND m.deleted_at IS NULL LIMIT 1) AS m_name,
            (SELECT m.photo FROM {p}masters m WHERE m.user_id = u.id AND m.deleted_at IS NULL LIMIT 1) AS m_photo,
            (SELECT COUNT(*) FROM {p}resumes r WHERE r.user_id = u.id AND r.deleted_at IS NULL) AS resumes,
            (SELECT COUNT(*) FROM {p}vacancies v WHERE v.author_id = u.id AND v.deleted_at IS NULL) AS vacancies
            FROM {p}users u' . $where . ' ORDER BY u.id DESC LIMIT ' . self::PER_PAGE . ' OFFSET ' . (($page - 1) * self::PER_PAGE), $p);
        return $this->render('clients', 'clients', ['title' => 'Клієнти', 'rows' => $rows, 'total' => $total, 'counts' => $counts, 'types' => $types,
            'type' => $type, 'page' => $page, 'q' => $q, 'demo' => Demo::content()['users'], 'canEdit' => App::auth()->can('identity.user:update')]);
    }

    public function team(Request $req): Response
    {
        $db = App::db();
        $q = mb_substr(trim((string) ($req->query['q'] ?? '')), 0, 100);
        $w = ['u.deleted_at IS NULL', self::STAFF_SQL];
        $p = [];
        self::search($q, $w, $p);
        $rows = $db->all('SELECT u.id, u.login, u.display_name, u.status, u.owner_flag, u.phone_hash, u.email_hash, u.created_at, u.last_login_at,
            (SELECT GROUP_CONCAT(r.name ORDER BY r.id SEPARATOR ", ") FROM {p}user_roles ur JOIN {p}roles r ON r.id = ur.role_id WHERE ur.user_id = u.id) AS roles
            FROM {p}users u WHERE ' . implode(' AND ', $w) . ' ORDER BY u.owner_flag DESC, u.id', $p);
        return $this->render('team', 'team', ['title' => 'Команда', 'rows' => $rows, 'q' => $q]);
    }

    /* ---------------- виправлення картки клієнта ---------------- */

    private function target(int $id): array
    {
        $u = App::db()->one('SELECT * FROM {p}users WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$u) throw new HttpException(404, 'Користувача не знайдено.');
        $me = App::auth()->user();
        $staff = (int) $u['owner_flag'] === 1 || App::auth()->rolesOf($id);
        // Картку члена команди виправляє лише головний адміністратор (так само, як і блокування персоналу).
        if ($staff && !$me['is_owner'] && $id !== (int) $me['id']) throw new HttpException(403, 'Картки членів команди виправляє лише головний адміністратор.');
        return $u;
    }

    private static function employerOf(int $uid): ?array
    {
        return App::db()->one('SELECT * FROM {p}employers WHERE owner_id = ? AND deleted_at IS NULL ORDER BY id LIMIT 1', [$uid]);
    }

    private static function masterOf(int $uid): ?array
    {
        try { return Services::ofUser($uid); } catch (\PDOException) { return null; }
    }

    public function editForm(Request $req): Response
    {
        $u = $this->target((int) $req->params['id']);
        [$settlements] = \App\Controllers\SiteController::reference();
        return $this->render('client_edit', 'clients', ['title' => 'Картка: ' . $u['display_name'], 'u' => $u,
            'employer' => self::employerOf((int) $u['id']), 'master' => self::masterOf((int) $u['id']), 'settlements' => $settlements]);
    }

    public function editSave(Request $req): Response
    {
        $u = $this->target((int) $req->params['id']);
        $uid = (int) $u['id'];
        $db = App::db();
        $emp = self::employerOf($uid);
        $master = self::masterOf($uid);
        $note = trim($req->str('admin_note', 500));
        $err = [];

        // 1. Ім’я в акаунті
        $name = $req->str('display_name', 80);
        if (mb_strlen($name) < 2) $err['display_name'] = 'Вкажіть ім’я';

        // 2. Профіль роботодавця (якщо є або адміністратор створює його, щоб вставити логотип)
        $empData = null;
        $makeEmp = !$emp && !empty($req->post['emp_create']);
        if ($emp || $makeEmp) {
            $form = $req->str('legal_form', 16);
            if ($form !== '' && !array_key_exists($form, LEGAL_FORMS)) $form = 'other';
            $empData = [
                'kind' => match ($form) { 'person' => 'person', 'ФОП' => 'fop', default => 'company' },
                'legal_form' => in_array($form, ['', 'person'], true) ? null : $form,
                'name' => $req->str('emp_name', 120), 'edrpou' => preg_replace('/\D/', '', $req->str('edrpou', 12)) ?: null,
                'about' => $req->str('about', 3000), 'settlement_id' => ((int) $req->input('settlement_id')) ?: null,
                'website' => $req->str('website', 200) ?: null,
                'is_verified' => empty($req->post['is_verified']) ? 0 : 1,
            ];
            if (mb_strlen($empData['name']) < 2) $err['emp_name'] = 'Вкажіть назву або ім’я';
            if ($empData['edrpou'] !== null && !preg_match('/^(\d{8}|\d{10})$/', $empData['edrpou'])) $err['edrpou'] = 'ЄДРПОУ — 8 цифр, РНОКПП (ФОП) — 10';
            if ($empData['website'] !== null && !preg_match('#^https?://[^\s]+$#', $empData['website'])) $err['website'] = 'Адреса має починатися з https://';
        }

        // 3. Картка майстра: назва й фото (логотип)
        $mName = null;
        if ($master) {
            $mName = $req->str('master_name', 100);
            if (mb_strlen($mName) < 2) $err['master_name'] = 'Вкажіть ім’я або назву';
        }
        if ($err) throw new ValidationException($err);

        // Файли зберігаємо лише після перевірки полів, щоб не лишати «сиріт» у сховищі.
        $logo = $empData !== null && empty($req->post['logo_delete']) ? Media::store('logo', 'logo', 'e' . ($emp['id'] ?? 'u' . $uid)) : null;
        $photo = $master && empty($req->post['master_photo_delete']) ? Media::store('master', 'master_photo', 'm' . $uid) : null;

        $changed = [];
        if ($name !== $u['display_name']) {
            $db->update('users', ['display_name' => $name, 'updated_at' => Db::now()], 'id = ?', [$uid]);
            $changed[] = 'ім’я';
        }

        if ($empData !== null) {
            if ($logo) $empData += ['logo' => $logo, 'logo_updated_at' => Db::now()];
            elseif ($emp && $emp['logo'] && !empty($req->post['logo_delete'])) $empData += ['logo' => null, 'logo_updated_at' => Db::now()];
            if ($emp) {
                $diff = [];
                foreach ($empData as $k => $v) if ((string) ($emp[$k] ?? '') !== (string) ($v ?? '') && $k !== 'logo_updated_at') $diff[] = $k;
                if ($diff) {
                    $db->update('employers', [...$empData, 'updated_at' => Db::now()], 'id = ?', [$emp['id']]);
                    if (array_key_exists('logo', $empData) && $emp['logo']) Media::delete('logo', $emp['logo']);
                    Audit::log('jobs.employer_admin_edited', 'employer', $emp['id'], ['fields' => $diff, 'owner' => $uid]);
                    if (in_array('logo', $diff, true)) $changed[] = $logo ? 'логотип' : 'логотип (видалено)';
                    if (array_diff($diff, ['logo', 'is_verified'])) $changed[] = 'профіль роботодавця';
                    if (in_array('is_verified', $diff, true)) $changed[] = $empData['is_verified'] ? 'позначка «Перевірений роботодавець»' : 'позначку «Перевірений» знято';
                }
            } else {
                $eid = $db->insert('employers', [...$empData, 'owner_id' => $uid]);
                Audit::log('jobs.employer_admin_edited', 'employer', $eid, ['created' => true, 'owner' => $uid]);
                $changed[] = 'створено профіль роботодавця' . ($logo ? ' з логотипом' : '');
            }
        }

        if ($master) {
            $md = [];
            if ($mName !== $master['name']) $md['name'] = $mName;
            if ($photo) $md['photo'] = $photo;
            elseif ($master['photo'] && !empty($req->post['master_photo_delete'])) $md['photo'] = null;
            if ($md) {
                $db->update('masters', [...$md, 'updated_at' => Db::now()], 'id = ?', [$master['id']]);
                if (array_key_exists('photo', $md) && $master['photo']) Media::delete('master', $master['photo']);
                Audit::log('services.master_admin_edited', 'master', $master['id'], ['fields' => array_keys($md), 'owner' => $uid]);
                if (isset($md['name'])) $changed[] = 'назва картки майстра';
                if (array_key_exists('photo', $md)) $changed[] = $photo ? 'фото / логотип майстра' : 'фото майстра (видалено)';
            }
        }

        if (!$changed) {
            flash('info', 'Нічого не змінено, тож клієнту нічого не надсилали.');
            return Response::redirect(url('/admin/users/' . $uid));
        }
        Stats::forget();
        Audit::log('identity.client_admin_edited', 'user', $uid, ['changed' => $changed, 'note' => $note ?: null]);
        $sent = $uid !== (int) App::auth()->user()['id'] && !in_array($uid, Demo::content()['users'], true);
        if ($sent) self::notifyClient($uid, $changed, $note);
        flash('ok', 'Картку збережено: ' . implode(', ', $changed) . '.' . ($sent ? ' Клієнт отримав сповіщення.' : ''));
        return Response::redirect(url('/admin/users/' . $uid));
    }

    /** Сповіщення в кабінеті + лист (або SMS). Помилка пошти не скасовує збереження. */
    private static function notifyClient(int $uid, array $changed, string $note): void
    {
        $site = (string) App::setting('site.name', 'YEWORK');
        $head = 'Адміністрація сайту оновила ваш профіль';
        $what = 'Змінено: ' . implode(', ', $changed) . '.';
        $body = $what . ($note !== '' ? "\nКоментар адміністрації: $note" : '');
        try {
            Notices::add($uid, 'admin_edit', $head, $body, '/cabinet', 'user', $uid);
        } catch (\Throwable $e) {
            error_log('[YEWORK] notice failed: ' . $e->getMessage());
        }
        $link = rtrim((string) App::setting('site.url', ''), '/') . url('/cabinet');
        Services::notifyUser($uid, "Адміністрація оновила ваш профіль — $site",
            "$head.\n\n$body\n\nПереглянути кабінет: $link\n\nЯкщо щось не так, просто виправте це в кабінеті.\n\n— $site",
            "$site: адміністрація оновила ваш профіль. " . $what, 'admin_edit');
    }
}

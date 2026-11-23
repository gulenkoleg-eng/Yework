<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\App;
use App\Core\Audit;
use App\Core\Db;
use App\Core\Demo;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Services;
use App\Core\View;

/** Адмінка «Послуг»: модерація профілів майстрів, відгуки, категорії послуг. */
final class ServicesAdminController
{
    private function render(string $view, string $nav, array $data): Response
    {
        $me = App::auth()->user();
        if (!$me['is_staff']) throw new HttpException(403, 'Адмін-панель доступна лише персоналу.');
        return new Response(View::render('admin/' . $view, [...$data, 'me' => $me, 'nav' => $nav], 'admin'));
    }

    public const TABS = ['pending' => 'Чекають', 'changed' => 'Змінені', 'published' => 'Опубліковані', 'rejected' => 'Відхилені', 'archived' => 'Архів', 'all' => 'Усі', 'reviews' => 'Відгуки'];
    /** Умова кожної вкладки списку (0.5.24). «Усі» — разом із чернетками. */
    private const TAB_SQL = [
        'pending' => "m.status = 'pending'", 'changed' => "m.status = 'published' AND m.needs_review = 1", 'published' => "m.status = 'published'",
        'rejected' => "m.status = 'rejected'", 'archived' => "m.status = 'archived'", 'all' => '1 = 1',
    ];
    public const PER_PAGE = 30;
    public const KINDS = ['real' => 'Справжні', 'demo' => 'Приклади'];

    /** Фільтри списку майстрів із рядка запиту: лише відомі ключі й прості значення (як у модерації вакансій 0.5.22). */
    public static function filters(array $q): array
    {
        return [
            'status' => array_key_exists((string) ($q['status'] ?? ''), self::TABS) ? (string) $q['status'] : 'pending',
            'q' => mb_substr(trim((string) ($q['q'] ?? '')), 0, 100),
            'cat' => max(0, (int) ($q['cat'] ?? 0)),
            'place' => preg_match('/^[cs]:\d+$/', (string) ($q['place'] ?? '')) ? (string) $q['place'] : '',
            'kind' => array_key_exists((string) ($q['kind'] ?? ''), self::KINDS) ? (string) $q['kind'] : '',
            'page' => max(1, (int) ($q['page'] ?? 1)),
        ];
    }

    /** Посилання на список майстрів із фільтрами (порожні й типові значення не пишемо). */
    public static function listUrl(array $f, array $over = []): string
    {
        $f = [...$f, ...$over];
        return url('/admin/masters', [
            'status' => ($f['status'] ?? 'pending') === 'pending' ? null : $f['status'], 'q' => $f['q'] ?? null, 'cat' => ($f['cat'] ?? 0) ?: null,
            'place' => $f['place'] ?? null, 'kind' => $f['kind'] ?? null, 'page' => ($f['page'] ?? 1) > 1 ? $f['page'] : null,
        ]);
    }

    public function index(Request $req): Response
    {
        $db = App::db();
        $f = self::filters($req->query);
        $status = $f['status'];
        if ($status === 'reviews') {
            $counts = [
                'pending' => (int) $db->value("SELECT COUNT(*) FROM {p}masters WHERE status = 'pending' AND deleted_at IS NULL"),
                'changed' => (int) $db->value("SELECT COUNT(*) FROM {p}masters WHERE status = 'published' AND needs_review = 1 AND deleted_at IS NULL"),
            ];
            $rows = $db->all('SELECT r.*, u.display_name AS client, m.name AS master, o.done_by FROM {p}master_reviews r JOIN {p}users u ON u.id = r.client_id
                JOIN {p}masters m ON m.id = r.master_id JOIN {p}service_orders o ON o.id = r.order_id ORDER BY r.id DESC LIMIT 100');
            return $this->render('masters', 'masters', ['title' => 'Майстри: відгуки', 'status' => $status, 'f' => $f, 'rows' => [], 'reviews' => $rows, 'counts' => $counts]);
        }

        // Умови фільтрів (крім вкладки): за ними ж рахуємо лічильники на вкладках.
        $w = ['m.deleted_at IS NULL'];
        $p = [];
        if ($f['q'] !== '') {
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            $digits = preg_replace('/\D/', '', $f['q']);
            $w[] = '(m.name LIKE ? OR u.display_name LIKE ? OR u.login LIKE ? OR m.about LIKE ? OR m.areas LIKE ? OR m.id = ?' . (strlen($digits) >= 5 ? ' OR m.contact_phone LIKE ?' : '') . ')';
            array_push($p, $like, $like, $like, $like, $like, ctype_digit($f['q']) ? (int) $f['q'] : 0);
            if (strlen($digits) >= 5) $p[] = '%' . $digits . '%';
        }
        if ($f['cat']) { $w[] = '(m.category_id = ? OR m.category2_id = ?)'; array_push($p, $f['cat'], $f['cat']); }
        if ($f['place'] !== '') {
            [$kind, $pid] = explode(':', $f['place']);
            $w[] = $kind === 'c' ? 's.community_id = ?' : 'm.settlement_id = ?';
            $p[] = (int) $pid;
        }
        $demo = array_map('intval', Demo::content()['masters']);
        if ($f['kind'] !== '') $w[] = $demo ? 'm.id ' . ($f['kind'] === 'demo' ? '' : 'NOT ') . 'IN (' . implode(',', $demo) . ')' : ($f['kind'] === 'demo' ? '0 = 1' : '1 = 1');
        $from = ' FROM {p}masters m JOIN {p}users u ON u.id = m.user_id JOIN {p}service_categories c ON c.id = m.category_id
            LEFT JOIN {p}service_categories c2 ON c2.id = m.category2_id LEFT JOIN {p}settlements s ON s.id = m.settlement_id WHERE ' . implode(' AND ', $w);

        $sel = [];
        foreach (self::TAB_SQL as $k => $sql) $sel[] = "COALESCE(SUM(CASE WHEN $sql THEN 1 ELSE 0 END), 0) AS `$k`";
        $counts = array_map('intval', $db->one('SELECT ' . implode(', ', $sel) . $from, $p) ?? []);
        $total = $counts[$status] ?? 0;
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $f['page'] = min($f['page'], $pages);
        $order = match ($status) { 'pending' => 'm.submitted_at ASC, m.id ASC', 'all' => 'm.name ASC, m.id ASC', default => 'm.updated_at DESC, m.id DESC' };
        $rows = $db->all('SELECT m.*, c.name AS category, c2.name AS category2, s.name AS settlement, u.display_name, u.login, u.status AS user_status,
            (SELECT COUNT(*) FROM {p}master_prices mp WHERE mp.master_id = m.id) AS n_prices,
            (SELECT COUNT(*) FROM {p}master_works mw WHERE mw.master_id = m.id) AS n_works,
            (SELECT COUNT(*) FROM {p}master_work_photos ph JOIN {p}master_works mw2 ON mw2.id = ph.work_id WHERE mw2.master_id = m.id) AS n_photos'
            . $from . ' AND ' . self::TAB_SQL[$status] . " ORDER BY $order LIMIT " . self::PER_PAGE . ' OFFSET ' . (($f['page'] - 1) * self::PER_PAGE), $p);
        // Подробиці (прайс, фото, відхилення) — лише для рядків поточної сторінки.
        foreach ($rows as &$m) {
            $id = (int) $m['id'];
            $m['prices'] = Services::prices($id);
            $m['works'] = Services::works($id);
            $m['travel'] = Services::travelText($m, Services::travelAreas($id));
            $m['is_demo'] = in_array($id, $demo, true);
            $m['rejected_before'] = (int) $db->value("SELECT COUNT(*) FROM {p}audit_log WHERE action = 'services.master_rejected' AND entity_type = 'master' AND entity_id = ?", [(string) $id]);
        }
        unset($m);

        $places = $db->all('SELECT s.id, s.name, c.id AS community_id, c.name AS community FROM {p}settlements s JOIN {p}communities c ON c.id = s.community_id ORDER BY c.name, s.name');
        $categories = $db->all('SELECT id, name FROM {p}service_categories ORDER BY sort, name');
        return $this->render('masters', 'masters', ['title' => 'Майстри', 'status' => $status, 'f' => $f, 'rows' => $rows, 'reviews' => [], 'counts' => $counts,
            'total' => $total, 'pages' => $pages, 'places' => $places, 'categories' => $categories, 'canEdit' => App::auth()->can(MasterEditController::PERM)]);
    }

    public function decide(Request $req): Response
    {
        $me = App::auth()->user();
        $db = App::db();
        $m = $db->one('SELECT * FROM {p}masters WHERE id = ? AND deleted_at IS NULL', [(int) $req->params['id']]);
        if (!$m) throw new HttpException(404, 'Майстра не знайдено.');
        if ((int) $m['user_id'] === (int) $me['id']) throw new HttpException(403, 'Не можна модерувати власний профіль майстра.');
        // Повертаємося в той самий список із тими самими фільтрами (0.5.24).
        parse_str($req->str('back', 600), $bq);
        $back = self::listUrl(self::filters(is_array($bq) ? $bq : []));
        $decision = $req->str('decision', 10);
        if ($decision === 'approve') {
            if ($m['status'] === 'pending') {
                Services::goLive((int) $m['id'], ['moderated_by' => $me['id'], 'moderated_at' => Db::now(), 'needs_review' => 0]);
                flash('ok', 'Майстра опубліковано до ' . date('d.m.Y', strtotime(Services::showTo((int) $m['period_months']))) . '.');
            } elseif ($m['status'] === 'published' && $m['needs_review']) {
                $db->update('masters', ['needs_review' => 0, 'moderated_by' => $me['id'], 'moderated_at' => Db::now()], 'id = ?', [$m['id']]);
                flash('ok', 'Зміни переглянуто.');
            } else {
                throw new HttpException(409, 'Профіль уже оброблено.');
            }
            Audit::log('services.master_approved', 'master', $m['id']);
        } elseif ($decision === 'reject') {
            $reason = $req->str('reason', 500);
            if (mb_strlen($reason) < 5) { flash('bad', 'Вкажіть причину — її побачить майстер.'); return Response::redirect($back); }
            if (!in_array($m['status'], ['pending', 'published'], true)) throw new HttpException(409, 'Профіль уже оброблено.');
            $db->update('masters', ['status' => 'rejected', 'needs_review' => 0, 'moderated_by' => $me['id'], 'moderated_at' => Db::now(), 'reject_reason' => $reason], 'id = ?', [$m['id']]);
            Audit::log('services.master_rejected', 'master', $m['id'], ['reason' => $reason]);
            flash('ok', $m['status'] === 'published' ? 'Майстра знято з каталогу. Він побачить причину в кабінеті.' : 'Профіль відхилено. Майстер побачить причину в кабінеті.');
        }
        return Response::redirect($back);
    }

    /** Приклади оголошень (0.5.14): додати або видалити трьох майстрів і резюме-приклад. */
    public function demo(Request $req): Response
    {
        $me = App::auth()->user();
        if (!$me['is_staff']) throw new HttpException(403, 'Адмін-панель доступна лише персоналу.');
        $back = Response::redirect(url($req->str('back', 10) === 'resumes' ? '/admin/resumes' : '/admin/masters', ['status' => 'published']));
        if ($req->str('do', 8) === 'remove') {
            if (empty($req->post['confirm'])) { flash('bad', 'Поставте галочку, що видаляєте приклади.'); return $back; }
            $n = Demo::remove((int) $me['id']);
            flash('ok', $n ? 'Приклади видалено разом із фото. На сайті їх більше немає.' : 'Прикладів уже не було.');
            return $back;
        }
        try {
            $n = Demo::add((int) $me['id']);
        } catch (\Throwable $e) {
            error_log('[YEWORK] demo add: ' . $e->getMessage());
            flash('bad', 'Не вдалося додати приклади: ' . $e->getMessage());
            return $back;
        }
        flash('ok', $n ? 'Додано приклади: 3 майстри в «Послугах» і резюме. Видалити їх можна тут же, однією кнопкою.' : 'Приклади вже додано.');
        return $back;
    }

    /** Приховати або повернути відгук. Рейтинг майстра перераховується. */
    public function review(Request $req): Response
    {
        $db = App::db();
        $r = $db->one('SELECT * FROM {p}master_reviews WHERE id = ?', [(int) $req->params['id']]);
        if (!$r) throw new HttpException(404, 'Відгук не знайдено.');
        $hide = ($req->post['hide'] ?? '') === '1';
        $reason = $req->str('reason', 300);
        if ($hide && mb_strlen($reason) < 5) { flash('bad', 'Вкажіть причину, чому відгук приховано.'); return Response::redirect(url('/admin/masters', ['status' => 'reviews'])); }
        $db->update('master_reviews', ['is_hidden' => $hide ? 1 : 0, 'hidden_reason' => $hide ? $reason : null], 'id = ?', [$r['id']]);
        Services::recalc((int) $r['master_id']);
        Audit::log($hide ? 'services.review_hidden' : 'services.review_shown', 'master', $r['master_id'], ['review' => (int) $r['id'], 'reason' => $reason]);
        flash('ok', $hide ? 'Відгук приховано, рейтинг майстра перераховано.' : 'Відгук знову показується.');
        return Response::redirect(url('/admin/masters', ['status' => 'reviews']));
    }

    /* ---------------- категорії послуг ---------------- */

    public function categories(Request $req): Response
    {
        $rows = App::db()->all('SELECT c.*, (SELECT COUNT(*) FROM {p}masters m WHERE (m.category_id = c.id OR m.category2_id = c.id) AND m.deleted_at IS NULL) AS masters
            FROM {p}service_categories c ORDER BY c.sort, c.name');
        return $this->render('service_categories', 'svccats', ['title' => 'Категорії послуг', 'rows' => $rows]);
    }

    public function categoriesSave(Request $req): Response
    {
        $db = App::db();
        $back = Response::redirect(url('/admin/service-categories'));
        if ($req->str('do', 8) === 'add') {
            $name = $req->str('name', 80);
            if (mb_strlen($name) < 3) { flash('bad', 'Вкажіть назву категорії.'); return $back; }
            $slug = 'c' . substr(bin2hex(random_bytes(4)), 0, 7);
            $sort = (int) $db->value('SELECT COALESCE(MAX(sort), 0) FROM {p}service_categories') + 10;
            $id = $db->insert('service_categories', ['slug' => $slug, 'name' => $name, 'sort' => $sort]);
            Audit::log('services.category_added', 'service_category', $id, ['name' => $name]);
            flash('ok', 'Категорію «' . $name . '» додано.');
            return $back;
        }
        $n = 0;
        foreach ((array) ($req->post['cat'] ?? []) as $id => $c) {
            if (!is_array($c)) continue;
            $name = mb_substr(trim((string) ($c['name'] ?? '')), 0, 80);
            if (mb_strlen($name) < 3) continue;
            $n += $db->update('service_categories', ['name' => $name, 'sort' => (int) ($c['sort'] ?? 100), 'is_active' => !empty($c['active']) ? 1 : 0], 'id = ?', [(int) $id]);
        }
        Audit::log('services.categories_updated', 'settings', 'service_categories');
        flash('ok', 'Категорії збережено.');
        return $back;
    }
}

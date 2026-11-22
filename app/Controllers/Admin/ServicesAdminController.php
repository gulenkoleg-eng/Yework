<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\App;
use App\Core\Audit;
use App\Core\Db;
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

    public const TABS = ['pending' => 'Чекають', 'changed' => 'Змінені', 'published' => 'Опубліковані', 'rejected' => 'Відхилені', 'archived' => 'Архів', 'reviews' => 'Відгуки'];

    public function index(Request $req): Response
    {
        $db = App::db();
        $status = array_key_exists((string) ($req->query['status'] ?? ''), self::TABS) ? (string) $req->query['status'] : 'pending';
        $counts = [
            'pending' => (int) $db->value("SELECT COUNT(*) FROM {p}masters WHERE status = 'pending' AND deleted_at IS NULL"),
            'changed' => (int) $db->value("SELECT COUNT(*) FROM {p}masters WHERE status = 'published' AND needs_review = 1 AND deleted_at IS NULL"),
        ];
        if ($status === 'reviews') {
            $rows = $db->all('SELECT r.*, u.display_name AS client, m.name AS master, o.done_by FROM {p}master_reviews r JOIN {p}users u ON u.id = r.client_id
                JOIN {p}masters m ON m.id = r.master_id JOIN {p}service_orders o ON o.id = r.order_id ORDER BY r.id DESC LIMIT 100');
            return $this->render('masters', 'masters', ['title' => 'Майстри: відгуки', 'status' => $status, 'rows' => [], 'reviews' => $rows, 'counts' => $counts]);
        }
        $where = match ($status) {
            'changed' => "m.status = 'published' AND m.needs_review = 1",
            default => 'm.status = ' . $db->pdo->quote($status),
        };
        $ids = array_column($db->all("SELECT m.id FROM {p}masters m WHERE $where AND m.deleted_at IS NULL ORDER BY " . ($status === 'pending' ? 'm.submitted_at ASC' : 'm.updated_at DESC') . ' LIMIT 100'), 'id');
        $rows = [];
        foreach ($ids as $id) {
            $m = Services::find((int) $id);
            if (!$m) continue;
            $m['prices'] = Services::prices((int) $id);
            $m['works'] = Services::works((int) $id);
            $m['rejected_before'] = (int) $db->value("SELECT COUNT(*) FROM {p}audit_log WHERE action = 'services.master_rejected' AND entity_type = 'master' AND entity_id = ?", [(string) $id]);
            $rows[] = $m;
        }
        return $this->render('masters', 'masters', ['title' => 'Майстри', 'status' => $status, 'rows' => $rows, 'reviews' => [], 'counts' => $counts]);
    }

    public function decide(Request $req): Response
    {
        $me = App::auth()->user();
        $db = App::db();
        $m = $db->one('SELECT * FROM {p}masters WHERE id = ? AND deleted_at IS NULL', [(int) $req->params['id']]);
        if (!$m) throw new HttpException(404, 'Майстра не знайдено.');
        if ((int) $m['user_id'] === (int) $me['id']) throw new HttpException(403, 'Не можна модерувати власний профіль майстра.');
        $back = url('/admin/masters', ['status' => $req->str('back', 12) ?: null]);
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

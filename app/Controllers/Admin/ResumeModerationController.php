<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\App;
use App\Core\Audit;
use App\Core\Db;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Resumes;
use App\Core\View;

/** Модерація резюме. Право те саме, що й для вакансій: jobs.listing:moderate. */
final class ResumeModerationController
{
    public static function pendingCount(): int
    {
        try {
            return (int) App::db()->value("SELECT COUNT(*) FROM {p}resumes WHERE status = 'pending' AND deleted_at IS NULL");
        } catch (\Throwable) {
            return 0; // таблиці ще немає (міграція не застосована)
        }
    }

    public function index(Request $req): Response
    {
        $me = App::auth()->user();
        if (!$me['is_staff']) throw new HttpException(403, 'Адмін-панель доступна лише персоналу.');
        $status = in_array($req->query['status'] ?? 'pending', ['pending', 'published', 'rejected'], true) ? (string) ($req->query['status'] ?? 'pending') : 'pending';
        $ids = array_column(App::db()->all('SELECT id FROM {p}resumes WHERE status = ? AND deleted_at IS NULL ORDER BY ' . ($status === 'pending' ? 'submitted_at ASC' : 'moderated_at DESC') . ' LIMIT 100', [$status]), 'id');
        $rows = array_values(array_filter(array_map(static fn ($id) => Resumes::find((int) $id), $ids)));
        foreach ($rows as &$r) {
            $r['author_rejected'] = (int) App::db()->value("SELECT COUNT(*) FROM {p}audit_log WHERE action = 'resume.rejected' AND entity_type = 'resume' AND entity_id = ?", [(string) $r['id']]);
        }
        unset($r);
        return new Response(View::render('admin/resumes', ['title' => 'Модерація резюме', 'rows' => $rows, 'status' => $status, 'me' => $me, 'nav' => 'resumes'], 'admin'));
    }

    public function decide(Request $req): Response
    {
        $me = App::auth()->user();
        $id = (int) $req->params['id'];
        $decision = $req->str('decision', 10);
        $db = App::db();
        $r = $db->one('SELECT * FROM {p}resumes WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$r) throw new HttpException(404, 'Резюме не знайдено.');
        if ((int) $r['user_id'] === (int) $me['id']) throw new HttpException(403, 'Не можна модерувати власне резюме.');
        $back = $req->str('back', 12) ?: null;
        if ($decision === 'approve') {
            if ($r['status'] !== 'pending') throw new HttpException(409, 'Резюме вже оброблено.');
            $db->update('resumes', ['status' => 'published', 'moderated_by' => $me['id'], 'moderated_at' => Db::now(), 'published_at' => $r['published_at'] ?? Db::now(), 'reject_reason' => null], 'id = ?', [$id]);
            Audit::log('resume.approved', 'resume', $id);
            flash('ok', 'Резюме опубліковано.');
        } elseif ($decision === 'reject') {
            $reason = $req->str('reason', 500);
            if (mb_strlen($reason) < 5) { flash('bad', 'Вкажіть причину відхилення — її побачить автор резюме.'); return Response::redirect(url('/admin/resumes', ['status' => $back])); }
            if (!in_array($r['status'], ['pending', 'published'], true)) throw new HttpException(409, 'Резюме вже оброблено.');
            $db->update('resumes', ['status' => 'rejected', 'moderated_by' => $me['id'], 'moderated_at' => Db::now(), 'reject_reason' => $reason], 'id = ?', [$id]);
            Audit::log('resume.rejected', 'resume', $id, ['reason' => $reason]);
            flash('ok', 'Резюме відхилено. Автор побачить причину в кабінеті.');
        }
        return Response::redirect(url('/admin/resumes', ['status' => $back]));
    }
}

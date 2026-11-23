<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\CabinetController;
use App\Controllers\SiteController;
use App\Core\App;
use App\Core\Audit;
use App\Core\Db;
use App\Core\HttpException;
use App\Core\Notices;
use App\Core\Request;
use App\Core\Response;
use App\Core\Services;
use App\Core\Stats;
use App\Core\Vacancies;

/**
 * Адміністрація виправляє чуже оголошення (0.5.3). Потрібне право «Редагування оголошень (вакансії)».
 * Стан вакансії не змінюється (повторної модерації немає), власник отримує сповіщення з переліком змін.
 */
final class ListingEditController
{
    public const PERM = 'jobs.listing:update';

    /** Що показуємо власнику в переліку змін. Кілька полів можуть мати один підпис (зарплата, термін показу). */
    public const VACANCY_FIELDS = [
        'title' => 'посада', 'category_id' => 'категорія', 'settlement_id' => 'населений пункт', 'address' => 'адреса',
        'employment' => 'зайнятість', 'salary_from' => 'зарплата', 'salary_to' => 'зарплата', 'salary_period' => 'зарплата',
        'description' => 'опис роботи', 'requirements' => 'вимоги', 'offers' => 'що пропонуєте', 'no_experience' => '«можна без досвіду»',
        'contact_name' => 'контактна особа', 'contact_phone' => 'телефон', 'show_from' => 'термін показу', 'show_to' => 'термін показу',
    ];

    private function vacancy(int $id): array
    {
        $v = App::db()->one('SELECT v.*, e.owner_id, e.name AS employer_name FROM {p}vacancies v JOIN {p}employers e ON e.id = v.employer_id WHERE v.id = ? AND v.deleted_at IS NULL', [$id]);
        if (!$v) throw new HttpException(404, 'Вакансію не знайдено.');
        return $v;
    }

    public function vacancyForm(Request $req): Response
    {
        $v = $this->vacancy((int) $req->params['id']);
        [$settlements, $categories] = SiteController::reference();
        return new Response(view('cabinet/vacancy_form', ['title' => 'Виправлення вакансії', 'v' => $v, 'adminEdit' => true,
            'employer' => ['name' => $v['employer_name']], 'settlements' => $settlements, 'categories' => $categories, 'premoderation' => false, 'me' => App::auth()->user()]));
    }

    public function vacancySave(Request $req): Response
    {
        $cur = $this->vacancy((int) $req->params['id']);
        // В архівній вакансії термін показу не чіпаємо: поновлює її сам роботодавець.
        $d = CabinetController::vacancyInput($req, $cur, $cur['status'] !== 'archived');
        $note = trim($req->str('admin_note', 500));

        $changed = [];
        foreach ($d as $k => $val) {
            if ((string) ($cur[$k] ?? '') !== (string) ($val ?? '')) $changed[] = $k;
        }
        $id = (int) $cur['id'];
        if (!$changed) {
            flash('info', 'Нічого не змінено, тож власнику нічого не надсилали.');
            return Response::redirect(url('/jobs/' . $id));
        }

        $db = App::db();
        $db->update('vacancies', [...$d, 'updated_at' => Db::now()], 'id = ?', [$id]);
        $db->run('UPDATE {p}vacancies SET version = version + 1 WHERE id = ?', [$id]);
        // Змінили термін показу опублікованої вакансії — одразу перераховуємо, чи вона вже на сайті, чи чекає дати.
        if (in_array($cur['status'], ['published', 'scheduled'], true) && (in_array('show_from', $changed, true) || in_array('show_to', $changed, true))) {
            $keep = $cur['status'] === 'published' ? ['published_at' => $cur['published_at']] : [];
            Vacancies::goLive($id);
            if ($keep) $db->update('vacancies', $keep, 'id = ?', [$id]);
        }
        Stats::forget();

        $labels = array_values(array_unique(array_map(static fn ($k) => self::VACANCY_FIELDS[$k] ?? $k, $changed)));
        Audit::log('jobs.vacancy_admin_edited', 'vacancy', $id, ['fields' => $changed, 'note' => $note ?: null, 'owner' => (int) $cur['owner_id']]);
        self::notifyOwner($cur, $d['title'], $labels, $note);

        flash('ok', 'Вакансію виправлено. Власник отримав сповіщення: змінено ' . implode(', ', $labels) . '.');
        return Response::redirect(url('/jobs/' . $id));
    }

    /** Сповіщення в кабінеті + лист (або SMS, якщо email немає). Помилка пошти не скасовує виправлення. */
    public static function notifyOwner(array $v, string $title, array $labels, string $note): void
    {
        $id = (int) $v['id'];
        $owner = (int) $v['owner_id'];
        $site = (string) App::setting('site.name', 'YEWORK');
        $head = "Адміністрація сайту виправила вашу вакансію «{$title}»";
        $what = 'Змінено: ' . implode(', ', $labels) . '.';
        $body = $what . ($note !== '' ? "\nКоментар адміністрації: $note" : '');
        try {
            Notices::add($owner, 'admin_edit', $head, $body, '/cabinet/vacancies/' . $id, 'vacancy', $id);
        } catch (\Throwable $e) {
            error_log('[YEWORK] notice failed: ' . $e->getMessage());
        }
        $base = rtrim((string) App::setting('site.url', ''), '/');
        $link = $base . url('/cabinet/vacancies/' . $id);
        Services::notifyUser($owner, "Адміністрація виправила вашу вакансію — $site",
            "$head.\n\n$body\n\nПереглянути вакансію: $link\n\nЯкщо щось не так, просто відредагуйте її в кабінеті.\n\n— $site",
            "$site: адміністрація виправила вашу вакансію «" . mb_strimwidth($title, 0, 40, '…') . '». ' . $what,
            'admin_edit');
    }
}

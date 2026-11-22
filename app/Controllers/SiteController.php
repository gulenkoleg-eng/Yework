<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Ads;
use App\Core\App;
use App\Core\Crypto;
use App\Core\HttpException;
use App\Core\Messaging;
use App\Core\Media;
use App\Core\Pages;
use App\Core\Request;
use App\Core\Response;
use App\Core\Resumes;
use App\Core\Stats;
use App\Core\ValidationException;

final class SiteController
{
    public const PAGE = 20;

    /** Населені пункти й категорії (з кількістю активних вакансій). */
    public static function reference(): array
    {
        $db = App::db();
        $settlements = $db->all('SELECT s.id, s.name, c.name AS community FROM {p}settlements s JOIN {p}communities c ON c.id = s.community_id ORDER BY s.name');
        $categories = $db->all("SELECT c.id, c.slug, c.name, c.icon, (SELECT COUNT(*) FROM {p}vacancies v WHERE v.category_id = c.id AND v.status = 'published' AND v.deleted_at IS NULL) AS cnt
            FROM {p}job_categories c WHERE c.is_active = 1 ORDER BY c.sort");
        return [$settlements, $categories];
    }

    private const LIST_SQL = "SELECT v.id, v.title, v.employment, v.salary_from, v.salary_to, v.salary_period, v.no_experience, v.published_at,
        e.name AS employer, e.legal_form, e.logo, e.is_verified, v.employer_id, s.name AS settlement, c.name AS category
        FROM {p}vacancies v JOIN {p}employers e ON e.id = v.employer_id JOIN {p}settlements s ON s.id = v.settlement_id JOIN {p}job_categories c ON c.id = v.category_id";

    public function home(Request $req): Response
    {
        [$settlements, $categories] = self::reference();
        $latest = App::db()->all(self::LIST_SQL . " WHERE v.status = 'published' AND v.deleted_at IS NULL ORDER BY v.published_at DESC LIMIT 6");
        $promos = Ads::forPlacement('home', Ads::slots());
        Ads::trackImpressions($req, $promos);
        return new Response(view('site/home', ['stats' => Stats::public(), 'settlements' => $settlements, 'categories' => $categories, 'latest' => $latest, 'promos' => $promos]));
    }

    public function jobs(Request $req): Response
    {
        $db = App::db();
        [$settlements, $categories] = self::reference();
        $f = [
            'q' => mb_substr(trim((string) ($req->query['q'] ?? '')), 0, 100),
            'settlement' => (int) ($req->query['settlement'] ?? 0),
            'category' => preg_replace('/[^a-z_]/', '', (string) ($req->query['category'] ?? '')),
            'employment' => array_key_exists((string) ($req->query['employment'] ?? ''), EMPLOYMENT_TYPES) ? (string) $req->query['employment'] : '',
            'salary' => max(0, (int) ($req->query['salary'] ?? 0)),
            'noexp' => ($req->query['noexp'] ?? '') === '1',
            'sort' => ($req->query['sort'] ?? '') === 'salary' ? 'salary' : 'new',
            'employer' => max(0, (int) ($req->query['employer'] ?? 0)),
        ];
        $where = ["v.status = 'published'", 'v.deleted_at IS NULL'];
        $p = [];
        if ($f['q'] !== '') { $where[] = '(v.title LIKE ? OR v.description LIKE ? OR e.name LIKE ?)'; $like = '%' . addcslashes($f['q'], '%_\\') . '%'; array_push($p, $like, $like, $like); }
        if ($f['settlement']) { $where[] = 'v.settlement_id = ?'; $p[] = $f['settlement']; }
        if ($f['category'] !== '') { $where[] = 'c.slug = ?'; $p[] = $f['category']; }
        if ($f['employment'] !== '') { $where[] = 'v.employment = ?'; $p[] = $f['employment']; }
        if ($f['salary']) { $where[] = '(COALESCE(v.salary_to, v.salary_from) >= ?)'; $p[] = $f['salary']; }
        if ($f['noexp']) $where[] = 'v.no_experience = 1';
        if ($f['employer']) { $where[] = 'v.employer_id = ?'; $p[] = $f['employer']; }
        $w = ' WHERE ' . implode(' AND ', $where);
        $total = (int) $db->value('SELECT COUNT(*) FROM {p}vacancies v JOIN {p}employers e ON e.id = v.employer_id JOIN {p}job_categories c ON c.id = v.category_id' . $w, $p);
        $page = max(1, min((int) ($req->query['page'] ?? 1), max(1, (int) ceil($total / self::PAGE))));
        // Поки в роботодавця діє банер, його вакансії стоять першими (у звичайному сортуванні «Нові»).
        $promoted = Ads::promotedEmployerIds();
        $first = $promoted && $f['sort'] === 'new' ? 'v.employer_id IN (' . implode(',', $promoted) . ') DESC, ' : '';
        $order = $f['sort'] === 'salary' ? 'COALESCE(v.salary_to, v.salary_from, 0) DESC, v.published_at DESC' : $first . 'v.published_at DESC';
        $items = $db->all(self::LIST_SQL . $w . " ORDER BY $order LIMIT " . self::PAGE . ' OFFSET ' . (($page - 1) * self::PAGE), $p);
        foreach ($items as &$it) $it['promoted'] = $first !== '' && in_array((int) $it['employer_id'], $promoted, true);
        unset($it);

        // Сторінка вакансій одного роботодавця (сюди веде банер): показуємо, кого він шукає.
        $employer = $f['employer'] ? $db->one("SELECT id, name, legal_form, logo, about, is_verified FROM {p}employers WHERE id = ? AND status = 'active' AND deleted_at IS NULL", [$f['employer']]) : null;
        $employerPromo = $employer ? Ads::liveFor((int) $employer['id']) : null;
        $promos = [];
        if (!$employer && $page === 1) {
            $promos = Ads::forPlacement('jobs', 1);
            Ads::trackImpressions($req, $promos);
        }
        return new Response(view('site/jobs', ['f' => $f, 'items' => $items, 'total' => $total, 'page' => $page, 'settlements' => $settlements, 'categories' => $categories,
            'employer' => $employer, 'employerPromo' => $employerPromo, 'promos' => $promos, 'title' => $employer ? 'Вакансії: ' . employer_name($employer['legal_form'], $employer['name']) : 'Вакансії']));
    }

    public function vacancy(Request $req): Response
    {
        $id = (int) $req->params['id'];
        $db = App::db();
        $v = $db->one("SELECT v.*, e.name AS employer, e.legal_form, e.logo, e.about AS employer_about, e.is_verified, e.kind AS employer_kind, e.website, e.owner_id,
            s.name AS settlement, c.name AS category, c.slug AS category_slug
            FROM {p}vacancies v JOIN {p}employers e ON e.id = v.employer_id JOIN {p}settlements s ON s.id = v.settlement_id JOIN {p}job_categories c ON c.id = v.category_id
            WHERE v.id = ? AND v.deleted_at IS NULL", [$id]);
        $me = App::auth()->user();
        $isOwner = $v && $me && (int) $v['owner_id'] === (int) $me['id'];
        $canModerate = App::auth()->can('jobs.listing:moderate');
        if (!$v || ($v['status'] !== 'published' && !$isOwner && !$canModerate)) throw new HttpException(404, 'Вакансію не знайдено або її знято з публікації.');
        if ($v['status'] === 'published' && !$isOwner) $db->run('UPDATE {p}vacancies SET views = views + 1 WHERE id = ?', [$id]);
        $applied = $me ? (bool) $db->value('SELECT 1 FROM {p}applications WHERE vacancy_id = ? AND user_id = ?', [$id, $me['id']]) : false;
        $myResume = $me && !$isOwner ? Resumes::ofUser((int) $me['id']) : null;
        $similar = $db->all(self::LIST_SQL . " WHERE v.status = 'published' AND v.deleted_at IS NULL AND v.id <> ? AND v.category_id = ? ORDER BY v.published_at DESC LIMIT 3", [$id, $v['category_id']]);
        return new Response(view('site/vacancy', ['v' => $v, 'isOwner' => $isOwner, 'applied' => $applied, 'myResume' => $myResume, 'similar' => $similar, 'title' => $v['title'], 'description' => mb_substr(preg_replace('/\s+/', ' ', $v['description']), 0, 160)]));
    }

    public function apply(Request $req): Response
    {
        $id = (int) $req->params['id'];
        $db = App::db();
        $me = App::auth()->user();
        $v = $db->one("SELECT v.id, v.title, e.owner_id, e.name AS employer FROM {p}vacancies v JOIN {p}employers e ON e.id = v.employer_id WHERE v.id = ? AND v.status = 'published' AND v.deleted_at IS NULL", [$id]);
        if (!$v) throw new HttpException(404, 'Вакансію не знайдено.');
        if ((int) $v['owner_id'] === (int) $me['id']) throw new ValidationException([], 'Це ваша вакансія.');
        $msg = $req->str('message', 2000);
        // Резюме додається, лише якщо його вже схвалив модератор.
        $resume = !empty($req->post['with_resume']) ? Resumes::ofUser((int) $me['id']) : null;
        $resumeId = $resume && $resume['status'] === 'published' ? (int) $resume['id'] : null;
        $new = $db->run('INSERT IGNORE INTO {p}applications (vacancy_id, user_id, message, resume_id) VALUES (?, ?, ?, ?)', [$id, $me['id'], $msg, $resumeId])->rowCount() > 0;
        if ($new) self::notifyEmployer($v, $me, $msg, $resumeId);
        flash('ok', $resumeId ? 'Відгук із резюме надіслано. Роботодавець отримає ваше резюме у PDF і контакти.' : 'Відгук надіслано. Роботодавець побачить ваше ім’я та контакти й зв’яжеться з вами.');
        return Response::redirect(url('/jobs/' . $id));
    }

    /** Лист роботодавцю про новий відгук; якщо відгук із резюме — з PDF у вкладенні. Помилка пошти не заважає відгуку. */
    private static function notifyEmployer(array $v, array $me, string $msg, ?int $resumeId): void
    {
        try {
            $owner = App::db()->one('SELECT email_enc FROM {p}users WHERE id = ? AND deleted_at IS NULL', [$v['owner_id']]);
            $to = Crypto::decrypt($owner['email_enc'] ?? null);
            if (!$to) return;
            $site = (string) App::setting('site.name', 'YEWORK');
            $base = rtrim((string) App::setting('site.url', ''), '/');
            $r = $resumeId ? Resumes::find($resumeId) : null;
            $name = $r ? $r['name'] : $me['display_name'];
            $phone = Crypto::decrypt($me['phone_enc']);
            $email = Crypto::decrypt($me['email_enc']);
            $text = "Новий відгук на вакансію «{$v['title']}».\n\nКандидат: $name" . ($r ? "\nБажана посада: {$r['title']}" : '')
                . ($phone ? "\nТелефон: " . phone_pretty($phone) : '') . ($email ? "\nEmail: $email" : '')
                . (trim($msg) !== '' ? "\n\nПовідомлення:\n$msg" : '')
                . ($r ? "\n\nРезюме кандидата — у вкладенні (PDF)." : '')
                . "\n\nУсі відгуки: " . $base . url('/cabinet/vacancies/' . $v['id']) . "\n\n— $site";
            $files = $r ? [['name' => Resumes::fileName($r), 'mime' => 'application/pdf', 'data' => Resumes::pdf($r, true, (bool) $r['profile']['show_photo'])]] : [];
            Messaging::send('email', $to, $text, $r ? 'application_resume' : 'application', "Новий відгук: {$v['title']}", $files);
        } catch (\Throwable $e) {
            error_log('[YEWORK] application notify failed: ' . $e->getMessage());
        }
    }

    /** Логотипи та банери з storage/uploads. */
    public function media(Request $req): Response
    {
        return Media::serve((string) $req->params['kind'], (string) $req->params['file']);
    }

    /** Перехід з банера: рахуємо клік і ведемо на вакансії роботодавця. */
    public function promoClick(Request $req): Response
    {
        $t = Ads::today();
        $b = App::db()->one('SELECT b.id, b.employer_id, e.owner_id FROM {p}banners b JOIN {p}employers e ON e.id = b.employer_id WHERE b.id = ? AND ' . Ads::LIVE_SQL, [(int) $req->params['id'], $t, $t]);
        if (!$b) return Response::redirect(url('/jobs'));
        Ads::trackClick($req, $b);
        return Response::redirect(url('/jobs', ['employer' => $b['employer_id']]), 302);
    }

    public function about(Request $req): Response { return $this->page('about'); }
    public function rules(Request $req): Response { return $this->page('rules'); }
    public function contacts(Request $req): Response { return $this->page('contacts'); }

    /** Статична сторінка, текст якої редагується в адмінці (розділ «Сторінки»). */
    private function page(string $slug): Response
    {
        $p = Pages::get($slug);
        if (!$p['visible'] && !App::auth()->can('content.page:update')) throw new HttpException(404, 'Такої сторінки немає. Можливо, її видалили або адреса введена з помилкою.');
        $plain = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(Pages::toHtml($p['body'])), ENT_QUOTES, 'UTF-8')));
        return new Response(view('site/page', ['title' => $p['title'], 'page' => $p, 'description' => mb_substr($plain, 0, 160)]));
    }
}

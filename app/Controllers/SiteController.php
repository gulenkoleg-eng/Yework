<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\HttpException;
use App\Core\Pages;
use App\Core\Request;
use App\Core\Response;
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
        e.name AS employer, e.is_verified, s.name AS settlement, c.name AS category
        FROM {p}vacancies v JOIN {p}employers e ON e.id = v.employer_id JOIN {p}settlements s ON s.id = v.settlement_id JOIN {p}job_categories c ON c.id = v.category_id";

    public function home(Request $req): Response
    {
        [$settlements, $categories] = self::reference();
        $latest = App::db()->all(self::LIST_SQL . " WHERE v.status = 'published' AND v.deleted_at IS NULL ORDER BY v.published_at DESC LIMIT 6");
        return new Response(view('site/home', ['stats' => Stats::public(), 'settlements' => $settlements, 'categories' => $categories, 'latest' => $latest]));
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
        ];
        $where = ["v.status = 'published'", 'v.deleted_at IS NULL'];
        $p = [];
        if ($f['q'] !== '') { $where[] = '(v.title LIKE ? OR v.description LIKE ? OR e.name LIKE ?)'; $like = '%' . addcslashes($f['q'], '%_\\') . '%'; array_push($p, $like, $like, $like); }
        if ($f['settlement']) { $where[] = 'v.settlement_id = ?'; $p[] = $f['settlement']; }
        if ($f['category'] !== '') { $where[] = 'c.slug = ?'; $p[] = $f['category']; }
        if ($f['employment'] !== '') { $where[] = 'v.employment = ?'; $p[] = $f['employment']; }
        if ($f['salary']) { $where[] = '(COALESCE(v.salary_to, v.salary_from) >= ?)'; $p[] = $f['salary']; }
        if ($f['noexp']) $where[] = 'v.no_experience = 1';
        $w = ' WHERE ' . implode(' AND ', $where);
        $total = (int) $db->value('SELECT COUNT(*) FROM {p}vacancies v JOIN {p}employers e ON e.id = v.employer_id JOIN {p}job_categories c ON c.id = v.category_id' . $w, $p);
        $page = max(1, min((int) ($req->query['page'] ?? 1), max(1, (int) ceil($total / self::PAGE))));
        $order = $f['sort'] === 'salary' ? 'COALESCE(v.salary_to, v.salary_from, 0) DESC, v.published_at DESC' : 'v.published_at DESC';
        $items = $db->all(self::LIST_SQL . $w . " ORDER BY $order LIMIT " . self::PAGE . ' OFFSET ' . (($page - 1) * self::PAGE), $p);
        return new Response(view('site/jobs', ['f' => $f, 'items' => $items, 'total' => $total, 'page' => $page, 'settlements' => $settlements, 'categories' => $categories, 'title' => 'Вакансії']));
    }

    public function vacancy(Request $req): Response
    {
        $id = (int) $req->params['id'];
        $db = App::db();
        $v = $db->one("SELECT v.*, e.name AS employer, e.about AS employer_about, e.is_verified, e.kind AS employer_kind, e.website, e.owner_id,
            s.name AS settlement, c.name AS category, c.slug AS category_slug
            FROM {p}vacancies v JOIN {p}employers e ON e.id = v.employer_id JOIN {p}settlements s ON s.id = v.settlement_id JOIN {p}job_categories c ON c.id = v.category_id
            WHERE v.id = ? AND v.deleted_at IS NULL", [$id]);
        $me = App::auth()->user();
        $isOwner = $v && $me && (int) $v['owner_id'] === (int) $me['id'];
        $canModerate = App::auth()->can('jobs.listing:moderate');
        if (!$v || ($v['status'] !== 'published' && !$isOwner && !$canModerate)) throw new HttpException(404, 'Вакансію не знайдено або її знято з публікації.');
        if ($v['status'] === 'published' && !$isOwner) $db->run('UPDATE {p}vacancies SET views = views + 1 WHERE id = ?', [$id]);
        $applied = $me ? (bool) $db->value('SELECT 1 FROM {p}applications WHERE vacancy_id = ? AND user_id = ?', [$id, $me['id']]) : false;
        $similar = $db->all(self::LIST_SQL . " WHERE v.status = 'published' AND v.deleted_at IS NULL AND v.id <> ? AND v.category_id = ? ORDER BY v.published_at DESC LIMIT 3", [$id, $v['category_id']]);
        return new Response(view('site/vacancy', ['v' => $v, 'isOwner' => $isOwner, 'applied' => $applied, 'similar' => $similar, 'title' => $v['title'], 'description' => mb_substr(preg_replace('/\s+/', ' ', $v['description']), 0, 160)]));
    }

    public function apply(Request $req): Response
    {
        $id = (int) $req->params['id'];
        $db = App::db();
        $me = App::auth()->user();
        $v = $db->one("SELECT v.id, e.owner_id FROM {p}vacancies v JOIN {p}employers e ON e.id = v.employer_id WHERE v.id = ? AND v.status = 'published' AND v.deleted_at IS NULL", [$id]);
        if (!$v) throw new HttpException(404, 'Вакансію не знайдено.');
        if ((int) $v['owner_id'] === (int) $me['id']) throw new ValidationException([], 'Це ваша вакансія.');
        $msg = $req->str('message', 2000);
        $db->run('INSERT IGNORE INTO {p}applications (vacancy_id, user_id, message) VALUES (?, ?, ?)', [$id, $me['id'], $msg]);
        flash('ok', 'Відгук надіслано. Роботодавець побачить ваше ім’я та контакти й зв’яжеться з вами.');
        return Response::redirect(url('/jobs/' . $id));
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

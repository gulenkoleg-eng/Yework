<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Монетизація (перемикач і ціни) та банерна реклама роботодавців.
 * Поки монетизацію вимкнено, усе безкоштовне: банер замовляється з ціною 0 і після модерації одразу показується.
 */
final class Ads
{
    public const PLACEMENTS = ['home' => 'Головна сторінка', 'jobs' => 'Сторінка вакансій', 'both' => 'Головна і вакансії', 'side' => 'Збоку на головній'];
    /** Короткий опис місця для форми замовлення. */
    public const PLACEMENT_HINTS = [
        'home' => 'Великий банер по центру, у блоці «Шукають працівників»',
        'jobs' => 'Широкий банер над списком вакансій',
        'both' => 'По центру на головній і над списком вакансій',
        'side' => 'Менший банер у колонці праворуч. На телефоні — нижче на сторінці',
    ];
    /** Окремі місця, які мають свій ліміт одночасних банерів. 'both' займає home і jobs. */
    public const PLACES = ['home' => 'По центру на головній', 'jobs' => 'Над списком вакансій', 'side' => 'Збоку на головній'];
    public const MAX_DAYS = 90;

    /** Шаблони банерів (0.5.12): роботодавець пише лише текст, вигляд дає шаблон. Стилі — assets/css/banners.css. */
    public const TEMPLATES = [
        'classic' => ['Класика', 'Картка в кольорах сайту з жовтою смугою'],
        'sun' => ['Сонячний', 'Увесь банер у фірмовому жовтому'],
        'night' => ['Нічний', 'Темний, із жовтим сяйвом'],
        'wave' => ['Хвиля', 'Яскравий градієнт кольору сайту'],
        'urgent' => ['Терміново', 'Червона стрічка для швидкого набору'],
        'photo' => ['Фото', 'Ваша картинка на весь банер'],
    ];
    /** Готові позначки й тексти кнопки (можна написати й свої). */
    public const BADGES = ['Терміново', 'Новий набір', 'Без досвіду', 'Соцпакет', 'Навчаємо', 'Сезонна робота', 'Вахта'];
    public const CTAS = ['Хочу до вас', 'Відгукнутися', 'Дізнатися більше', 'Приєднатися до команди', 'Дивитися вакансії'];
    public const MAX_PERKS = 3;

    /**
     * Ціни за замовчуванням (грн). Розраховані на невеликі громади: тиждень банера на головній — як оголошення в місцевій газеті,
     * місяць — дешевше за одну платну вакансію на великих сайтах.
     */
    public const DEFAULT_PRICES = [
        'banner_home_day' => 30,    // банер на головній, за день
        'banner_jobs_day' => 20,    // банер над списком вакансій, за день
        'banner_both_day' => 40,    // обидва місця, за день
        'banner_side_day' => 15,    // менший банер збоку на головній, за день (0.5.19)
        'discount_week' => 10,      // % знижки від 7 днів
        'discount_month' => 25,     // % знижки від 30 днів
        'vacancy_bump' => 20,       // підняти вакансію нагору списку (разово)
        'vacancy_highlight' => 40,  // виділити вакансію кольором на 7 днів
        'vacancy_urgent' => 30,     // позначка «Терміново» на 7 днів
        'master_more_works' => 50,  // майстер: +10 об'єктів у портфоліо, за місяць
        'master_top' => 30,         // майстер: підняти нагору каталогу на 7 днів
    ];

    public const PRICE_LABELS = [
        'banner_home_day' => 'Банер на головній, грн за день',
        'banner_jobs_day' => 'Банер на сторінці вакансій, грн за день',
        'banner_both_day' => 'Банер в обох місцях, грн за день',
        'banner_side_day' => 'Банер збоку на головній, грн за день',
        'discount_week' => 'Знижка від 7 днів, %',
        'discount_month' => 'Знижка від 30 днів, %',
        'vacancy_bump' => 'Підняти вакансію нагору, грн',
        'vacancy_highlight' => 'Виділити вакансію кольором на 7 днів, грн',
        'vacancy_urgent' => 'Позначка «Терміново» на 7 днів, грн',
        'master_more_works' => 'Майстер: +10 об’єктів у портфоліо, грн за місяць',
        'master_top' => 'Майстер: підняти нагору каталогу на 7 днів, грн',
    ];

    /**
     * Скільки банерів одночасно (0.5.19): по центру (на головній і над вакансіями) і збоку на головній.
     * Більше — замовлення на ці дні не приймається, а роботодавець бачить, коли місце звільниться.
     */
    public const DEFAULT_SLOTS = 1;
    public const DEFAULT_SIDE_SLOTS = 4;
    public const MAX_SIDE_SLOTS = 8;

    public static function monetizationOn(): bool
    {
        return (bool) App::setting('monetization.enabled', false);
    }

    public static function prices(): array
    {
        $saved = (array) App::setting('billing.prices', []);
        $out = [];
        foreach (self::DEFAULT_PRICES as $k => $v) $out[$k] = isset($saved[$k]) ? max(0, (int) $saved[$k]) : $v;
        return $out;
    }

    /** Банерів по центру одночасно (головна й сторінка вакансій). */
    public static function slots(): int
    {
        return max(1, min(10, (int) App::setting('ads.slots_main', self::DEFAULT_SLOTS)));
    }

    /** Банерів збоку на головній одночасно. */
    public static function sideSlots(): int
    {
        return max(1, min(self::MAX_SIDE_SLOTS, (int) App::setting('ads.slots_side', self::DEFAULT_SIDE_SLOTS)));
    }

    public static function limitFor(string $place): int
    {
        return $place === 'side' ? self::sideSlots() : self::slots();
    }

    /** Які окремі місця займає банер: 'both' — головну по центру і сторінку вакансій. */
    public static function placesOf(string $placement): array
    {
        return $placement === 'both' ? ['home', 'jobs'] : (isset(self::PLACES[$placement]) ? [$placement] : []);
    }

    /** Повна ціна банера (без урахування того, чи ввімкнена монетизація). */
    public static function bannerPrice(string $placement, int $days): int
    {
        $p = self::prices();
        $day = $p['banner_' . $placement . '_day'] ?? $p['banner_both_day'];
        $disc = $days >= 30 ? $p['discount_month'] : ($days >= 7 ? $p['discount_week'] : 0);
        return (int) round($day * $days * (100 - min(90, $disc)) / 100);
    }

    /** Налаштування шаблонів з адмінки: які вимкнені і який стоїть за замовчуванням. */
    public static function templateSettings(): array
    {
        $s = (array) App::setting('ads.templates', []);
        $off = array_values(array_intersect(array_keys(self::TEMPLATES), (array) ($s['off'] ?? [])));
        if (count($off) >= count(self::TEMPLATES)) $off = []; // хоч один шаблон має лишатися
        $on = array_values(array_diff(array_keys(self::TEMPLATES), $off));
        $def = in_array($s['default'] ?? '', $on, true) ? $s['default'] : $on[0];
        return ['off' => $off, 'on' => $on, 'default' => $def];
    }

    /** Шаблони, які роботодавець може обрати зараз: [key => [назва, опис]]. */
    public static function availableTemplates(): array
    {
        return array_intersect_key(self::TEMPLATES, array_flip(self::templateSettings()['on']));
    }

    /** Шаблон для показу. Уже замовлені банери показуються у своєму шаблоні, навіть якщо його згодом вимкнули. */
    public static function templateOf(array $b): string
    {
        $t = (string) ($b['template'] ?? 'classic');
        if (!isset(self::TEMPLATES[$t])) $t = 'classic';
        if ($t === 'photo' && empty($b['image'])) $t = 'night'; // без картинки фото-шаблон не має сенсу
        return $t;
    }

    /** Переваги-чипи банера. */
    public static function perks(array $b): array
    {
        $p = json_decode((string) ($b['perks'] ?? ''), true);
        return is_array($p) ? array_slice(array_values(array_filter(array_map('strval', $p), static fn ($x) => trim($x) !== '')), 0, self::MAX_PERKS) : [];
    }

    /** Текст кнопки: свій або «Дивитися N вакансій». */
    public static function ctaOf(array $b): string
    {
        $c = trim((string) ($b['cta'] ?? ''));
        if ($c !== '' && $c !== 'Дивитися вакансії') return $c;
        if (!empty($b['vacancy_id'])) return 'Дивитися вакансію';
        $n = (int) ($b['vacancies'] ?? 0);
        return $n > 0 ? 'Дивитися ' . plural($n, 'вакансію', 'вакансії', 'вакансій') : 'Дивитися вакансії';
    }

    /* ---------- куди веде кнопка (0.5.15) ---------- */

    /** Опубліковані вакансії роботодавців, на які може вести банер: [id, employer_id, title]. */
    public static function targetVacancies(array $employerIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $employerIds))));
        if (!$ids) return [];
        return App::db()->all("SELECT id, employer_id, title FROM {p}vacancies WHERE status = 'published' AND deleted_at IS NULL AND employer_id IN ("
            . implode(',', $ids) . ') ORDER BY published_at DESC, id DESC LIMIT 1000');
    }

    /**
     * Вибір «Куди веде кнопка» з форми: 'all' — усі вакансії роботодавця, число — одна його опублікована вакансія.
     * Повертає [vacancy_id|null, помилка|null]. Порожній вибір — помилка: роботодавець має сам вказати, куди веде банер.
     */
    public static function targetInput(Request $req, int $employerId): array
    {
        $t = $req->str('target', 20);
        if ($t === 'all') return [null, null];
        if ($t === '' || !ctype_digit($t)) return [null, 'Оберіть вакансію, на яку веде кнопка банера'];
        $ok = $employerId > 0 && App::db()->value("SELECT id FROM {p}vacancies WHERE id = ? AND employer_id = ? AND status = 'published' AND deleted_at IS NULL", [(int) $t, $employerId]);
        return $ok ? [(int) $t, null] : [null, 'Ця вакансія не опублікована або належить іншому роботодавцю. Оберіть іншу'];
    }

    /** Вакансія банера, якщо вона досі опублікована і належить тому ж роботодавцю. */
    public static function targetVacancy(array $b): ?array
    {
        if (empty($b['vacancy_id'])) return null;
        try {
            return App::db()->one("SELECT id, title FROM {p}vacancies WHERE id = ? AND employer_id = ? AND status = 'published' AND deleted_at IS NULL", [(int) $b['vacancy_id'], (int) $b['employer_id']]);
        } catch (\PDOException) {
            return null;
        }
    }

    /** Адреса, куди веде банер: його вакансія, а якщо її зняли чи вакансію не обрано — усі вакансії роботодавця. */
    public static function targetUrl(array $b): string
    {
        $v = self::targetVacancy($b);
        return $v ? url('/jobs/' . $v['id']) : url('/jobs', ['employer' => (int) $b['employer_id']]);
    }

    /* ---------- редагування й видалення (0.5.15) ---------- */

    /** Роботодавець може редагувати банер, поки той не завершився і не скасований. */
    public static function employerCanEdit(array $b): bool
    {
        return in_array($b['status'], ['pending', 'approved', 'rejected'], true) && $b['ends_on'] >= self::today();
    }

    /** Дати й місце показу вже не змінити: банер оплачено або він уже показується (адміністратор може завжди). */
    public static function datesLocked(array $b): bool
    {
        return !empty($b['paid_at']) || ($b['status'] === 'approved' && $b['starts_on'] <= self::today());
    }

    /** Роботодавець не видаляє оплачений банер: він потрібен для обліку оплат. */
    public static function employerCanDelete(array $b): bool
    {
        return !((int) $b['price'] > 0 && !empty($b['paid_at']));
    }

    /** Значення форми редагування з наявного банера. */
    public static function formValues(array $b): array
    {
        return ['title' => $b['title'], 'body' => $b['body'], 'badge' => (string) $b['badge'], 'cta' => (string) $b['cta'], 'perks' => self::perks($b),
            'template' => $b['template'] ?? 'classic', 'placement' => $b['placement'], 'starts_on' => $b['starts_on'], 'days' => (int) $b['days'],
            'target' => empty($b['vacancy_id']) ? 'all' : (string) (int) $b['vacancy_id']];
    }

    /** Нова картинка з форми редагування (або прибрати стару). Повертає ім’я файлу; стару картинку видаляє, якщо її замінили. */
    public static function editImage(Request $req, array $b): ?string
    {
        $new = Media::store('banner', 'image', 'e' . $b['employer_id']);
        if ($new) { Media::delete('banner', $b['image']); return $new; }
        if ($req->input('drop_image')) { Media::delete('banner', $b['image']); return null; }
        return $b['image'] ?: null;
    }

    /** Видаляє банер разом зі статистикою і картинкою. */
    public static function delete(array $b): void
    {
        $db = App::db();
        $db->run('DELETE FROM {p}banner_stats WHERE banner_id = ?', [$b['id']]);
        $db->run('DELETE FROM {p}banners WHERE id = ?', [$b['id']]);
        Media::delete('banner', $b['image'] ?? null);
    }

    /** Поля банера з форми (кабінет і адмінка). $allowed — шаблони, які можна обрати. */
    public static function bannerInput(Request $req, array $allowed): array
    {
        $d = [
            'title' => $req->str('title', 80), 'body' => $req->str('body', 300),
            'placement' => array_key_exists($req->str('placement', 8), self::PLACEMENTS) ? $req->str('placement', 8) : '',
            'starts_on' => $req->str('starts_on', 10), 'days' => (int) $req->input('days', 0),
            'template' => $req->str('template', 12), 'badge' => $req->str('badge', 24) ?: null, 'cta' => $req->str('cta', 32) ?: null,
        ];
        if (!in_array($d['template'], $allowed, true)) $d['template'] = in_array(self::templateSettings()['default'], $allowed, true) ? self::templateSettings()['default'] : $allowed[0];
        $perks = [];
        foreach ((array) ($req->post['perks'] ?? []) as $pk) {
            $pk = trim(mb_substr(preg_replace('/\s+/u', ' ', (string) $pk) ?? '', 0, 24));
            if ($pk !== '' && count($perks) < self::MAX_PERKS) $perks[] = $pk;
        }
        $d['perks'] = $perks ? json_encode($perks, JSON_UNESCAPED_UNICODE) : null;
        return $d;
    }

    /** Перевірка полів банера. Адміністратор може ставити банер на будь-яку дату в межах 90 днів. */
    public static function bannerErrors(array $d, bool $admin = false): array
    {
        $err = [];
        $today = self::today();
        if (mb_strlen($d['title']) < 3) $err['title'] = 'Напишіть заголовок, наприклад «Шукаємо водія категорії C»';
        if (mb_strlen($d['body']) < 10) $err['body'] = 'Коротко опишіть, кого шукаєте';
        if ($d['placement'] === '') $err['placement'] = 'Оберіть місце показу';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['starts_on']) || !strtotime($d['starts_on'])) $err['starts_on'] = 'Оберіть дату початку';
        elseif ($d['starts_on'] < $today) $err['starts_on'] = 'Дата початку не може бути в минулому';
        elseif ($d['starts_on'] > date('Y-m-d', strtotime($admin ? '+90 days' : '+60 days'))) $err['starts_on'] = 'Замовити можна не раніше ніж за ' . ($admin ? 90 : 60) . ' днів';
        if ($d['days'] < 1 || $d['days'] > self::MAX_DAYS) $err['days'] = 'Від 1 до ' . self::MAX_DAYS . ' днів';
        return $err;
    }

    public static function today(): string
    {
        return date('Y-m-d'); // часовий пояс сайту — Київ
    }

    /** Банер показується: схвалений, у межах дат і безкоштовний або оплачений. */
    public const LIVE_SQL = "b.status = 'approved' AND b.starts_on <= ? AND b.ends_on >= ? AND (b.price = 0 OR b.paid_at IS NOT NULL)";

    /** Банери для місця показу (home | jobs) у випадковому порядку. */
    public static function forPlacement(string $where, int $limit): array
    {
        $t = self::today();
        try {
            return App::db()->all('SELECT b.*, e.name AS employer, e.legal_form, e.logo, e.owner_id,
                (SELECT COUNT(*) FROM {p}vacancies v WHERE v.employer_id = b.employer_id AND v.status = \'published\' AND v.deleted_at IS NULL) AS vacancies
                FROM {p}banners b JOIN {p}employers e ON e.id = b.employer_id
                WHERE ' . self::LIVE_SQL . ' AND ' . ($where === 'side' ? 'b.placement = ?' : "b.placement IN (?, 'both')") . " AND e.status = 'active' AND e.deleted_at IS NULL
                ORDER BY RAND() LIMIT " . max(1, $limit), [$t, $t, $where]);
        } catch (\PDOException) {
            return []; // база ще не оновлена (немає таблиці) — сайт працює без банерів
        }
    }

    /** id роботодавців з активним банером — їхні вакансії стоять першими в пошуку. */
    public static function promotedEmployerIds(): array
    {
        static $ids = null;
        if ($ids !== null) return $ids;
        $t = self::today();
        try {
            $ids = array_map('intval', array_column(App::db()->all('SELECT DISTINCT b.employer_id FROM {p}banners b WHERE ' . self::LIVE_SQL, [$t, $t]), 'employer_id'));
        } catch (\PDOException) {
            $ids = [];
        }
        return $ids;
    }

    public static function liveFor(int $employerId): ?array
    {
        $t = self::today();
        try {
            return App::db()->one('SELECT b.* FROM {p}banners b WHERE b.employer_id = ? AND ' . self::LIVE_SQL . ' ORDER BY b.id DESC LIMIT 1', [$employerId, $t, $t]);
        } catch (\PDOException) {
            return null;
        }
    }

    private static function countable(Request $req, array $banner): bool
    {
        $ua = $req->userAgent();
        if ($ua === '' || preg_match('/bot|crawl|spider|slurp|preview|monitor|curl|wget|python|headless/i', $ua)) return false;
        $me = App::auth()->user();
        return !$me || (int) $me['id'] !== (int) $banner['owner_id']; // власник банера свої покази не накручує
    }

    /** Записує покази банерів, які щойно потрапили на сторінку. */
    public static function trackImpressions(Request $req, array $banners): void
    {
        $ids = [];
        foreach ($banners as $b) if (self::countable($req, $b)) $ids[] = (int) $b['id'];
        if (!$ids) return;
        $day = self::today();
        $sql = 'INSERT INTO {p}banner_stats (banner_id, day, impressions) VALUES ' . implode(',', array_fill(0, count($ids), '(?, ?, 1)')) . ' ON DUPLICATE KEY UPDATE impressions = impressions + 1';
        $p = [];
        foreach ($ids as $id) array_push($p, $id, $day);
        try { App::db()->run($sql, $p); } catch (\PDOException) {}
    }

    public static function trackClick(Request $req, array $banner): void
    {
        if (!self::countable($req, $banner)) return;
        try {
            App::db()->run('INSERT INTO {p}banner_stats (banner_id, day, clicks) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE clicks = clicks + 1', [$banner['id'], self::today()]);
        } catch (\PDOException) {}
    }

    /**
     * Заброньовані періоди кожного місця (0.5.19): банери на модерації й схвалені, які ще не завершилися.
     * Повертає [place => [[starts_on, ends_on], …]] за датою початку. Назви роботодавців не потрібні: роботодавець бачить лише, коли місце зайняте.
     */
    public static function bookings(?int $exceptId = null): array
    {
        $out = array_fill_keys(array_keys(self::PLACES), []);
        try {
            $rows = App::db()->all("SELECT id, placement, starts_on, ends_on FROM {p}banners WHERE status IN ('pending','approved') AND ends_on >= ?"
                . ($exceptId ? ' AND id <> ' . (int) $exceptId : '') . ' ORDER BY starts_on, ends_on', [self::today()]);
        } catch (\PDOException) {
            return $out;
        }
        foreach ($rows as $r) foreach (self::placesOf((string) $r['placement']) as $pl) $out[$pl][] = [$r['starts_on'], $r['ends_on']];
        return $out;
    }

    /** Скільки банерів стоїть у місці на день. */
    private static function busyOn(array $periods, string $day): int
    {
        $n = 0;
        foreach ($periods as [$s, $e]) if ($s <= $day && $e >= $day) $n++;
        return $n;
    }

    /**
     * Перевіряє, чи є вільні місця на всі дні періоду. Повертає перший зайнятий день або null.
     */
    public static function firstFullDay(string $placement, string $from, string $to, ?int $exceptId = null, ?array $bookings = null): ?string
    {
        $bookings ??= self::bookings($exceptId);
        for ($d = $from; $d <= $to; $d = date('Y-m-d', strtotime($d . ' +1 day'))) {
            foreach (self::placesOf($placement) as $pl) if (self::busyOn($bookings[$pl], $d) >= self::limitFor($pl)) return $d;
        }
        return null;
    }

    /** Найближча дата від $from, з якої банер на $days днів поміститься в обране місце (шукаємо на рік уперед). */
    public static function nextFreeStart(string $placement, int $days, ?string $from = null, ?int $exceptId = null, ?array $bookings = null): ?string
    {
        $bookings ??= self::bookings($exceptId);
        $d = max($from ?? self::today(), self::today());
        for ($i = 0; $i < 366; $i++) {
            $end = date('Y-m-d', strtotime($d . ' +' . (max(1, $days) - 1) . ' days'));
            $full = self::firstFullDay($placement, $d, $end, null, $bookings);
            if ($full === null) return $d;
            $d = date('Y-m-d', strtotime($full . ' +1 day'));
            if ($d > date('Y-m-d', strtotime('+366 days'))) break;
        }
        return null;
    }

    /**
     * Зайнятість місць для форми замовлення: ліміт, заброньовані періоди і з якого дня місце вільне.
     * [place => ['label', 'limit', 'periods' => [[from, to, live]], 'free_from' => дата|null, 'now' => скільки зараз]]
     */
    public static function occupancy(?int $exceptId = null): array
    {
        $b = self::bookings($exceptId);
        $t = self::today();
        $out = [];
        foreach (self::PLACES as $pl => $label) {
            $out[$pl] = ['label' => $label, 'limit' => self::limitFor($pl), 'now' => self::busyOn($b[$pl], $t),
                'periods' => array_map(static fn ($p) => [$p[0], $p[1], $p[0] <= $t], $b[$pl]),
                'free_from' => self::nextFreeStart($pl, 1, $t, null, $b)];
        }
        return $out;
    }

    /** Повідомлення, коли обрані дні зайняті: з якого дня можна поставити банер. */
    public static function fullMessage(string $placement, string $full, int $days, ?int $exceptId = null): string
    {
        $next = self::nextFreeStart($placement, $days, null, $exceptId);
        $where = mb_strtolower(self::PLACEMENTS[$placement] ?? 'це місце');
        return 'На ' . date('d.m', strtotime($full)) . ' місце «' . $where . '» вже зайняте.'
            . ($next ? ' Найближче вільне на ' . plural($days, 'день', 'дні', 'днів') . ': з ' . date('d.m.Y', strtotime($next)) . '. Оберіть цю дату або інше місце показу.' : ' Оберіть інші дати або інше місце показу.');
    }

    /** Статистика банера по днях: [day => [impressions, clicks]] за весь період показу. */
    public static function stats(array $banner): array
    {
        $rows = App::db()->all('SELECT day, impressions, clicks FROM {p}banner_stats WHERE banner_id = ? ORDER BY day', [$banner['id']]);
        $by = [];
        foreach ($rows as $r) $by[$r['day']] = ['impressions' => (int) $r['impressions'], 'clicks' => (int) $r['clicks']];
        $out = [];
        $end = min($banner['ends_on'], self::today());
        for ($d = $banner['starts_on']; $d <= $end; $d = date('Y-m-d', strtotime($d . ' +1 day'))) $out[$d] = $by[$d] ?? ['impressions' => 0, 'clicks' => 0];
        return $out;
    }

    /** Статус для людей: «На модерації», «Показується», «Заплановано з 01.12», «Завершено»… */
    public static function statusLabel(array $b): array
    {
        $t = self::today();
        return match (true) {
            $b['status'] === 'pending' => ['На модерації', 'warn'],
            $b['status'] === 'rejected' => ['Відхилено', 'bad'],
            $b['status'] === 'cancelled' => ['Скасовано', 'muted'],
            (int) $b['price'] > 0 && !$b['paid_at'] && $b['ends_on'] >= $t => ['Чекає оплати', 'warn'],
            $b['ends_on'] < $t => ['Завершено', 'muted'],
            $b['starts_on'] > $t => ['Заплановано з ' . date('d.m', strtotime($b['starts_on'])), 'muted'],
            default => ['Показується', 'ok'],
        };
    }
}

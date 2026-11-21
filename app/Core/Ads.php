<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Монетизація (перемикач і ціни) та банерна реклама роботодавців.
 * Поки монетизацію вимкнено, усе безкоштовне: банер замовляється з ціною 0 і після модерації одразу показується.
 */
final class Ads
{
    public const PLACEMENTS = ['home' => 'Головна сторінка', 'jobs' => 'Сторінка вакансій', 'both' => 'Головна і вакансії'];
    public const MAX_DAYS = 90;

    /**
     * Ціни за замовчуванням (грн). Розраховані на невеликі громади: тиждень банера на головній — як оголошення в місцевій газеті,
     * місяць — дешевше за одну платну вакансію на великих сайтах.
     */
    public const DEFAULT_PRICES = [
        'banner_home_day' => 30,    // банер на головній, за день
        'banner_jobs_day' => 20,    // банер над списком вакансій, за день
        'banner_both_day' => 40,    // обидва місця, за день
        'discount_week' => 10,      // % знижки від 7 днів
        'discount_month' => 25,     // % знижки від 30 днів
        'vacancy_bump' => 20,       // підняти вакансію нагору списку (разово)
        'vacancy_highlight' => 40,  // виділити вакансію кольором на 7 днів
        'vacancy_urgent' => 30,     // позначка «Терміново» на 7 днів
    ];

    public const PRICE_LABELS = [
        'banner_home_day' => 'Банер на головній, грн за день',
        'banner_jobs_day' => 'Банер на сторінці вакансій, грн за день',
        'banner_both_day' => 'Банер в обох місцях, грн за день',
        'discount_week' => 'Знижка від 7 днів, %',
        'discount_month' => 'Знижка від 30 днів, %',
        'vacancy_bump' => 'Підняти вакансію нагору, грн',
        'vacancy_highlight' => 'Виділити вакансію кольором на 7 днів, грн',
        'vacancy_urgent' => 'Позначка «Терміново» на 7 днів, грн',
    ];

    /** Скільки банерів одночасно в одному місці (більше — замовлення на ці дні не приймається). */
    public const DEFAULT_SLOTS = 3;

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

    public static function slots(): int
    {
        return max(1, min(10, (int) App::setting('ads.slots', self::DEFAULT_SLOTS)));
    }

    /** Повна ціна банера (без урахування того, чи ввімкнена монетизація). */
    public static function bannerPrice(string $placement, int $days): int
    {
        $p = self::prices();
        $day = $p['banner_' . $placement . '_day'] ?? $p['banner_both_day'];
        $disc = $days >= 30 ? $p['discount_month'] : ($days >= 7 ? $p['discount_week'] : 0);
        return (int) round($day * $days * (100 - min(90, $disc)) / 100);
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
            return App::db()->all('SELECT b.id, b.title, b.body, b.image, b.employer_id, e.name AS employer, e.legal_form, e.logo, e.owner_id,
                (SELECT COUNT(*) FROM {p}vacancies v WHERE v.employer_id = b.employer_id AND v.status = \'published\' AND v.deleted_at IS NULL) AS vacancies
                FROM {p}banners b JOIN {p}employers e ON e.id = b.employer_id
                WHERE ' . self::LIVE_SQL . " AND b.placement IN (?, 'both') AND e.status = 'active' AND e.deleted_at IS NULL
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
     * Перевіряє, чи є вільні місця на всі дні періоду. Повертає перший зайнятий день або null.
     */
    public static function firstFullDay(string $placement, string $from, string $to, ?int $exceptId = null): ?string
    {
        $places = $placement === 'both' ? ['home', 'jobs'] : [$placement];
        $rows = App::db()->all("SELECT id, placement, starts_on, ends_on FROM {p}banners WHERE status IN ('pending','approved') AND starts_on <= ? AND ends_on >= ?" . ($exceptId ? ' AND id <> ' . $exceptId : ''), [$to, $from]);
        $slots = self::slots();
        for ($d = $from; $d <= $to; $d = date('Y-m-d', strtotime($d . ' +1 day'))) {
            foreach ($places as $pl) {
                $n = 0;
                foreach ($rows as $r) if (($r['placement'] === $pl || $r['placement'] === 'both') && $r['starts_on'] <= $d && $r['ends_on'] >= $d) $n++;
                if ($n >= $slots) return $d;
            }
        }
        return null;
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

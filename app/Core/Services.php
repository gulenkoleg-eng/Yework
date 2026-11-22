<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Розділ «Послуги» (0.5.0): профілі майстрів, прайс, портфоліо, замовлення через сайт і відгуки.
 *
 * Статуси профілю майстра:
 *  - draft     — чернетка, бачить лише майстер;
 *  - pending   — на перевірці модератором;
 *  - published — показується в каталозі до дати show_to (термін 1, 2, 3 місяці або рік);
 *  - rejected  — відхилено з причиною;
 *  - archived  — в архіві: закінчився термін або майстер зняв сам. Кнопка «Поновити» повертає без повторної перевірки.
 * Після першого схвалення зміни видно одразу, а профіль позначається needs_review для модератора.
 *
 * Зірки й відгуки: лише від клієнта, чиє замовлення через сайт позначено «Виконано» (майстром або самим клієнтом після того,
 * як майстер прийняв замовлення). Один відгук від клієнта на майстра; після нового виконаного замовлення його можна оновити.
 */
final class Services
{
    public const STATUS = [
        'draft' => ['Чернетка', 'muted'], 'pending' => ['На перевірці', 'warn'], 'published' => ['Опубліковано', 'ok'],
        'rejected' => ['Відхилено', 'bad'], 'archived' => ['В архіві', 'muted'],
    ];
    public const ARCHIVE_REASONS = ['expired' => 'Закінчився термін показу', 'manual' => 'Знято вручну'];

    public const PERIODS = [1 => '1 місяць', 2 => '2 місяці', 3 => '3 місяці', 12 => '1 рік'];

    public const LEGAL = ['person' => 'Приватна особа', 'ФОП' => 'ФОП', 'ТОВ' => 'ТОВ', 'ПП' => 'ПП'];
    public const PAYMENTS = ['cash' => 'Готівка', 'card' => 'Переказ на картку', 'invoice' => 'Безготівковий розрахунок (рахунок)'];

    public const KINDS = ['service' => 'Послуга', 'goods' => 'Обладнання / матеріали'];
    public const PRICE_TYPES = ['fixed' => 'Ціна', 'range' => 'Від – до', 'from' => 'Від', 'deal' => 'Договірна'];
    public const UNITS = [
        'job' => 'за роботу', 'pcs' => 'за шт.', 'm' => 'за м', 'm2' => 'за м²', 'm3' => 'за м³', 'point' => 'за точку',
        'hour' => 'за год', 'day' => 'за день', 'visit' => 'за виїзд', 'km' => 'за км', 'lesson' => 'за заняття',
    ];
    public const MAX_PRICES = 40;

    public const ORDER_STATUS = [
        'new' => ['Нове', 'warn'], 'accepted' => ['Прийнято', 'ok'], 'done' => ['Виконано', 'ok'],
        'declined' => ['Майстер відмовився', 'muted'], 'cancelled' => ['Скасовано', 'muted'],
    ];

    public const DEFAULT_LIMITS = ['works' => 3, 'photos' => 5];

    /** Скільки об'єктів у портфоліо і фото в одному об'єкті дається безкоштовно (адмінка → Монетизація). */
    public static function limits(): array
    {
        $s = (array) App::setting('services.limits', []);
        return [
            'works' => max(0, min(50, (int) ($s['works'] ?? self::DEFAULT_LIMITS['works']))),
            'photos' => max(1, min(20, (int) ($s['photos'] ?? self::DEFAULT_LIMITS['photos']))),
        ];
    }

    public static function premoderation(): bool
    {
        return (bool) (((array) App::setting('moderation.premoderation', []))['services'] ?? true);
    }

    /** Активні категорії з кількістю опублікованих майстрів. */
    public static function categories(bool $withCounts = true): array
    {
        try {
            return App::db()->all('SELECT c.id, c.slug, c.name' . ($withCounts ? ", (SELECT COUNT(*) FROM {p}masters m WHERE (m.category_id = c.id OR m.category2_id = c.id) AND " . self::LIVE_SQL . ') AS cnt' : '') . '
                FROM {p}service_categories c WHERE c.is_active = 1 ORDER BY c.sort, c.name');
        } catch (\PDOException) {
            return []; // міграція 008 ще не застосована
        }
    }

    /** Майстер показується в каталозі. */
    public const LIVE_SQL = "m.status = 'published' AND m.is_hidden = 0 AND m.deleted_at IS NULL";

    public static function liveCount(): int
    {
        try {
            return (int) App::db()->value('SELECT COUNT(*) FROM {p}masters m JOIN {p}users u ON u.id = m.user_id WHERE ' . self::LIVE_SQL . " AND u.status = 'active'");
        } catch (\PDOException) {
            return 0;
        }
    }

    public static function ofUser(int $uid): ?array
    {
        return App::db()->one('SELECT * FROM {p}masters WHERE user_id = ? AND deleted_at IS NULL', [$uid]);
    }

    public static function find(int $id): ?array
    {
        return App::db()->one('SELECT m.*, c.name AS category, c.slug AS category_slug, c2.name AS category2, s.name AS settlement, u.display_name, u.status AS user_status
            FROM {p}masters m JOIN {p}users u ON u.id = m.user_id JOIN {p}service_categories c ON c.id = m.category_id
            LEFT JOIN {p}service_categories c2 ON c2.id = m.category2_id LEFT JOIN {p}settlements s ON s.id = m.settlement_id
            WHERE m.id = ? AND m.deleted_at IS NULL AND u.deleted_at IS NULL', [$id]);
    }

    public static function isPublic(array $m): bool
    {
        return $m['status'] === 'published' && !(int) $m['is_hidden'] && ($m['user_status'] ?? 'active') === 'active';
    }

    public static function prices(int $masterId): array
    {
        return App::db()->all('SELECT * FROM {p}master_prices WHERE master_id = ? ORDER BY sort, id', [$masterId]);
    }

    /** Об'єкти портфоліо разом із фото. */
    public static function works(int $masterId): array
    {
        $db = App::db();
        $works = $db->all('SELECT * FROM {p}master_works WHERE master_id = ? ORDER BY sort, id DESC', [$masterId]);
        if (!$works) return [];
        $ids = array_map('intval', array_column($works, 'id'));
        $photos = $db->all('SELECT * FROM {p}master_work_photos WHERE work_id IN (' . implode(',', $ids) . ') ORDER BY sort, id');
        $by = [];
        foreach ($photos as $p) $by[(int) $p['work_id']][] = $p;
        foreach ($works as &$w) $w['photos'] = $by[(int) $w['id']] ?? [];
        unset($w);
        return $works;
    }

    public static function work(int $masterId, int $workId): ?array
    {
        $w = App::db()->one('SELECT * FROM {p}master_works WHERE id = ? AND master_id = ?', [$workId, $masterId]);
        if ($w) $w['photos'] = App::db()->all('SELECT * FROM {p}master_work_photos WHERE work_id = ? ORDER BY sort, id', [$workId]);
        return $w;
    }

    /** «7 000 – 8 000 грн за роботу», «від 450 грн за м», «Договірна». */
    public static function priceText(array $p): string
    {
        $unit = self::UNITS[$p['unit']] ?? '';
        $from = $p['price_from'] !== null ? (int) $p['price_from'] : null;
        $to = $p['price_to'] !== null ? (int) $p['price_to'] : null;
        $t = match ($p['price_type']) {
            'range' => $from !== null && $to !== null ? money($from) . ' – ' . money($to) . ' грн' : null,
            'from' => $from !== null ? 'від ' . money($from) . ' грн' : null,
            'fixed' => $from !== null ? money($from) . ' грн' : null,
            default => null,
        };
        return $t === null ? 'Договірна' : $t . ($unit !== '' && $p['unit'] !== 'job' ? ' ' . $unit : '');
    }

    public static function payments(?string $csv): array
    {
        $out = [];
        foreach (explode(',', (string) $csv) as $k) if (isset(self::PAYMENTS[$k])) $out[$k] = self::PAYMENTS[$k];
        return $out;
    }

    public static function rating(array $m): ?float
    {
        $n = (int) $m['rating_count'];
        return $n ? round((int) $m['rating_sum'] / $n, 1) : null;
    }

    /** Зірки (HTML). $value від 0 до 5. */
    public static function stars(float $value, int $size = 16): string
    {
        $pct = max(0, min(100, $value / 5 * 100));
        return '<span class="stars" style="--s:' . $size . 'px" role="img" aria-label="Оцінка ' . e(number_format($value, 1, ',', '')) . ' з 5"><span style="width:' . $pct . '%"></span></span>';
    }

    public static function ratingText(array $m): string
    {
        $r = self::rating($m);
        return $r === null ? 'Ще немає відгуків' : number_format($r, 1, ',', '') . ' · ' . plural((int) $m['rating_count'], 'відгук', 'відгуки', 'відгуків');
    }

    /** Перерахунок кешу рейтингу й кількості виконаних замовлень. */
    public static function recalc(int $masterId): void
    {
        $db = App::db();
        $r = $db->one('SELECT COALESCE(SUM(rating),0) AS s, COUNT(*) AS n FROM {p}master_reviews WHERE master_id = ? AND is_hidden = 0', [$masterId]);
        $done = (int) $db->value("SELECT COUNT(*) FROM {p}service_orders WHERE master_id = ? AND status = 'done'", [$masterId]);
        $db->update('masters', ['rating_sum' => (int) $r['s'], 'rating_count' => (int) $r['n'], 'orders_done' => $done], 'id = ?', [$masterId]);
    }

    public static function photoUrl(?string $file): ?string
    {
        return Media::url('master', $file);
    }

    public static function today(): string { return date('Y-m-d'); }

    /** Останній день показу: сьогодні + N місяців (обидві дати включно). */
    public static function showTo(int $months, ?string $from = null): string
    {
        $months = isset(self::PERIODS[$months]) ? $months : 1;
        return date('Y-m-d', strtotime(($from ?? self::today()) . ' 12:00 +' . $months . ' months -1 day'));
    }

    /** Виводить профіль у каталог на обраний термін. */
    public static function goLive(int $id, array $extra = []): void
    {
        $db = App::db();
        $m = $db->one('SELECT period_months, published_at FROM {p}masters WHERE id = ?', [$id]);
        $db->update('masters', [...$extra, 'status' => 'published', 'show_to' => self::showTo((int) ($m['period_months'] ?? 1)),
            'published_at' => $m['published_at'] ?? Db::now(), 'archived_at' => null, 'archive_reason' => null, 'reject_reason' => null, 'updated_at' => Db::now()], 'id = ?', [$id]);
    }

    public static function archive(int $id, string $reason): void
    {
        if (!isset(self::ARCHIVE_REASONS[$reason])) $reason = 'manual';
        App::db()->update('masters', ['status' => 'archived', 'archived_at' => Db::now(), 'archive_reason' => $reason, 'updated_at' => Db::now()], 'id = ?', [$id]);
    }

    /** Переносить профілі з терміном, що минув, в архів. Запускається сам не частіше ніж раз на 5 хвилин. */
    public static function sweep(): void
    {
        $f = YW_ROOT . '/storage/cache/services_sweep.txt';
        if (is_file($f) && filemtime($f) > time() - 300) return;
        @touch($f);
        try {
            $rows = App::db()->all("SELECT id FROM {p}masters WHERE status = 'published' AND show_to IS NOT NULL AND show_to < ? AND deleted_at IS NULL", [self::today()]);
            foreach ($rows as $r) {
                self::archive((int) $r['id'], 'expired');
                Audit::log('services.master_archived', 'master', (int) $r['id'], ['reason' => 'expired'], null);
            }
        } catch (\Throwable $e) {
            error_log('[YEWORK] services sweep: ' . $e->getMessage()); // міграція 008 ще не застосована
        }
    }

    /** Повідомлення людині: email, а якщо його немає — SMS (як запрошення за резюме). */
    public static function notifyUser(int $uid, string $subject, string $emailText, string $smsText, string $template): void
    {
        $u = App::db()->one('SELECT phone_enc, email_enc FROM {p}users WHERE id = ?', [$uid]);
        if (!$u) return;
        $email = Crypto::decrypt($u['email_enc']);
        $phone = Crypto::decrypt($u['phone_enc']);
        try {
            if ($email) Messaging::send('email', $email, $emailText, $template, $subject);
            elseif ($phone) Messaging::send('sms', $phone, $smsText, $template);
        } catch (\Throwable $e) {
            error_log('[YEWORK] services notify: ' . $e->getMessage());
        }
    }

    public static function siteBase(): string
    {
        return rtrim((string) App::setting('site.url', ''), '/');
    }

    /** Нові замовлення майстра (для лічильника в кабінеті). */
    public static function newOrdersFor(int $uid): int
    {
        try {
            return (int) App::db()->value("SELECT COUNT(*) FROM {p}service_orders o JOIN {p}masters m ON m.id = o.master_id WHERE m.user_id = ? AND o.status = 'new'", [$uid]);
        } catch (\PDOException) {
            return 0;
        }
    }

    public static function pendingCount(): int
    {
        try {
            return (int) App::db()->value("SELECT COUNT(*) FROM {p}masters WHERE (status = 'pending' OR (status = 'published' AND needs_review = 1)) AND deleted_at IS NULL");
        } catch (\PDOException) {
            return 0;
        }
    }
}

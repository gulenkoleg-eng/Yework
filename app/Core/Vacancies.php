<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Термін показу вакансій і архів.
 *
 * Роботодавець задає період «з … по …» (дати за київським часом, обидві включно).
 *  - 'scheduled' — вакансію схвалено, але показ почнеться пізніше;
 *  - 'published' — показується на сайті;
 *  - 'archived'  — в архіві: знято вручну, знайшли працівника або закінчився термін.
 * Перехід за датами робить sweep(): він запускається сам на звичайних запитах (не частіше ніж раз на 5 хвилин),
 * тож окремий cron не потрібен.
 */
final class Vacancies
{
    public const DEFAULT_DAYS = 30;
    public const MAX_DAYS = 90;
    private const SWEEP_EVERY = 300;

    public const STATUS = [
        'draft' => ['Чернетка', 'muted'], 'pending' => ['На модерації', 'warn'], 'scheduled' => ['Заплановано', 'warn'],
        'published' => ['Опубліковано', 'ok'], 'rejected' => ['Відхилено', 'bad'], 'archived' => ['В архіві', 'muted'],
    ];

    public const REASONS = ['hired' => 'Знайшли працівника', 'manual' => 'Знято вручну', 'expired' => 'Закінчився термін показу'];

    public static function today(): string { return date('Y-m-d'); }

    public static function plusDays(string $date, int $days): string
    {
        return date('Y-m-d', strtotime($date . ' 12:00 +' . $days . ' days'));
    }

    /** Кінець дня show_to за Києвом у UTC — для старого поля expires_at. */
    private static function expiresAt(string $showTo): string
    {
        return gmdate('Y-m-d H:i:s', strtotime($showTo . ' 23:59:59'));
    }

    /**
     * Перевіряє період із форми. $minFrom — найраніша дозволена дата початку (за замовчуванням сьогодні).
     * @return array{0:string,1:string}
     */
    public static function period(Request $req, ?string $minFrom = null): array
    {
        $today = self::today();
        $minFrom = $minFrom !== null && $minFrom < $today ? $minFrom : $today;
        $valid = static fn (string $d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && checkdate((int) substr($d, 5, 2), (int) substr($d, 8, 2), (int) substr($d, 0, 4));
        $from = $req->str('show_from', 10) ?: $today;
        $to = $req->str('show_to', 10);
        $err = [];
        if (!$valid($from)) $err['show_from'] = 'Вкажіть дату початку';
        elseif ($from < $minFrom) $err['show_from'] = 'Дата початку не може бути в минулому';
        elseif ($from > self::plusDays($today, self::MAX_DAYS)) $err['show_from'] = 'Почати показ можна не пізніше ніж через ' . self::MAX_DAYS . ' днів';
        if (!$valid($to)) $err['show_to'] = 'Вкажіть, до якої дати показувати';
        elseif (!isset($err['show_from'])) {
            if ($to < $from) $err['show_to'] = 'Дата «по» раніша за дату «з»';
            elseif ($to < $today) $err['show_to'] = 'Дата закінчення вже минула';
            elseif ($to > self::plusDays($from, self::MAX_DAYS - 1)) $err['show_to'] = 'Найдовший термін показу — ' . self::MAX_DAYS . ' днів';
        }
        if ($err) throw new ValidationException($err);
        return [$from, $to];
    }

    /**
     * Виводить вакансію на сайт за її періодом: одразу (published) або пізніше (scheduled).
     * Повертає новий статус.
     */
    public static function goLive(int $id, array $extra = []): string
    {
        $db = App::db();
        $v = $db->one('SELECT show_from, show_to FROM {p}vacancies WHERE id = ?', [$id]);
        $today = self::today();
        $from = $v['show_from'] ?? null ?: $today;
        $to = $v['show_to'] ?? null ?: self::plusDays($from, self::DEFAULT_DAYS - 1);
        // Модерація затягнулася і дата «з» уже минула — нічого страшного, показуємо з сьогодні.
        $status = $from > $today ? 'scheduled' : 'published';
        $set = [...$extra, 'status' => $status, 'show_from' => $from, 'show_to' => $to, 'expires_at' => self::expiresAt($to),
            'archived_at' => null, 'archive_reason' => null, 'reject_reason' => null];
        if ($status === 'published') $set['published_at'] = Db::now();
        $db->update('vacancies', $set, 'id = ?', [$id]);
        Stats::forget();
        return $status;
    }

    public static function archive(int $id, string $reason): void
    {
        if (!isset(self::REASONS[$reason])) $reason = 'manual';
        App::db()->update('vacancies', ['status' => 'archived', 'archived_at' => Db::now(), 'archive_reason' => $reason, 'updated_at' => Db::now()], 'id = ?', [$id]);
        Stats::forget();
    }

    /** Переносить вакансії за датами. Безпечно викликати на кожному запиті. */
    public static function sweep(bool $force = false): void
    {
        $f = YW_ROOT . '/storage/cache/vacancy_sweep.txt';
        if (!$force && is_file($f) && filemtime($f) > time() - self::SWEEP_EVERY) return;
        @touch($f);
        try {
            $db = App::db();
            $today = self::today();
            $expired = $db->all("SELECT id FROM {p}vacancies WHERE status = 'published' AND show_to IS NOT NULL AND show_to < ? AND deleted_at IS NULL", [$today]);
            foreach ($expired as $r) self::archive((int) $r['id'], 'expired');
            $due = $db->all("SELECT id FROM {p}vacancies WHERE status = 'scheduled' AND show_from <= ? AND deleted_at IS NULL", [$today]);
            foreach ($due as $r) self::goLive((int) $r['id']);
        } catch (\Throwable $e) {
            // Наприклад, міграція 006 ще не застосована. Сайт працює далі, спробуємо пізніше.
            error_log('[YEWORK] vacancy sweep: ' . $e->getMessage());
        }
    }
}

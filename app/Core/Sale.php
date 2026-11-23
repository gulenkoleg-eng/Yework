<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Святкова акція-заставка (0.5.26): при вході на сайт на ~2 секунди з'являється яскрава анімована заставка
 * («ЧОРНА П'ЯТНИЦЯ −65%», «Новий рік», «8 березня» чи свій текст). Хто встиг натиснути — «впіймав» знижку
 * на платні послуги сайту (банери) до кінця акції.
 *
 * Заставка показується не частіше разу на день для одного відвідувача, не показується пошуковим роботам,
 * а для тих, хто вимкнув анімацію в системі (prefers-reduced-motion), — без руху.
 * Гість, що встиг натиснути, закріплює знижку входом або реєстрацією в тій самій вкладці браузера.
 */
final class Sale
{
    public const KEY = 'sale.splash';

    /** Подія → назва в адмінці, заголовок на заставці за замовчуванням, підпис. */
    public const EVENTS = [
        'blackfriday' => ['Чорна п’ятниця', 'ЧОРНА П’ЯТНИЦЯ', 'Знижка на рекламу для тих, хто встигне'],
        'newyear' => ['Новий рік', 'НОВОРІЧНА ЗНИЖКА', 'Подарунок від сайту до свят'],
        'march8' => ['8 березня', '8 БЕРЕЗНЯ', 'Весняна знижка для вашої компанії'],
        'custom' => ['Інше свято', 'СВЯТКОВА ЗНИЖКА', 'Тільки кілька днів'],
    ];

    public const SECONDS = [2 => '2 секунди', 3 => '3 секунди', 4 => '4 секунди', 5 => '5 секунд'];

    /** Скільки секунд «на мережу» понад час показу дає сервер, перш ніж відмовити. */
    private const SLACK = 2.5;

    public const DEFAULTS = ['enabled' => false, 'event' => 'blackfriday', 'title' => '', 'subtitle' => '', 'percent' => 30,
        'from' => '', 'to' => '', 'seconds' => 2, 'id' => ''];

    public static function config(): array
    {
        try {
            $s = App::setting(self::KEY);
        } catch (\Throwable) {
            $s = null;
        }
        $c = self::DEFAULTS;
        if (is_array($s)) {
            $c['enabled'] = (bool) ($s['enabled'] ?? false);
            if (isset(self::EVENTS[$s['event'] ?? ''])) $c['event'] = $s['event'];
            foreach (['title', 'subtitle', 'id'] as $k) $c[$k] = trim((string) ($s[$k] ?? ''));
            $c['percent'] = max(1, min(90, (int) ($s['percent'] ?? 30)));
            foreach (['from', 'to'] as $k) if (self::validDate((string) ($s[$k] ?? ''))) $c[$k] = $s[$k];
            $c['seconds'] = isset(self::SECONDS[(int) ($s['seconds'] ?? 2)]) ? (int) $s['seconds'] : 2;
        }
        return $c;
    }

    public static function validDate(string $d): bool
    {
        return (bool) preg_match('/^(\d{4})-(\d\d)-(\d\d)$/', $d, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    /** Заголовок на заставці: свій текст або типовий для події. */
    public static function title(array $c): string
    {
        return $c['title'] !== '' ? $c['title'] : self::EVENTS[$c['event']][1];
    }

    public static function subtitle(array $c): string
    {
        return $c['subtitle'] !== '' ? $c['subtitle'] : self::EVENTS[$c['event']][2];
    }

    /** Назва акції для кабінету й рахунку: «Чорна п’ятниця» або свій заголовок для іншого свята. */
    public static function name(array $c): string
    {
        if ($c['event'] !== 'custom') return self::EVENTS[$c['event']][0];
        return $c['title'] !== '' ? mb_convert_case(mb_strtolower($c['title']), MB_CASE_TITLE) : self::EVENTS['custom'][0];
    }

    /** Чи йде акція зараз: увімкнена і сьогодні в межах дат. */
    public static function running(?array $c = null, ?string $today = null): bool
    {
        $c ??= self::config();
        $today ??= date('Y-m-d');
        return $c['enabled'] && $c['id'] !== '' && $c['from'] !== '' && $c['to'] !== '' && $today >= $c['from'] && $today <= $c['to'];
    }

    public static function isBot(Request $req): bool
    {
        $ua = $req->userAgent();
        return $ua === '' || (bool) preg_match('/bot|crawl|spider|slurp|preview|monitor|curl|wget|python|headless|lighthouse|facebookexternalhit|embedly/i', $ua);
    }

    /** Cookie «вже бачив сьогодні». */
    public static function seenCookie(array $c): string
    {
        return $c['id'] . '.' . date('Ymd');
    }

    /**
     * Дані для заставки на цій сторінці або null (акція не йде, пошуковий робот).
     * 'auto' — показати одразу при вході: не частіше разу на день і лише тим, хто ще не впіймав знижку.
     * Інакше заставка лежить на сторінці схованою й відкривається натисканням на «YE» в шапці (0.5.27).
     * $preview — адміністратор дивиться, як виглядає заставка (показується завжди, знижку не дає).
     */
    public static function splash(Request $req, ?array $me): ?array
    {
        $c = self::config();
        $preview = isset($req->query['sale_preview']) && ($me['is_staff'] ?? false);
        $auto = true;
        if (!$preview) {
            if ($req->method !== 'GET' || !self::running($c) || self::isBot($req)) return null;
            if (($req->cookies['yw_sale'] ?? '') === self::seenCookie($c)) $auto = false;
            elseif ($me && self::claimOf((int) $me['id'], $c['id'])) $auto = false;
            elseif (!$me && App::session()->get('sale_pending') === $c['id']) $auto = false;
        } elseif ($c['id'] === '') {
            $c['id'] = 'preview';
        }
        if ($c['from'] === '') { $c['from'] = date('Y-m-d'); $c['to'] = date('Y-m-d', strtotime('+6 days')); }
        if (!$preview && $auto) self::markShown($c);
        return ['c' => $c, 'title' => self::title($c), 'subtitle' => self::subtitle($c), 'preview' => $preview, 'guest' => !$me, 'auto' => $auto,
            'monetization' => Ads::monetizationOn(), 'cookie' => self::seenCookie($c)];
    }

    /** Запам'ятовує в сесії час показу, щоб потім перевірити, що натиснули вчасно. */
    private static function markShown(array $c): void
    {
        App::session()->set('sale_shown', ['id' => $c['id'], 'at' => microtime(true), 'seconds' => $c['seconds']]);
    }

    /** Повторний показ натисканням на «YE» в шапці (0.5.27). */
    public static function show(Request $req): bool
    {
        $c = self::config();
        if (!self::running($c) || self::isBot($req)) return false;
        self::markShown($c);
        return true;
    }

    /**
     * Натискання на заставку. Повертає ['ok' => bool, 'message' => ..., 'guest' => bool].
     * Знижку дає лише той, хто натиснув у межах часу показу (плюс невеликий запас на мережу).
     */
    public static function catch(?array $me): array
    {
        $c = self::config();
        if (!self::running($c)) return ['ok' => false, 'message' => 'Акція вже завершилася.'];
        $shown = App::session()->get('sale_shown');
        App::session()->set('sale_shown', null); // одна спроба на один показ
        if (!is_array($shown) || ($shown['id'] ?? '') !== $c['id']) return ['ok' => false, 'message' => 'Заставку не знайдено. Оновіть сторінку.'];
        $late = microtime(true) - (float) $shown['at'] > (int) $shown['seconds'] + self::SLACK;
        if ($late) return ['ok' => false, 'message' => 'Трохи не встигли. Спробуйте завтра!'];
        if (!$me) {
            App::session()->set('sale_pending', $c['id']);
            return ['ok' => true, 'guest' => true];
        }
        self::claim((int) $me['id'], $c);
        return ['ok' => true, 'guest' => false];
    }

    /** Записує знижку користувачу (повторне натискання нічого не змінює). */
    public static function claim(int $userId, array $c): void
    {
        $st = App::db()->run('INSERT IGNORE INTO {p}sale_claims (campaign, user_id, event, name, percent, ends_on) VALUES (?, ?, ?, ?, ?, ?)',
            [$c['id'], $userId, $c['event'], mb_substr(self::name($c), 0, 80), $c['percent'], $c['to']]);
        if ($st->rowCount() > 0) Audit::log('billing.sale_caught', 'user', $userId, ['campaign' => $c['id'], 'percent' => $c['percent']], $userId);
    }

    /** Після входу чи реєстрації: гість, що впіймав знижку в цій вкладці, отримує її в свій кабінет. */
    public static function attachPending(int $userId): void
    {
        try {
            $pending = App::session()->get('sale_pending');
            if (!$pending) return;
            App::session()->set('sale_pending', null);
            $c = self::config();
            if ($pending === $c['id'] && self::running($c)) self::claim($userId, $c);
        } catch (\Throwable $e) {
            error_log('[YEWORK] sale attach: ' . $e->getMessage());
        }
    }

    public static function claimOf(int $userId, string $campaign): ?array
    {
        try {
            return App::db()->one('SELECT * FROM {p}sale_claims WHERE user_id = ? AND campaign = ?', [$userId, $campaign]);
        } catch (\Throwable) {
            return null; // міграцію ще не застосовано
        }
    }

    /**
     * Діюча знижка користувача: впіймана й ще не закінчилася. Знижка лишається до кінця своєї акції,
     * навіть якщо заставку вже вимкнули. Якщо діють кілька — найбільша.
     */
    public static function activeFor(int $userId): ?array
    {
        try {
            return App::db()->one('SELECT * FROM {p}sale_claims WHERE user_id = ? AND ends_on >= ? ORDER BY percent DESC, id DESC LIMIT 1', [$userId, date('Y-m-d')]);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Ціна зі знижкою. */
    public static function apply(int $price, int $percent): int
    {
        return (int) round($price * (100 - max(0, min(90, $percent))) / 100);
    }

    /** Знижка, вже застосована до банера (зберігається, щоб після акції редагування не скасувало її). */
    public static function bannerUse(int $bannerId): ?array
    {
        try {
            return App::db()->one('SELECT * FROM {p}sale_uses WHERE banner_id = ?', [$bannerId]);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Ціна банера з урахуванням святкової знижки. Для нового банера — діюча знижка замовника,
     * для вже замовленого — та, що була при замовленні (або діюча, якщо вона більша).
     * @return array{0:int,1:?array} [ціна, знижка або null]
     */
    public static function bannerPrice(int $full, int $userId, ?int $bannerId = null): array
    {
        $use = $bannerId ? self::bannerUse($bannerId) : null;
        $claim = self::activeFor($userId);
        $pick = $use ? ['claim_id' => $use['claim_id'], 'percent' => (int) $use['percent'], 'name' => $use['name']] : null;
        if ($claim && (!$pick || (int) $claim['percent'] > $pick['percent'])) $pick = ['claim_id' => $claim['id'], 'percent' => (int) $claim['percent'], 'name' => $claim['name']];
        if (!$pick || $full <= 0) return [$full, null];
        return [self::apply($full, $pick['percent']), $pick];
    }

    /** Запам'ятовує знижку банера (або прибирає, якщо банер став безкоштовним). */
    public static function remember(int $bannerId, ?array $pick, int $full): void
    {
        try {
            if (!$pick) {
                App::db()->run('DELETE FROM {p}sale_uses WHERE banner_id = ?', [$bannerId]);
                return;
            }
            App::db()->run('REPLACE INTO {p}sale_uses (banner_id, claim_id, name, percent, full_price) VALUES (?, ?, ?, ?, ?)',
                [$bannerId, $pick['claim_id'], $pick['name'], $pick['percent'], $full]);
        } catch (\Throwable $e) {
            error_log('[YEWORK] sale remember: ' . $e->getMessage());
        }
    }

    /** Для «Монетизації»: скільки людей упіймали знижку цієї акції і скільки банерів з нею замовили. */
    public static function stats(string $campaign): array
    {
        try {
            $db = App::db();
            return [
                'claims' => (int) $db->value('SELECT COUNT(*) FROM {p}sale_claims WHERE campaign = ?', [$campaign]),
                'used' => (int) $db->value('SELECT COUNT(*) FROM {p}sale_uses u JOIN {p}sale_claims c ON c.id = u.claim_id WHERE c.campaign = ?', [$campaign]),
            ];
        } catch (\Throwable) {
            return ['claims' => 0, 'used' => 0];
        }
    }

    /** «2026-11-29» → «29.11». */
    public static function short(string $d): string
    {
        return $d === '' ? '' : date('d.m', strtotime($d));
    }

    /** «21.11.26» або «21.11.2026» → «2026-11-21». */
    public static function parseHuman(string $s): ?string
    {
        $s = trim($s);
        if (self::validDate($s)) return $s;
        if (!preg_match('/^(\d{1,2})[.\-\/](\d{1,2})[.\-\/](\d{2}|\d{4})$/', $s, $m)) return null;
        $y = strlen($m[3]) === 2 ? 2000 + (int) $m[3] : (int) $m[3];
        $d = sprintf('%04d-%02d-%02d', $y, (int) $m[2], (int) $m[1]);
        return self::validDate($d) ? $d : null;
    }
}

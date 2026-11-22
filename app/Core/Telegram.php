<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;

/**
 * Telegram-бот для персоналу сайту.
 *
 * - Налаштування (токен, група, адреса сайту) зберігаються в таблиці settings під ключем telegram.config,
 *   токен — зашифрованим. Нічого не зашито в код: після переїзду на інший домен достатньо в адмінці
 *   натиснути «Підключити до цього домену».
 * - Працює через вебхук (Telegram сам надсилає запити на /telegram/webhook), тож не потрібні фонові процеси.
 * - Діяти через бота можуть лише працівники, які прив'язали Telegram у своєму профілі, і лише в межах прав своєї ролі.
 *   Кожна дія записується в журнал дій з позначкою «через Telegram».
 * - Персональні дані шукачів (ім'я, телефон, фото) в Telegram не надсилаються.
 */
final class Telegram
{
    public const SETTING = 'telegram.config';
    public const MODERATE = 'jobs.listing:moderate';
    private const LINK_TTL = 900;

    public const VACANCY_REASONS = [
        'incomplete' => 'Неповний або незрозумілий опис вакансії',
        'rules' => 'Вакансія порушує правила сайту',
        'duplicate' => 'Повторне оголошення (така вакансія вже є)',
        'contacts' => 'Посилання або контакти в тексті опису',
        'suspicious' => 'Підозріле оголошення, потрібна додаткова перевірка',
    ];

    public const RESUME_REASONS = [
        'incomplete' => 'Резюме заповнене не повністю',
        'photo' => 'Фото не відповідає правилам сайту',
        'rules' => 'Резюме порушує правила сайту',
        'contacts' => 'Посилання або контакти в тексті резюме',
    ];

    public const LABELS = [
        'telegram.settings' => 'Змінено налаштування Telegram-бота',
        'telegram.webhook' => 'Telegram-бота підключено до домену',
        'telegram.webhook_off' => 'Telegram-бота відключено від сайту',
        'telegram.linked' => 'Прив’язано Telegram',
        'telegram.unlinked' => 'Відв’язано Telegram',
    ];

    /** Відкладені дії: виконуються після відповіді браузеру, щоб сайт не чекав на Telegram. */
    private static array $queue = [];
    private static bool $hooked = false;

    /* ---------------- налаштування ---------------- */

    /** @return array{tokenEnc:?string, botUsername:string, botName:string, chatId:string, chatTitle:string, moderation:bool, updates:bool, secret:string, siteUrl:string, webhookAt:?string, seenChats:array} */
    public static function config(): array
    {
        $c = App::setting(self::SETTING, []);
        return (is_array($c) ? $c : []) + [
            'tokenEnc' => null, 'botUsername' => '', 'botName' => '', 'chatId' => '', 'chatTitle' => '',
            'moderation' => true, 'updates' => true, 'secret' => '', 'siteUrl' => '', 'webhookAt' => null, 'seenChats' => [],
        ];
    }

    public static function saveConfig(array $c, ?int $actorId = null): void
    {
        App::setSetting(self::SETTING, $c, $actorId);
    }

    public static function token(): ?string
    {
        $enc = self::config()['tokenEnc'];
        return $enc ? Crypto::decrypt($enc) : null;
    }

    public static function ready(): bool
    {
        $c = self::config();
        return !empty($c['tokenEnc']) && $c['botUsername'] !== '';
    }

    public static function groupReady(): bool
    {
        return self::ready() && self::config()['chatId'] !== '';
    }

    public static function validToken(string $t): bool
    {
        return (bool) preg_match('/^\d{5,12}:[A-Za-z0-9_-]{30,60}$/', $t);
    }

    public static function validChatId(string $id): bool
    {
        return (bool) preg_match('/^-?\d{5,20}$/', $id);
    }

    /** Адреса сайту для посилань у повідомленнях (зберігається при підключенні вебхука). */
    public static function siteUrl(): string
    {
        $u = self::config()['siteUrl'] ?: (string) App::setting('site.url', '');
        return rtrim($u, '/');
    }

    public static function link(string $path): string
    {
        return self::siteUrl() . '/' . ltrim($path, '/');
    }

    /** Адреса поточного сайту з запиту адміністратора: https://домен[/підпапка]. */
    public static function currentSiteUrl(Request $req): string
    {
        $host = preg_replace('/[^A-Za-z0-9.:-]/', '', (string) ($req->server['HTTP_HOST'] ?? ''));
        return ($req->isHttps() ? 'https' : 'http') . '://' . $host . App::basePath();
    }

    /* ---------------- Bot API ---------------- */

    private static function apiBase(): string
    {
        return rtrim((string) (getenv('YW_TELEGRAM_API') ?: 'https://api.telegram.org'), '/');
    }

    /** Виклик Bot API. Помилку повертає зрозумілим текстом (токен у текст не потрапляє). */
    public static function api(string $method, array $params = [], ?string $token = null, int $timeout = 10): mixed
    {
        $token ??= self::token();
        if (!$token) throw new RuntimeException('Бота не налаштовано: немає токена.');
        $url = self::apiBase() . '/bot' . $token . '/' . $method;
        $body = $params ? json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '{}';
        $headers = ['Content-Type: application/json', 'User-Agent: YEWORK-Bot'];
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 6, CURLOPT_TIMEOUT => $timeout, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
            ]);
            $raw = curl_exec($ch);
            $err = curl_error($ch);
            if ($raw === false) throw new RuntimeException('Немає зв’язку з Telegram: ' . str_replace($token, '***', $err));
        } else {
            $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => implode("\r\n", $headers), 'content' => $body, 'timeout' => $timeout, 'ignore_errors' => true]]);
            $raw = @file_get_contents($url, false, $ctx);
            if ($raw === false) throw new RuntimeException('Немає зв’язку з Telegram. Увімкніть розширення PHP «curl».');
        }
        $j = json_decode((string) $raw, true);
        if (!is_array($j)) throw new RuntimeException('Telegram повернув незрозумілу відповідь.');
        if (empty($j['ok'])) throw new RuntimeException(self::explain((int) ($j['error_code'] ?? 0), (string) ($j['description'] ?? '')));
        return $j['result'] ?? null;
    }

    private static function explain(int $code, string $d): string
    {
        if ($code === 401 || $code === 404) return 'Telegram не прийняв токен. Перевірте, що скопіювали його з @BotFather повністю.';
        if (str_contains($d, 'chat not found')) return 'Бот не бачить цю групу. Додайте бота в групу і перевірте ID групи.';
        if (str_contains($d, 'bot was kicked') || str_contains($d, 'not a member')) return 'Бота видалили з групи. Додайте його знову.';
        if (str_contains($d, 'not enough rights')) return 'У бота немає права писати в групу. Дозвольте йому надсилати повідомлення.';
        if (str_contains($d, 'HTTPS url must be provided') || str_contains($d, 'bad webhook')) return 'Telegram потребує адресу сайту з https://. ' . $d;
        return 'Telegram: ' . ($d ?: 'помилка ' . $code);
    }

    public static function send(string|int $chatId, string $html, ?array $keyboard = null): ?array
    {
        $p = ['chat_id' => $chatId, 'text' => self::cut($html), 'parse_mode' => 'HTML', 'disable_web_page_preview' => true];
        if ($keyboard !== null) $p['reply_markup'] = ['inline_keyboard' => $keyboard];
        $r = self::api('sendMessage', $p);
        return is_array($r) ? $r : null;
    }

    private static function cut(string $html): string
    {
        return mb_strlen($html) > 4000 ? mb_substr($html, 0, 3990) . '…' : $html;
    }

    private static function h(?string $s): string
    {
        return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /* ---------------- події сайту (з журналу дій) ---------------- */

    /** Викликається з Audit::log. Нічого не робить, якщо бота не налаштовано. */
    public static function onAudit(string $action, ?string $type, int|string|null $id): void
    {
        static $map = [
            'jobs.vacancy_submitted' => ['post', 'vacancy'], 'resume.submitted' => ['post', 'resume'],
            'jobs.vacancy_approved' => ['refresh', 'vacancy'], 'jobs.vacancy_rejected' => ['refresh', 'vacancy'], 'jobs.vacancy_archived' => ['refresh', 'vacancy'],
            'resume.approved' => ['refresh', 'resume'], 'resume.rejected' => ['refresh', 'resume'], 'resume.deleted' => ['refresh', 'resume'],
        ];
        if (!isset($map[$action]) || $id === null || !self::ready()) return;
        [$what, $entity] = $map[$action];
        if ($what === 'post' && !(self::groupReady() && self::config()['moderation'])) return;
        self::defer($what === 'post' ? [self::class, 'postPending'] : [self::class, 'refresh'], [$entity, (int) $id]);
    }

    /** Після встановлення нової версії: повідомити групу. */
    public static function afterVersionChange(): void
    {
        try {
            if (self::groupReady() && self::config()['updates']) self::defer([self::class, 'announceVersion'], [false]);
        } catch (Throwable $e) {
            error_log('[YEWORK] telegram: ' . $e->getMessage());
        }
    }

    public static function defer(callable $fn, array $args = []): void
    {
        self::$queue[] = [$fn, $args];
        if (self::$hooked) return;
        self::$hooked = true;
        register_shutdown_function(static function (): void {
            ignore_user_abort(true);
            if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
            elseif (function_exists('litespeed_finish_request')) @litespeed_finish_request();
            self::flush();
        });
    }

    public static function flush(): void
    {
        while ($job = array_shift(self::$queue)) {
            try {
                ($job[0])(...$job[1]);
            } catch (Throwable $e) {
                error_log('[YEWORK] telegram: ' . $e->getMessage());
            }
        }
    }

    /* ---------------- повідомлення модерації ---------------- */

    public static function loadVacancy(int $id): ?array
    {
        return App::db()->one('SELECT v.*, e.name AS employer, e.is_verified, s.name AS settlement, c.name AS category, mu.display_name AS moderator
            FROM {p}vacancies v JOIN {p}employers e ON e.id = v.employer_id LEFT JOIN {p}settlements s ON s.id = v.settlement_id
            LEFT JOIN {p}job_categories c ON c.id = v.category_id LEFT JOIN {p}users mu ON mu.id = v.moderated_by
            WHERE v.id = ?', [$id]);
    }

    public static function loadResume(int $id): ?array
    {
        $r = App::db()->one('SELECT r.*, c.name AS category, s.name AS settlement, p.birth_year, mu.display_name AS moderator
            FROM {p}resumes r LEFT JOIN {p}profiles p ON p.user_id = r.user_id LEFT JOIN {p}settlements s ON s.id = p.settlement_id
            LEFT JOIN {p}job_categories c ON c.id = r.category_id LEFT JOIN {p}users mu ON mu.id = r.moderated_by
            WHERE r.id = ?', [$id]);
        if ($r) $r['experience'] = Resumes::decodeList($r['experience'] ?? null);
        return $r;
    }

    private static function excerpt(?string $s, int $n): string
    {
        $s = trim(preg_replace('/\s+/u', ' ', (string) $s) ?? '');
        return mb_strlen($s) > $n ? mb_substr($s, 0, $n) . '…' : $s;
    }

    /**
     * Текст і кнопки повідомлення за поточним станом у базі.
     * @return array{0:string, 1:list<list<array>>, 2:bool} [текст, кнопки, чи ще на модерації]
     */
    public static function render(string $type, int $id): array
    {
        $isV = $type === 'vacancy';
        $x = $isV ? self::loadVacancy($id) : self::loadResume($id);
        if (!$x) return ['ℹ️ ' . ($isV ? 'Вакансію' : 'Резюме') . ' #' . $id . ' видалено.', [], false];
        $lines = [];
        if ($isV) {
            $lines[] = '📋 <b>Вакансія #' . $id . '</b>';
            $lines[] = '<b>' . self::h($x['title']) . '</b>';
            $lines[] = '🏢 ' . self::h($x['employer']) . ($x['is_verified'] ? ' ✓' : '');
            $lines[] = '📍 ' . self::h(trim(($x['settlement'] ?? '') . ' · ' . ($x['category'] ?? ''), ' ·'));
            $lines[] = '💰 ' . self::h(salary_text($x['salary_from'] !== null ? (int) $x['salary_from'] : null, $x['salary_to'] !== null ? (int) $x['salary_to'] : null, (string) $x['salary_period']));
            $lines[] = '';
            $lines[] = self::h(self::excerpt($x['description'], 400));
        } else {
            $age = Resumes::age($x['birth_year'] !== null ? (int) $x['birth_year'] : null);
            $exp = count($x['experience']);
            $lines[] = '📄 <b>Резюме #' . $id . '</b>';
            $lines[] = '<b>' . self::h($x['title']) . '</b>';
            $lines[] = '📍 ' . self::h(trim(($x['settlement'] ?? '') . ' · ' . ($x['category'] ?? ''), ' ·'));
            if ($x['salary_from']) $lines[] = '💰 від ' . self::h(money((int) $x['salary_from'])) . ' грн';
            $lines[] = '👤 ' . ($age ? self::h($age) . ' · ' : '') . ($exp ? 'досвід: ' . plural($exp, 'місце', 'місця', 'місць') : 'без досвіду');
            if (trim((string) $x['about']) !== '') { $lines[] = ''; $lines[] = self::h(self::excerpt($x['about'], 300)); }
            $lines[] = '';
            $lines[] = '<i>Ім’я, фото і контакти — лише на сайті.</i>';
        }
        $lines[] = '';
        $pending = $x['status'] === 'pending' && empty($x['deleted_at']);
        $who = self::h($x['moderator'] ?: '—');
        $when = $x['moderated_at'] ? ' · ' . local_date($x['moderated_at'], 'd.m H:i') : '';
        if ($pending) $lines[] = '⏳ <b>Чекає на модерацію</b>';
        elseif (!empty($x['deleted_at'])) $lines[] = 'ℹ️ Видалено автором.';
        elseif ($x['status'] === 'published' && $x['moderated_by']) $lines[] = '✅ <b>Схвалено</b>: ' . $who . $when;
        elseif ($x['status'] === 'scheduled' && $x['moderated_by']) $lines[] = '✅ <b>Схвалено</b>: ' . $who . $when . "\nЗ’явиться на сайті з " . self::h(date('d.m.Y', strtotime((string) $x['show_from'])));
        elseif ($x['status'] === 'rejected') $lines[] = '❌ <b>Відхилено</b>: ' . $who . $when . "\nПричина: " . self::h($x['reject_reason']);
        else $lines[] = 'ℹ️ Більше не на модерації (автор змінив або зняв).';

        $p = $isV ? 'v' : 'r';
        $open = ['text' => '🔗 Відкрити на сайті', 'url' => self::link($isV ? '/admin/moderation' : '/admin/resumes')];
        $kb = $pending
            ? [[['text' => '✅ Схвалити', 'callback_data' => "$p:a:$id"], ['text' => '❌ Відхилити', 'callback_data' => "$p:r:$id"]], [$open]]
            : [];
        if (!self::isPublicUrl($open['url'])) $kb = array_map(static fn ($row) => array_values(array_filter($row, static fn ($b) => !isset($b['url']))), $kb);
        $kb = array_values(array_filter($kb));
        return [implode("\n", $lines), $kb, $pending];
    }

    /** Telegram приймає в кнопках лише повні адреси http(s). */
    private static function isPublicUrl(string $u): bool
    {
        return (bool) preg_match('#^https?://[^/\s]+\.[^/\s]+#', $u);
    }

    private static function reasonsKeyboard(string $type, int $id): array
    {
        $p = $type === 'vacancy' ? 'v' : 'r';
        $kb = [];
        foreach ($type === 'vacancy' ? self::VACANCY_REASONS : self::RESUME_REASONS as $k => $label) $kb[] = [['text' => $label, 'callback_data' => "$p:x:$id:$k"]];
        $other = self::link($type === 'vacancy' ? '/admin/moderation' : '/admin/resumes');
        if (self::isPublicUrl($other)) $kb[] = [['text' => '✏️ Інша причина — на сайті', 'url' => $other]];
        $kb[] = [['text' => '↩️ Назад', 'callback_data' => "$p:b:$id"]];
        return $kb;
    }

    /** Нове оголошення на модерації — у групу. */
    public static function postPending(string $type, int $id, string|int|null $chatId = null): void
    {
        [$text, $kb, $pending] = self::render($type, $id);
        if (!$pending) return;
        $chatId ??= self::config()['chatId'];
        $m = self::send($chatId, ($chatId === self::config()['chatId'] ? '🆕 Нове на модерації' . "\n\n" : '') . $text, $kb);
        if ($m && isset($m['message_id'])) {
            App::db()->insert('telegram_messages', ['entity_type' => $type, 'entity_id' => $id, 'chat_id' => (int) $m['chat']['id'], 'message_id' => (int) $m['message_id']]);
        }
    }

    /** Оновити всі відкриті повідомлення про оголошення: хто і що вирішив, прибрати кнопки. */
    public static function refresh(string $type, int $id): void
    {
        $msgs = App::db()->all('SELECT * FROM {p}telegram_messages WHERE entity_type = ? AND entity_id = ? AND closed_at IS NULL', [$type, $id]);
        if (!$msgs) return;
        [$text, $kb, $pending] = self::render($type, $id);
        foreach ($msgs as $m) {
            try {
                self::api('editMessageText', ['chat_id' => (int) $m['chat_id'], 'message_id' => (int) $m['message_id'], 'text' => self::cut($text),
                    'parse_mode' => 'HTML', 'disable_web_page_preview' => true, 'reply_markup' => ['inline_keyboard' => $kb]]);
            } catch (RuntimeException $e) {
                if (!str_contains($e->getMessage(), 'not modified')) error_log('[YEWORK] telegram edit: ' . $e->getMessage());
            }
            if (!$pending) App::db()->update('telegram_messages', ['closed_at' => Db::now()], 'id = ?', [$m['id']]);
        }
    }

    /* ---------------- оновлення сайту ---------------- */

    /** Telegram HTML з тексту CHANGELOG (жирний, код, списки, посилання). */
    public static function mdToHtml(string $md): string
    {
        $s = self::h($md);
        $s = preg_replace('/\*\*(.+?)\*\*/u', '<b>$1</b>', $s) ?? $s;
        $s = preg_replace('/`([^`]+)`/u', '<code>$1</code>', $s) ?? $s;
        $s = preg_replace('/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/u', '<a href="$2">$1</a>', $s) ?? $s;
        $s = preg_replace('/^\s*[-*]\s+/mu', '• ', $s) ?? $s;
        $s = preg_replace('/^#+\s*(.+)$/mu', '<b>$1</b>', $s) ?? $s;
        return trim($s);
    }

    public static function versionText(string $version): string
    {
        $notes = (string) (Updater::localChangelog()[$version]['notes'] ?? '');
        if (mb_strlen($notes) > 3200) {
            $notes = mb_substr($notes, 0, 3200);
            $notes = mb_substr($notes, 0, (int) mb_strrpos($notes, "\n")) . "\n…";
        }
        $site = (string) App::setting('site.name', 'YEWORK');
        $t = '🚀 <b>' . self::h($site) . ': встановлено версію ' . self::h($version) . '</b>';
        if ($notes !== '') $t .= "\n\n" . self::mdToHtml($notes);
        $u = self::link('/admin/updates');
        if (self::isPublicUrl($u)) $t .= "\n\n<a href=\"" . self::h($u) . '">Історія версій в адмінці</a>';
        return $t;
    }

    /** Надіслати в групу опис поточної версії. Без $force — лише один раз для кожної версії. */
    public static function announceVersion(bool $force = false): bool
    {
        if (!self::groupReady()) return false;
        if (!$force && App::setting('telegram.announced') === YW_VERSION) return false;
        self::send(self::config()['chatId'], self::versionText(YW_VERSION));
        App::setSetting('telegram.announced', YW_VERSION);
        return true;
    }

    /* ---------------- прив'язка акаунтів ---------------- */

    /** Посилання для прив'язки: t.me/бот?start=одноразовий-код (діє 15 хвилин). */
    public static function createLinkUrl(int $userId): string
    {
        $code = Crypto::token(12);
        $db = App::db();
        $db->run('DELETE FROM {p}telegram_link_codes WHERE user_id = ? OR expires_at < UTC_TIMESTAMP()', [$userId]);
        $db->insert('telegram_link_codes', ['code_hash' => hash('sha256', $code), 'user_id' => $userId, 'expires_at' => Db::now(self::LINK_TTL)]);
        return 'https://t.me/' . rawurlencode(self::config()['botUsername']) . '?start=' . $code;
    }

    public static function account(int $userId): ?array
    {
        return App::db()->one('SELECT * FROM {p}telegram_accounts WHERE user_id = ?', [$userId]);
    }

    public static function unlink(int $userId, ?int $actorId): void
    {
        if (App::db()->run('DELETE FROM {p}telegram_accounts WHERE user_id = ?', [$userId])->rowCount()) {
            Audit::log('telegram.unlinked', 'user', $userId, [], $actorId);
        }
    }

    /** Працівник за Telegram-акаунтом: активний, з роллю (або власник), з переліком прав. */
    public static function staffByTg(int $tgUserId): ?array
    {
        $u = App::db()->one('SELECT u.* FROM {p}telegram_accounts t JOIN {p}users u ON u.id = t.user_id WHERE t.tg_user_id = ? AND u.deleted_at IS NULL', [$tgUserId]);
        if (!$u || $u['status'] !== 'active') return null;
        $isOwner = (int) $u['owner_flag'] === 1;
        $auth = App::auth();
        if (!$isOwner && !$auth->rolesOf((int) $u['id'])) return null;
        $u['perms'] = $auth->permsOf((int) $u['id'], $isOwner);
        return $u;
    }

    /* ---------------- модерація з Telegram ---------------- */

    /** @return array{0:bool,1:string} */
    public static function moderate(string $type, int $id, array $actor, string $decision, ?string $reason = null): array
    {
        $db = App::db();
        $isV = $type === 'vacancy';
        $table = $isV ? 'vacancies' : 'resumes';
        $x = $db->one("SELECT * FROM {p}$table WHERE id = ? AND deleted_at IS NULL", [$id]);
        if (!$x) return [false, $isV ? 'Вакансію не знайдено.' : 'Резюме не знайдено.'];
        $owner = (int) ($isV ? $x['author_id'] : $x['user_id']);
        if ($owner === (int) $actor['id']) return [false, $isV ? 'Не можна модерувати власну вакансію.' : 'Не можна модерувати власне резюме.'];
        if ($x['status'] !== 'pending') return [false, 'Вже оброблено.'];
        $uid = (int) $actor['id'];
        if ($decision === 'approve') {
            if ($isV) {
                // Як на сайті: вакансія виходить за своїм періодом показу (одразу або з дати «з»).
                $st = $db->tx(function (Db $db) use ($id, $uid) {
                    if ($db->value('SELECT status FROM {p}vacancies WHERE id = ? FOR UPDATE', [$id]) !== 'pending') return null;
                    return Vacancies::goLive($id, ['moderated_by' => $uid, 'moderated_at' => Db::now()]);
                });
                if ($st === null) return [false, 'Вже оброблено.'];
                Audit::log('jobs.vacancy_approved', 'vacancy', $id, ['via' => 'telegram'], $uid);
                return [true, $st === 'scheduled' ? 'Схвалено ✅ З’явиться на сайті з ' . date('d.m', strtotime((string) $db->value('SELECT show_from FROM {p}vacancies WHERE id = ?', [$id]))) : 'Схвалено ✅'];
            }
            $set = ['status' => 'published', 'moderated_by' => $uid, 'moderated_at' => Db::now(), 'published_at' => $x['published_at'] ?? Db::now(), 'reject_reason' => null];
            if (!$db->update($table, $set, "id = ? AND status = 'pending'", [$id])) return [false, 'Вже оброблено.'];
            Audit::log('resume.approved', $type, $id, ['via' => 'telegram'], $uid);
            return [true, 'Схвалено ✅'];
        }
        $reason = trim((string) $reason);
        if (mb_strlen($reason) < 5) return [false, 'Потрібна причина відхилення.'];
        $set = ['status' => 'rejected', 'moderated_by' => $uid, 'moderated_at' => Db::now(), 'reject_reason' => $reason];
        if (!$db->update($table, $set, "id = ? AND status = 'pending'", [$id])) return [false, 'Вже оброблено.'];
        Audit::log($isV ? 'jobs.vacancy_rejected' : 'resume.rejected', $type, $id, ['reason' => $reason, 'via' => 'telegram'], $uid);
        if ($isV) Stats::forget();
        return [true, 'Відхилено ❌'];
    }

    /* ---------------- вебхук ---------------- */

    public static function handle(array $u): void
    {
        if (isset($u['callback_query']) && is_array($u['callback_query'])) { self::onCallback($u['callback_query']); return; }
        if (isset($u['my_chat_member']['chat']) && is_array($u['my_chat_member']['chat'])) {
            $st = (string) ($u['my_chat_member']['new_chat_member']['status'] ?? '');
            if (in_array($st, ['member', 'administrator'], true)) self::rememberChat($u['my_chat_member']['chat']);
            return;
        }
        $m = $u['message'] ?? null;
        if (!is_array($m) || !isset($m['chat']['id'])) return;
        $chat = $m['chat'];
        $private = ($chat['type'] ?? '') === 'private';
        if (!$private) self::rememberChat($chat);
        $text = trim((string) ($m['text'] ?? ''));
        if (!preg_match('~^/([a-z_]+)(?:@(\w+))?(?:\s+(.+))?$~i', $text, $c)) {
            if ($private) self::reply($chat['id'], self::helpText());
            return;
        }
        if (!empty($c[2]) && strcasecmp($c[2], self::config()['botUsername']) !== 0) return; // команда іншому боту
        $cmd = strtolower($c[1]);
        $arg = trim($c[3] ?? '');
        $from = $m['from'] ?? [];
        switch ($cmd) {
            case 'start':
                if ($private && $arg !== '') { self::linkByCode($arg, $from, $chat['id']); return; }
                if ($private) self::reply($chat['id'], self::helpText());
                return;
            case 'id':
                self::reply($chat['id'], 'ID цього чату: <code>' . self::h((string) $chat['id']) . '</code>' . ($private ? '' : "\nВставте його в адмінці: Telegram → ID групи."));
                return;
            case 'pending':
            case 'queue':
                self::sendQueue($from, $chat['id']);
                return;
            case 'unlink':
                if (!$private) return;
                $uid = App::db()->value('SELECT user_id FROM {p}telegram_accounts WHERE tg_user_id = ?', [(int) ($from['id'] ?? 0)]);
                if ($uid) { self::unlink((int) $uid, (int) $uid); self::reply($chat['id'], 'Telegram відв’язано від акаунта на сайті.'); }
                else self::reply($chat['id'], 'Цей Telegram не прив’язаний до жодного акаунта.');
                return;
            default:
                if ($private) self::reply($chat['id'], self::helpText());
        }
    }

    private static function helpText(): string
    {
        $site = self::h((string) App::setting('site.name', 'YEWORK'));
        return "Це службовий бот сайту <b>$site</b> для персоналу.\n\n"
            . "Щоб прив’язати акаунт: увійдіть на сайт → Адмін-панель → <b>Telegram</b> → «Прив’язати Telegram».\n\n"
            . "Команди:\n/pending — оголошення, що чекають на модерацію\n/id — ID цього чату\n/unlink — відв’язати Telegram";
    }

    private static function reply(string|int $chatId, string $html): void
    {
        try { self::send($chatId, $html); } catch (RuntimeException $e) { error_log('[YEWORK] telegram reply: ' . $e->getMessage()); }
    }

    private static function rememberChat(array $chat): void
    {
        if (!in_array($chat['type'] ?? '', ['group', 'supergroup'], true)) return;
        $c = self::config();
        $seen = is_array($c['seenChats']) ? $c['seenChats'] : [];
        $id = (string) $chat['id'];
        $title = mb_substr((string) ($chat['title'] ?? ''), 0, 100);
        if (($seen[$id] ?? null) === $title) return;
        unset($seen[$id]);
        $seen = [$id => $title] + array_slice($seen, 0, 4, true);
        $c['seenChats'] = $seen;
        if ($c['chatId'] === $id) $c['chatTitle'] = $title;
        self::saveConfig($c);
    }

    private static function linkByCode(string $code, array $from, string|int $chatId): void
    {
        $db = App::db();
        $tgId = (int) ($from['id'] ?? 0);
        $row = preg_match('/^[A-Za-z0-9_-]{8,64}$/', $code)
            ? $db->one('SELECT * FROM {p}telegram_link_codes WHERE code_hash = ? AND used_at IS NULL AND expires_at > UTC_TIMESTAMP()', [hash('sha256', $code)])
            : null;
        if (!$row || $tgId <= 0) {
            self::reply($chatId, 'Посилання застаріло або вже використане. Відкрийте адмінку → Telegram і натисніть «Прив’язати Telegram» ще раз.');
            return;
        }
        $uid = (int) $row['user_id'];
        $db->update('telegram_link_codes', ['used_at' => Db::now()], 'code_hash = ?', [$row['code_hash']]);
        $user = $db->one('SELECT * FROM {p}users WHERE id = ? AND deleted_at IS NULL', [$uid]);
        if (!$user || $user['status'] !== 'active' || ((int) $user['owner_flag'] !== 1 && !App::auth()->rolesOf($uid))) {
            self::reply($chatId, 'Прив’язати Telegram можуть лише працівники сайту.');
            return;
        }
        $name = trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? ''));
        $db->tx(function (Db $db) use ($uid, $tgId, $from, $name) {
            $db->run('DELETE FROM {p}telegram_accounts WHERE tg_user_id = ? OR user_id = ?', [$tgId, $uid]);
            $db->insert('telegram_accounts', ['user_id' => $uid, 'tg_user_id' => $tgId, 'tg_username' => isset($from['username']) ? mb_substr((string) $from['username'], 0, 64) : null, 'tg_name' => mb_substr($name, 0, 128) ?: null]);
        });
        Audit::log('telegram.linked', 'user', $uid, ['tg' => isset($from['username']) ? '@' . $from['username'] : $name], $uid);
        $staff = self::staffByTg($tgId);
        $can = $staff && isset($staff['perms'][self::MODERATE]);
        self::reply($chatId, '✅ Готово! Telegram прив’язано до акаунта <b>' . self::h($user['display_name']) . "</b>.\n\n"
            . ($can ? "Тепер ви можете схвалювати й відхиляти оголошення кнопками під повідомленнями в групі.\n/pending — показати чергу модерації."
                    : 'У вашої ролі немає права модерації, тож кнопки модерації для вас не діятимуть.'));
    }

    private static function sendQueue(array $from, string|int $chatId): void
    {
        $staff = self::staffByTg((int) ($from['id'] ?? 0));
        if (!$staff || !isset($staff['perms'][self::MODERATE])) {
            self::reply($chatId, $staff ? 'У вашої ролі немає права модерації.' : 'Спершу прив’яжіть Telegram: адмінка → Telegram → «Прив’язати Telegram».');
            return;
        }
        $db = App::db();
        $v = array_column($db->all("SELECT id FROM {p}vacancies WHERE status = 'pending' AND deleted_at IS NULL ORDER BY submitted_at ASC LIMIT 5"), 'id');
        $r = [];
        try { $r = array_column($db->all("SELECT id FROM {p}resumes WHERE status = 'pending' AND deleted_at IS NULL ORDER BY submitted_at ASC LIMIT 5"), 'id'); } catch (Throwable) {}
        $nv = (int) $db->value("SELECT COUNT(*) FROM {p}vacancies WHERE status = 'pending' AND deleted_at IS NULL");
        $nr = 0;
        try { $nr = (int) $db->value("SELECT COUNT(*) FROM {p}resumes WHERE status = 'pending' AND deleted_at IS NULL"); } catch (Throwable) {}
        if (!$v && !$r) { self::reply($chatId, 'Черга модерації порожня 👌'); return; }
        self::reply($chatId, "На модерації: вакансій — <b>$nv</b>, резюме — <b>$nr</b>." . ($nv + $nr > count($v) + count($r) ? ' Показую найстаріші.' : ''));
        foreach ($v as $id) self::postPending('vacancy', (int) $id, $chatId);
        foreach ($r as $id) self::postPending('resume', (int) $id, $chatId);
    }

    private static function onCallback(array $q): void
    {
        $qid = (string) ($q['id'] ?? '');
        $answer = static function (string $text, bool $alert = false) use ($qid): void {
            try { self::api('answerCallbackQuery', ['callback_query_id' => $qid, 'text' => $text, 'show_alert' => $alert]); } catch (RuntimeException) {}
        };
        if (!preg_match('/^([vr]):([arxb]):(\d{1,18})(?::(\w{1,20}))?$/', (string) ($q['data'] ?? ''), $d)) { $answer('Невідома дія.'); return; }
        $type = $d[1] === 'v' ? 'vacancy' : 'resume';
        $id = (int) $d[3];
        $staff = self::staffByTg((int) ($q['from']['id'] ?? 0));
        if (!$staff) {
            $linked = App::db()->value('SELECT user_id FROM {p}telegram_accounts WHERE tg_user_id = ?', [(int) ($q['from']['id'] ?? 0)]);
            $answer($linked ? 'Ваш акаунт на сайті не має ролі персоналу або заблокований.' : 'Спершу прив’яжіть Telegram: адмінка → Telegram → «Прив’язати Telegram».', true);
            return;
        }
        if (!isset($staff['perms'][self::MODERATE])) { $answer('У вашої ролі немає права модерації.', true); return; }
        $msg = $q['message'] ?? null;
        $chatId = isset($msg['chat']['id']) ? (int) $msg['chat']['id'] : null;
        $msgId = isset($msg['message_id']) ? (int) $msg['message_id'] : null;
        $db = App::db();
        // Повідомлення, яке ми самі не записали (наприклад, після відновлення бази), — запам'ятати, щоб оновлювати.
        if ($chatId && $msgId && !$db->value('SELECT id FROM {p}telegram_messages WHERE chat_id = ? AND message_id = ?', [$chatId, $msgId])) {
            $db->insert('telegram_messages', ['entity_type' => $type, 'entity_id' => $id, 'chat_id' => $chatId, 'message_id' => $msgId]);
        }
        switch ($d[2]) {
            case 'r':
                [, , $pending] = self::render($type, $id);
                if (!$pending) { $answer('Вже оброблено.'); self::refresh($type, $id); return; }
                if ($chatId && $msgId) {
                    try { self::api('editMessageReplyMarkup', ['chat_id' => $chatId, 'message_id' => $msgId, 'reply_markup' => ['inline_keyboard' => self::reasonsKeyboard($type, $id)]]); } catch (RuntimeException) {}
                }
                $answer('Оберіть причину відхилення');
                return;
            case 'b':
                $answer('');
                if ($chatId && $msgId) {
                    [, $kb] = self::render($type, $id);
                    try { self::api('editMessageReplyMarkup', ['chat_id' => $chatId, 'message_id' => $msgId, 'reply_markup' => ['inline_keyboard' => $kb]]); } catch (RuntimeException) {}
                }
                return;
            case 'a':
                [$ok, $text] = self::moderate($type, $id, $staff, 'approve');
                break;
            default: // 'x' — відхилити з обраною причиною
                $reasons = $type === 'vacancy' ? self::VACANCY_REASONS : self::RESUME_REASONS;
                $key = $d[4] ?? '';
                if (!isset($reasons[$key])) { $answer('Невідома причина.'); return; }
                [$ok, $text] = self::moderate($type, $id, $staff, 'reject', $reasons[$key]);
        }
        $answer($text, !$ok);
        self::refresh($type, $id);
    }

    /* ---------------- вебхук: підключення ---------------- */

    public static function connectWebhook(string $siteUrl): void
    {
        if (!str_starts_with($siteUrl, 'https://')) throw new RuntimeException('Telegram працює лише з сайтами на https://. Відкрийте адмінку за адресою з https і натисніть кнопку ще раз.');
        $c = self::config();
        if ($c['secret'] === '') $c['secret'] = Crypto::token(24);
        self::api('setWebhook', [
            'url' => rtrim($siteUrl, '/') . '/telegram/webhook', 'secret_token' => $c['secret'],
            'allowed_updates' => ['message', 'callback_query', 'my_chat_member'], 'drop_pending_updates' => true, 'max_connections' => 5,
        ]);
        $c['siteUrl'] = rtrim($siteUrl, '/');
        $c['webhookAt'] = Db::now();
        self::saveConfig($c);
    }

    public static function disconnectWebhook(): void
    {
        try { self::api('deleteWebhook', ['drop_pending_updates' => true]); } catch (RuntimeException $e) { error_log('[YEWORK] telegram: ' . $e->getMessage()); }
        $c = self::config();
        $c['webhookAt'] = null;
        self::saveConfig($c);
    }

    public static function webhookInfo(): ?array
    {
        try { $r = self::api('getWebhookInfo', [], null, 6); return is_array($r) ? $r : null; } catch (RuntimeException) { return null; }
    }
}

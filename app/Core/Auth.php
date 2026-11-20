<?php
declare(strict_types=1);

namespace App\Core;

use App\Permissions;

/**
 * Вхід, сесії, права. Токен сесії — у httpOnly-cookie, у базі лише його SHA-256.
 * Персонал (будь-яка роль) — 12 годин, звичайні користувачі — 30 днів.
 */
final class Auth
{
    public const COOKIE = 'yw_session';
    public const STAFF_TTL = 12 * 3600;
    public const USER_TTL = 30 * 86400;
    public const CODE_TTL = 600;
    public const CODE_RESEND = 60;
    public const CODE_MAX_ATTEMPTS = 5;
    public const CODES_PER_HOUR = 5;

    private ?array $user = null;
    private bool $resolved = false;
    private array $perms = [];

    public function user(): ?array
    {
        if ($this->resolved) return $this->user;
        $this->resolved = true;
        $token = $_COOKIE[self::COOKIE] ?? '';
        if (!is_string($token) || strlen($token) < 20) return null;
        $db = App::db();
        $row = $db->one('SELECT u.*, s.id AS sid, s.last_seen_at AS seen FROM {p}user_sessions s JOIN {p}users u ON u.id = s.user_id
            WHERE s.token_hash = ? AND s.revoked_at IS NULL AND s.expires_at > UTC_TIMESTAMP() AND u.deleted_at IS NULL', [hash('sha256', $token)]);
        if (!$row || $row['status'] !== 'active') return null;
        if (strtotime($row['seen'] . ' UTC') < time() - 300) $db->update('user_sessions', ['last_seen_at' => Db::now()], 'id = ?', [$row['sid']]);
        $row['roles'] = $this->rolesOf((int) $row['id']);
        $row['is_owner'] = (int) $row['owner_flag'] === 1;
        $row['is_staff'] = $row['is_owner'] || count($row['roles']) > 0;
        $this->perms = $this->permsOf((int) $row['id'], $row['is_owner']);
        return $this->user = $row;
    }

    public function id(): ?int { $u = $this->user(); return $u ? (int) $u['id'] : null; }

    public function can(string $perm): bool
    {
        $this->user();
        return isset($this->perms[$perm]);
    }

    public function isStaff(): bool { return (bool) ($this->user()['is_staff'] ?? false); }

    /** @return list<array{id:int,slug:string,name:string}> */
    public function rolesOf(int $userId): array
    {
        return App::db()->all('SELECT r.id, r.slug, r.name FROM {p}user_roles ur JOIN {p}roles r ON r.id = ur.role_id
            WHERE ur.user_id = ? AND r.deleted_at IS NULL AND (ur.expires_at IS NULL OR ur.expires_at > UTC_TIMESTAMP())', [$userId]);
    }

    /** @return array<string,true> */
    public function permsOf(int $userId, bool $isOwner): array
    {
        if ($isOwner) return array_fill_keys(array_column(Permissions::all(), 'code'), true);
        $codes = App::db()->all('SELECT DISTINCT rp.permission_code AS c FROM {p}user_roles ur JOIN {p}role_permissions rp ON rp.role_id = ur.role_id JOIN {p}roles r ON r.id = ur.role_id
            WHERE ur.user_id = ? AND r.deleted_at IS NULL AND (ur.expires_at IS NULL OR ur.expires_at > UTC_TIMESTAMP())
            UNION SELECT permission_code FROM {p}critical_grants WHERE user_id = ? AND revoked_at IS NULL AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP())', [$userId, $userId]);
        return array_fill_keys(array_column($codes, 'c'), true);
    }

    public function permissions(): array { $this->user(); return array_keys($this->perms); }

    /* ---------------- вхід / вихід ---------------- */

    public function attempt(string $identifier, string $password, Request $req): void
    {
        $db = App::db();
        $id = trim($identifier);
        RateLimit::hit('login-ip:' . $req->ip(), 20, 900, 'Забагато спроб входу з цієї адреси. Зачекайте.');
        RateLimit::hit('login-acc:' . mb_strtolower($id), 6, 900, 'Забагато спроб входу в цей акаунт. Зачекайте.');
        $c = preg_match('/^[a-zA-Z][a-zA-Z0-9._-]{2,31}$/', $id) ? null : Crypto::parseContact($id);
        $u = $c ? $this->userByContact($c) : $db->one('SELECT * FROM {p}users WHERE login = ? AND deleted_at IS NULL', [$id]);
        // Перевіряємо пароль навіть для неіснуючого логіну, щоб час відповіді не видавав, чи існує акаунт.
        $ok = Crypto::verifyPassword($password, $u['password_hash'] ?? '$2y$12$abcdefghijklmnopqrstuuJ0nH1Jm8b0h1H6wMZ7pWlqvJb7lZWa6');
        if (!$u || !$ok) {
            Audit::log('auth.login_failed', null, null, ['by' => $c['kind'] ?? 'login'], $u ? (int) $u['id'] : null);
            throw new ValidationException(['login' => ' '], 'Невірний логін або пароль.');
        }
        if ($u['status'] !== 'active') throw new ValidationException(['login' => ' '], 'Акаунт заблоковано. Зверніться до підтримки.');
        RateLimit::reset('login-acc:' . mb_strtolower($id));
        if (password_needs_rehash($u['password_hash'], defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT)) {
            $db->update('users', ['password_hash' => Crypto::hashPassword($password)], 'id = ?', [$u['id']]);
        }
        $db->update('users', ['last_login_at' => Db::now()], 'id = ?', [$u['id']]);
        Audit::log('auth.login', null, null, [], (int) $u['id']);
        $this->startSession((int) $u['id'], $req);
    }

    public function startSession(int $userId, Request $req): void
    {
        $u = App::db()->one('SELECT owner_flag FROM {p}users WHERE id = ?', [$userId]);
        $staff = ((int) ($u['owner_flag'] ?? 0)) === 1 || count($this->rolesOf($userId)) > 0;
        $ttl = $staff ? self::STAFF_TTL : self::USER_TTL;
        $token = Crypto::token();
        App::db()->insert('user_sessions', ['user_id' => $userId, 'token_hash' => hash('sha256', $token), 'ip' => $req->ip(), 'user_agent' => $req->userAgent(), 'expires_at' => Db::now($ttl)]);
        setcookie(self::COOKIE, $token, ['expires' => time() + $ttl, 'path' => App::basePath() . '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => $req->isHttps()]);
        $_COOKIE[self::COOKIE] = $token;
        $this->resolved = false;
        App::session()->regenerate();
    }

    public function logout(Request $req): void
    {
        $token = $_COOKIE[self::COOKIE] ?? '';
        if (is_string($token) && $token !== '') {
            $s = App::db()->one('SELECT user_id FROM {p}user_sessions WHERE token_hash = ?', [hash('sha256', $token)]);
            App::db()->update('user_sessions', ['revoked_at' => Db::now()], 'token_hash = ?', [hash('sha256', $token)]);
            if ($s) Audit::log('auth.logout', null, null, [], (int) $s['user_id']);
        }
        setcookie(self::COOKIE, '', ['expires' => 1, 'path' => App::basePath() . '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => $req->isHttps()]);
        $this->user = null;
    }

    public function revokeAll(int $userId, ?int $exceptSessionId = null): void
    {
        App::db()->run('UPDATE {p}user_sessions SET revoked_at = UTC_TIMESTAMP() WHERE user_id = ? AND revoked_at IS NULL AND id <> ?', [$userId, $exceptSessionId ?? 0]);
    }

    /* ---------------- контакти та коди ---------------- */

    public function userByContact(array $c): ?array
    {
        $col = $c['kind'] === 'phone' ? 'phone_hash' : 'email_hash';
        return App::db()->one("SELECT * FROM {p}users WHERE $col = ? AND deleted_at IS NULL", [Crypto::hmac($c['value'])]);
    }

    private static function codeHash(string $purpose, string $target, string $code): string
    {
        return Crypto::hmac("$purpose:$target:$code");
    }

    /** Створює 6-значний код (зберігається лише HMAC). Обмеження: раз на хвилину, 5 на годину на контакт, 10 на годину з IP. */
    public function issueCode(string $purpose, array $c, ?int $userId, Request $req): string
    {
        $db = App::db();
        $target = Crypto::hmac($c['value']);
        RateLimit::hit('code-ip:' . $req->ip(), 10, 3600, 'Забагато запитів коду з цієї адреси. Спробуйте пізніше.');
        $last = $db->one('SELECT created_at FROM {p}verification_codes WHERE target_hash = ? AND purpose = ? ORDER BY id DESC LIMIT 1', [$target, $purpose]);
        if ($last) {
            $age = time() - strtotime($last['created_at'] . ' UTC');
            if ($age < self::CODE_RESEND) throw new HttpException(429, 'Новий код можна запросити через ' . (self::CODE_RESEND - $age) . ' с.');
        }
        $n = (int) $db->value('SELECT COUNT(*) FROM {p}verification_codes WHERE target_hash = ? AND created_at > UTC_TIMESTAMP() - INTERVAL 1 HOUR', [$target]);
        if ($n >= self::CODES_PER_HOUR) throw new HttpException(429, 'Забагато кодів на цей контакт. Спробуйте за годину.');
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $db->insert('verification_codes', ['purpose' => $purpose, 'channel' => $c['channel'], 'target_hash' => $target, 'user_id' => $userId, 'code_hash' => self::codeHash($purpose, $target, $code), 'expires_at' => Db::now(self::CODE_TTL), 'ip' => $req->ip()]);
        return $code;
    }

    /** Перевіряє код. Після 5 помилок код згорає. */
    public function consumeCode(string $purpose, array $c, string $code): array
    {
        $db = App::db();
        $target = Crypto::hmac($c['value']);
        $row = $db->one('SELECT * FROM {p}verification_codes WHERE target_hash = ? AND purpose = ? AND consumed_at IS NULL AND expires_at > UTC_TIMESTAMP() ORDER BY id DESC LIMIT 1', [$target, $purpose]);
        if (!$row) throw new ValidationException(['code' => 'Код невірний або прострочений'], 'Невірний або прострочений код. Запросіть новий.');
        if ((int) $row['attempts'] >= self::CODE_MAX_ATTEMPTS) throw new ValidationException(['code' => 'Запросіть новий код'], 'Забагато невдалих спроб. Запросіть новий код.');
        if (!preg_match('/^\d{6}$/', $code) || !hash_equals($row['code_hash'], self::codeHash($purpose, $target, $code))) {
            $db->run('UPDATE {p}verification_codes SET attempts = attempts + 1 WHERE id = ?', [$row['id']]);
            throw new ValidationException(['code' => 'Невірний код'], 'Невірний код. Перевірте повідомлення.');
        }
        if (!$db->run('UPDATE {p}verification_codes SET consumed_at = UTC_TIMESTAMP() WHERE id = ? AND consumed_at IS NULL', [$row['id']])->rowCount()) {
            throw new ValidationException(['code' => 'Код уже використано'], 'Код уже використано.');
        }
        return $row;
    }
}

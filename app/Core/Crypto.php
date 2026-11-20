<?php
declare(strict_types=1);

namespace App\Core;

/** Паролі, токени, шифрування персональних даних (AES-256-GCM), HMAC для пошуку без розшифрування. */
final class Crypto
{
    public static function hashPassword(string $pw): string
    {
        return defined('PASSWORD_ARGON2ID')
            ? password_hash($pw, PASSWORD_ARGON2ID)
            : password_hash($pw, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function verifyPassword(string $pw, string $hash): bool
    {
        return password_verify($pw, $hash);
    }

    public static function token(int $bytes = 32): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }

    public static function newKey(): string
    {
        return base64_encode(random_bytes(32));
    }

    public static function hmac(string $value, ?string $key = null): string
    {
        return hash_hmac('sha256', $value, base64_decode($key ?? App::config()['appSecret']));
    }

    public static function encrypt(?string $plain, ?string $key = null): ?string
    {
        if ($plain === null || $plain === '') return null;
        $k = base64_decode($key ?? App::config()['dataKey']);
        $iv = random_bytes(12);
        $tag = '';
        $data = openssl_encrypt($plain, 'aes-256-gcm', $k, OPENSSL_RAW_DATA, $iv, $tag);
        return 'v1.' . base64_encode($iv) . '.' . base64_encode($tag) . '.' . base64_encode((string) $data);
    }

    public static function decrypt(?string $payload, ?string $key = null): ?string
    {
        if (!$payload) return null;
        [$v, $iv, $tag, $data] = array_pad(explode('.', $payload), 4, '');
        if ($v !== 'v1') return null;
        $k = base64_decode($key ?? App::config()['dataKey']);
        $r = openssl_decrypt(base64_decode($data), 'aes-256-gcm', $k, OPENSSL_RAW_DATA, base64_decode($iv), base64_decode($tag));
        return $r === false ? null : $r;
    }

    /** Український номер → +380XXXXXXXXX або null. */
    public static function normalizePhone(string $raw): ?string
    {
        $d = preg_replace('/\D/', '', $raw) ?? '';
        if (strlen($d) === 10 && $d[0] === '0') $d = '38' . $d;
        elseif (strlen($d) === 9) $d = '380' . $d;
        return preg_match('/^380\d{9}$/', $d) ? '+' . $d : null;
    }

    /** @return array{kind:'phone'|'email', value:string, channel:'sms'|'email'}|null */
    public static function parseContact(string $raw): ?array
    {
        $v = trim($raw);
        if (str_contains($v, '@')) {
            $e = mb_strtolower($v);
            return filter_var($e, FILTER_VALIDATE_EMAIL) && strlen($e) <= 254 ? ['kind' => 'email', 'value' => $e, 'channel' => 'email'] : null;
        }
        $p = self::normalizePhone($v);
        return $p ? ['kind' => 'phone', 'value' => $p, 'channel' => 'sms'] : null;
    }
}

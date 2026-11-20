<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Обмеження частоти запитів. Зберігається у файлах storage/cache/rl — працює і до встановлення, і без Redis.
 */
final class RateLimit
{
    private static function file(string $key): string
    {
        return YW_ROOT . '/storage/cache/rl/' . hash('sha256', $key) . '.json';
    }

    /** Рахує спробу; після $max спроб за $window секунд кидає 429. */
    public static function hit(string $key, int $max, int $window, string $message = 'Забагато спроб. Спробуйте пізніше.'): void
    {
        $f = self::file($key);
        $fp = @fopen($f, 'c+');
        if (!$fp) return; // не блокуємо сайт, якщо папка недоступна для запису
        flock($fp, LOCK_EX);
        $raw = stream_get_contents($fp);
        $d = $raw ? json_decode($raw, true) : null;
        $now = time();
        if (!is_array($d) || ($d['until'] ?? 0) < $now) $d = ['n' => 0, 'until' => $now + $window];
        $d['n']++;
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($d));
        flock($fp, LOCK_UN);
        fclose($fp);
        if ($d['n'] > $max) {
            $wait = max(1, $d['until'] - $now);
            throw new HttpException(429, $message . ($wait > 90 ? ' (≈' . ceil($wait / 60) . ' хв)' : " ($wait с)"));
        }
    }

    public static function reset(string $key): void
    {
        @unlink(self::file($key));
    }

    /** Прибирання старих файлів (викликається зрідка). */
    public static function gc(): void
    {
        foreach (glob(YW_ROOT . '/storage/cache/rl/*.json') ?: [] as $f) {
            if (filemtime($f) < time() - 86400) @unlink($f);
        }
    }
}

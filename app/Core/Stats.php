<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Публічна статистика: нові вакансії сьогодні, роботодавці, що шукають, відвідувачі сьогодні / за місяць.
 * Відвідувач = анонімний хеш (IP + браузер + сіль дня). Сам IP не зберігається, а сіль змінюється щодня.
 */
final class Stats
{
    public static function trackVisit(Request $req): void
    {
        if ($req->method !== 'GET' || $req->wantsJson()) return;
        $ua = $req->userAgent();
        if ($ua === '' || preg_match('/bot|crawl|spider|slurp|preview|monitor|curl|wget|python|headless/i', $ua)) return;
        if (isset($req->cookies['yw_v']) && $req->cookies['yw_v'] === gmdate('Ymd')) return; // уже враховано сьогодні
        $day = gmdate('Y-m-d');
        $hash = substr(hash_hmac('sha256', $req->ip() . '|' . $ua, $day . App::config()['appSecret']), 0, 32);
        try {
            App::db()->run('INSERT IGNORE INTO {p}daily_visitors (day, visitor_hash) VALUES (?, ?)', [$day, $hash]);
        } catch (\Throwable) {
            return;
        }
        setcookie('yw_v', gmdate('Ymd'), ['expires' => time() + 86400, 'path' => App::basePath() . '/', 'samesite' => 'Lax', 'httponly' => true]);
    }

    /** Кешується на 5 хвилин у storage/cache. */
    public static function public(): array
    {
        $f = YW_ROOT . '/storage/cache/stats.json';
        if (is_file($f) && filemtime($f) > time() - 300) {
            $d = json_decode((string) file_get_contents($f), true);
            if (is_array($d)) return $d;
        }
        $db = App::db();
        $d = [
            'vacanciesToday' => (int) $db->value("SELECT COUNT(*) FROM {p}vacancies WHERE status = 'published' AND deleted_at IS NULL AND published_at >= ?", [self::kyivDayStartUtc()]),
            'vacanciesActive' => (int) $db->value("SELECT COUNT(*) FROM {p}vacancies WHERE status = 'published' AND deleted_at IS NULL"),
            'employersHiring' => (int) $db->value("SELECT COUNT(DISTINCT employer_id) FROM {p}vacancies WHERE status = 'published' AND deleted_at IS NULL"),
            'visitorsToday' => (int) $db->value('SELECT COUNT(*) FROM {p}daily_visitors WHERE day = ?', [gmdate('Y-m-d')]),
            'visitorsMonth' => (int) $db->value('SELECT COUNT(DISTINCT visitor_hash) FROM {p}daily_visitors WHERE day >= ?', [gmdate('Y-m-01')]),
        ];
        @file_put_contents($f, json_encode($d), LOCK_EX);
        return $d;
    }

    public static function kyivDayStartUtc(): string
    {
        return gmdate('Y-m-d H:i:s', strtotime(date('Y-m-d 00:00:00')));
    }

    public static function forget(): void
    {
        @unlink(YW_ROOT . '/storage/cache/stats.json');
    }
}

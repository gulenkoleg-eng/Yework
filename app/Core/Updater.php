<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Оновлення сайту з GitHub, резервні копії та відкат.
 *
 * Звідки беруться версії:
 *  1) якщо в репозиторії є релізи (Releases) — кожен реліз це версія, опис релізу — список змін;
 *  2) якщо релізів немає — береться гілка (main): версія з app/bootstrap.php, зміни з CHANGELOG.md.
 *
 * Що робить оновлення:
 *  завантажує архів → перевіряє його → робить резервну копію файлів і бази → копіює нові файли
 *  (config/ і storage/ не чіпає) → у наступному запиті вже новий код запускає міграції бази.
 *  Якщо міграції впали — автоматично повертає все з резервної копії.
 */
final class Updater
{
    public const DEFAULT_REPO = 'gulenkoleg-eng/yework';
    public const DEFAULT_BRANCH = 'main';
    private const KEEP_BACKUPS = 6;
    private const MAX_ZIP = 60 * 1024 * 1024;
    private const CACHE_TTL = 3600;
    /** Файли й папки сайту, які входять у резервну копію файлів. */
    private const SKIP_TOP = ['config', 'storage', '.git', '.github', 'node_modules', 'dist'];

    // ---------- Шляхи ----------

    public static function dir(string $sub = ''): string
    {
        $d = YW_ROOT . '/storage/updates' . ($sub !== '' ? '/' . $sub : '');
        if (!is_dir($d)) @mkdir($d, 0755, true);
        return $d;
    }

    public static function backupsDir(): string
    {
        $d = YW_ROOT . '/storage/backups';
        if (!is_dir($d)) {
            @mkdir($d, 0755, true);
            @file_put_contents($d . '/.htaccess', "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Deny from all\n</IfModule>\n");
            @file_put_contents($d . '/index.php', "<?php http_response_code(403);\n");
        }
        return $d;
    }

    private static function cachePath(): string { return YW_ROOT . '/storage/cache/updates.json'; }
    public static function pendingPath(): string { return self::dir() . '/pending.json'; }
    public static function maintenancePath(): string { return YW_ROOT . '/storage/maintenance.json'; }

    // ---------- Налаштування підключення ----------

    /** @return array{repo:string,branch:string,tokenEnc:?string,tokenHint:?string} */
    public static function config(): array
    {
        $c = (array) App::setting('updates.github', []);
        return [
            'repo' => (string) ($c['repo'] ?? self::DEFAULT_REPO) ?: self::DEFAULT_REPO,
            'branch' => (string) ($c['branch'] ?? self::DEFAULT_BRANCH) ?: self::DEFAULT_BRANCH,
            'tokenEnc' => $c['tokenEnc'] ?? null,
            'tokenHint' => $c['tokenHint'] ?? null,
        ];
    }

    public static function saveConfig(string $repo, string $branch, ?string $token, bool $clearToken, ?int $actorId): void
    {
        $repo = trim($repo);
        if (preg_match('#github\.com/([\w.-]+/[\w.-]+?)(?:\.git)?/?$#i', $repo, $m)) $repo = $m[1];
        if (!preg_match('#^[\w.-]+/[\w.-]+$#', $repo)) throw new ValidationException(['repo' => 'Вкажіть репозиторій у вигляді власник/назва, наприклад ' . self::DEFAULT_REPO]);
        $branch = trim($branch) ?: self::DEFAULT_BRANCH;
        if (!preg_match('#^[\w./-]{1,100}$#', $branch)) throw new ValidationException(['branch' => 'Некоректна назва гілки']);
        $prev = self::config();
        $v = ['repo' => $repo, 'branch' => $branch, 'tokenEnc' => $prev['tokenEnc'], 'tokenHint' => $prev['tokenHint']];
        $token = $token !== null ? trim($token) : '';
        if ($clearToken) { $v['tokenEnc'] = null; $v['tokenHint'] = null; }
        if ($token !== '') {
            if (!preg_match('#^[A-Za-z0-9_]{20,255}$#', $token)) throw new ValidationException(['token' => 'Це не схоже на токен GitHub. Він починається з github_pat_ або ghp_.']);
            $v['tokenEnc'] = Crypto::encrypt($token);
            $v['tokenHint'] = substr($token, 0, 11) . '…' . substr($token, -4);
        }
        App::setSetting('updates.github', $v, $actorId);
        @unlink(self::cachePath());
    }

    private static function token(): ?string
    {
        $enc = self::config()['tokenEnc'];
        if (!$enc) return null;
        try { return Crypto::decrypt($enc) ?: null; } catch (Throwable) { return null; }
    }

    // ---------- Що потрібно від сервера ----------

    /** @return list<string> Проблеми, через які оновлення неможливе. */
    public static function serverProblems(): array
    {
        $p = [];
        if (!class_exists(ZipArchive::class)) $p[] = 'На хостингу вимкнено розширення PHP «zip». Увімкніть його в налаштуваннях PHP (там само, де вибирали версію PHP).';
        if (!function_exists('curl_init') && !filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) $p[] = 'Сайт не може звертатися до інших сайтів: увімкніть розширення PHP «curl».';
        foreach (['', 'app', 'app/Core', 'storage'] as $d) {
            $path = YW_ROOT . ($d !== '' ? '/' . $d : '');
            if (is_dir($path) && !is_writable($path)) { $p[] = 'Немає дозволу на запис у папку ' . ($d ?: 'сайту') . '. Встановіть права 755 через FTP.'; break; }
        }
        return $p;
    }

    // ---------- Запити до GitHub ----------

    private static function api(): string
    {
        return rtrim((string) (getenv('YW_UPDATES_API') ?: 'https://api.github.com'), '/');
    }

    /** @return array{status:int, body:string} */
    private static function http(string $url, string $accept = 'application/vnd.github+json', ?string $saveTo = null): array
    {
        $headers = ['Accept: ' . $accept, 'User-Agent: YEWORK-Updater', 'X-GitHub-Api-Version: 2022-11-28'];
        if ($t = self::token()) $headers[] = 'Authorization: Bearer ' . $t;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            $fh = $saveTo ? fopen($saveTo, 'wb') : null;
            curl_setopt_array($ch, [
                CURLOPT_HTTPHEADER => $headers, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5,
                CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_TIMEOUT => $saveTo ? 180 : 30, CURLOPT_FAILONERROR => false,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
            ]);
            if ($fh) { curl_setopt($ch, CURLOPT_FILE, $fh); } else { curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); }
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $err = curl_error($ch);
            if ($fh) fclose($fh);
            if ($body === false) throw new RuntimeException('Немає зв’язку з GitHub: ' . $err);
            if ($saveTo && $status >= 400) { $body = (string) file_get_contents($saveTo); @unlink($saveTo); }
            return ['status' => $status, 'body' => $saveTo && $status < 400 ? '' : (string) $body];
        }
        $ctx = stream_context_create(['http' => ['header' => implode("\r\n", $headers), 'timeout' => $saveTo ? 180 : 30, 'ignore_errors' => true, 'follow_location' => 1]]);
        $body = @file_get_contents($url, false, $ctx);
        $status = 0;
        foreach ($http_response_header ?? [] as $h) if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) $status = (int) $m[1];
        if ($body === false) throw new RuntimeException('Немає зв’язку з GitHub.');
        if ($saveTo && $status < 400) { file_put_contents($saveTo, $body); return ['status' => $status, 'body' => '']; }
        return ['status' => $status, 'body' => $body];
    }

    private static function apiJson(string $path): mixed
    {
        $r = self::http(self::api() . $path);
        if ($r['status'] === 200) return json_decode($r['body'], true);
        if ($r['status'] === 404) return null;
        throw new RuntimeException(self::explainStatus($r['status'], $r['body']));
    }

    private static function apiRaw(string $path): ?string
    {
        $r = self::http(self::api() . $path, 'application/vnd.github.raw+json');
        if ($r['status'] === 200) return $r['body'];
        if ($r['status'] === 404) return null;
        throw new RuntimeException(self::explainStatus($r['status'], $r['body']));
    }

    private static function explainStatus(int $status, string $body): string
    {
        return match (true) {
            $status === 401 => 'GitHub не прийняв токен: він недійсний або закінчився термін дії. Створіть новий токен і збережіть його в блоці «Підключення до GitHub».',
            $status === 403 && str_contains($body, 'rate limit') => 'GitHub тимчасово обмежив кількість перевірок. Спробуйте за годину або додайте токен.',
            $status === 403 => 'Токен не має доступу до репозиторію. Перевірте, що в ньому вибрано репозиторій і право «Contents: Read-only».',
            default => 'GitHub відповів помилкою ' . $status . '. Спробуйте пізніше.',
        };
    }

    // ---------- Перевірка оновлень ----------

    /**
     * @return array{checkedAt:int, mode:string, latest:?string, versions:list<array{version:string,title:string,date:?string,notes:string,zip:string}>, error:?string}
     */
    public static function check(bool $force = false): array
    {
        if (!$force && is_file(self::cachePath())) {
            $c = json_decode((string) file_get_contents(self::cachePath()), true);
            if (is_array($c) && ($c['checkedAt'] ?? 0) > time() - self::CACHE_TTL && ($c['current'] ?? '') === YW_VERSION) return $c;
        }
        $cfg = self::config();
        $repo = $cfg['repo'];
        $res = ['checkedAt' => time(), 'current' => YW_VERSION, 'mode' => 'releases', 'latest' => null, 'versions' => [], 'error' => null];
        try {
            $releases = self::apiJson('/repos/' . $repo . '/releases?per_page=30');
            if ($releases === null) throw new RuntimeException(self::token()
                ? 'Репозиторій ' . $repo . ' не знайдено, або токен не має до нього доступу.'
                : 'Репозиторій ' . $repo . ' закритий (приватний). Додайте токен GitHub у блоці «Підключення до GitHub» нижче.');
            foreach ((array) $releases as $r) {
                if (!empty($r['draft']) || !empty($r['prerelease'])) continue;
                $v = self::normVersion((string) ($r['tag_name'] ?? ''));
                if ($v === null) continue;
                $res['versions'][] = ['version' => $v, 'title' => (string) ($r['name'] ?: $r['tag_name']), 'date' => $r['published_at'] ?? null,
                    'notes' => (string) ($r['body'] ?? ''), 'zip' => '/repos/' . $repo . '/zipball/' . rawurlencode((string) $r['tag_name']),
                    'url' => self::safeGithubUrl((string) ($r['html_url'] ?? '')), 'prs' => self::refsIn((string) ($r['name'] ?? ''))['prs'], 'commits' => []];
            }
            if (!$res['versions']) {
                // Релізів немає — беремо гілку.
                $res['mode'] = 'branch';
                $commit = self::apiJson('/repos/' . $repo . '/commits/' . rawurlencode($cfg['branch']));
                if (!$commit || empty($commit['sha'])) throw new RuntimeException('У репозиторії немає гілки «' . $cfg['branch'] . '».');
                $sha = (string) $commit['sha'];
                $boot = (string) self::apiRaw('/repos/' . $repo . '/contents/app/bootstrap.php?ref=' . $sha);
                $latest = preg_match("/YW_VERSION\s*=\s*'([^']+)'/", $boot, $m) ? self::normVersion($m[1]) : null;
                if ($latest === null) throw new RuntimeException('Не вдалося визначити версію в гілці «' . $cfg['branch'] . '».');
                $changelog = (string) self::apiRaw('/repos/' . $repo . '/contents/CHANGELOG.md?ref=' . $sha);
                $sections = self::parseChangelog($changelog);
                if (!isset($sections[$latest])) $sections = [$latest => ['title' => $latest, 'date' => null, 'notes' => '', 'prs' => [], 'commits' => []]] + $sections;
                foreach ($sections as $v => $s) {
                    $res['versions'][] = ['version' => $v, 'title' => $s['title'], 'date' => $s['date'] ?? ($v === $latest ? ($commit['commit']['committer']['date'] ?? null) : null),
                        'notes' => $s['notes'], 'zip' => $v === $latest ? '/repos/' . $repo . '/zipball/' . $sha : '',
                        'url' => null, 'prs' => $s['prs'], 'commits' => $s['commits']];
                }
            }
            usort($res['versions'], static fn ($a, $b) => version_compare($b['version'], $a['version']));
            $res['latest'] = $res['versions'][0]['version'] ?? null;
        } catch (Throwable $e) {
            $res['error'] = $e->getMessage();
        }
        @file_put_contents(self::cachePath(), json_encode($res, JSON_UNESCAPED_UNICODE), LOCK_EX);
        return $res;
    }

    /** Остання перевірка з кешу без звернення до GitHub (для значка в меню). */
    public static function cachedNewer(): ?string
    {
        $c = @json_decode((string) @file_get_contents(self::cachePath()), true);
        $l = is_array($c) ? ($c['latest'] ?? null) : null;
        return $l && version_compare($l, YW_VERSION, '>') ? $l : null;
    }

    public static function normVersion(string $tag): ?string
    {
        return preg_match('/^v?(\d+\.\d+\.\d+(?:[.-][0-9A-Za-z.-]+)?)$/', trim($tag), $m) ? $m[1] : null;
    }

    /** CHANGELOG.md → [версія => [title, date, notes]]. Розділи виду «## 0.3.0 — 2026-11-21». */
    public static function parseChangelog(string $md): array
    {
        $out = [];
        $cur = null;
        foreach (preg_split('/\R/u', $md) as $line) {
            if (preg_match('/^##\s+\[?v?(\d+\.\d+\.\d+[^\]\s]*)\]?\s*(?:[—–-]\s*(.*))?$/u', $line, $m)) {
                $cur = $m[1];
                $rest = trim($m[2] ?? '');
                $date = preg_match('/\d{4}-\d{2}-\d{2}/', $rest, $d) ? $d[0] : null;
                $out[$cur] = ['title' => $cur, 'date' => $date, 'notes' => ''] + self::refsIn($rest);
                continue;
            }
            if ($cur !== null) $out[$cur]['notes'] .= $line . "\n";
        }
        foreach ($out as &$s) $s['notes'] = trim($s['notes']);
        return $out;
    }

    /** Посилання з заголовка версії: «PR #3», «коміт bf59d09». @return array{prs:list<int>, commits:list<string>} */
    public static function refsIn(string $s): array
    {
        preg_match_all('/#(\d{1,6})\b/', $s, $p);
        preg_match_all('/\b(?:коміт|commit)\s+([0-9a-f]{7,40})\b/iu', $s, $c);
        return ['prs' => array_values(array_unique(array_map('intval', $p[1]))), 'commits' => array_values(array_unique($c[1]))];
    }

    /** Адреса репозиторію на GitHub (для посилань в адмінці). */
    public static function repoUrl(): string
    {
        return 'https://github.com/' . self::config()['repo'];
    }

    private static function safeGithubUrl(string $u): ?string
    {
        return preg_match('#^https://github\.com/[\w./%-]+$#', $u) ? $u : null;
    }

    /** Посилання на GitHub для версії: реліз, PR (опис і змінені файли), коміти. @return list<array{0:string,1:string}> */
    public static function versionLinks(array $v): array
    {
        $base = self::repoUrl();
        $out = [];
        if (!empty($v['url'])) $out[] = ['Реліз на GitHub', (string) $v['url']];
        foreach ($v['prs'] ?? [] as $n) {
            $out[] = ['Опис змін · PR #' . (int) $n, $base . '/pull/' . (int) $n];
            $out[] = ['Змінені файли', $base . '/pull/' . (int) $n . '/files'];
        }
        foreach ($v['commits'] ?? [] as $c) $out[] = ['Коміт ' . substr((string) $c, 0, 7), $base . '/commit/' . $c];
        return $out;
    }

    /** Список змін поточної (встановленої) версії з локального CHANGELOG.md. */
    public static function localChangelog(): array
    {
        $f = yw_file('CHANGELOG.md');
        return is_file($f) ? self::parseChangelog((string) file_get_contents($f)) : [];
    }

    /** Дуже простий Markdown → HTML (списки, заголовки, жирний, код, посилання). Все екранується. */
    public static function markdown(string $md): string
    {
        $html = '';
        $inList = false;
        foreach (preg_split('/\R/u', trim($md)) as $line) {
            $t = rtrim($line);
            if (preg_match('/^\s*[-*]\s+(.*)$/u', $t, $m)) {
                if (!$inList) { $html .= '<ul>'; $inList = true; }
                $html .= '<li>' . self::inline($m[1]) . '</li>';
                continue;
            }
            if ($inList) { $html .= '</ul>'; $inList = false; }
            if ($t === '') continue;
            if (preg_match('/^#{1,6}\s+(.*)$/u', $t, $m)) { $html .= '<h4>' . self::inline($m[1]) . '</h4>'; continue; }
            $html .= '<p>' . self::inline($t) . '</p>';
        }
        if ($inList) $html .= '</ul>';
        return $html;
    }

    private static function inline(string $s): string
    {
        $s = e($s);
        $s = preg_replace('/\*\*(.+?)\*\*/u', '<b>$1</b>', $s) ?? $s;
        $s = preg_replace('/`([^`]+)`/u', '<code>$1</code>', $s) ?? $s;
        return preg_replace('/\[([^\]]+)\]\((https:\/\/[^)\s]+)\)/u', '<a href="$2" target="_blank" rel="noopener">$1</a>', $s) ?? $s;
    }

    // ---------- Блокування ----------

    /** @template T @param callable():T $fn @return T */
    private static function locked(callable $fn): mixed
    {
        $fh = fopen(self::dir() . '/.lock', 'c');
        if (!$fh || !flock($fh, LOCK_EX | LOCK_NB)) throw new RuntimeException('Зараз уже йде оновлення або відновлення. Зачекайте хвилину й оновіть сторінку.');
        try { return $fn(); } finally { flock($fh, LOCK_UN); fclose($fh); }
    }

    private static function prepareRuntime(): void
    {
        @set_time_limit(600);
        @ignore_user_abort(true);
        @ini_set('memory_limit', '256M');
    }

    public static function maintenance(bool $on, string $why = 'update'): void
    {
        if ($on) @file_put_contents(self::maintenancePath(), json_encode(['since' => time(), 'why' => $why]), LOCK_EX);
        else @unlink(self::maintenancePath());
    }

    // ---------- Оновлення ----------

    /**
     * Крок 1 (старий код): завантажити, перевірити, зробити копію, скопіювати файли.
     * Після нього потрібен окремий запит на finish(), щоб міграції виконав уже новий код.
     */
    public static function install(string $version, ?int $actorId): array
    {
        if ($p = self::serverProblems()) throw new RuntimeException(implode(' ', $p));
        $info = self::check(true);
        if ($info['error']) throw new RuntimeException($info['error']);
        $target = null;
        foreach ($info['versions'] as $v) if ($v['version'] === $version && $v['zip'] !== '') $target = $v;
        if (!$target) throw new RuntimeException('Версію ' . $version . ' не знайдено на GitHub. Натисніть «Перевірити оновлення».');
        if (version_compare($version, YW_VERSION, '<=')) throw new RuntimeException('Версія ' . $version . ' не новіша за встановлену (' . YW_VERSION . '). Щоб повернутися назад, скористайтеся резервною копією.');

        return self::locked(function () use ($target, $version, $actorId) {
            self::prepareRuntime();
            self::cleanWork();
            $zip = self::dir('work') . '/package.zip';
            $r = self::http(self::api() . $target['zip'], 'application/vnd.github+json', $zip);
            if ($r['status'] >= 400 || !is_file($zip)) throw new RuntimeException(self::explainStatus($r['status'], $r['body']));
            if (filesize($zip) > self::MAX_ZIP) throw new RuntimeException('Архів оновлення завеликий.');
            $src = self::extract($zip, self::dir('work') . '/pkg');
            $pkgVersion = self::packageVersion($src);
            if ($pkgVersion !== $version) throw new RuntimeException('В архіві версія ' . ($pkgVersion ?? 'невідома') . ', а очікувалась ' . $version . '. Оновлення скасовано, сайт не змінено.');

            $backup = self::createBackup('update', 'Перед оновленням до ' . $version, $actorId);
            self::maintenance(true, 'update');
            try {
                $changes = self::copyTree($src);
                self::updateBackupMeta($backup, ['to' => $version, 'added' => $changes['added'], 'replaced' => count($changes['replaced']), 'removedDupes' => $changes['removed']]);
            } catch (Throwable $e) {
                // Файли могли скопіюватися частково — повертаємо як було.
                try { self::restoreFiles($backup); } catch (Throwable) {}
                self::maintenance(false);
                throw new RuntimeException('Не вдалося скопіювати файли (' . $e->getMessage() . '). Сайт повернуто до попереднього стану.');
            }
            file_put_contents(self::pendingPath(), json_encode(['backup' => $backup, 'from' => YW_VERSION, 'to' => $version, 'by' => $actorId, 'at' => time()]), LOCK_EX);
            self::cleanWork();
            if (function_exists('opcache_reset')) @opcache_reset();
            return ['backup' => $backup, 'to' => $version];
        });
    }

    public static function pending(): ?array
    {
        $p = @json_decode((string) @file_get_contents(self::pendingPath()), true);
        return is_array($p) ? $p : null;
    }

    /** Крок 2 (новий код): міграції бази, синхронізація прав. Якщо щось не так — автоматичний відкат. */
    public static function finish(?int $actorId): array
    {
        $p = self::pending();
        if (!$p) return ['ok' => true, 'nothing' => true];
        return self::locked(function () use ($p, $actorId) {
            self::prepareRuntime();
            $db = App::db();
            $before = self::appliedMigrations($db);
            try {
                $warnings = Installer::upgrade($db);
                $applied = array_values(array_diff(self::appliedMigrations($db), $before));
                self::updateBackupMeta($p['backup'], ['migrations' => $applied, 'finishedAt' => gmdate('c')]);
                self::markVersion();
                @unlink(self::pendingPath());
                self::maintenance(false);
                Audit::log('system.updated', 'version', $p['to'], ['from' => $p['from'], 'to' => $p['to'], 'backup' => $p['backup'], 'migrations' => $applied, 'warnings' => $warnings], $actorId);
                @unlink(self::cachePath());
                Telegram::afterVersionChange();
                return ['ok' => true, 'from' => $p['from'], 'to' => $p['to'], 'migrations' => $applied, 'warnings' => $warnings];
            } catch (Throwable $e) {
                error_log('[YEWORK] update finish failed: ' . $e->getMessage());
                @unlink(self::pendingPath());
                self::doRestore($p['backup'], true, $actorId, false);
                self::maintenance(false);
                Audit::log('system.update_failed', 'version', $p['to'], ['from' => $p['from'], 'to' => $p['to'], 'error' => mb_substr($e->getMessage(), 0, 500)], $actorId);
                return ['ok' => false, 'from' => $p['from'], 'to' => $p['to'], 'error' => $e->getMessage()];
            }
        });
    }

    private static function appliedMigrations(Db $db): array
    {
        try { return array_column($db->all('SELECT name FROM {p}schema_migrations'), 'name'); } catch (Throwable) { return []; }
    }

    /** Після завантаження файлів по FTP: один раз запускає міграції й синхронізацію прав для нової версії. */
    public static function autoUpgrade(): void
    {
        $f = YW_ROOT . '/storage/cache/version.txt';
        $have = @file_get_contents($f);
        if ($have === YW_VERSION || is_file(self::pendingPath())) return;
        $fh = @fopen(YW_ROOT . '/storage/cache/version.lock', 'c');
        if (!$fh || !flock($fh, LOCK_EX | LOCK_NB)) return;
        try {
            if (@file_get_contents($f) === YW_VERSION) return;
            Installer::upgrade(App::db());
            if ($have !== false) Audit::log('system.upgraded', 'version', YW_VERSION, ['from' => trim((string) $have), 'to' => YW_VERSION, 'auto' => true], null);
            self::markVersion();
            if ($have !== false) Telegram::afterVersionChange();
        } catch (Throwable $e) {
            error_log('[YEWORK] auto upgrade failed: ' . $e->getMessage());
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }

    private static function markVersion(): void
    {
        @file_put_contents(YW_ROOT . '/storage/cache/version.txt', YW_VERSION, LOCK_EX);
    }

    private static function cleanWork(): void
    {
        self::rrmdir(self::dir() . '/work');
    }

    /** Безпечне розпакування. Повертає папку, у якій лежить index.php сайту. */
    private static function extract(string $zipPath, string $to): string
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) throw new RuntimeException('Архів оновлення пошкоджено.');
        @mkdir($to, 0755, true);
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            $norm = str_replace('\\', '/', $name);
            if ($norm === '' || str_starts_with($norm, '/') || preg_match('#(^|/)\.\.(/|$)#', $norm) || str_contains($norm, ':') || str_contains($norm, "\0")) {
                $zip->close();
                throw new RuntimeException('Архів містить небезпечний шлях: ' . $name);
            }
            $dest = $to . '/' . $norm;
            if (str_ends_with($norm, '/')) { @mkdir($dest, 0755, true); continue; }
            @mkdir(dirname($dest), 0755, true);
            $in = $zip->getStream($name);
            if (!$in) { $zip->close(); throw new RuntimeException('Не вдалося прочитати ' . $name); }
            $out = fopen($dest, 'wb');
            stream_copy_to_stream($in, $out);
            fclose($in);
            fclose($out);
        }
        $zip->close();
        if (is_file($to . '/index.php') && is_file($to . '/app/bootstrap.php')) return $to;
        $dirs = array_values(array_filter(glob($to . '/*') ?: [], 'is_dir'));
        if (count($dirs) === 1 && is_file($dirs[0] . '/index.php') && is_file($dirs[0] . '/app/bootstrap.php')) return $dirs[0];
        throw new RuntimeException('Це не архів YEWORK: немає index.php або app/bootstrap.php.');
    }

    private static function packageVersion(string $src): ?string
    {
        $b = (string) @file_get_contents($src . '/app/bootstrap.php');
        return preg_match("/YW_VERSION\s*=\s*'([^']+)'/", $b, $m) ? self::normVersion($m[1]) : null;
    }

    /**
     * Копіює нові файли поверх старих. config/ і storage/ — лише відсутні файли (захисні .htaccess).
     * Також прибирає старі копії файлів з малими літерами (після FTP-клієнта), якщо поруч є правильний файл.
     * @return array{added:list<string>, replaced:list<string>, removed:list<string>}
     */
    private static function copyTree(string $src): array
    {
        $added = []; $replaced = []; $removed = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
        $files = [];
        foreach ($it as $f) {
            $rel = substr(str_replace('\\', '/', $f->getPathname()), strlen($src) + 1);
            $top = explode('/', $rel)[0];
            if (in_array($top, ['.git', '.github', 'node_modules', 'dist'], true) || $rel === '.gitignore') continue;
            if ($f->isDir()) continue;
            $files[] = $rel;
        }
        // index.php першим: новий index.php одразу показує відвідувачам «сайт оновлюється».
        usort($files, static fn ($a, $b) => ($b === 'index.php') <=> ($a === 'index.php') ?: strcmp($a, $b));
        foreach ($files as $rel) {
            $top = explode('/', $rel)[0];
            $dest = YW_ROOT . '/' . $rel;
            $exists = is_file($dest);
            if (in_array($top, ['config', 'storage'], true) && ($exists || is_file(YW_ROOT . '/' . strtolower($rel)))) continue;
            if (!is_dir(dirname($dest)) && !@mkdir(dirname($dest), 0755, true)) throw new RuntimeException('не вдалося створити папку ' . dirname($rel));
            $tmp = $dest . '.yw-new';
            if ($rel === '.htaccess' && $exists) {
                $ok = @file_put_contents($tmp, self::mergeHtaccess((string) file_get_contents($dest), (string) file_get_contents($src . '/' . $rel))) !== false;
            } else {
                $ok = @copy($src . '/' . $rel, $tmp);
            }
            if (!$ok) throw new RuntimeException('не вдалося записати ' . $rel);
            if (!@rename($tmp, $dest)) { @unlink($tmp); throw new RuntimeException('не вдалося замінити ' . $rel); }
            if ($exists) $replaced[] = $rel; else $added[] = $rel;
        }
        // Дублікати в нижньому регістрі (app/core/http.php поруч з app/Core/Http.php).
        foreach ($files as $rel) {
            $top = explode('/', $rel)[0];
            if (in_array($top, ['config', 'storage'], true)) continue;
            $lower = strtolower($rel);
            if ($lower === $rel) continue;
            $a = YW_ROOT . '/' . $rel; $b = YW_ROOT . '/' . $lower;
            if (is_file($b) && is_file($a) && @fileinode($a) !== @fileinode($b)) { if (@unlink($b)) $removed[] = $lower; }
        }
        if ($removed) self::removeEmptyDirs(['app', 'assets', 'database', 'docs']);
        return ['added' => $added, 'replaced' => $replaced, 'removed' => $removed];
    }

    /**
     * Хостинг інколи сам дописує в .htaccess вибір версії PHP (AddHandler, блоки «# BEGIN … # END»).
     * Такі рядки переносимо в новий .htaccess, щоб після оновлення не змінилась версія PHP.
     */
    public static function mergeHtaccess(string $old, string $new): string
    {
        $keep = [];
        $lines = preg_split('/\R/u', $old) ?: [];
        $inBlock = false;
        foreach ($lines as $l) {
            $t = trim($l);
            if (!$inBlock && preg_match('/^#.*\bBEGIN\b/', $t) && !preg_match('/YEWORK/i', $t)) $inBlock = true;
            if ($inBlock) {
                $keep[] = $l;
                if (preg_match('/^#.*\bEND\b/', $t)) $inBlock = false;
                continue;
            }
            if (preg_match('/^(AddHandler|SetHandler|AddType\s+application\/x-httpd|php_value|php_flag|php_admin_|suPHP_)/i', $t) && !str_contains($new, $t)) $keep[] = $l;
        }
        $keep = array_values(array_filter($keep, static fn ($l) => trim($l) !== ''));
        if (!$keep) return $new;
        return "# Рядки, які додав хостинг (збережено під час оновлення YEWORK)\n" . implode("\n", $keep) . "\n\n" . $new;
    }

    // ---------- Резервні копії ----------

    /** @return string id копії */
    public static function createBackup(string $kind, string $note, ?int $actorId): string
    {
        if (!class_exists(ZipArchive::class)) throw new RuntimeException('На хостингу вимкнено розширення PHP «zip».');
        self::prepareRuntime();
        $id = gmdate('Ymd-His') . '-' . preg_replace('/[^0-9a-z.]/i', '', YW_VERSION);
        $dir = self::backupsDir() . '/' . $id;
        if (is_dir($dir)) { $id .= '-' . substr(bin2hex(random_bytes(2)), 0, 3); $dir = self::backupsDir() . '/' . $id; }
        if (!@mkdir($dir, 0755, true)) throw new RuntimeException('Не вдалося створити папку резервної копії в storage/backups.');
        try {
            $files = self::zipSite($dir . '/files.zip');
            $tables = self::dumpDb($dir . '/db.sql');
        } catch (Throwable $e) {
            self::rrmdir($dir);
            throw new RuntimeException('Не вдалося зробити резервну копію: ' . $e->getMessage());
        }
        $meta = ['id' => $id, 'kind' => $kind, 'note' => $note, 'version' => YW_VERSION, 'createdAt' => gmdate('c'), 'by' => $actorId,
            'files' => $files, 'tables' => $tables, 'size' => (int) filesize($dir . '/files.zip') + (int) filesize($dir . '/db.sql')];
        file_put_contents($dir . '/meta.json', json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
        self::pruneBackups($id);
        return $id;
    }

    private static function updateBackupMeta(string $id, array $add): void
    {
        $f = self::backupsDir() . '/' . $id . '/meta.json';
        $m = json_decode((string) @file_get_contents($f), true) ?: [];
        file_put_contents($f, json_encode([...$m, ...$add], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    }

    /** @return list<array> від найновішої */
    public static function backups(): array
    {
        $out = [];
        foreach (glob(self::backupsDir() . '/*/meta.json') ?: [] as $f) {
            $m = json_decode((string) file_get_contents($f), true);
            if (is_array($m) && is_file(dirname($f) . '/files.zip')) $out[] = $m;
        }
        usort($out, static fn ($a, $b) => strcmp($b['id'], $a['id']));
        return $out;
    }

    public static function backup(string $id): ?array
    {
        if (!preg_match('/^[0-9A-Za-z.-]+$/', $id)) return null;
        foreach (self::backups() as $b) if ($b['id'] === $id) return $b;
        return null;
    }

    public static function deleteBackup(string $id): void
    {
        if (!self::backup($id)) throw new RuntimeException('Резервну копію не знайдено.');
        self::rrmdir(self::backupsDir() . '/' . $id);
    }

    private static function pruneBackups(string $keepId): void
    {
        $list = self::backups();
        foreach (array_slice($list, self::KEEP_BACKUPS) as $b) if ($b['id'] !== $keepId) self::rrmdir(self::backupsDir() . '/' . $b['id']);
    }

    /** Файли сайту в zip (без config/ і storage/). @return int кількість файлів */
    private static function zipSite(string $path): int
    {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('не вдалося створити архів');
        $n = 0;
        foreach (self::siteFiles() as $rel) { $zip->addFile(YW_ROOT . '/' . $rel, $rel); $n++; }
        if (!$zip->close()) throw new RuntimeException('не вдалося записати архів файлів');
        return $n;
    }

    /** Відносні шляхи файлів сайту, що належать YEWORK (без config/, storage/ і чужих папок у public_html). */
    private static function siteFiles(): array
    {
        $out = [];
        $rootFiles = ['index.php', '.htaccess', 'readme.md', 'changelog.md', 'license', 'license.md'];
        foreach (scandir(YW_ROOT) ?: [] as $e) {
            if ($e === '.' || $e === '..') continue;
            $p = YW_ROOT . '/' . $e;
            if (is_file($p) && in_array(strtolower($e), $rootFiles, true)) $out[] = $e;
            if (is_dir($p) && in_array(strtolower($e), ['app', 'assets', 'database', 'docs'], true)) {
                $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($p, \FilesystemIterator::SKIP_DOTS));
                foreach ($it as $f) if ($f->isFile() && !str_ends_with($f->getFilename(), '.yw-new')) $out[] = substr(str_replace('\\', '/', $f->getPathname()), strlen(YW_ROOT) + 1);
            }
        }
        return $out;
    }

    /** Дамп таблиць сайту (з префіксом). Кожна інструкція закінчується рядком «-- yw:end». @return list<string> */
    private static function dumpDb(string $path): array
    {
        $db = App::db();
        $pdo = $db->pdo;
        $tables = self::siteTables($db);
        $fh = fopen($path, 'wb');
        $w = static function (string $sql) use ($fh): void { fwrite($fh, $sql . ";\n-- yw:end\n"); };
        fwrite($fh, '-- YEWORK ' . YW_VERSION . ' резервна копія бази ' . gmdate('c') . " UTC\n");
        foreach ($tables as $t) {
            $create = $pdo->query('SHOW CREATE TABLE `' . $t . '`')->fetch(\PDO::FETCH_NUM)[1];
            $w('DROP TABLE IF EXISTS `' . $t . '`');
            $w($create);
            $cols = $pdo->query('SHOW COLUMNS FROM `' . $t . '`')->fetchAll(\PDO::FETCH_ASSOC);
            $bin = [];
            foreach ($cols as $c) $bin[$c['Field']] = (bool) preg_match('/binary|blob/i', $c['Type']);
            $names = '`' . implode('`,`', array_column($cols, 'Field')) . '`';
            $stmt = $pdo->query('SELECT * FROM `' . $t . '`', \PDO::FETCH_ASSOC);
            $batch = [];
            foreach ($stmt as $row) {
                $vals = [];
                foreach ($row as $k => $v) {
                    $vals[] = $v === null ? 'NULL' : (is_int($v) || is_float($v) ? (string) $v : (!empty($bin[$k]) ? '0x' . bin2hex((string) $v) : $pdo->quote((string) $v)));
                }
                $batch[] = '(' . implode(',', $vals) . ')';
                if (count($batch) >= 200) { $w('INSERT INTO `' . $t . '` (' . $names . ') VALUES ' . implode(",\n", $batch)); $batch = []; }
            }
            if ($batch) $w('INSERT INTO `' . $t . '` (' . $names . ') VALUES ' . implode(",\n", $batch));
        }
        // Тригери (захист журналу дій).
        foreach ($tables as $t) {
            try {
                foreach ($pdo->query("SHOW TRIGGERS LIKE " . $pdo->quote($t))->fetchAll(\PDO::FETCH_ASSOC) as $tr) {
                    $w('CREATE TRIGGER `' . $tr['Trigger'] . '` ' . $tr['Timing'] . ' ' . $tr['Event'] . ' ON `' . $t . '` FOR EACH ROW ' . $tr['Statement']);
                }
            } catch (Throwable) {}
        }
        fclose($fh);
        return $tables;
    }

    private static function siteTables(Db $db): array
    {
        $rows = $db->all('SELECT table_name AS t FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = \'BASE TABLE\'');
        $tables = array_column($rows, 't');
        if ($db->prefix !== '') $tables = array_values(array_filter($tables, static fn ($t) => str_starts_with($t, $db->prefix)));
        sort($tables);
        return $tables;
    }

    // ---------- Відновлення ----------

    /**
     * Повертає сайт до стану з резервної копії. Перед цим автоматично робить копію поточного стану,
     * тож відновлення теж можна скасувати.
     */
    public static function restore(string $id, bool $withDb, ?int $actorId): array
    {
        $b = self::backup($id);
        if (!$b) throw new RuntimeException('Резервну копію не знайдено.');
        if (is_file(self::pendingPath())) @unlink(self::pendingPath());
        return self::locked(function () use ($id, $withDb, $actorId, $b) {
            self::prepareRuntime();
            $safety = self::createBackup('restore', 'Перед відновленням копії ' . $id, $actorId);
            self::doRestore($id, $withDb, $actorId, true, $safety);
            return ['version' => $b['version'], 'safety' => $safety];
        });
    }

    private static function doRestore(string $id, bool $withDb, ?int $actorId, bool $log, ?string $safety = null): void
    {
        $b = self::backup($id);
        if (!$b) throw new RuntimeException('Резервну копію не знайдено.');
        self::maintenance(true, 'restore');
        $from = YW_VERSION;
        try {
            if ($withDb) self::restoreDb($id);
            self::restoreFiles($id);
        } finally {
            self::maintenance(false);
        }
        if (function_exists('opcache_reset')) @opcache_reset();
        @unlink(self::cachePath());
        @unlink(YW_ROOT . '/storage/cache/version.txt');
        @file_put_contents(YW_ROOT . '/storage/cache/version.txt', (string) $b['version']);
        if ($log) Audit::log('system.restored', 'backup', $id, ['from' => $from, 'to' => $b['version'], 'withDb' => $withDb, 'safetyBackup' => $safety], $actorId);
    }

    /** Файли: прибрати ті, що додало оновлення, і розпакувати копію поверх. */
    private static function restoreFiles(string $id): void
    {
        $dir = self::backupsDir() . '/' . $id;
        $meta = json_decode((string) @file_get_contents($dir . '/meta.json'), true) ?: [];
        $zip = new ZipArchive();
        if ($zip->open($dir . '/files.zip') !== true) throw new RuntimeException('Архів файлів у копії пошкоджено.');
        $inBackup = [];
        for ($i = 0; $i < $zip->numFiles; $i++) $inBackup[(string) $zip->getNameIndex($i)] = true;
        // Усе, що зараз є в папках сайту, але чого не було в копії — прибираємо (так зникають файли нової версії).
        foreach (self::siteFiles() as $rel) {
            if (!isset($inBackup[$rel])) @unlink(YW_ROOT . '/' . $rel);
        }
        foreach (array_keys($inBackup) as $rel) {
            if ($rel === '' || str_ends_with($rel, '/') || str_contains($rel, '..')) continue;
            $dest = YW_ROOT . '/' . $rel;
            @mkdir(dirname($dest), 0755, true);
            $data = $zip->getFromName($rel);
            if ($data === false) continue;
            $tmp = $dest . '.yw-new';
            if (@file_put_contents($tmp, $data) === false || !@rename($tmp, $dest)) { @unlink($tmp); $zip->close(); throw new RuntimeException('не вдалося відновити ' . $rel); }
        }
        $zip->close();
        self::removeEmptyDirs(['app', 'assets', 'database', 'docs']);
        unset($meta);
    }

    private static function restoreDb(string $id): void
    {
        $file = self::backupsDir() . '/' . $id . '/db.sql';
        $sql = @file_get_contents($file);
        if ($sql === false || $sql === '') throw new RuntimeException('Файл бази в копії відсутній.');
        $db = App::db();
        $pdo = $db->pdo;
        // Записи журналу дій і сесії входу, зроблені після копії, зберігаємо:
        // журнал не має губитися, а адміністратор не має «вилетіти» з адмінки.
        $keep = ['audit_log' => [], 'user_sessions' => []];
        if (preg_match('/^-- YEWORK \S+ резервна копія бази (\S+)/u', $sql, $m)) {
            $since = gmdate('Y-m-d H:i:s', (int) strtotime($m[1]));
            foreach (array_keys($keep) as $t) {
                try { $keep[$t] = $db->all('SELECT * FROM {p}' . $t . ' WHERE created_at >= ? ORDER BY id', [$since]); } catch (Throwable) {}
            }
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        try {
            if ($db->prefix !== '') foreach (self::siteTables($db) as $t) $pdo->exec('DROP TABLE IF EXISTS `' . $t . '`');
            foreach (explode(";\n-- yw:end\n", $sql) as $stmt) {
                $stmt = trim(preg_replace('/^--[^\n]*\n/', '', $stmt) ?? $stmt);
                if ($stmt === '' || str_starts_with($stmt, '--')) continue;
                try {
                    $pdo->exec($stmt);
                } catch (\PDOException $e) {
                    if (str_starts_with($stmt, 'CREATE TRIGGER')) continue; // хостинг без права TRIGGER
                    throw $e;
                }
            }
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
        foreach ($keep as $t => $rows) {
            if (!$rows) continue;
            try { $cols = array_column($db->all('SHOW COLUMNS FROM {p}' . $t), 'Field'); } catch (Throwable) { continue; }
            foreach ($rows as $r) {
                $row = array_intersect_key($r, array_flip($cols));
                try { $db->run('INSERT IGNORE INTO {p}' . $t . ' (`' . implode('`,`', array_keys($row)) . '`) VALUES (' . implode(',', array_fill(0, count($row), '?')) . ')', array_values($row)); } catch (Throwable) {}
            }
        }
    }

    // ---------- Дрібниці ----------

    private static function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) $f->isDir() && !$f->isLink() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        @rmdir($dir);
    }

    private static function removeEmptyDirs(array $tops): void
    {
        foreach ($tops as $top) {
            foreach (scandir(YW_ROOT) ?: [] as $e) {
                if (strtolower($e) !== $top || !is_dir(YW_ROOT . '/' . $e)) continue;
                $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(YW_ROOT . '/' . $e, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
                foreach ($it as $f) if ($f->isDir() && count(scandir($f->getPathname()) ?: []) === 2) @rmdir($f->getPathname());
            }
        }
    }

    public static function humanSize(int $b): string
    {
        return $b >= 1048576 ? number_format($b / 1048576, 1, ',', ' ') . ' МБ' : max(1, (int) round($b / 1024)) . ' КБ';
    }
}

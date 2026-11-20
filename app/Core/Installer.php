<?php
declare(strict_types=1);

namespace App\Core;

use App\Permissions;
use PDOException;

/**
 * Встановлення та оновлення: перевірка сервера, перевірка БД, міграції, стартові дані, запис конфігурації.
 */
final class Installer
{
    /** Ім'я файлу конфігурації: латиниця, цифри, крапка, дефіс, підкреслення; закінчується на .php */
    public const CONFIG_FILE_RE = '/^[a-z0-9][a-z0-9._-]{0,58}\.php$/';
    public const PASSWORD_MIN = 12;
    public const THEMES = ['modern-light', 'modern-dark', 'corporate-blue', 'emerald', 'premium', 'soft-ui'];

    public static function setupKeyPath(): string { return App::configDir() . '/setup-key.php'; }

    /**
     * Ключ встановлення зберігається у config/setup-key.php (PHP-файл не можна прочитати через браузер).
     * Власник бачить його у файловому менеджері хостингу.
     */
    public static function setupKey(): ?string
    {
        $f = self::setupKeyPath();
        if (!is_file($f)) {
            if (!is_writable(App::configDir())) return null;
            $key = implode('-', str_split(strtoupper(substr(bin2hex(random_bytes(8)), 0, 12)), 4));
            file_put_contents($f, "<?php // Ключ встановлення YEWORK. Видаляється автоматично після встановлення.\nreturn '" . $key . "';\n");
            @chmod($f, 0600);
        }
        return (string) (include $f);
    }

    /** @return list<array{id:string,label:string,ok:bool,warn?:bool,detail:string}> */
    public static function requirements(Request $req): array
    {
        $ext = static fn (string $e) => extension_loaded($e);
        $r = [];
        $r[] = ['id' => 'php', 'label' => 'PHP 8.2 або новіший', 'ok' => PHP_VERSION_ID >= 80200, 'detail' => 'Встановлено ' . PHP_VERSION . (PHP_VERSION_ID >= 80400 ? '' : '. Рекомендуємо 8.4')];
        foreach (['pdo_mysql' => 'Підключення до MySQL (pdo_mysql)', 'openssl' => 'Шифрування (openssl)', 'mbstring' => 'Українські тексти (mbstring)', 'json' => 'JSON'] as $e => $label) {
            $r[] = ['id' => $e, 'label' => $label, 'ok' => $ext($e), 'detail' => $ext($e) ? 'Увімкнено' : 'Увімкніть розширення в панелі хостингу (розділ «Версія PHP» → «Розширення»)'];
        }
        $r[] = ['id' => 'argon', 'label' => 'Сучасне хешування паролів', 'ok' => true, 'warn' => !defined('PASSWORD_ARGON2ID'), 'detail' => defined('PASSWORD_ARGON2ID') ? 'Argon2id' : 'Argon2id недоступний, використовується bcrypt (теж безпечно)'];
        foreach (['config' => 'Папка config доступна для запису', 'storage' => 'Папка storage доступна для запису'] as $d => $label) {
            $w = is_writable(YW_ROOT . '/' . $d);
            $r[] = ['id' => $d, 'label' => $label, 'ok' => $w, 'detail' => $w ? 'Так' : "Встановіть права 755 (або 775) на папку $d у файловому менеджері"];
        }
        $https = $req->isHttps();
        $r[] = ['id' => 'https', 'label' => 'Захищене з’єднання (HTTPS)', 'ok' => true, 'warn' => !$https, 'detail' => $https ? 'Так' : 'Сайт відкрито через http://. Увімкніть безкоштовний SSL (Let’s Encrypt) у панелі хостингу.'];
        $r[] = ['id' => 'rewrite', 'label' => 'Короткі адреси сторінок (.htaccess)', 'ok' => true, 'warn' => !self::rewriteWorks($req), 'detail' => self::rewriteWorks($req) ? 'Працюють' : 'Схоже, mod_rewrite вимкнено: сторінки відкриватимуться через index.php'];
        return $r;
    }

    private static function rewriteWorks(Request $req): bool
    {
        return !str_contains($req->server['REQUEST_URI'] ?? '', 'index.php');
    }

    public static function requirementsOk(array $req): bool
    {
        foreach ($req as $x) if (!$x['ok']) return false;
        return true;
    }

    /** Зрозуміле пояснення помилки підключення до MySQL. */
    public static function explainDbError(PDOException $e, array $db): string
    {
        $code = (int) ($e->errorInfo[1] ?? 0);
        $msg = $e->getMessage();
        return match (true) {
            $code === 1045 => 'Невірний користувач або пароль бази даних. Перевірте їх у панелі хостингу (розділ «Бази даних MySQL»).',
            $code === 1049 => 'Бази «' . $db['database'] . '» не існує. Створіть її в панелі хостингу або перевірте назву (часто вона має префікс акаунта, напр. user_yework).',
            $code === 1044 => 'Користувач не має доступу до цієї бази. У панелі хостингу додайте користувача до бази з усіма правами.',
            $code === 2002 || str_contains($msg, 'Connection refused') => 'Сервер MySQL не відповідає за адресою ' . $db['host'] . ':' . $db['port'] . '. На хостингу зазвичай хост — localhost.',
            $code === 2005 || str_contains($msg, 'getaddrinfo') => 'Невідомий хост «' . $db['host'] . '». На хостингу зазвичай вказують localhost.',
            default => 'Не вдалося підключитися: ' . $msg,
        };
    }

    /** @return array{ok:bool, message:string, version?:string, existing?:bool} */
    public static function checkDb(array $db): array
    {
        try {
            $c = Db::connect($db, true, $db['prefix']);
            $ver = (string) $c->value('SELECT VERSION()');
            $isMaria = stripos($ver, 'mariadb') !== false;
            $num = (float) preg_replace('/^(\d+\.\d+).*/', '$1', $ver);
            if ((!$isMaria && $num < 8.0) || ($isMaria && $num < 10.6)) {
                return ['ok' => false, 'message' => "Версія бази даних $ver застара. Потрібен MySQL 8.0+ або MariaDB 10.6+."];
            }
            $existing = (int) $c->value('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$db['prefix'] . 'users'])
                && (int) $c->value('SELECT COUNT(*) FROM `' . $db['prefix'] . 'users`') > 0;
            if ($existing) return ['ok' => false, 'existing' => true, 'message' => 'У цій базі вже є встановлений YEWORK з префіксом «' . $db['prefix'] . '». Оберіть іншу базу або інший префікс таблиць.'];
            // Перевірка права створювати таблиці.
            $t = $db['prefix'] . 'install_probe';
            $c->pdo->exec("CREATE TABLE IF NOT EXISTS `$t` (id INT) ENGINE=InnoDB");
            $c->pdo->exec("DROP TABLE `$t`");
            return ['ok' => true, 'version' => $ver, 'message' => 'Підключено. ' . ($isMaria ? 'MariaDB ' : 'MySQL ') . preg_replace('/-.*$/', '', $ver) . '.'];
        } catch (PDOException $e) {
            if (in_array((int) ($e->errorInfo[1] ?? 0), [1142, 1044], true)) return ['ok' => false, 'message' => 'Користувач бази не має права створювати таблиці. Надайте йому ВСІ права на цю базу в панелі хостингу.'];
            return ['ok' => false, 'message' => self::explainDbError($e, $db)];
        }
    }

    /** Застосовує нові міграції з database/migrations. Файли *.optional.sql не зупиняють встановлення. */
    public static function migrate(Db $db): array
    {
        $db->pdo->exec(str_replace('{p}', $db->prefix, 'CREATE TABLE IF NOT EXISTS `{p}schema_migrations` (name VARCHAR(190) NOT NULL PRIMARY KEY, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'));
        $done = array_column($db->all('SELECT name FROM {p}schema_migrations'), 'name');
        $files = glob(YW_ROOT . '/database/migrations/*.sql') ?: [];
        sort($files);
        $warnings = [];
        foreach ($files as $f) {
            $name = basename($f);
            if (in_array($name, $done, true)) continue;
            $sql = (string) file_get_contents($f);
            $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
            $optional = str_contains($name, '.optional.');
            foreach (array_filter(array_map('trim', explode(";\n", $sql))) as $stmt) {
                try {
                    $db->pdo->exec(str_replace('{p}', $db->prefix, rtrim($stmt, ';')));
                } catch (PDOException $e) {
                    if (!$optional) throw $e;
                    $warnings[] = $name . ': ' . $e->getMessage();
                    break;
                }
            }
            $db->insert('schema_migrations', ['name' => $name]);
        }
        return $warnings;
    }

    /** Синхронізує права та системні ролі. Ідемпотентно — безпечно запускати після кожного оновлення. */
    public static function syncPermissions(Db $db): void
    {
        foreach (Permissions::all() as $p) {
            $db->run('INSERT INTO {p}permissions (code, module, resource, action, is_critical, description) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE is_critical = VALUES(is_critical), description = VALUES(description)',
                [$p['code'], $p['module'], $p['resource'], $p['action'], $p['critical'] ? 1 : 0, $p['description']]);
        }
        foreach (Permissions::roles() as $r) {
            $db->run('INSERT INTO {p}roles (slug, name, description, is_system) VALUES (?,?,?,1) ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), is_system = 1', [$r['slug'], $r['name'], $r['description']]);
            if ($r['permissions'] === '*') continue;
            $id = (int) $db->value('SELECT id FROM {p}roles WHERE slug = ?', [$r['slug']]);
            foreach ($r['permissions'] as $code) $db->run('INSERT IGNORE INTO {p}role_permissions (role_id, permission_code) VALUES (?, ?)', [$id, $code]);
        }
    }

    public static function seedReference(Db $db): void
    {
        if ($db->value('SELECT COUNT(*) FROM {p}regions')) return;
        $oblast = $db->insert('regions', ['kind' => 'oblast', 'name' => 'Київська область']);
        $raion = $db->insert('regions', ['kind' => 'raion', 'name' => 'Обухівський район', 'parent_id' => $oblast]);
        $launch = [
            ['Миронівська громада', 'Миронівка', 49.6631, 30.9851],
            ['Богуславська громада', 'Богуслав', 49.5464, 30.8742],
            ['Кагарлицька громада', 'Кагарлик', 49.8576, 30.8276],
        ];
        foreach ($launch as [$community, $city, $lat, $lon]) {
            $c = $db->insert('communities', ['region_id' => $raion, 'name' => $community, 'is_launch' => 1]);
            $db->insert('settlements', ['community_id' => $c, 'name' => $city, 'kind' => 'city', 'lat' => $lat, 'lon' => $lon]);
        }
    }

    public const JOB_CATEGORIES = [
        ['trade', 'Торгівля та продажі', 'shop'], ['agro', 'Сільське господарство', 'leaf'], ['construction', 'Будівництво та ремонт', 'hammer'],
        ['transport', 'Водії та логістика', 'truck'], ['production', 'Виробництво', 'factory'], ['food', 'Кафе, ресторани, кухня', 'cup'],
        ['education', 'Освіта', 'book'], ['medicine', 'Медицина та фармація', 'heart'], ['office', 'Офіс і адміністрування', 'folder'],
        ['accounting', 'Бухгалтерія та фінанси', 'calc'], ['it', 'IT та комп’ютери', 'code'], ['beauty', 'Краса та здоров’я', 'spark'],
        ['security', 'Охорона', 'shield'], ['cleaning', 'Прибирання та побут', 'home'], ['public', 'Державна служба', 'bank'], ['other', 'Інше', 'dots'],
    ];

    public static function seedJobCategories(Db $db): void
    {
        foreach (self::JOB_CATEGORIES as $i => [$slug, $name, $icon]) {
            $db->run('INSERT IGNORE INTO {p}job_categories (slug, name, icon, sort) VALUES (?,?,?,?)', [$slug, $name, $icon, ($i + 1) * 10]);
        }
    }

    /** 6 стандартних тем; обрана стає активною. */
    public static function seedThemes(Db $db, string $activeSlug, ?int $actorId): void
    {
        $themes = json_decode((string) file_get_contents(YW_ROOT . '/database/seed/themes.json'), true);
        $activeVersion = null;
        foreach ($themes as $t) {
            $id = (int) $db->value('SELECT id FROM {p}themes WHERE slug = ?', [$t['id']]);
            if (!$id) $id = $db->insert('themes', ['slug' => $t['id'], 'name' => $t['name'], 'description' => $t['desc'], 'is_system' => 1, 'default_mode' => $t['defaultMode'], 'created_by' => $actorId]);
            $vid = (int) $db->value('SELECT id FROM {p}theme_versions WHERE theme_id = ? AND version = 1', [$id]);
            if (!$vid) $vid = $db->insert('theme_versions', ['theme_id' => $id, 'version' => 1, 'tokens' => json_encode(['base' => $t['base'], 'light' => $t['light'], 'dark' => $t['dark']], JSON_UNESCAPED_UNICODE), 'comment' => 'Стандартна тема', 'created_by' => $actorId]);
            if ($t['id'] === $activeSlug) $activeVersion = $vid;
        }
        if (!$activeVersion) throw new \RuntimeException('Невідома тема: ' . $activeSlug);
        $db->run('UPDATE {p}theme_publications SET active_flag = NULL WHERE active_flag = 1');
        $db->insert('theme_publications', ['theme_version_id' => $activeVersion, 'published_by' => $actorId, 'active_flag' => 1]);
    }

    /**
     * Повне встановлення. $in: db{host,port,database,user,password,prefix}, site{name,slogan,url,configFile,theme}, admin{login,displayName,email,phone,password}
     * @return array{steps: list<string>, configPath: string, warnings: list<string>}
     */
    public static function run(array $in): array
    {
        $steps = [];
        if (!is_writable(App::configDir())) throw new \RuntimeException('Папка config недоступна для запису.');
        $db = Db::connect($in['db'], true, $in['db']['prefix']);
        $appSecret = Crypto::newKey();
        $dataKey = Crypto::newKey();
        $phone = $in['admin']['phone'] ? Crypto::normalizePhone($in['admin']['phone']) : null;
        $email = $in['admin']['email'] ? mb_strtolower($in['admin']['email']) : null;

        self::cleanupFailedInstall($db);
        $warnings = self::migrate($db);
        $steps[] = 'Структура бази даних';

        $db->tx(function (Db $db) use ($in, $appSecret, $dataKey, $phone, $email, &$steps) {
            self::syncPermissions($db);
            $steps[] = 'Права та стартові ролі';
            self::seedReference($db);
            self::seedJobCategories($db);
            $steps[] = 'Довідник громад і категорій вакансій';
            $owner = $db->insert('users', [
                'login' => $in['admin']['login'], 'display_name' => $in['admin']['displayName'],
                'password_hash' => Crypto::hashPassword($in['admin']['password']), 'owner_flag' => 1,
                'phone_hash' => $phone ? Crypto::hmac($phone, $appSecret) : null, 'phone_enc' => $phone ? Crypto::encrypt($phone, $dataKey) : null,
                'phone_verified_at' => $phone ? Db::now() : null,
                'email_hash' => $email ? Crypto::hmac($email, $appSecret) : null, 'email_enc' => $email ? Crypto::encrypt($email, $dataKey) : null,
            ]);
            $ownerRole = (int) $db->value("SELECT id FROM {p}roles WHERE slug = 'owner'");
            $db->insert('user_roles', ['user_id' => $owner, 'role_id' => $ownerRole]);
            foreach (Permissions::critical() as $code) {
                $db->insert('critical_grants', ['user_id' => $owner, 'permission_code' => $code, 'reason' => 'Власник платформи (встановлення)']);
            }
            $steps[] = 'Головний адміністратор';
            self::seedThemes($db, $in['site']['theme'], $owner);
            $steps[] = '6 тем оформлення';
            $settings = [
                'site.name' => $in['site']['name'], 'site.slogan' => $in['site']['slogan'], 'site.url' => rtrim($in['site']['url'], '/'),
                'site.launch_communities' => ['Миронівська', 'Богуславська', 'Кагарлицька'],
                'security.admin_2fa_required' => true, 'monetization.enabled' => false,
                'moderation.premoderation' => ['jobs' => true, 'gigs' => true, 'services' => true],
                'messaging.sms' => ['driver' => 'log'], 'messaging.email' => ['driver' => 'mail'],
            ];
            foreach ($settings as $k => $v) $db->insert('settings', ['key' => $k, 'value' => json_encode($v, JSON_UNESCAPED_UNICODE), 'updated_by' => $owner]);
            $steps[] = 'Налаштування сайту';
            $db->insert('audit_log', ['actor_id' => $owner, 'action' => 'install.completed', 'data' => json_encode(['version' => YW_VERSION, 'theme' => $in['site']['theme']])]);
        });

        $path = self::writeConfig($in, $appSecret, $dataKey);
        $steps[] = 'Файл конфігурації та блокування інсталятора';
        return ['steps' => $steps, 'configPath' => $path, 'warnings' => $warnings];
    }

    /** Якщо попередня спроба встановлення обірвалася (таблиці є, власника немає) — прибираємо її залишки. */
    private static function cleanupFailedInstall(Db $db): void
    {
        $tables = array_column($db->all('SELECT table_name AS t FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE ?', [str_replace('_', '\\_', $db->prefix) . '%']), 't');
        if (!$tables) return;
        if (in_array($db->prefix . 'users', $tables, true) && (int) $db->value('SELECT COUNT(*) FROM {p}users') > 0) {
            throw new \RuntimeException('У базі вже є встановлений YEWORK.');
        }
        $db->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($tables as $t) $db->pdo->exec('DROP TABLE IF EXISTS `' . str_replace('`', '', $t) . '`');
        $db->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    private static function writeConfig(array $in, string $appSecret, string $dataKey): string
    {
        $cfg = [
            'format' => 'yework-config', 'version' => 1, 'installedAt' => gmdate('c'),
            'db' => ['host' => $in['db']['host'], 'port' => (int) $in['db']['port'], 'database' => $in['db']['database'], 'user' => $in['db']['user'], 'password' => $in['db']['password'], 'prefix' => $in['db']['prefix']],
            'appSecret' => $appSecret, 'dataKey' => $dataKey,
        ];
        $php = "<?php\n// YEWORK — файл конфігурації. Створено інсталятором " . gmdate('Y-m-d H:i') . " UTC.\n"
            . "// УВАГА: містить ключ шифрування персональних даних. Збережіть копію поза сервером.\n"
            . "// Без цього файлу резервна копія бази марна (телефони й email зашифровані).\n"
            . 'return ' . var_export($cfg, true) . ";\n";
        $dir = App::configDir();
        $file = $in['site']['configFile'];
        $tmp = $dir . '/.' . $file . '.tmp';
        file_put_contents($tmp, $php, LOCK_EX);
        @chmod($tmp, 0600);
        rename($tmp, $dir . '/' . $file);
        file_put_contents(App::lockPath(), json_encode(['configFile' => $file, 'installedAt' => gmdate('c'), 'version' => YW_VERSION]), LOCK_EX);
        @chmod(App::lockPath(), 0600);
        @unlink(self::setupKeyPath());
        return 'config/' . $file;
    }

    /** Після оновлення файлів: нові міграції + синхронізація прав. Викликається з адмінки. */
    public static function upgrade(Db $db): array
    {
        $w = self::migrate($db);
        self::syncPermissions($db);
        self::seedJobCategories($db);
        return $w;
    }
}

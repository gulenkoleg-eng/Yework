<?php
declare(strict_types=1);

namespace App\Core;

use App\Controllers;
use Throwable;

/**
 * Ядро застосунку: конфігурація, підключення до БД, маршрутизація, обробка помилок.
 */
final class App
{
    /** Помилки валідації та старі значення форми для повторного показу. */
    public static array $errors = [];
    public static array $old = [];
    public static ?string $formError = null;
    private static ?array $config = null;
    private static ?Db $db = null;
    private static ?Session $session = null;
    private static ?Auth $auth = null;
    private static array $settings = [];
    private static bool $settingsLoaded = false;

    public static function configDir(): string { return YW_ROOT . '/config'; }
    public static function lockPath(): string { return self::configDir() . '/install.lock'; }
    public static function isInstalled(): bool { return is_file(self::lockPath()); }

    /** Підпапка сайту, якщо його встановлено не в корінь домену (напр. /yework). */
    public static function basePath(): string
    {
        static $base = null;
        if ($base === null) {
            $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
            $base = rtrim($dir === '/' || $dir === '.' ? '' : $dir, '/');
        }
        return $base;
    }

    /** @return array{site: array, db: array, appSecret: string, dataKey: string} */
    public static function config(): array
    {
        if (self::$config === null) {
            $lock = json_decode((string) file_get_contents(self::lockPath()), true);
            $file = $lock['configFile'] ?? '';
            if (!preg_match(Installer::CONFIG_FILE_RE, $file)) throw new \RuntimeException('install.lock пошкоджено');
            self::$config = require self::configDir() . '/' . $file;
        }
        return self::$config;
    }

    public static function db(): Db
    {
        return self::$db ??= Db::connect(self::config()['db']);
    }

    public static function session(): Session
    {
        return self::$session ??= new Session();
    }

    public static function auth(): Auth
    {
        return self::$auth ??= new Auth();
    }

    /** Значення з таблиці settings (кешується на запит). */
    public static function setting(string $key, mixed $default = null): mixed
    {
        if (!self::$settingsLoaded) {
            self::$settingsLoaded = true;
            foreach (self::db()->all('SELECT `key`, `value` FROM {p}settings') as $r) self::$settings[$r['key']] = json_decode($r['value'], true);
        }
        return array_key_exists($key, self::$settings) ? self::$settings[$key] : $default;
    }

    public static function setSetting(string $key, mixed $value, ?int $actorId = null): void
    {
        self::db()->run('INSERT INTO {p}settings (`key`, `value`, updated_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_by = VALUES(updated_by), updated_at = NOW()',
            [$key, json_encode($value, JSON_UNESCAPED_UNICODE), $actorId]);
        self::$settings[$key] = $value;
    }

    public static function run(): void
    {
        $req = Request::capture();
        try {
            $res = self::dispatch($req);
        } catch (HttpException $e) {
            $res = self::errorPage($req, $e->status, $e->getMessage());
        } catch (Throwable $e) {
            error_log('[YEWORK] ' . $e::class . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());
            $res = self::errorPage($req, 500, 'Щось пішло не так. Ми вже записали помилку в журнал. Спробуйте ще раз за хвилину.');
        }
        $res->send();
    }

    private static function dispatch(Request $req): Response
    {
        Csrf::ensureCookie();
        $path = $req->path;

        if (!self::isInstalled()) {
            if (!str_starts_with($path, '/install')) return Response::redirect(url('/install'));
            return (new Controllers\InstallController())->handle($req);
        }
        if ($path === '/install/done' && App::session()->get('install')) {
            return (new Controllers\InstallController())->handle($req);
        }
        if (str_starts_with($path, '/install')) {
            return self::errorPage($req, 410, 'Сайт уже встановлено. Інсталятор вимкнено назавжди.');
        }

        Updater::autoUpgrade();
        Vacancies::sweep();
        if ($req->method === 'POST') Csrf::verify($req);
        Stats::trackVisit($req);

        $router = new Router();
        require yw_file('app/routes.php');
        return $router->dispatch($req);
    }

    public static function errorPage(Request $req, int $status, string $message): Response
    {
        if ($req->wantsJson()) return Response::json(['message' => $message], $status);
        $titles = [403 => 'Доступ заборонено', 404 => 'Сторінку не знайдено', 410 => 'Інсталятор вимкнено', 419 => 'Сторінка застаріла', 429 => 'Забагато запитів', 500 => 'Помилка сервера'];
        $html = View::render('site/error', ['status' => $status, 'title' => $titles[$status] ?? 'Помилка', 'message' => $message], self::isInstalled() && $status !== 500 ? 'site' : 'bare');
        return new Response($html, $status);
    }
}

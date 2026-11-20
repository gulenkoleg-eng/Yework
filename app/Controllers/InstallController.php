<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Crypto;
use App\Core\Csrf;
use App\Core\Installer;
use App\Core\RateLimit;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

/**
 * Веб-інсталятор із 6 кроків. Стан між кроками — у сесії PHP (storage/sessions), очищується після встановлення.
 */
final class InstallController
{
    private const STEPS = [1 => 'Ключ встановлення', 2 => 'База даних', 3 => 'Сайт', 4 => 'Тема оформлення', 5 => 'Головний адміністратор', 6 => 'Встановлення'];

    private array $st;

    public function handle(Request $req): Response
    {
        $s = App::session();
        $this->st = $s->get('install', ['maxStep' => 1, 'db' => ['host' => 'localhost', 'port' => 3306, 'database' => '', 'user' => '', 'password' => '', 'prefix' => 'yw_'],
            'site' => ['name' => 'YEWORK', 'slogan' => 'Є робота!', 'url' => '', 'configFile' => 'yework-config', 'theme' => 'modern-light'],
            'admin' => ['login' => '', 'displayName' => '', 'email' => '', 'phone' => '']]);
        if ($this->st['site']['url'] === '') $this->st['site']['url'] = ($req->isHttps() ? 'https://' : 'http://') . ($req->server['HTTP_HOST'] ?? 'localhost') . App::basePath();

        if ($req->path === '/install/done') return $this->done();

        $step = max(1, min(6, (int) ($req->query['step'] ?? 1)));
        if ($step > $this->st['maxStep']) $step = $this->st['maxStep'];
        $data = ['error' => null, 'ok' => null, 'fields' => []];

        if ($req->method === 'POST') {
            Csrf::verify($req);
            $r = $this->post($req, $step, $data);
            $s->set('install', $this->st);
            if ($r) return $r;
        }
        return $this->page($req, $step, $data);
    }

    private function go(int $step): Response
    {
        $this->st['maxStep'] = max($this->st['maxStep'], $step);
        App::session()->set('install', $this->st);
        return Response::redirect(url('/install', ['step' => $step]));
    }

    private function post(Request $req, int $step, array &$data): ?Response
    {
        switch ($step) {
            case 1:
                RateLimit::hit('install-key:' . $req->ip(), 10, 900, 'Забагато спроб. Зачекайте 15 хвилин.');
                $key = strtoupper(preg_replace('/\s+/', '', $req->str('key', 40)));
                $real = Installer::setupKey();
                if (!$real || !hash_equals($real, $key)) { $data['error'] = 'Невірний ключ встановлення. Відкрийте файл config/setup-key.php у файловому менеджері хостингу та скопіюйте ключ.'; return null; }
                if (!Installer::requirementsOk(Installer::requirements($req))) { $data['error'] = 'Сервер не відповідає вимогам. Виправте пункти, позначені червоним, і оновіть сторінку.'; return null; }
                $this->st['keyOk'] = true;
                return $this->go(2);

            case 2:
                $db = [
                    'host' => $req->str('host', 255) ?: 'localhost', 'port' => (int) ($req->str('port', 5) ?: 3306),
                    'database' => $req->str('database', 64), 'user' => $req->str('user', 80),
                    'password' => is_string($req->post['password'] ?? null) && $req->post['password'] !== '' ? (string) $req->post['password'] : ($this->st['db']['password'] ?? ''),
                    'prefix' => strtolower($req->str('prefix', 10)),
                ];
                $f = [];
                if (!preg_match('/^[A-Za-z0-9_.\-]{1,255}$/', $db['host'])) $f['host'] = 'Некоректний хост';
                if ($db['port'] < 1 || $db['port'] > 65535) $f['port'] = 'Порт 1–65535';
                if (!preg_match('/^[A-Za-z0-9_$-]{1,64}$/', $db['database'])) $f['database'] = 'Вкажіть назву бази (латиниця, цифри, _)';
                if ($db['user'] === '') $f['user'] = 'Вкажіть користувача бази';
                if ($db['password'] === '') $f['password'] = 'Вкажіть пароль бази';
                if (!preg_match('/^[a-z][a-z0-9]{0,6}_$/', $db['prefix'])) $f['prefix'] = 'Префікс: латинські літери/цифри і «_» в кінці, напр. yw_';
                $this->st['db'] = $db;
                if ($f) { $data['fields'] = $f; $data['error'] = 'Перевірте виділені поля.'; return null; }
                RateLimit::hit('install-db:' . $req->ip(), 30, 900, 'Забагато перевірок. Зачекайте кілька хвилин.');
                $r = Installer::checkDb($db);
                $this->st['dbOk'] = $r['ok'];
                if (!$r['ok']) { $data['error'] = $r['message']; return null; }
                if (($req->post['action'] ?? '') === 'check') { $data['ok'] = $r['message']; return null; }
                return $this->go(3);

            case 3:
                $site = [
                    'name' => $req->str('name', 60), 'slogan' => $req->str('slogan', 80), 'url' => rtrim($req->str('url', 200), '/'),
                    'configFile' => strtolower($req->str('configFile', 60)), 'theme' => $this->st['site']['theme'],
                ];
                $site['configFile'] = preg_replace('/\.php$/', '', $site['configFile']);
                $f = [];
                if (mb_strlen($site['name']) < 2) $f['name'] = 'Вкажіть назву сайту';
                if (!preg_match('#^https?://[^\s/]+#', $site['url'])) $f['url'] = 'Адреса має починатися з https:// або http://';
                if (!preg_match(Installer::CONFIG_FILE_RE, $site['configFile'] . '.php') || $site['configFile'] === 'setup-key') $f['configFile'] = 'Латиниця, цифри, крапка, дефіс (напр. yework-config)';
                $this->st['site'] = $site;
                if ($f) { $data['fields'] = $f; $data['error'] = 'Перевірте виділені поля.'; return null; }
                return $this->go(4);

            case 4:
                $t = $req->str('theme', 30);
                if (!in_array($t, Installer::THEMES, true)) { $data['error'] = 'Оберіть тему.'; return null; }
                $this->st['site']['theme'] = $t;
                return $this->go(5);

            case 5:
                $a = ['login' => $req->str('login', 32), 'displayName' => $req->str('displayName', 80), 'email' => $req->str('email', 254), 'phone' => $req->str('phone', 20)];
                $pw = (string) ($req->post['password'] ?? '');
                $pw2 = (string) ($req->post['password2'] ?? '');
                $f = [];
                if (!preg_match('/^[a-zA-Z][a-zA-Z0-9._-]{2,31}$/', $a['login'])) $f['login'] = 'Логін: 3–32 символи, латиниця, цифри, . _ - (починається з літери)';
                if (mb_strlen($a['displayName']) < 2) $f['displayName'] = 'Вкажіть ім’я';
                if ($a['email'] !== '' && !filter_var($a['email'], FILTER_VALIDATE_EMAIL)) $f['email'] = 'Некоректний email';
                if ($a['phone'] !== '' && !Crypto::normalizePhone($a['phone'])) $f['phone'] = 'Формат: 067 123 45 67';
                if ($a['email'] === '' && $a['phone'] === '') $f['email'] = 'Вкажіть email або телефон — для відновлення доступу';
                if (mb_strlen($pw) < Installer::PASSWORD_MIN || !preg_match('/\p{Ll}/u', $pw) || !preg_match('/\p{Lu}/u', $pw) || !preg_match('/\d/', $pw)) $f['password'] = 'Щонайменше 12 символів, великі й малі літери та цифра';
                elseif ($pw !== $pw2) $f['password2'] = 'Паролі не збігаються';
                $this->st['admin'] = $a;
                if ($f) { $data['fields'] = $f; $data['error'] = 'Перевірте виділені поля.'; return null; }
                // Пароль тримаємо в сесії до кроку 6 лише зашифрованим ключем, що живе в cookie браузера.
                $k = Crypto::newKey();
                $this->st['adminPwEnc'] = Crypto::encrypt($pw, $k);
                setcookie('yw_ik', $k, ['expires' => 0, 'path' => App::basePath() . '/install', 'httponly' => true, 'samesite' => 'Strict', 'secure' => $req->isHttps()]);
                return $this->go(6);

            case 6:
                if (empty($this->st['keyOk']) || empty($this->st['dbOk'])) return $this->go(1);
                $pw = Crypto::decrypt($this->st['adminPwEnc'] ?? null, $req->cookies['yw_ik'] ?? '');
                if (!$pw) { $data['error'] = 'Сесія встановлення застаріла. Введіть пароль адміністратора ще раз.'; return $this->go(5); }
                try {
                    $result = Installer::run(['db' => $this->st['db'], 'site' => [...$this->st['site'], 'configFile' => $this->st['site']['configFile'] . '.php'], 'admin' => [...$this->st['admin'], 'password' => $pw]]);
                } catch (\Throwable $e) {
                    error_log('[YEWORK install] ' . $e->getMessage() . "\n" . $e->getTraceAsString());
                    $data['error'] = 'Встановлення не вдалося: ' . ($e instanceof \PDOException ? Installer::explainDbError($e, $this->st['db']) : $e->getMessage());
                    return null;
                }
                $this->st = ['result' => $result, 'site' => $this->st['site'], 'admin' => ['login' => $this->st['admin']['login']]];
                App::session()->set('install', $this->st);
                setcookie('yw_ik', '', ['expires' => 1, 'path' => App::basePath() . '/install']);
                return Response::redirect(url('/install/done'));
        }
        return null;
    }

    private function page(Request $req, int $step, array $data): Response
    {
        $html = View::render('install/step' . $step, [
            ...$data, 'st' => $this->st, 'step' => $step, 'steps' => self::STEPS,
            'requirements' => $step === 1 ? Installer::requirements($req) : [],
            'keyFileReady' => $step === 1 ? Installer::setupKey() !== null : true,
            'themes' => $step === 4 ? json_decode((string) file_get_contents(YW_ROOT . '/database/seed/themes.json'), true) : [],
        ], 'install');
        return new Response($html, $data['error'] ? 422 : 200);
    }

    private function done(): Response
    {
        // Сюди потрапляємо одразу після встановлення (поки lock ще не перевірявся в цьому запиті).
        $r = $this->st['result'] ?? null;
        if (!$r) return Response::redirect(url('/'));
        $html = View::render('install/done', ['st' => $this->st, 'result' => $r, 'step' => 7, 'steps' => self::STEPS], 'install');
        App::session()->set('install', null);
        return new Response($html);
    }
}

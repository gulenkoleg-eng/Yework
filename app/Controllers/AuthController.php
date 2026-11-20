<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Audit;
use App\Core\Crypto;
use App\Core\Db;
use App\Core\Messaging;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;

/**
 * Вхід (логін / телефон / email), реєстрація з підтвердженням коду, відновлення доступу.
 * Відповідь на запит коду однакова, чи існує акаунт: так не можна дізнатися, хто зареєстрований.
 */
final class AuthController
{
    public const PASSWORD_MIN = 8;

    private function safeNext(Request $req): string
    {
        $n = (string) $req->input('next', '');
        return preg_match('#^/[A-Za-z0-9/_\-?=&]*$#', $n) && !str_starts_with($n, '//') ? $n : '';
    }

    public function loginForm(Request $req): Response
    {
        if (App::auth()->user()) return Response::redirect(url($this->safeNext($req) ?: '/cabinet'));
        return new Response(view('auth/login', ['next' => $this->safeNext($req), 'title' => 'Вхід']));
    }

    public function login(Request $req): Response
    {
        $login = $req->str('login', 254);
        $pw = (string) ($req->post['password'] ?? '');
        if ($login === '' || $pw === '') throw new ValidationException(['login' => $login === '' ? 'Вкажіть логін, телефон або email' : null, 'password' => $pw === '' ? 'Вкажіть пароль' : null]);
        App::auth()->attempt($login, $pw, $req);
        $next = $this->safeNext($req);
        if (!$next) $next = App::auth()->isStaff() ? '/admin' : '/cabinet';
        return Response::redirect(url($next));
    }

    public function logout(Request $req): Response
    {
        App::auth()->logout($req);
        flash('ok', 'Ви вийшли з акаунта.');
        return Response::redirect(url('/'));
    }

    /* ---------------- реєстрація ---------------- */

    public function registerForm(Request $req): Response
    {
        if (App::auth()->user()) return Response::redirect(url('/cabinet'));
        $st = App::session()->get('reg');
        if (($req->query['restart'] ?? '') === '1') { App::session()->set('reg', null); $st = null; }
        return new Response(view('auth/register', ['st' => $st, 'title' => 'Реєстрація']));
    }

    public function register(Request $req): Response
    {
        $s = App::session();
        $st = $s->get('reg');
        $stage = $req->str('stage', 10);

        if ($stage === 'contact' || !$st) {
            $c = Crypto::parseContact($req->str('contact', 254));
            if (!$c) throw new ValidationException(['contact' => 'Вкажіть український номер (067 123 45 67) або email']);
            $site = (string) App::setting('site.name', 'YEWORK');
            $existing = App::auth()->userByContact($c);
            if ($existing) {
                // Акаунт уже є: надсилаємо підказку з кодом відновлення, а відповідь та сама.
                try {
                    $code = App::auth()->issueCode('recover', $c, (int) $existing['id'], $req);
                    Messaging::send($c['channel'], $c['value'], "$site: цей " . ($c['kind'] === 'phone' ? 'номер' : 'email') . " уже зареєстровано. Код для відновлення пароля: $code. Якщо це не ви, проігноруйте.", 'register_exists', "$site: у вас уже є акаунт");
                } catch (\Throwable) {}
            } else {
                $code = App::auth()->issueCode('register', $c, null, $req);
                $r = Messaging::send($c['channel'], $c['value'], "$site: код реєстрації $code. Діє 10 хвилин. Нікому його не повідомляйте.", 'register_code', "$site: код підтвердження");
                if (!$r['ok']) throw new ValidationException(['contact' => 'Не вдалося надіслати код'], 'Не вдалося надіслати код. Спробуйте інший спосіб (телефон або email) або напишіть нам.');
            }
            $s->set('reg', ['contact' => $c['value'], 'kind' => $c['kind'], 'at' => time()]);
            return Response::redirect(url('/register'));
        }

        $c = Crypto::parseContact((string) $st['contact']);
        $name = $req->str('displayName', 80);
        $pw = (string) ($req->post['password'] ?? '');
        $err = [];
        if (mb_strlen($name) < 2) $err['displayName'] = 'Вкажіть ім’я';
        if (mb_strlen($pw) < self::PASSWORD_MIN || !preg_match('/\p{L}/u', $pw) || !preg_match('/\d/', $pw)) $err['password'] = 'Щонайменше 8 символів, літери та цифри';
        if (empty($req->post['terms'])) $err['terms'] = 'Потрібна згода з правилами';
        if ($err) throw new ValidationException($err);
        App::auth()->consumeCode('register', $c, $req->str('code', 6));

        $db = App::db();
        $h = Crypto::hmac($c['value']);
        try {
            $id = $db->insert('users', [
                'login' => 'u' . bin2hex(random_bytes(5)), 'display_name' => $name, 'password_hash' => Crypto::hashPassword($pw),
                ...($c['kind'] === 'phone'
                    ? ['phone_hash' => $h, 'phone_enc' => Crypto::encrypt($c['value']), 'phone_verified_at' => Db::now()]
                    : ['email_hash' => $h, 'email_enc' => Crypto::encrypt($c['value']), 'email_verified_at' => Db::now()]),
            ]);
        } catch (\PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) throw new ValidationException(['contact' => ' '], 'Цей контакт уже зареєстровано. Увійдіть або відновіть пароль.');
            throw $e;
        }
        Audit::log('auth.registered', 'user', $id, ['via' => $c['kind']], $id);
        $s->set('reg', null);
        App::auth()->startSession($id, $req);
        flash('ok', 'Вітаємо в ' . App::setting('site.name', 'YEWORK') . '! Акаунт створено.');
        return Response::redirect(url('/cabinet'));
    }

    /* ---------------- відновлення ---------------- */

    public function recoverForm(Request $req): Response
    {
        $st = App::session()->get('rec');
        if (($req->query['restart'] ?? '') === '1') { App::session()->set('rec', null); $st = null; }
        return new Response(view('auth/recover', ['st' => $st, 'title' => 'Відновлення доступу']));
    }

    public function recover(Request $req): Response
    {
        $s = App::session();
        $st = $s->get('rec');
        $stage = $req->str('stage', 10);

        if ($stage === 'contact' || !$st) {
            $c = Crypto::parseContact($req->str('contact', 254));
            if (!$c) throw new ValidationException(['contact' => 'Вкажіть український номер або email']);
            $u = App::auth()->userByContact($c);
            if ($u && $u['status'] === 'active') {
                $site = (string) App::setting('site.name', 'YEWORK');
                $code = App::auth()->issueCode('recover', $c, (int) $u['id'], $req);
                Messaging::send($c['channel'], $c['value'], "$site: код для зміни пароля $code. Діє 10 хвилин. Якщо ви не запитували, проігноруйте.", 'recover_code', "$site: відновлення доступу");
            }
            $s->set('rec', ['contact' => $c['value'], 'kind' => $c['kind'], 'at' => time()]);
            return Response::redirect(url('/recover'));
        }

        $c = Crypto::parseContact((string) $st['contact']);
        $pw = (string) ($req->post['password'] ?? '');
        if (mb_strlen($pw) < self::PASSWORD_MIN || !preg_match('/\p{L}/u', $pw) || !preg_match('/\d/', $pw)) throw new ValidationException(['password' => 'Щонайменше 8 символів, літери та цифри']);
        $row = App::auth()->consumeCode('recover', $c, $req->str('code', 6));
        $u = App::auth()->userByContact($c);
        if (!$u || (int) $u['id'] !== (int) $row['user_id'] || $u['status'] !== 'active') throw new ValidationException(['code' => 'Невірний код'], 'Невірний або прострочений код.');
        $staff = (int) $u['owner_flag'] === 1 || App::auth()->rolesOf((int) $u['id']);
        if ($staff && mb_strlen($pw) < 12) throw new ValidationException(['password' => 'Для адміністраторів — щонайменше 12 символів']);
        App::db()->update('users', ['password_hash' => Crypto::hashPassword($pw), 'updated_at' => Db::now()], 'id = ?', [$u['id']]);
        App::auth()->revokeAll((int) $u['id']);
        Audit::log('auth.password_reset', 'user', $u['id'], ['via' => $c['kind']], (int) $u['id']);
        $s->set('rec', null);
        App::auth()->startSession((int) $u['id'], $req);
        flash('ok', 'Пароль змінено. Ви увійшли в акаунт.');
        return Response::redirect(url($staff ? '/admin' : '/cabinet'));
    }
}

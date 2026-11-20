<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Захист від CSRF за схемою «подвійне надсилання»: випадковий токен у cookie (SameSite=Lax)
 * і той самий токен у прихованому полі форми або заголовку X-CSRF. Чужий сайт не може прочитати cookie.
 */
final class Csrf
{
    private const COOKIE = 'yw_csrf';
    private static ?string $token = null;

    public static function ensureCookie(): void
    {
        $t = $_COOKIE[self::COOKIE] ?? '';
        if (!is_string($t) || !preg_match('/^[A-Za-z0-9_-]{32,64}$/', $t)) {
            $t = Crypto::token(24);
            setcookie(self::COOKIE, $t, ['expires' => 0, 'path' => App::basePath() . '/', 'samesite' => 'Lax', 'secure' => Request::capture()->isHttps(), 'httponly' => false]);
        }
        self::$token = $t;
    }

    public static function token(): string
    {
        if (self::$token === null) self::ensureCookie();
        return (string) self::$token;
    }

    public static function verify(Request $req): void
    {
        $sent = $req->post['_csrf'] ?? ($req->server['HTTP_X_CSRF'] ?? '');
        $cookie = $req->cookies[self::COOKIE] ?? '';
        if (!is_string($sent) || !is_string($cookie) || $cookie === '' || !hash_equals($cookie, $sent)) {
            throw new HttpException(403, 'Сторінка застаріла або відкрита в іншій вкладці. Оновіть сторінку і спробуйте ще раз.');
        }
    }
}

<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Коротка сесія PHP (файли в storage/sessions) — для повідомлень після перенаправлення та кроків інсталятора.
 * Вхід користувача зберігається окремо (див. Auth): токен у cookie + хеш у таблиці sessions.
 */
final class Session
{
    private bool $started = false;

    private function start(): void
    {
        if ($this->started) return;
        $this->started = true;
        if (session_status() === PHP_SESSION_ACTIVE) return;
        $dir = YW_ROOT . '/storage/sessions';
        if (is_dir($dir) && is_writable($dir)) session_save_path($dir);
        session_name('yw_s');
        session_set_cookie_params(['lifetime' => 0, 'path' => App::basePath() . '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => Request::capture()->isHttps()]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.gc_maxlifetime', '7200');
        session_start();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->start();
        if ($value === null) unset($_SESSION[$key]); else $_SESSION[$key] = $value;
    }

    public function regenerate(): void
    {
        $this->start();
        session_regenerate_id(true);
    }

    public function destroy(): void
    {
        $this->start();
        $_SESSION = [];
        session_destroy();
    }
}

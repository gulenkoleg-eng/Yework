<?php
declare(strict_types=1);

const YW_VERSION = '0.4.8';

mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Kyiv');
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', YW_ROOT . '/storage/logs/php-errors.log');
error_reporting(E_ALL);

/**
 * Шлях до файлу програми. Деякі FTP-клієнти (напр. Total Commander) при завантаженні
 * перетворюють назви на малі літери: app/core/http.php замість app/Core/Http.php.
 * Тому, якщо файлу з оригінальною назвою немає, шукаємо його варіант у нижньому регістрі.
 */
function yw_file(string $rel): string
{
    $file = YW_ROOT . '/' . $rel;
    $lower = YW_ROOT . '/' . strtolower($rel);
    if ($lower === $file || !is_file($lower)) return $file;
    // Є обидва варіанти (напр. після оновлення з адмінки, а потім заливки по FTP) — беремо новіший.
    return is_file($file) && filemtime($file) >= filemtime($lower) ? $file : $lower;
}

// Автозавантаження класів App\... з папки app/.
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $file = yw_file('app/' . str_replace('\\', '/', substr($class, 4)) . '.php');
        if (is_file($file)) require $file;
    }
});

require yw_file('app/Core/Http.php');
require yw_file('app/helpers.php');

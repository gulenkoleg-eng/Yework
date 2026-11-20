<?php
declare(strict_types=1);

const YW_VERSION = '0.2.0';

mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Kyiv');
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', YW_ROOT . '/storage/logs/php-errors.log');
error_reporting(E_ALL);

// Автозавантаження класів App\... з папки app/.
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $file = YW_ROOT . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) require $file;
    }
});

require YW_ROOT . '/app/Core/Http.php';
require YW_ROOT . '/app/helpers.php';

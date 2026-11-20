<?php
/**
 * YEWORK — Є робота!
 * Єдина точка входу. Усі запити (крім файлів з assets/) проходять тут.
 */
declare(strict_types=1);

if (PHP_VERSION_ID < 80200) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<h1>Потрібен PHP 8.2 або новіший</h1><p>Зараз встановлено PHP ' . htmlspecialchars(PHP_VERSION) . '. Змініть версію PHP у панелі хостингу (рекомендовано 8.4).</p>';
    exit;
}

define('YW_ROOT', __DIR__);
require YW_ROOT . '/app/bootstrap.php';

App\Core\App::run();

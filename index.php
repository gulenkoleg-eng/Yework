<?php
/**
 * YEWORK — Є робота!
 * Єдина точка входу. Усі запити (крім файлів з assets/) проходять тут.
 *
 * Цей файл навмисно написано простим PHP, який працює навіть на старих версіях,
 * щоб на будь-якому хостингу замість «білої сторінки» показати зрозумілу причину.
 */
declare(strict_types=1);

define('YW_ROOT', __DIR__);

/** Зрозуміла сторінка про проблему з сервером (до встановлення показує технічні деталі). */
function yw_fail(string $title, string $html, int $status = 500): void
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
    }
    echo '<!doctype html><html lang="uk"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>YEWORK: потрібне налаштування</title><style>body{font:16px/1.55 system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#f5f6f8;color:#14161c;margin:0;padding:40px 16px}'
        . '.b{max-width:720px;margin:0 auto;background:#fff;border:1px solid #e3e6eb;border-radius:16px;padding:28px}h1{font-size:24px;margin:0 0 12px}'
        . 'code,pre{background:#f0f2f5;border-radius:6px;padding:2px 6px;font-size:14px}pre{padding:12px;white-space:pre-wrap;word-break:break-word}li{margin:6px 0}.t{color:#b45309;font-weight:700;font-size:13px;letter-spacing:.06em}</style></head>'
        . '<body><div class="b"><div class="t">YEWORK · ПЕРЕВІРКА СЕРВЕРА</div><h1>' . $title . '</h1>' . $html . '</div></body></html>';
}

$ywInstalled = is_file(YW_ROOT . '/config/install.lock');

// 1. Версія PHP.
if (PHP_VERSION_ID < 80200) {
    yw_fail('Потрібен PHP 8.2 або новіший',
        '<p>Зараз на сайті працює PHP <b>' . htmlspecialchars(PHP_VERSION) . '</b>.</p>'
        . '<p>У панелі хостингу відкрийте налаштування PHP для цього сайту і виберіть версію <b>8.4</b>. Потім оновіть цю сторінку.</p>');
    exit;
}

// 2. Розширення PHP.
$ywMissing = [];
foreach (['pdo_mysql' => 'робота з MySQL', 'mbstring' => 'українські тексти', 'openssl' => 'шифрування', 'json' => 'JSON', 'session' => 'сесії'] as $ywExt => $ywWhy) {
    if (!extension_loaded($ywExt)) $ywMissing[] = '<li><code>' . $ywExt . '</code> (' . $ywWhy . ')</li>';
}
if ($ywMissing) {
    yw_fail('Увімкніть розширення PHP',
        '<p>На хостингу вимкнено розширення, без яких сайт не працює:</p><ul>' . implode('', $ywMissing) . '</ul>'
        . '<p>У панелі хостингу відкрийте налаштування PHP (розділ «Версія PHP» або «Розширення PHP»), поставте галочки біля цих розширень і збережіть. Потім оновіть сторінку.</p>');
    exit;
}

// 3. Папки, у які сайт пише файли.
if (!$ywInstalled) {
    $ywDirs = [];
    foreach (['config', 'storage', 'storage/logs', 'storage/cache', 'storage/sessions'] as $ywDir) {
        $ywPath = YW_ROOT . '/' . $ywDir;
        if (!is_dir($ywPath)) @mkdir($ywPath, 0755, true);
        if (!is_dir($ywPath) || !is_writable($ywPath)) $ywDirs[] = '<li><code>' . $ywDir . '</code></li>';
    }
    if ($ywDirs) {
        yw_fail('Немає дозволу на запис у папки',
            '<p>Сайту потрібно створювати файли в цих папках, але хостинг не дозволяє:</p><ul>' . implode('', $ywDirs) . '</ul>'
            . '<p>У FTP-клієнті або файловому менеджері хостингу клацніть на папку правою кнопкою → «Права доступу» (chmod / атрибути) і встановіть <b>755</b>. Якщо не допоможе, спробуйте <b>775</b>. Для папки <code>storage</code> увімкніть «застосувати до вкладених папок».</p>');
        exit;
    }
}

// 4. Фатальні помилки: замість порожньої сторінки показуємо причину.
register_shutdown_function(static function () use ($ywInstalled): void {
    $e = error_get_last();
    if (!$e || !in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) return;
    @error_log('[YEWORK] FATAL ' . $e['message'] . ' @ ' . $e['file'] . ':' . $e['line']);
    while (ob_get_level() > 0) @ob_end_clean();
    if ($ywInstalled) {
        yw_fail('Помилка сервера', '<p>Щось пішло не так. Помилку записано в журнал <code>storage/logs/php-errors.log</code>. Спробуйте ще раз за хвилину.</p>');
    } else {
        $file = str_replace(YW_ROOT, '', (string) $e['file']);
        yw_fail('Помилка під час запуску',
            '<p>Сайт ще не встановлено, тому показуємо технічні деталі. Надішліть цей текст розробнику:</p>'
            . '<pre>' . htmlspecialchars($e['message'] . "\n" . $file . ':' . $e['line'] . "\nPHP " . PHP_VERSION) . '</pre>');
    }
});

set_exception_handler(static function (Throwable $e) use ($ywInstalled): void {
    @error_log('[YEWORK] ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    while (ob_get_level() > 0) @ob_end_clean();
    if ($ywInstalled) {
        yw_fail('Помилка сервера', '<p>Щось пішло не так. Помилку записано в журнал <code>storage/logs/php-errors.log</code>. Спробуйте ще раз за хвилину.</p>');
    } else {
        yw_fail('Помилка під час запуску',
            '<p>Сайт ще не встановлено, тому показуємо технічні деталі. Надішліть цей текст розробнику:</p>'
            . '<pre>' . htmlspecialchars(get_class($e) . ': ' . $e->getMessage() . "\n" . str_replace(YW_ROOT, '', $e->getFile()) . ':' . $e->getLine() . "\nPHP " . PHP_VERSION) . '</pre>');
    }
});

require YW_ROOT . '/app/bootstrap.php';

App\Core\App::run();

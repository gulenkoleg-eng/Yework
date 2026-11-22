<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Telegram;

/** Вебхук: сюди Telegram надсилає повідомлення й натискання кнопок. */
final class TelegramController
{
    public function webhook(Request $req): Response
    {
        $secret = Telegram::config()['secret'];
        $sent = (string) ($req->server['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '');
        if ($secret === '' || !Telegram::ready() || !hash_equals($secret, $sent)) return Response::json(['ok' => false], 403);
        try {
            Telegram::handle($req->post);
        } catch (\Throwable $e) {
            // Завжди відповідаємо 200, інакше Telegram повторюватиме той самий запит.
            error_log('[YEWORK] telegram webhook: ' . $e->getMessage());
        }
        return Response::json(['ok' => true]);
    }
}

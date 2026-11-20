<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Вихідні повідомлення (SMS / email). Кожне записується в чергу outbox (зашифровано) і одразу надсилається.
 *
 * SMS: провайдера ще не обрано, тому драйвер «log» — повідомлення видно власнику в адмінці
 *      («Система → Повідомлення»). Новий провайдер = новий метод sendSms*, решта коду не змінюється.
 * Email: «log», «mail» (функція mail() хостингу) або «smtp».
 */
final class Messaging
{
    /** @return array{ok:bool, driver:string} */
    public static function send(string $channel, string $to, string $text, string $template, ?string $subject = null): array
    {
        $db = App::db();
        $cfg = (array) App::setting('messaging.' . $channel, ['driver' => 'log']);
        $driver = (string) ($cfg['driver'] ?? 'log');
        $id = $db->insert('outbox', ['channel' => $channel, 'driver' => $driver, 'to_enc' => Crypto::encrypt($to), 'subject' => $subject, 'body_enc' => Crypto::encrypt($text), 'template' => $template]);
        try {
            match (true) {
                $driver === 'log' => null,
                $channel === 'email' && $driver === 'mail' => self::sendMail($to, $subject ?? 'YEWORK', $text),
                $channel === 'email' && $driver === 'smtp' => Smtp::send($cfg, $to, $subject ?? 'YEWORK', $text),
                default => throw new \RuntimeException("Невідомий драйвер $driver"),
            };
            $db->update('outbox', ['status' => $driver === 'log' ? 'logged' : 'sent', 'attempts' => 1, 'sent_at' => Db::now()], 'id = ?', [$id]);
            return ['ok' => true, 'driver' => $driver];
        } catch (\Throwable $e) {
            error_log('[YEWORK messaging] ' . $e->getMessage());
            $db->update('outbox', ['status' => 'failed', 'attempts' => 1, 'error' => mb_substr($e->getMessage(), 0, 500)], 'id = ?', [$id]);
            return ['ok' => false, 'driver' => $driver];
        }
    }

    private static function sendMail(string $to, string $subject, string $text): void
    {
        $from = (string) (App::setting('messaging.email')['from'] ?? ('no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost')));
        $headers = "From: $from\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\nMIME-Version: 1.0";
        if (!mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $text, $headers)) throw new \RuntimeException('Функція mail() повернула помилку');
    }
}

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
    /**
     * @param list<array{name:string, mime:string, data:string}> $attachments вкладення (лише email; у черзі зберігається тільки назва файлу)
     * @return array{ok:bool, driver:string}
     */
    public static function send(string $channel, string $to, string $text, string $template, ?string $subject = null, array $attachments = []): array
    {
        $db = App::db();
        $cfg = (array) App::setting('messaging.' . $channel, ['driver' => 'log']);
        $driver = (string) ($cfg['driver'] ?? 'log');
        $id = $db->insert('outbox', ['channel' => $channel, 'driver' => $driver, 'to_enc' => Crypto::encrypt($to), 'subject' => $subject, 'body_enc' => Crypto::encrypt($text . ($attachments ? "\n\n[Вкладення: " . implode(', ', array_column($attachments, 'name')) . ']' : '')), 'template' => $template]);
        try {
            match (true) {
                $driver === 'log' => null,
                $channel === 'email' && $driver === 'mail' => self::sendMail($to, $subject ?? 'YEWORK', $text, $attachments),
                $channel === 'email' && $driver === 'smtp' => Smtp::send($cfg, $to, $subject ?? 'YEWORK', $text, $attachments),
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

    private static function sendMail(string $to, string $subject, string $text, array $attachments = []): void
    {
        $from = (string) (App::setting('messaging.email')['from'] ?? ('no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost')));
        if ($attachments) {
            [$ctype, $body] = self::mimeBody($text, $attachments);
            $headers = "From: $from\r\nMIME-Version: 1.0\r\nContent-Type: $ctype";
        } else {
            $body = $text;
            $headers = "From: $from\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\nMIME-Version: 1.0";
        }
        if (!mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers)) throw new \RuntimeException('Функція mail() повернула помилку');
    }

    /**
     * Тіло листа multipart/mixed: текст + вкладення.
     * @return array{0:string, 1:string} [Content-Type, тіло]
     */
    public static function mimeBody(string $text, array $attachments): array
    {
        $b = 'yw-' . bin2hex(random_bytes(12));
        $body = "--$b\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text));
        foreach ($attachments as $a) {
            $name = '=?UTF-8?B?' . base64_encode($a['name']) . '?=';
            $ascii = preg_replace('/[^A-Za-z0-9._-]/', '_', $a['name']);
            $body .= "--$b\r\nContent-Type: {$a['mime']}; name=\"$name\"\r\nContent-Transfer-Encoding: base64\r\n"
                . "Content-Disposition: attachment; filename=\"$ascii\"; filename*=UTF-8''" . rawurlencode($a['name']) . "\r\n\r\n" . chunk_split(base64_encode($a['data']));
        }
        return ["multipart/mixed; boundary=\"$b\"", $body . "--$b--\r\n"];
    }
}

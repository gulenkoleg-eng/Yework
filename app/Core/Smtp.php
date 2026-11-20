<?php
declare(strict_types=1);

namespace App\Core;

/** Мінімальний SMTP-клієнт: SSL (465) або STARTTLS (587), AUTH LOGIN. Без сторонніх бібліотек. */
final class Smtp
{
    /** @param array{host:string,port:int,secure:string,user:string,passEnc?:string,from:string,fromName?:string} $c */
    public static function send(array $c, string $to, string $subject, string $text): void
    {
        $host = (string) $c['host'];
        $port = (int) $c['port'];
        $secure = (string) ($c['secure'] ?? 'tls');
        $remote = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true, 'peer_name' => $host]]);
        $s = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
        if (!$s) throw new \RuntimeException("SMTP: не вдалося з'єднатися з $host:$port ($errstr)");
        stream_set_timeout($s, 20);
        $read = static function () use ($s): string {
            $data = '';
            while (($line = fgets($s, 1024)) !== false) { $data .= $line; if (isset($line[3]) && $line[3] === ' ') break; }
            return $data;
        };
        $cmd = static function (string $line, array $expect) use ($s, $read): string {
            if ($line !== '') fwrite($s, $line . "\r\n");
            $r = $read();
            if (!in_array((int) substr($r, 0, 3), $expect, true)) throw new \RuntimeException('SMTP: ' . trim($r));
            return $r;
        };
        $ehlo = 'yework.local';
        $cmd('', [220]);
        $cmd("EHLO $ehlo", [250]);
        if ($secure === 'tls') {
            $cmd('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($s, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) throw new \RuntimeException('SMTP: не вдалося увімкнути TLS');
            $cmd("EHLO $ehlo", [250]);
        }
        if (!empty($c['user'])) {
            $pass = Crypto::decrypt($c['passEnc'] ?? null) ?? '';
            $cmd('AUTH LOGIN', [334]);
            $cmd(base64_encode((string) $c['user']), [334]);
            $cmd(base64_encode($pass), [235]);
        }
        $from = (string) $c['from'];
        $cmd("MAIL FROM:<$from>", [250]);
        $cmd("RCPT TO:<$to>", [250, 251]);
        $cmd('DATA', [354]);
        $name = (string) ($c['fromName'] ?? (App::setting('site.name') ?: 'YEWORK'));
        $msg = 'From: =?UTF-8?B?' . base64_encode($name) . "?= <$from>\r\nTo: <$to>\r\nSubject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n"
            . 'Date: ' . date('r') . "\r\nMessage-ID: <" . bin2hex(random_bytes(8)) . '@' . preg_replace('/^.*@/', '', $from) . ">\r\n"
            . "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($text));
        $cmd($msg . "\r\n.", [250]);
        $cmd('QUIT', [221]);
        fclose($s);
    }
}

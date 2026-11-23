<?php
declare(strict_types=1);

namespace App\Core;

/** Ці класи в одному файлі, бо вони маленькі; автозавантажувач підключає файл через Request. */
final class Request
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $post,
        public readonly array $cookies,
        public readonly array $server,
        public array $params = [],
    ) {}

    public static function capture(): self
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $base = App::basePath();
        if ($base !== '' && str_starts_with($uri, $base)) $uri = substr($uri, strlen($base)) ?: '/';
        $uri = '/' . trim(rawurldecode($uri), '/');
        if ($uri === '/index.php') $uri = '/';
        $post = $_POST;
        if (str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
            $raw = file_get_contents('php://input', false, null, 0, 200_000);
            $post = json_decode((string) $raw, true) ?: [];
        }
        return new self(strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'), $uri, $_GET, $post, $_COOKIE, $_SERVER);
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    public function str(string $key, int $max = 10000): string
    {
        $v = $this->input($key, '');
        return is_string($v) ? mb_substr(trim($v), 0, $max) : '';
    }

    public function ip(): string
    {
        return $this->server['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public function userAgent(): string
    {
        return mb_substr($this->server['HTTP_USER_AGENT'] ?? '', 0, 300);
    }

    public function isHttps(): bool
    {
        return (!empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off')
            || ($this->server['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
            || (int) ($this->server['SERVER_PORT'] ?? 80) === 443;
    }

    public function wantsJson(): bool
    {
        return str_contains($this->server['HTTP_ACCEPT'] ?? '', 'application/json') || str_starts_with($this->path, '/api/');
    }
}

final class Response
{
    private array $headers = ['Content-Type' => 'text/html; charset=utf-8'];
    /** Файл, який віддаємо потоком (не читаючи весь у пам’ять). */
    private ?string $file = null;

    public function __construct(public string $body = '', public int $status = 200) {}

    public static function redirect(string $to, int $status = 303): self
    {
        $r = new self('', $status);
        $r->headers['Location'] = $to;
        return $r;
    }

    public static function json(mixed $data, int $status = 200): self
    {
        $r = new self(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $status);
        $r->headers['Content-Type'] = 'application/json; charset=utf-8';
        return $r;
    }

    /** Віддає файл на завантаження. */
    public static function download(string $path, string $name, string $type = 'application/octet-stream'): self
    {
        $r = new self();
        $r->file = $path;
        $r->headers['Content-Type'] = $type;
        $r->headers['Content-Length'] = (string) filesize($path);
        $r->headers['Content-Disposition'] = 'attachment; filename="' . preg_replace('/[^\w.-]/', '_', $name) . '"';
        $r->headers['Cache-Control'] = 'private, no-store';
        return $r;
    }

    public function header(string $k, string $v): self { $this->headers[$k] = $v; return $this; }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $k => $v) header("$k: $v");
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: DENY');
            header('Referrer-Policy: strict-origin-when-cross-origin');
        }
        if ($this->file !== null) {
            while (ob_get_level() > 0) ob_end_clean();
            readfile($this->file);
            return;
        }
        echo $this->body;
    }
}

class HttpException extends \RuntimeException
{
    public function __construct(public readonly int $status, string $message = '')
    {
        parent::__construct($message);
    }
}

/** Помилки валідації: повертаємо форму з підсвіченими полями. */
final class ValidationException extends HttpException
{
    public readonly array $errors;

    public function __construct(array $errors, string $message = 'Перевірте виділені поля.')
    {
        $this->errors = array_filter($errors, static fn ($v) => $v !== null);
        parent::__construct(422, $message);
    }
}

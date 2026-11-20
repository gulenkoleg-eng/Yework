<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Простий маршрутизатор: '/jobs/{id}' → [Controller::class, 'method'].
 * Права вказуються третім аргументом: null — публічно, [] — потрібен вхід, ['perm.code'] — потрібні права.
 */
final class Router
{
    private array $routes = [];

    public function get(string $pattern, array $handler, ?array $perms = null): void { $this->add('GET', $pattern, $handler, $perms); }
    public function post(string $pattern, array $handler, ?array $perms = null): void { $this->add('POST', $pattern, $handler, $perms); }

    private function add(string $method, string $pattern, array $handler, ?array $perms): void
    {
        $re = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#u';
        $this->routes[] = [$method, $re, $handler, $perms];
    }

    public function dispatch(Request $req): Response
    {
        $allowed = false;
        foreach ($this->routes as [$method, $re, $handler, $perms]) {
            if (!preg_match($re, $req->path, $m)) continue;
            if ($method !== $req->method) { $allowed = true; continue; }
            $req->params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            if ($perms !== null) {
                $user = App::auth()->user();
                if (!$user) {
                    if ($req->wantsJson()) throw new HttpException(401, 'Потрібно увійти.');
                    return Response::redirect(url('/login', ['next' => $req->path]));
                }
                foreach ($perms as $p) {
                    if (!App::auth()->can($p)) throw new HttpException(403, 'Недостатньо прав для цієї дії.');
                }
            }
            [$class, $fn] = $handler;
            try {
                return (new $class())->$fn($req);
            } catch (ValidationException $e) {
                if ($req->wantsJson()) return Response::json(['message' => $e->getMessage(), 'fields' => $e->errors], 422);
                App::$errors = $e->errors;
                App::$old = $req->post;
                App::$formError = $e->getMessage();
                // Повторно показати ту саму сторінку з помилками (GET-обробник того ж шляху).
                foreach ($this->routes as [$m2, $re2, $h2, $p2]) {
                    if ($m2 === 'GET' && preg_match($re2, $req->path)) {
                        [$c2, $f2] = $h2;
                        $r = (new $c2())->$f2($req);
                        $r->status = 422;
                        return $r;
                    }
                }
                throw $e;
            }
        }
        throw new HttpException($allowed ? 405 : 404, $allowed ? 'Метод не підтримується.' : 'Такої сторінки немає. Можливо, її видалили або адреса введена з помилкою.');
    }
}

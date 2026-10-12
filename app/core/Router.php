<?php
declare(strict_types=1);

/**
 * Маршрутизация: сопоставление URL с контроллерами.
 * Регистрация: $router->add('GET', '/login', 'LoginPage');
 */
class Router
{
    /** @var array<string, array<string,string>> метод => путь => класс контроллера */
    private array $routes = ['GET' => [], 'POST' => []];

    public function add(string $method, string $path, string $handlerClass): void
    {
        $this->routes[$method][rtrim($path, '/') ?: '/'] = $handlerClass;
    }

    /** Разбор пути: убираем префикс подпапки (например, /oks если приложение в sebekin.ru/oks). */
    private function normalizePath(string $uri): string
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        if ($scriptDir !== '' && $scriptDir !== '/' && str_starts_with($path, $scriptDir)) {
            $path = substr($path, strlen($scriptDir)) ?: '/';
        }
        return rtrim($path, '/') ?: '/';
    }

    /** @return callable возвращает объект контроллера с методом handle() */
    public function resolve(string $uri, string $method): callable
    {
        $path = $this->normalizePath($uri);
        $method = strtoupper($method);
        $table = $this->routes[$method] ?? [];

        if (isset($table[$path])) {
            $cls = $table[$path];
            return fn() => new $cls();
        }

        // Точный поиск среди GET, если метод неизвестен
        foreach (['GET', 'POST'] as $m) {
            if (isset($this->routes[$m][$path])) {
                $cls = $this->routes[$m][$path];
                return fn() => new $cls();
            }
        }

        http_response_code(404);
        return fn() => new class { public function handle(): void { echo '<h1 style="font-family:sans-serif">Страница не найдена (404)</h1><p><a href="/">На главную</a></p>'; } };
    }
}

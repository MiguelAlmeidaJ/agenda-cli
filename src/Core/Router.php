<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    private array $routes = [];

    public function get(string $path, callable|array $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable|array $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    private function add(string $method, string $path, callable|array $handler): void
    {
        $path = $this->normalize($path);
        $pattern = preg_replace('/\{[a-zA-Z_][a-zA-Z0-9_]*\}/', '([^/]+)', $path);
        $this->routes[$method][] = ['pattern' => '#^' . $pattern . '$#', 'handler' => $handler];
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = $this->normalize((string) parse_url($uri, PHP_URL_PATH));

        foreach ($this->routes[$method] ?? [] as $route) {
            if (!preg_match($route['pattern'], $path, $matches)) {
                continue;
            }

            array_shift($matches);
            $handler = $route['handler'];
            if (is_array($handler) && is_string($handler[0])) {
                $handler = [new $handler[0](), $handler[1]];
            }
            $handler(...array_map('urldecode', array_values($matches)));
            return;
        }

        http_response_code(404);
        View::render('errors/404', ['title' => 'Página não encontrada']);
    }

    private function normalize(string $path): string
    {
        return ($path === '' || $path === '/') ? '/' : '/' . trim($path, '/');
    }
}

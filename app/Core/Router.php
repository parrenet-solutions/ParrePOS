<?php

namespace App\Core;

use App\Shared\Exceptions\HttpException;

class Router
{
    private array $routes = [];
    private array $middlewares = [];

    public function add(string $method, string $path, callable $handler, array $middlewares = []): void
    {
        $method = strtoupper($method);
        $this->routes[$method][$path] = [
            'handler' => $handler,
            'middlewares' => $middlewares,
        ];
    }

    public function get(string $path, callable $handler, array $middlewares = []): void
    {
        $this->add('GET', $path, $handler, $middlewares);
    }

    public function post(string $path, callable $handler, array $middlewares = []): void
    {
        $this->add('POST', $path, $handler, $middlewares);
    }

    public function addMiddleware(object $middleware): void
    {
        $this->middlewares[] = $middleware;
    }

    public function dispatch(Request $request): array
    {
        $method = $request->getMethod();
        $path = $request->getPath();

        if (!isset($this->routes[$method][$path])) {
            throw new HttpException(404, 'NOT_FOUND', 'Ruta no encontrada');
        }

        $route = $this->routes[$method][$path];
        $handler = $route['handler'];
        $pipeline = array_merge($this->middlewares, $route['middlewares']);

        $lastRequest = $request;

        $next = function (Request $req) use ($handler, &$lastRequest): array {
            $lastRequest = $req;
            return $handler($req);
        };

        foreach (array_reverse($pipeline) as $middleware) {
            $next = function (Request $req) use ($middleware, $next, &$lastRequest): array {
                $lastRequest = $req;
                if (!method_exists($middleware, 'handle')) {
                    throw new HttpException(500, 'MIDDLEWARE_INVALID', 'Middleware inválido');
                }
                return $middleware->handle($req, $next);
            };
        }

        $result = $next($request);
        $result['request'] = $lastRequest;

        return $result;
    }
}

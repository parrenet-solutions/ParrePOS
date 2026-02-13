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
        $paramNames = [];
        $pattern = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', function (array $matches) use (&$paramNames): string {
            $paramNames[] = $matches[1];
            return '([^/]+)';
        }, $path);
        $pattern = '#^' . $pattern . '$#';

        $this->routes[$method][$path] = [
            'handler' => $handler,
            'middlewares' => $middlewares,
            'pattern' => $pattern,
            'params' => $paramNames,
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

    public function put(string $path, callable $handler, array $middlewares = []): void
    {
        $this->add('PUT', $path, $handler, $middlewares);
    }

    public function addMiddleware(object $middleware): void
    {
        $this->middlewares[] = $middleware;
    }

    public function dispatch(Request $request): array
    {
        $method = $request->getMethod();
        $path = $request->getPath();

        if (!isset($this->routes[$method])) {
            throw new HttpException(404, 'NOT_FOUND', 'Ruta no encontrada');
        }

        $route = $this->routes[$method][$path] ?? null;
        $params = [];

        if ($route === null) {
            foreach ($this->routes[$method] as $candidate) {
                if (preg_match($candidate['pattern'], $path, $matches)) {
                    array_shift($matches);
                    $params = [];
                    foreach ($candidate['params'] as $index => $name) {
                        $params[$name] = $matches[$index] ?? null;
                    }
                    $route = $candidate;
                    break;
                }
            }
        }

        if ($route === null) {
            throw new HttpException(404, 'NOT_FOUND', 'Ruta no encontrada');
        }

        $request = $request->withParams($params);
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

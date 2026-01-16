<?php

namespace App\Core\Middleware;

use App\Core\Request;

class RequestIdMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): array
    {
        $existing = $request->getAttribute('request_id') ?? $request->getHeader('X-Request-Id');
        $requestId = $existing ?: bin2hex(random_bytes(16));
        $request = $request->withAttribute('request_id', $requestId);

        return $next($request);
    }
}

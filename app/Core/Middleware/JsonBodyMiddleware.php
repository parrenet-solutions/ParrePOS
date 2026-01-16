<?php

namespace App\Core\Middleware;

use App\Core\Request;
use App\Shared\Exceptions\HttpException;

class JsonBodyMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): array
    {
        $contentType = $request->getHeader('Content-Type') ?? '';
        $rawBody = $request->getRawBody();

        if ($rawBody !== '' && stripos($contentType, 'application/json') !== false) {
            $decoded = json_decode($rawBody, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new HttpException(400, 'JSON_INVALID', 'JSON inválido');
            }
            $request = $request->withJson($decoded);
        }

        return $next($request);
    }
}

<?php

namespace App\RBAC;

use App\Core\Middleware\MiddlewareInterface;
use App\Core\Request;
use App\Shared\Exceptions\HttpException;

class AuthorizationMiddleware implements MiddlewareInterface
{
    private string $requiredPermission;

    public function __construct(string $requiredPermission)
    {
        $this->requiredPermission = $requiredPermission;
    }

    public function handle(Request $request, callable $next): array
    {
        $permissions = $request->getAttribute('permissions', []);
        if (!in_array($this->requiredPermission, $permissions, true)) {
            throw new HttpException(403, 'FORBIDDEN', 'Permiso requerido');
        }

        return $next($request);
    }
}

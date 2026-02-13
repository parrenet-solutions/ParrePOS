<?php

namespace App\Auth;

use App\Core\Middleware\MiddlewareInterface;
use App\Core\Request;
use App\RBAC\RoleRepository;
use App\Shared\Exceptions\HttpException;

class AuthMiddleware implements MiddlewareInterface
{
    private JwtService $jwtService;
    private RoleRepository $roleRepository;

    public function __construct(JwtService $jwtService, RoleRepository $roleRepository)
    {
        $this->jwtService = $jwtService;
        $this->roleRepository = $roleRepository;
    }

    public function handle(Request $request, callable $next): array
    {
        $authHeader = $request->getHeader('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            throw new HttpException(401, 'UNAUTHORIZED', 'Token requerido');
        }

        $token = trim(substr($authHeader, 7));
        $payload = $this->jwtService->decodeAccess($token);

        $userId = (int) ($payload['sub'] ?? 0);
        $tenantId = (int) ($payload['tenant_id'] ?? 0);

        if ($userId <= 0 || $tenantId <= 0) {
            throw new HttpException(401, 'UNAUTHORIZED', 'Token inválido');
        }

        $roles = $this->roleRepository->getRolesForUser($userId, $tenantId);
        $permissions = $this->roleRepository->getPermissionsForUser($userId, $tenantId);

        $request = $request
            ->withAttribute('user_id', $userId)
            ->withAttribute('tenant_id', $tenantId)
            ->withAttribute('roles', $roles)
            ->withAttribute('permissions', $permissions);

        return $next($request);
    }
}

<?php

namespace App\Users;

use App\Core\Request;
use App\Shared\Exceptions\HttpException;

class UserController
{
    private UserRepository $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function me(Request $request): array
    {
        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $userId = (int) $request->getAttribute('user_id', 0);

        if ($tenantId <= 0 || $userId <= 0) {
            throw new HttpException(401, 'UNAUTHORIZED', 'Sesión inválida');
        }

        $user = $this->userRepository->findById($userId, $tenantId);
        if (!$user) {
            throw new HttpException(404, 'NOT_FOUND', 'Usuario no encontrado');
        }

        return [
            'status' => 200,
            'data' => [
                'user_id' => $userId,
                'email' => $user['email'],
                'tenant_id' => $tenantId,
                'roles' => (array) $request->getAttribute('roles', []),
                'permissions' => (array) $request->getAttribute('permissions', []),
            ],
        ];
    }
}

<?php

namespace App\Auth;

use App\Core\Request;
use App\Core\Security\RateLimiter;
use App\Audit\AuditLogger;
use App\Shared\Exceptions\HttpException;
use App\RBAC\RoleRepository;
use App\Users\UserRepository;
use App\Users\UserService;

class AuthController
{
    private UserRepository $userRepository;
    private UserService $userService;
    private JwtService $jwtService;
    private RefreshTokenRepository $refreshTokenRepository;
    private RateLimiter $rateLimiter;
    private AuditLogger $auditLogger;
    private RoleRepository $roleRepository;

    public function __construct(
        UserRepository $userRepository,
        UserService $userService,
        JwtService $jwtService,
        RefreshTokenRepository $refreshTokenRepository,
        RateLimiter $rateLimiter,
        AuditLogger $auditLogger,
        RoleRepository $roleRepository
    ) {
        $this->userRepository = $userRepository;
        $this->userService = $userService;
        $this->jwtService = $jwtService;
        $this->refreshTokenRepository = $refreshTokenRepository;
        $this->rateLimiter = $rateLimiter;
        $this->auditLogger = $auditLogger;
        $this->roleRepository = $roleRepository;
    }

    public function login(Request $request): array
    {
        $payload = $request->getJson();
        if (!is_array($payload)) {
            throw new HttpException(400, 'INVALID_BODY', 'Se requiere JSON');
        }

        $email = trim((string) ($payload['email'] ?? ''));
        $password = (string) ($payload['password'] ?? '');

        if ($email === '' || $password === '') {
            throw new HttpException(422, 'VALIDATION_ERROR', 'Email y password requeridos');
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if ($this->rateLimiter->tooManyAttempts('login:' . $ip, 10, 60)) {
            throw new HttpException(429, 'RATE_LIMIT', 'Demasiados intentos');
        }

        $user = $this->userRepository->findByEmail($email);
        if (!$user || !$this->userService->verifyPassword($password, $user['password_hash'])) {
            $this->auditLogger->log((int) ($user['tenant_id'] ?? 0), (int) ($user['id'] ?? 0), 'auth.login_failed', ['email' => $email]);
            throw new HttpException(401, 'UNAUTHORIZED', 'Credenciales inválidas');
        }

        if (($user['status'] ?? 'ACTIVE') !== 'ACTIVE') {
            throw new HttpException(403, 'USER_INACTIVE', 'Usuario inactivo');
        }

        $tenantId = (int) $user['tenant_id'];
        $userId = (int) $user['id'];
        $roles = $this->roleRepository->getRolesForUser($userId, $tenantId);

        $accessToken = $this->jwtService->generateAccessToken($userId, $tenantId, $roles);
        $refreshToken = $this->jwtService->generateRefreshToken($userId, $tenantId);

        $tokenHash = hash('sha256', $refreshToken);
        $expiresAt = date('Y-m-d H:i:s', time() + $this->jwtService->getRefreshTtl());
        $this->refreshTokenRepository->create($tenantId, $userId, $tokenHash, $expiresAt);

        if (!$this->auditLogger->hasAction($tenantId, $userId, 'auth.login')) {
            $this->auditLogger->log($tenantId, $userId, 'auth.first_login', ['ip' => $ip]);
        }
        $this->auditLogger->log($tenantId, $userId, 'auth.login', ['ip' => $ip]);

        return [
            'status' => 200,
            'data' => [
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'token_type' => 'Bearer',
                'expires_in' => $this->jwtService->getAccessTtl(),
            ],
        ];
    }

    public function refresh(Request $request): array
    {
        $payload = $request->getJson();
        if (!is_array($payload)) {
            throw new HttpException(400, 'INVALID_BODY', 'Se requiere JSON');
        }

        $refreshToken = (string) ($payload['refresh_token'] ?? '');
        if ($refreshToken === '') {
            throw new HttpException(422, 'VALIDATION_ERROR', 'refresh_token requerido');
        }

        $decoded = $this->jwtService->decodeRefresh($refreshToken);
        $tokenHash = hash('sha256', $refreshToken);
        $stored = $this->refreshTokenRepository->findValid($tokenHash);

        if (!$stored) {
            throw new HttpException(401, 'UNAUTHORIZED', 'Refresh token inválido');
        }

        $userId = (int) $decoded['sub'];
        $tenantId = (int) $decoded['tenant_id'];

        if ((int) $stored['user_id'] !== $userId || (int) $stored['tenant_id'] !== $tenantId) {
            throw new HttpException(401, 'UNAUTHORIZED', 'Refresh token inválido');
        }

        $roles = $this->roleRepository->getRolesForUser($userId, $tenantId);
        $newAccess = $this->jwtService->generateAccessToken($userId, $tenantId, $roles);
        $newRefresh = $this->jwtService->generateRefreshToken($userId, $tenantId);

        $newHash = hash('sha256', $newRefresh);
        $expiresAt = date('Y-m-d H:i:s', time() + $this->jwtService->getRefreshTtl());
        $newId = $this->refreshTokenRepository->create($tenantId, $userId, $newHash, $expiresAt, null);
        $this->refreshTokenRepository->revoke((int) $stored['id'], $newId);

        $this->auditLogger->log($tenantId, $userId, 'auth.refresh', []);

        return [
            'status' => 200,
            'data' => [
                'access_token' => $newAccess,
                'refresh_token' => $newRefresh,
                'token_type' => 'Bearer',
                'expires_in' => $this->jwtService->getAccessTtl(),
            ],
        ];
    }

    public function logout(Request $request): array
    {
        $payload = $request->getJson();
        $refreshToken = is_array($payload) ? (string) ($payload['refresh_token'] ?? '') : '';

        if ($refreshToken !== '') {
            $tokenHash = hash('sha256', $refreshToken);
            $stored = $this->refreshTokenRepository->findValid($tokenHash);
            if ($stored) {
                $this->refreshTokenRepository->revoke((int) $stored['id']);
                $this->auditLogger->log((int) $stored['tenant_id'], (int) $stored['user_id'], 'auth.logout', []);
            }
        }

        return [
            'status' => 200,
            'data' => ['logout' => true],
        ];
    }

}

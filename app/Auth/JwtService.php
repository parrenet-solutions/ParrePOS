<?php

namespace App\Auth;

use App\Shared\Exceptions\HttpException;

class JwtService
{
    private string $accessSecret;
    private string $refreshSecret;
    private int $accessTtl;
    private int $refreshTtl;

    public function __construct(string $accessSecret, string $refreshSecret, int $accessTtl, int $refreshTtl)
    {
        $this->accessSecret = $accessSecret;
        $this->refreshSecret = $refreshSecret;
        $this->accessTtl = $accessTtl;
        $this->refreshTtl = $refreshTtl;
    }

    public function generateAccessToken(int $userId, int $tenantId, array $roles): string
    {
        return $this->encode([
            'typ' => 'access',
            'sub' => $userId,
            'tenant_id' => $tenantId,
            'roles' => $roles,
        ], $this->accessSecret, $this->accessTtl);
    }

    public function generateRefreshToken(int $userId, int $tenantId): string
    {
        return $this->encode([
            'typ' => 'refresh',
            'sub' => $userId,
            'tenant_id' => $tenantId,
        ], $this->refreshSecret, $this->refreshTtl);
    }

    public function decodeAccess(string $token): array
    {
        return $this->decode($token, $this->accessSecret, 'access');
    }

    public function decodeRefresh(string $token): array
    {
        return $this->decode($token, $this->refreshSecret, 'refresh');
    }

    public function getAccessTtl(): int
    {
        return $this->accessTtl;
    }

    public function getRefreshTtl(): int
    {
        return $this->refreshTtl;
    }

    private function encode(array $claims, string $secret, int $ttl): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $now = time();
        $payload = array_merge($claims, [
            'iat' => $now,
            'exp' => $now + $ttl,
            'jti' => bin2hex(random_bytes(16)),
        ]);

        $segments = [
            $this->base64UrlEncode(json_encode($header)),
            $this->base64UrlEncode(json_encode($payload)),
        ];

        $signature = hash_hmac('sha256', implode('.', $segments), $secret, true);
        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    private function decode(string $token, string $secret, string $expectedType): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new HttpException(401, 'TOKEN_INVALID', 'Token inválido');
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
        $signature = $this->base64UrlDecode($encodedSignature);
        $expected = hash_hmac('sha256', $encodedHeader . '.' . $encodedPayload, $secret, true);

        if (!hash_equals($expected, $signature)) {
            throw new HttpException(401, 'TOKEN_INVALID', 'Token inválido');
        }

        $payload = json_decode($this->base64UrlDecode($encodedPayload), true);
        if (!is_array($payload)) {
            throw new HttpException(401, 'TOKEN_INVALID', 'Token inválido');
        }

        if (($payload['typ'] ?? '') !== $expectedType) {
            throw new HttpException(401, 'TOKEN_INVALID', 'Token inválido');
        }

        if (($payload['exp'] ?? 0) < time()) {
            throw new HttpException(401, 'TOKEN_EXPIRED', 'Token expirado');
        }

        return $payload;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/')) ?: '';
    }
}

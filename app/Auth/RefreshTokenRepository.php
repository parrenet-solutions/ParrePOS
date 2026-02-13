<?php

namespace App\Auth;

use PDO;

class RefreshTokenRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(int $tenantId, int $userId, string $tokenHash, string $expiresAt, ?int $replacedBy = null): int
    {
        $stmt = $this->db->prepare('INSERT INTO refresh_tokens (tenant_id, user_id, token_hash, expires_at, replaced_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
        $stmt->execute([$tenantId, $userId, $tokenHash, $expiresAt, $replacedBy]);

        return (int) $this->db->lastInsertId();
    }

    public function findValid(string $tokenHash): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM refresh_tokens WHERE token_hash = ? AND revoked_at IS NULL AND expires_at > NOW() LIMIT 1');
        $stmt->execute([$tokenHash]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function revoke(int $id, ?int $replacedBy = null): void
    {
        $stmt = $this->db->prepare('UPDATE refresh_tokens SET revoked_at = NOW(), replaced_by = ? WHERE id = ?');
        $stmt->execute([$replacedBy, $id]);
    }
}

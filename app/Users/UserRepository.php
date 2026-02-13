<?php

namespace App\Users;

use PDO;

class UserRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function findByEmailAndTenantSlug(string $email, string $tenantSlug): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT users.*, tenants.slug AS tenant_slug, tenants.status AS tenant_status
             FROM users
             INNER JOIN tenants ON tenants.id = users.tenant_id
             WHERE users.email = ? AND tenants.slug = ?
             LIMIT 1'
        );
        $stmt->execute([$email, $tenantSlug]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findById(int $id, int $tenantId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

}

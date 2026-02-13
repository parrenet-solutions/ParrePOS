<?php

namespace App\Audit;

use PDO;

class AuditRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function insert(int $tenantId, int $userId, string $action, array $meta): void
    {
        $stmt = $this->db->prepare('INSERT INTO audit_log (tenant_id, user_id, action, meta, created_at) VALUES (?, ?, ?, ?, NOW())');
        $stmt->execute([$tenantId, $userId, $action, json_encode($meta)]);
    }

    public function hasAction(int $tenantId, int $userId, string $action): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM audit_log WHERE tenant_id = ? AND user_id = ? AND action = ? LIMIT 1');
        $stmt->execute([$tenantId, $userId, $action]);

        return (bool) $stmt->fetch();
    }
}

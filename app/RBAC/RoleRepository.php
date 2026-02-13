<?php

namespace App\RBAC;

use PDO;

class RoleRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getRolesForUser(int $userId, int $tenantId): array
    {
        $stmt = $this->db->prepare('SELECT r.code FROM roles r INNER JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ? AND ur.tenant_id = ?');
        $stmt->execute([$userId, $tenantId]);
        return array_map(fn ($row) => $row['code'], $stmt->fetchAll());
    }

    public function getPermissionsForUser(int $userId, int $tenantId): array
    {
        $stmt = $this->db->prepare(
            'SELECT DISTINCT p.code FROM permissions p '
            . 'INNER JOIN role_permissions rp ON rp.permission_id = p.id '
            . 'INNER JOIN roles r ON r.id = rp.role_id '
            . 'INNER JOIN user_roles ur ON ur.role_id = r.id '
            . 'WHERE ur.user_id = ? AND ur.tenant_id = ?'
        );
        $stmt->execute([$userId, $tenantId]);
        return array_map(fn ($row) => $row['code'], $stmt->fetchAll());
    }
}

<?php

namespace App\Pos;

use PDO;

class BranchRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(int $tenantId, array $data): int
    {
        $stmt = $this->db->prepare('INSERT INTO branches (tenant_id, name, address, status, created_at) VALUES (?, ?, ?, ?, NOW())');
        $stmt->execute([$tenantId, $data['name'], $data['address'], $data['status']]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $tenantId, int $id, array $data): void
    {
        $stmt = $this->db->prepare('UPDATE branches SET name = ?, address = ?, status = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?');
        $stmt->execute([$data['name'], $data['address'], $data['status'], $id, $tenantId]);
    }

    public function findById(int $tenantId, int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM branches WHERE tenant_id = ? AND id = ? LIMIT 1');
        $stmt->execute([$tenantId, $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function list(int $tenantId, ?string $search): array
    {
        if ($search) {
            $stmt = $this->db->prepare('SELECT * FROM branches WHERE tenant_id = ? AND name LIKE ? ORDER BY id DESC');
            $stmt->execute([$tenantId, '%' . $search . '%']);
            return $stmt->fetchAll();
        }

        $stmt = $this->db->prepare('SELECT * FROM branches WHERE tenant_id = ? ORDER BY id DESC');
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll();
    }
}

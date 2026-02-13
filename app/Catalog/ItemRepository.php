<?php

namespace App\Catalog;

use PDO;

class ItemRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(int $tenantId, array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO catalog_items (tenant_id, type, name, price, itbis_rate, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $tenantId,
            $data['type'],
            $data['name'],
            $data['price'],
            $data['itbis_rate'],
            $data['status'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $tenantId, int $id, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE catalog_items SET type = ?, name = ?, price = ?, itbis_rate = ?, status = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?'
        );
        $stmt->execute([
            $data['type'],
            $data['name'],
            $data['price'],
            $data['itbis_rate'],
            $data['status'],
            $id,
            $tenantId,
        ]);
    }

    public function findById(int $tenantId, int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM catalog_items WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function list(int $tenantId, ?string $search): array
    {
        if ($search) {
            $stmt = $this->db->prepare('SELECT * FROM catalog_items WHERE tenant_id = ? AND name LIKE ? ORDER BY id DESC');
            $stmt->execute([$tenantId, '%' . $search . '%']);
            return $stmt->fetchAll();
        }

        $stmt = $this->db->prepare('SELECT * FROM catalog_items WHERE tenant_id = ? ORDER BY id DESC');
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll();
    }
}

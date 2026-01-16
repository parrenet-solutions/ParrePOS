<?php

namespace App\Customers;

use PDO;

class CustomerRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(int $tenantId, array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO customers (tenant_id, name, type, doc_number, email, phone, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $tenantId,
            $data['name'],
            $data['type'],
            $data['doc_number'],
            $data['email'],
            $data['phone'],
            $data['status'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $tenantId, int $id, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE customers SET name = ?, type = ?, doc_number = ?, email = ?, phone = ?, status = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?'
        );
        $stmt->execute([
            $data['name'],
            $data['type'],
            $data['doc_number'],
            $data['email'],
            $data['phone'],
            $data['status'],
            $id,
            $tenantId,
        ]);
    }

    public function findById(int $tenantId, int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM customers WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function list(int $tenantId, ?string $search): array
    {
        if ($search) {
            $stmt = $this->db->prepare('SELECT * FROM customers WHERE tenant_id = ? AND name LIKE ? ORDER BY id DESC');
            $stmt->execute([$tenantId, '%' . $search . '%']);
            return $stmt->fetchAll();
        }

        $stmt = $this->db->prepare('SELECT * FROM customers WHERE tenant_id = ? ORDER BY id DESC');
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll();
    }

    public function existsDocNumber(int $tenantId, ?string $docNumber, ?int $excludeId = null): bool
    {
        if ($docNumber === null || $docNumber === '') {
            return false;
        }

        if ($excludeId) {
            $stmt = $this->db->prepare('SELECT id FROM customers WHERE tenant_id = ? AND doc_number = ? AND id <> ? LIMIT 1');
            $stmt->execute([$tenantId, $docNumber, $excludeId]);
        } else {
            $stmt = $this->db->prepare('SELECT id FROM customers WHERE tenant_id = ? AND doc_number = ? LIMIT 1');
            $stmt->execute([$tenantId, $docNumber]);
        }

        return (bool) $stmt->fetch();
    }
}

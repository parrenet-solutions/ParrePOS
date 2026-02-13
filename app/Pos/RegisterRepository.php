<?php

namespace App\Pos;

use PDO;

class RegisterRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(int $tenantId, array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO pos_registers (tenant_id, branch_id, name, device_id, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$tenantId, $data['branch_id'], $data['name'], $data['device_id'], $data['status']]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $tenantId, int $id, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE pos_registers SET branch_id = ?, name = ?, device_id = ?, status = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?'
        );
        $stmt->execute([$data['branch_id'], $data['name'], $data['device_id'], $data['status'], $id, $tenantId]);
    }

    public function findById(int $tenantId, int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM pos_registers WHERE tenant_id = ? AND id = ? LIMIT 1');
        $stmt->execute([$tenantId, $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findByDevice(int $tenantId, string $deviceId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM pos_registers WHERE tenant_id = ? AND device_id = ? LIMIT 1');
        $stmt->execute([$tenantId, $deviceId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function list(int $tenantId, ?string $search): array
    {
        if ($search) {
            $stmt = $this->db->prepare('SELECT * FROM pos_registers WHERE tenant_id = ? AND name LIKE ? ORDER BY id DESC');
            $stmt->execute([$tenantId, '%' . $search . '%']);
            return $stmt->fetchAll();
        }

        $stmt = $this->db->prepare('SELECT * FROM pos_registers WHERE tenant_id = ? ORDER BY id DESC');
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll();
    }

    public function existsDevice(int $tenantId, string $deviceId, ?int $excludeId = null): bool
    {
        if ($excludeId) {
            $stmt = $this->db->prepare('SELECT id FROM pos_registers WHERE tenant_id = ? AND device_id = ? AND id <> ? LIMIT 1');
            $stmt->execute([$tenantId, $deviceId, $excludeId]);
        } else {
            $stmt = $this->db->prepare('SELECT id FROM pos_registers WHERE tenant_id = ? AND device_id = ? LIMIT 1');
            $stmt->execute([$tenantId, $deviceId]);
        }

        return (bool) $stmt->fetch();
    }
}

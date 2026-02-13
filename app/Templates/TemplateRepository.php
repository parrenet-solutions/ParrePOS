<?php

namespace App\Templates;

use PDO;

class TemplateRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(int $tenantId, array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO invoice_templates (tenant_id, name, config_json, is_default, created_at) VALUES (?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$tenantId, $data['name'], json_encode($data['config']), $data['is_default']]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $tenantId, int $id, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE invoice_templates SET name = ?, config_json = ?, updated_at = NOW() WHERE tenant_id = ? AND id = ?'
        );
        $stmt->execute([$data['name'], json_encode($data['config']), $tenantId, $id]);
    }

    public function findById(int $tenantId, int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM invoice_templates WHERE tenant_id = ? AND id = ? LIMIT 1');
        $stmt->execute([$tenantId, $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function list(int $tenantId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM invoice_templates WHERE tenant_id = ? ORDER BY id DESC');
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll();
    }

    public function setDefault(int $tenantId, int $id): void
    {
        $this->db->prepare('UPDATE invoice_templates SET is_default = 0 WHERE tenant_id = ?')->execute([$tenantId]);
        $this->db->prepare('UPDATE invoice_templates SET is_default = 1 WHERE tenant_id = ? AND id = ?')
            ->execute([$tenantId, $id]);
    }
}

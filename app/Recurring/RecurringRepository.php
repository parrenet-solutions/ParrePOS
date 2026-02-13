<?php

namespace App\Recurring;

use PDO;

class RecurringRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(int $tenantId, array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO recurring_rules (tenant_id, customer_id, items_json, interval_days, status, next_run_at, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $tenantId,
            $data['customer_id'],
            json_encode($data['items']),
            $data['interval_days'],
            'ACTIVE',
            $data['next_run_at'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function list(int $tenantId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM recurring_rules WHERE tenant_id = ? ORDER BY id DESC');
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll();
    }

    public function findById(int $tenantId, int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM recurring_rules WHERE tenant_id = ? AND id = ? LIMIT 1');
        $stmt->execute([$tenantId, $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findAnyById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM recurring_rules WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function updateStatus(int $tenantId, int $id, string $status): void
    {
        $stmt = $this->db->prepare('UPDATE recurring_rules SET status = ?, updated_at = NOW() WHERE tenant_id = ? AND id = ?');
        $stmt->execute([$status, $tenantId, $id]);
    }

    public function listDue(): array
    {
        $stmt = $this->db->prepare('SELECT * FROM recurring_rules WHERE status = ? AND next_run_at <= NOW() ORDER BY next_run_at ASC');
        $stmt->execute(['ACTIVE']);
        return $stmt->fetchAll();
    }

    public function bumpNextRun(int $id, string $nextRunAt): void
    {
        $stmt = $this->db->prepare('UPDATE recurring_rules SET next_run_at = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$nextRunAt, $id]);
    }

    public function claimRun(int $id, string $currentNextRunAt, string $nextRunAt): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE recurring_rules SET next_run_at = ?, updated_at = NOW() WHERE id = ? AND next_run_at = ? AND status = ?'
        );
        $stmt->execute([$nextRunAt, $id, $currentNextRunAt, 'ACTIVE']);

        return $stmt->rowCount() > 0;
    }
}

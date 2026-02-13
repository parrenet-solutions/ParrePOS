<?php

namespace App\Pos;

use PDO;

class CashSessionRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function createOpen(int $tenantId, array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO pos_cash_sessions (tenant_id, branch_id, register_id, user_id, status, opening_amount, expected_amount, opened_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $tenantId,
            $data['branch_id'],
            $data['register_id'],
            $data['user_id'],
            'OPEN',
            $data['opening_amount'],
            $data['opening_amount'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function findOpenByRegister(int $tenantId, int $registerId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM pos_cash_sessions WHERE tenant_id = ? AND register_id = ? AND status = ? LIMIT 1');
        $stmt->execute([$tenantId, $registerId, 'OPEN']);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findById(int $tenantId, int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM pos_cash_sessions WHERE tenant_id = ? AND id = ? LIMIT 1');
        $stmt->execute([$tenantId, $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function close(int $tenantId, int $id, float $closingAmount, float $expectedAmount, float $difference): void
    {
        $stmt = $this->db->prepare(
            'UPDATE pos_cash_sessions SET status = ?, closing_amount = ?, expected_amount = ?, difference = ?, closed_at = NOW() WHERE tenant_id = ? AND id = ?'
        );
        $stmt->execute(['CLOSED', $closingAmount, $expectedAmount, $difference, $tenantId, $id]);
    }
}

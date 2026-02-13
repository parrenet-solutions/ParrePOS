<?php

namespace App\Pos;

use PDO;

class CashMovementRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(int $tenantId, array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO pos_cash_movements (tenant_id, branch_id, register_id, cash_session_id, type, amount, reason_code, description, reference, created_by, created_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $tenantId,
            $data['branch_id'],
            $data['register_id'],
            $data['cash_session_id'],
            $data['type'],
            $data['amount'],
            $data['reason_code'],
            $data['description'],
            $data['reference'],
            $data['created_by'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function listBySession(int $tenantId, int $cashSessionId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM pos_cash_movements WHERE tenant_id = ? AND cash_session_id = ? ORDER BY id DESC');
        $stmt->execute([$tenantId, $cashSessionId]);
        return $stmt->fetchAll();
    }

    public function findById(int $tenantId, int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM pos_cash_movements WHERE tenant_id = ? AND id = ? LIMIT 1');
        $stmt->execute([$tenantId, $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function sumBySession(int $tenantId, int $cashSessionId): array
    {
        $stmt = $this->db->prepare(
            'SELECT type, SUM(amount) as total FROM pos_cash_movements WHERE tenant_id = ? AND cash_session_id = ? GROUP BY type'
        );
        $stmt->execute([$tenantId, $cashSessionId]);
        $rows = $stmt->fetchAll();

        $result = ['IN' => 0.0, 'OUT' => 0.0];
        foreach ($rows as $row) {
            $type = $row['type'] ?? '';
            if (isset($result[$type])) {
                $result[$type] = (float) $row['total'];
            }
        }

        return $result;
    }
}

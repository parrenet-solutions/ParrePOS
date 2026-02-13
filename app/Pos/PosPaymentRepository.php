<?php

namespace App\Pos;

use PDO;

class PosPaymentRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function addPayments(int $tenantId, array $data, array $payments): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO pos_payments (tenant_id, branch_id, register_id, cash_session_id, sale_id, method, amount, reference, created_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );

        foreach ($payments as $payment) {
            $stmt->execute([
                $tenantId,
                $data['branch_id'],
                $data['register_id'],
                $data['cash_session_id'],
                $data['sale_id'],
                $payment['method'],
                $payment['amount'],
                $payment['reference'],
            ]);
        }
    }

    public function sumBySession(int $tenantId, int $cashSessionId): float
    {
        $stmt = $this->db->prepare('SELECT SUM(amount) as total FROM pos_payments WHERE tenant_id = ? AND cash_session_id = ?');
        $stmt->execute([$tenantId, $cashSessionId]);
        $row = $stmt->fetch();

        return (float) ($row['total'] ?? 0);
    }

    public function sumByMethod(int $tenantId, int $cashSessionId, string $method): float
    {
        $stmt = $this->db->prepare('SELECT SUM(amount) as total FROM pos_payments WHERE tenant_id = ? AND cash_session_id = ? AND method = ?');
        $stmt->execute([$tenantId, $cashSessionId, $method]);
        $row = $stmt->fetch();

        return (float) ($row['total'] ?? 0);
    }

    public function countBySession(int $tenantId, int $cashSessionId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) as cnt FROM pos_payments WHERE tenant_id = ? AND cash_session_id = ?');
        $stmt->execute([$tenantId, $cashSessionId]);
        $row = $stmt->fetch();

        return (int) ($row['cnt'] ?? 0);
    }
}

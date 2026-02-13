<?php

namespace App\Pos;

use PDO;

class PosSaleRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function createPaid(int $tenantId, array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO pos_sales (tenant_id, branch_id, register_id, cash_session_id, ticket_number, status, subtotal, tax_total, total, paid_total, created_at, paid_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $tenantId,
            $data['branch_id'],
            $data['register_id'],
            $data['cash_session_id'],
            $data['ticket_number'],
            'PAID',
            $data['subtotal'],
            $data['tax_total'],
            $data['total'],
            $data['paid_total'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function addItems(int $tenantId, int $saleId, array $items): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO pos_sale_items (tenant_id, sale_id, name, qty, unit_price, tax_rate, discount, line_total, tax_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        foreach ($items as $item) {
            $stmt->execute([
                $tenantId,
                $saleId,
                $item['name'],
                $item['qty'],
                $item['unit_price'],
                $item['tax_rate'],
                $item['discount'],
                $item['line_total'],
                $item['tax_amount'],
            ]);
        }
    }

    public function findById(int $tenantId, int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM pos_sales WHERE tenant_id = ? AND id = ? LIMIT 1');
        $stmt->execute([$tenantId, $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function list(int $tenantId, array $filters): array
    {
        $sql = 'SELECT * FROM pos_sales WHERE tenant_id = ?';
        $params = [$tenantId];

        if (!empty($filters['status'])) {
            $sql .= ' AND status = ?';
            $params[] = $filters['status'];
        } else {
            $sql .= ' AND status <> ?';
            $params[] = 'HOLD';
        }

        if (!empty($filters['register_id'])) {
            $sql .= ' AND register_id = ?';
            $params[] = $filters['register_id'];
        }

        if (!empty($filters['cash_session_id'])) {
            $sql .= ' AND cash_session_id = ?';
            $params[] = $filters['cash_session_id'];
        }

        $sql .= ' ORDER BY id DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getItems(int $tenantId, int $saleId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM pos_sale_items WHERE tenant_id = ? AND sale_id = ? ORDER BY id ASC');
        $stmt->execute([$tenantId, $saleId]);
        return $stmt->fetchAll();
    }

    public function void(int $tenantId, int $id, string $reason): void
    {
        $stmt = $this->db->prepare('UPDATE pos_sales SET status = ?, void_reason = ?, voided_at = NOW() WHERE tenant_id = ? AND id = ?');
        $stmt->execute(['VOID', $reason, $tenantId, $id]);
    }

    public function hold(int $tenantId, int $id, string $reason): void
    {
        $stmt = $this->db->prepare('UPDATE pos_sales SET status = ?, hold_reason = ? WHERE tenant_id = ? AND id = ?');
        $stmt->execute(['HOLD', $reason, $tenantId, $id]);
    }

    public function resume(int $tenantId, int $id): void
    {
        $stmt = $this->db->prepare('UPDATE pos_sales SET status = ?, hold_reason = NULL WHERE tenant_id = ? AND id = ?');
        $stmt->execute(['OPEN', $tenantId, $id]);
    }

    public function sumPaidForSession(int $tenantId, int $cashSessionId): float
    {
        $stmt = $this->db->prepare('SELECT SUM(paid_total) as total FROM pos_sales WHERE tenant_id = ? AND cash_session_id = ? AND status = ?');
        $stmt->execute([$tenantId, $cashSessionId, 'PAID']);
        $row = $stmt->fetch();

        return (float) ($row['total'] ?? 0);
    }

    public function nextTicketNumber(int $tenantId, int $branchId, int $registerId): int
    {
        $stmt = $this->db->prepare(
            'SELECT current_number FROM pos_ticket_sequences WHERE tenant_id = ? AND branch_id = ? AND register_id = ? LIMIT 1 FOR UPDATE'
        );
        $stmt->execute([$tenantId, $branchId, $registerId]);
        $row = $stmt->fetch();

        if (!$row) {
            $this->db->prepare('INSERT INTO pos_ticket_sequences (tenant_id, branch_id, register_id, current_number) VALUES (?, ?, ?, ?)')
                ->execute([$tenantId, $branchId, $registerId, 1]);
            return 1;
        }

        $next = (int) $row['current_number'] + 1;
        $this->db->prepare('UPDATE pos_ticket_sequences SET current_number = ? WHERE tenant_id = ? AND branch_id = ? AND register_id = ?')
            ->execute([$next, $tenantId, $branchId, $registerId]);

        return $next;
    }
}

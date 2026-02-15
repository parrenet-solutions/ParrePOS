<?php

namespace App\Inventory;

use PDO;

class InventoryRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function createMovement(int $tenantId, array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO inventory_movements (
                tenant_id, branch_id, item_id, sale_id, item_name, movement_type, qty, reason_code, reference_type, reference_id, created_by, created_at
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );

        $stmt->execute([
            $tenantId,
            $data['branch_id'],
            $data['item_id'],
            $data['sale_id'],
            $data['item_name'],
            $data['movement_type'],
            $data['qty'],
            $data['reason_code'],
            $data['reference_type'],
            $data['reference_id'],
            $data['created_by'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function listStock(int $tenantId, ?int $branchId = null, ?int $itemId = null): array
    {
        $sql = "SELECT
                    branch_id,
                    item_id,
                    MAX(item_name) AS item_name,
                    SUM(CASE WHEN movement_type = 'IN' THEN qty ELSE -qty END) AS stock
                FROM inventory_movements
                WHERE tenant_id = ?";
        $params = [$tenantId];

        if ($branchId !== null && $branchId > 0) {
            $sql .= ' AND branch_id = ?';
            $params[] = $branchId;
        }

        if ($itemId !== null && $itemId > 0) {
            $sql .= ' AND item_id = ?';
            $params[] = $itemId;
        }

        $sql .= ' GROUP BY branch_id, item_id ORDER BY branch_id ASC, item_id ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function listKardex(int $tenantId, array $filters, int $limit): array
    {
        $limit = max(1, min(500, $limit));
        $sql = 'SELECT id, branch_id, item_id, item_name, movement_type, qty, reason_code, reference_type, reference_id, sale_id, created_by, created_at
                FROM inventory_movements
                WHERE tenant_id = ?';
        $params = [$tenantId];

        $branchId = (int) ($filters['branch_id'] ?? 0);
        if ($branchId > 0) {
            $sql .= ' AND branch_id = ?';
            $params[] = $branchId;
        }

        $itemId = (int) ($filters['item_id'] ?? 0);
        if ($itemId > 0) {
            $sql .= ' AND item_id = ?';
            $params[] = $itemId;
        }

        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $sql .= ' AND created_at >= ?';
            $params[] = $dateFrom . ' 00:00:00';
        }

        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if ($dateTo !== '') {
            $sql .= ' AND created_at <= ?';
            $params[] = $dateTo . ' 23:59:59';
        }

        $sql .= ' ORDER BY id DESC LIMIT ' . $limit;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getCurrentStock(int $tenantId, int $branchId, int $itemId): float
    {
        $stmt = $this->db->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN movement_type = 'IN' THEN qty ELSE -qty END), 0) AS stock
             FROM inventory_movements
             WHERE tenant_id = ? AND branch_id = ? AND item_id = ?"
        );
        $stmt->execute([$tenantId, $branchId, $itemId]);
        $row = $stmt->fetch();
        return (float) ($row['stock'] ?? 0);
    }

    public function existsVoidReversal(int $tenantId, int $saleId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT id
             FROM inventory_movements
             WHERE tenant_id = ?
               AND reference_type = 'SALE_VOID'
               AND reference_id = ?
             LIMIT 1"
        );
        $stmt->execute([$tenantId, $saleId]);
        return (bool) $stmt->fetch();
    }

    public function listSaleOutMovements(int $tenantId, int $saleId): array
    {
        $stmt = $this->db->prepare(
            "SELECT branch_id, item_id, item_name, qty
             FROM inventory_movements
             WHERE tenant_id = ?
               AND reference_type = 'SALE'
               AND reference_id = ?
               AND movement_type = 'OUT'
             ORDER BY id ASC"
        );
        $stmt->execute([$tenantId, $saleId]);
        return $stmt->fetchAll();
    }
}

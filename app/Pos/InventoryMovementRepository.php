<?php

namespace App\Pos;

use PDO;

class InventoryMovementRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function createFromSale(int $tenantId, int $saleId, array $items): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO inventory_movements (tenant_id, sale_id, item_name, qty, created_at) VALUES (?, ?, ?, ?, NOW())'
        );

        foreach ($items as $item) {
            $stmt->execute([
                $tenantId,
                $saleId,
                $item['name'],
                $item['qty'],
            ]);
        }
    }
}

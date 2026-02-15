<?php

namespace App\Inventory;

use App\Audit\AuditLogger;
use App\Core\Request;

class InventoryController
{
    private InventoryRepository $repository;
    private InventoryService $service;
    private AuditLogger $audit;

    public function __construct(
        InventoryRepository $repository,
        InventoryService $service,
        AuditLogger $audit
    ) {
        $this->repository = $repository;
        $this->service = $service;
        $this->audit = $audit;
    }

    public function createMovement(Request $request): array
    {
        $payload = $request->getJson();
        if (!is_array($payload)) {
            $payload = [];
        }

        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $userId = (int) $request->getAttribute('user_id', 0);
        $data = $this->service->validateManualMovement($tenantId, $userId, $payload);

        $id = $this->repository->createMovement($tenantId, $data);
        $this->audit->log($tenantId, $userId, 'inventory.movement.create', [
            'movement_id' => $id,
            'branch_id' => $data['branch_id'],
            'item_id' => $data['item_id'],
            'movement_type' => $data['movement_type'],
            'qty' => $data['qty'],
            'reason_code' => $data['reason_code'],
        ]);

        return ['status' => 201, 'data' => ['id' => $id]];
    }

    public function stock(Request $request): array
    {
        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $query = $request->getQuery();

        $branchId = isset($query['branch_id']) ? (int) $query['branch_id'] : null;
        $itemId = isset($query['item_id']) ? (int) $query['item_id'] : null;

        return [
            'status' => 200,
            'data' => $this->repository->listStock($tenantId, $branchId, $itemId),
        ];
    }

    public function kardex(Request $request): array
    {
        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $query = $request->getQuery();

        $filters = [
            'branch_id' => $query['branch_id'] ?? null,
            'item_id' => $query['item_id'] ?? null,
            'date_from' => $query['date_from'] ?? null,
            'date_to' => $query['date_to'] ?? null,
        ];
        $limit = isset($query['limit']) ? (int) $query['limit'] : 100;

        return [
            'status' => 200,
            'data' => $this->repository->listKardex($tenantId, $filters, $limit),
        ];
    }
}

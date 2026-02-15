<?php

namespace App\Inventory;

use App\Catalog\ItemRepository;
use App\Pos\BranchRepository;
use App\Shared\Exceptions\HttpException;

class InventoryService
{
    private InventoryRepository $repository;
    private ItemRepository $itemRepository;
    private BranchRepository $branchRepository;

    public function __construct(
        InventoryRepository $repository,
        ItemRepository $itemRepository,
        BranchRepository $branchRepository
    ) {
        $this->repository = $repository;
        $this->itemRepository = $itemRepository;
        $this->branchRepository = $branchRepository;
    }

    public function validateManualMovement(int $tenantId, int $userId, array $payload): array
    {
        $branchId = (int) ($payload['branch_id'] ?? 0);
        $itemId = (int) ($payload['item_id'] ?? 0);
        $qty = (float) ($payload['qty'] ?? 0);
        $movementType = strtoupper(trim((string) ($payload['movement_type'] ?? '')));
        $reasonCode = strtoupper(trim((string) ($payload['reason_code'] ?? 'ADJUST')));

        if ($branchId <= 0 || $itemId <= 0 || $qty <= 0 || !in_array($movementType, ['IN', 'OUT'], true)) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'branch_id/item_id/qty/movement_type inválidos');
        }

        $branch = $this->branchRepository->findById($tenantId, $branchId);
        if (!$branch) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'Sucursal inválida');
        }

        $item = $this->itemRepository->findById($tenantId, $itemId);
        if (!$item) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'Item inválido');
        }

        if ($movementType === 'OUT') {
            $stock = $this->repository->getCurrentStock($tenantId, $branchId, $itemId);
            if ($stock < $qty) {
                throw new HttpException(409, 'OUT_OF_STOCK', 'Stock insuficiente', [
                    'item_id' => $itemId,
                    'branch_id' => $branchId,
                    'requested' => $qty,
                    'available' => $stock,
                ]);
            }
        }

        return [
            'branch_id' => $branchId,
            'item_id' => $itemId,
            'sale_id' => null,
            'item_name' => (string) ($item['name'] ?? ''),
            'movement_type' => $movementType,
            'qty' => $qty,
            'reason_code' => $reasonCode !== '' ? $reasonCode : 'ADJUST',
            'reference_type' => 'MANUAL',
            'reference_id' => null,
            'created_by' => $userId,
        ];
    }

    public function assertSaleStockAvailable(int $tenantId, int $branchId, array $items): void
    {
        foreach ($items as $item) {
            $itemId = (int) ($item['item_id'] ?? 0);
            $qty = (float) ($item['qty'] ?? 0);
            $name = trim((string) ($item['name'] ?? ''));

            if ($itemId <= 0 || $qty <= 0) {
                throw new HttpException(422, 'VALIDATION_ERROR', 'item_id requerido para venta con inventario');
            }

            $catalogItem = $this->itemRepository->findById($tenantId, $itemId);
            if (!$catalogItem) {
                throw new HttpException(422, 'VALIDATION_ERROR', 'Item de inventario inválido', [
                    'item_id' => $itemId,
                    'name' => $name,
                ]);
            }

            $stock = $this->repository->getCurrentStock($tenantId, $branchId, $itemId);
            if ($stock < $qty) {
                throw new HttpException(409, 'OUT_OF_STOCK', 'Stock insuficiente', [
                    'item_id' => $itemId,
                    'requested' => $qty,
                    'available' => $stock,
                ]);
            }
        }
    }

    public function createSaleOutMovements(int $tenantId, int $branchId, int $saleId, int $userId, array $items): void
    {
        foreach ($items as $item) {
            $itemId = (int) ($item['item_id'] ?? 0);
            $qty = (float) ($item['qty'] ?? 0);
            if ($itemId <= 0 || $qty <= 0) {
                continue;
            }

            $this->repository->createMovement($tenantId, [
                'branch_id' => $branchId,
                'item_id' => $itemId,
                'sale_id' => $saleId,
                'item_name' => trim((string) ($item['name'] ?? '')),
                'movement_type' => 'OUT',
                'qty' => $qty,
                'reason_code' => 'SALE',
                'reference_type' => 'SALE',
                'reference_id' => $saleId,
                'created_by' => $userId,
            ]);
        }
    }

    public function reverseSaleMovementsOnVoid(int $tenantId, int $saleId, int $userId): int
    {
        if ($this->repository->existsVoidReversal($tenantId, $saleId)) {
            return 0;
        }

        $rows = $this->repository->listSaleOutMovements($tenantId, $saleId);
        if ($rows === []) {
            return 0;
        }

        $created = 0;
        foreach ($rows as $row) {
            $this->repository->createMovement($tenantId, [
                'branch_id' => (int) ($row['branch_id'] ?? 0),
                'item_id' => (int) ($row['item_id'] ?? 0),
                'sale_id' => $saleId,
                'item_name' => (string) ($row['item_name'] ?? ''),
                'movement_type' => 'IN',
                'qty' => (float) ($row['qty'] ?? 0),
                'reason_code' => 'SALE_VOID',
                'reference_type' => 'SALE_VOID',
                'reference_id' => $saleId,
                'created_by' => $userId,
            ]);
            $created++;
        }

        return $created;
    }
}

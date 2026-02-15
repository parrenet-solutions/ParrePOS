<?php

namespace App\Pos;

use App\Audit\AuditLogger;
use App\Core\Request;
use App\Inventory\InventoryService;
use App\Settings\TenantSettingsRepository;
use App\Shared\Exceptions\HttpException;
use App\Shared\Helpers;
use PDO;

class PosSaleController
{
    private PosSaleRepository $repository;
    private PosSaleService $service;
    private CashSessionRepository $cashSessionRepository;
    private BranchRepository $branchRepository;
    private RegisterRepository $registerRepository;
    private InventoryService $inventoryService;
    private TenantSettingsRepository $settingsRepository;
    private PosPaymentRepository $paymentRepository;
    private AuditLogger $audit;
    private PDO $db;

    public function __construct(
        PosSaleRepository $repository,
        PosSaleService $service,
        CashSessionRepository $cashSessionRepository,
        BranchRepository $branchRepository,
        RegisterRepository $registerRepository,
        InventoryService $inventoryService,
        TenantSettingsRepository $settingsRepository,
        PosPaymentRepository $paymentRepository,
        AuditLogger $audit,
        PDO $db
    ) {
        $this->repository = $repository;
        $this->service = $service;
        $this->cashSessionRepository = $cashSessionRepository;
        $this->branchRepository = $branchRepository;
        $this->registerRepository = $registerRepository;
        $this->inventoryService = $inventoryService;
        $this->settingsRepository = $settingsRepository;
        $this->paymentRepository = $paymentRepository;
        $this->audit = $audit;
        $this->db = $db;
    }

    public function create(Request $request): array
    {
        $payload = $request->getJson();
        if (!is_array($payload)) {
            $payload = [];
        }

        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $userId = (int) $request->getAttribute('user_id', 0);
        $saleIdRef = isset($payload['sale_id']) ? (int) $payload['sale_id'] : 0;

        $data = $this->service->validateCreate($payload);

        if ($saleIdRef > 0) {
            $existingSale = $this->repository->findById($tenantId, $saleIdRef);
            if ($existingSale && $existingSale['status'] === 'HOLD') {
                throw new HttpException(409, 'INVALID_STATE', 'Venta en HOLD; reanude antes de pagar');
            }
        }

        if (!$this->branchRepository->findById($tenantId, $data['branch_id'])) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'Sucursal inválida');
        }

        if (!$this->registerRepository->findById($tenantId, $data['register_id'])) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'Caja inválida');
        }

        $session = $this->cashSessionRepository->findById($tenantId, $data['cash_session_id']);
        if (!$session || $session['status'] !== 'OPEN') {
            throw new HttpException(409, 'INVALID_STATE', 'Caja no abierta');
        }

        $totals = $this->service->totals($data['items']);
        $payments = $this->service->normalizePayments($payload, $totals['total']);
        $paidTotal = $payments['paid_total'];

        $modules = $this->settingsRepository->getModules($tenantId);
        $inventoryEnabled = in_array('inventory', $modules, true);
        if ($inventoryEnabled) {
            $this->inventoryService->assertSaleStockAvailable($tenantId, $data['branch_id'], $data['items']);
        }

        $saleId = Helpers::transaction($this->db, function () use (
            $tenantId,
            $userId,
            $data,
            $totals,
            $paidTotal,
            $payments,
            $inventoryEnabled
        ) {
            $sequence = $this->repository->nextTicketNumber($tenantId, $data['branch_id'], $data['register_id']);
            $ticketNumber = $this->service->formatTicket($data['branch_id'], $data['register_id'], $sequence);

            $id = $this->repository->createPaid($tenantId, [
                'branch_id' => $data['branch_id'],
                'register_id' => $data['register_id'],
                'cash_session_id' => $data['cash_session_id'],
                'ticket_number' => $ticketNumber,
                'subtotal' => $totals['subtotal'],
                'tax_total' => $totals['tax_total'],
                'total' => $totals['total'],
                'paid_total' => $paidTotal,
            ]);
            $this->repository->addItems($tenantId, $id, $data['items']);

            $this->paymentRepository->addPayments($tenantId, [
                'branch_id' => $data['branch_id'],
                'register_id' => $data['register_id'],
                'cash_session_id' => $data['cash_session_id'],
                'sale_id' => $id,
            ], $payments['items']);

            if ($inventoryEnabled) {
                $this->inventoryService->createSaleOutMovements(
                    $tenantId,
                    $data['branch_id'],
                    $id,
                    $userId,
                    $data['items']
                );
            }

            return $id;
        });

        $this->audit->log($tenantId, $userId, 'pos.sales.paid', ['sale_id' => $saleId]);

        return ['status' => 201, 'data' => ['id' => $saleId]];
    }

    public function void(Request $request): array
    {
        $payload = $request->getJson();
        if (!is_array($payload)) {
            $payload = [];
        }

        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $userId = (int) $request->getAttribute('user_id', 0);
        $id = (int) $request->getParam('id');
        $reason = trim((string) ($payload['reason'] ?? ''));

        if ($reason === '') {
            throw new HttpException(422, 'VALIDATION_ERROR', 'reason requerido');
        }

        $sale = $this->repository->findById($tenantId, $id);
        if (!$sale) {
            throw new HttpException(404, 'NOT_FOUND', 'Venta no encontrada');
        }

        if ($sale['status'] !== 'PAID') {
            throw new HttpException(409, 'INVALID_STATE', 'Venta no está pagada');
        }

        $modules = $this->settingsRepository->getModules($tenantId);
        $inventoryEnabled = in_array('inventory', $modules, true);

        Helpers::transaction($this->db, function () use ($tenantId, $id, $reason, $userId, $inventoryEnabled): void {
            $this->repository->void($tenantId, $id, $reason);

            if ($inventoryEnabled) {
                $this->inventoryService->reverseSaleMovementsOnVoid($tenantId, $id, $userId);
            }
        });
        $this->audit->log($tenantId, $userId, 'pos.sales.void', ['sale_id' => $id]);

        return ['status' => 200, 'data' => ['id' => $id, 'status' => 'VOID']];
    }

    public function hold(Request $request): array
    {
        $payload = $request->getJson();
        if (!is_array($payload)) {
            throw new HttpException(400, 'INVALID_BODY', 'Se requiere JSON');
        }

        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $userId = (int) $request->getAttribute('user_id', 0);
        $id = (int) $request->getParam('id');
        $reason = trim((string) ($payload['reason'] ?? ''));

        $sale = $this->repository->findById($tenantId, $id);
        if (!$sale) {
            throw new HttpException(404, 'NOT_FOUND', 'Venta no encontrada');
        }

        if (in_array($sale['status'], ['PAID', 'VOID', 'HOLD'], true)) {
            throw new HttpException(409, 'INVALID_STATE', 'Venta no puede ponerse en hold');
        }

        $this->repository->hold($tenantId, $id, $reason);
        $this->audit->log($tenantId, $userId, 'pos.sales.hold', ['sale_id' => $id, 'reason' => $reason]);

        return ['status' => 200, 'data' => ['id' => $id, 'status' => 'HOLD']];
    }

    public function resume(Request $request): array
    {
        $payload = $request->getJson();
        if (!is_array($payload)) {
            throw new HttpException(400, 'INVALID_BODY', 'Se requiere JSON');
        }

        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $userId = (int) $request->getAttribute('user_id', 0);
        $id = (int) $request->getParam('id');

        $sale = $this->repository->findById($tenantId, $id);
        if (!$sale) {
            throw new HttpException(404, 'NOT_FOUND', 'Venta no encontrada');
        }

        if ($sale['status'] !== 'HOLD') {
            throw new HttpException(409, 'INVALID_STATE', 'Venta no está en hold');
        }

        $this->repository->resume($tenantId, $id);
        $this->audit->log($tenantId, $userId, 'pos.sales.resume', ['sale_id' => $id]);

        return ['status' => 200, 'data' => ['id' => $id, 'status' => 'OPEN']];
    }

    public function get(Request $request): array
    {
        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $id = (int) $request->getParam('id');

        $sale = $this->repository->findById($tenantId, $id);
        if (!$sale) {
            throw new HttpException(404, 'NOT_FOUND', 'Venta no encontrada');
        }

        $sale['items'] = $this->repository->getItems($tenantId, $id);

        return ['status' => 200, 'data' => $sale];
    }

    public function list(Request $request): array
    {
        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $filters = [
            'status' => $request->getQuery()['status'] ?? null,
            'register_id' => $request->getQuery()['register_id'] ?? null,
            'cash_session_id' => $request->getQuery()['cash_session_id'] ?? null,
        ];

        return ['status' => 200, 'data' => $this->repository->list($tenantId, $filters)];
    }
}

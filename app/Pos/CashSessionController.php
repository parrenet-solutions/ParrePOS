<?php

namespace App\Pos;

use App\Audit\AuditLogger;
use App\Core\Request;
use App\Shared\Exceptions\HttpException;
use App\Pos\PosPaymentRepository;
use App\Pos\CashMovementRepository;
use App\Pos\PosSaleRepository;

class CashSessionController
{
    private CashSessionRepository $repository;
    private CashSessionService $service;
    private RegisterRepository $registerRepository;
    private BranchRepository $branchRepository;
    private PosPaymentRepository $paymentRepository;
    private PosSaleRepository $saleRepository;
    private CashMovementRepository $movementRepository;
    private AuditLogger $audit;

    public function __construct(
        CashSessionRepository $repository,
        CashSessionService $service,
        RegisterRepository $registerRepository,
        BranchRepository $branchRepository,
        PosPaymentRepository $paymentRepository,
        PosSaleRepository $saleRepository,
        CashMovementRepository $movementRepository,
        AuditLogger $audit
    ) {
        $this->repository = $repository;
        $this->service = $service;
        $this->registerRepository = $registerRepository;
        $this->branchRepository = $branchRepository;
        $this->paymentRepository = $paymentRepository;
        $this->saleRepository = $saleRepository;
        $this->movementRepository = $movementRepository;
        $this->audit = $audit;
    }

    public function open(Request $request): array
    {
        $payload = $request->getJson();
        if (!is_array($payload)) {
            throw new HttpException(400, 'INVALID_BODY', 'Se requiere JSON');
        }

        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $userId = (int) $request->getAttribute('user_id', 0);
        $data = $this->service->validateOpen($payload);

        if (!$this->branchRepository->findById($tenantId, $data['branch_id'])) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'Sucursal inválida');
        }

        if (!$this->registerRepository->findById($tenantId, $data['register_id'])) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'Caja inválida');
        }

        $existing = $this->repository->findOpenByRegister($tenantId, $data['register_id']);
        if ($existing) {
            throw new HttpException(409, 'INVALID_STATE', 'Caja ya está abierta');
        }

        $id = $this->repository->createOpen($tenantId, [
            'branch_id' => $data['branch_id'],
            'register_id' => $data['register_id'],
            'user_id' => $userId,
            'opening_amount' => $data['opening_amount'],
        ]);

        $this->audit->log($tenantId, $userId, 'pos.cash.open', ['cash_session_id' => $id]);

        return ['status' => 201, 'data' => ['id' => $id, 'status' => 'OPEN']];
    }

    public function close(Request $request): array
    {
        $payload = $request->getJson();
        if (!is_array($payload)) {
            throw new HttpException(400, 'INVALID_BODY', 'Se requiere JSON');
        }

        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $userId = (int) $request->getAttribute('user_id', 0);
        $id = (int) $request->getParam('id');

        $session = $this->repository->findById($tenantId, $id);
        if (!$session) {
            throw new HttpException(404, 'NOT_FOUND', 'Caja no encontrada');
        }

        if ($session['status'] !== 'OPEN') {
            throw new HttpException(409, 'INVALID_STATE', 'Caja ya está cerrada');
        }

        $closing = $this->service->validateClose($payload);
        $paymentsCount = $this->paymentRepository->countBySession($tenantId, $id);
        $cashSales = $paymentsCount > 0
            ? $this->paymentRepository->sumByMethod($tenantId, $id, 'CASH')
            : $this->saleRepository->sumPaidForSession($tenantId, $id);

        $movements = $this->movementRepository->sumBySession($tenantId, $id);
        $cashIn = $movements['IN'];
        $cashOut = $movements['OUT'];

        // change_total se integra en waves futuras.
        $changeTotal = 0.0;
        $expected = (float) $session['opening_amount'] + $cashSales + $cashIn - $cashOut - $changeTotal;
        $difference = $closing - $expected;

        $this->repository->close($tenantId, $id, $closing, $expected, $difference);
        $this->audit->log($tenantId, $userId, 'pos.cash.close', ['cash_session_id' => $id, 'difference' => $difference]);

        return ['status' => 200, 'data' => [
            'id' => $id,
            'status' => 'CLOSED',
            'expected_amount' => $expected,
            'difference' => $difference,
        ]];
    }
}

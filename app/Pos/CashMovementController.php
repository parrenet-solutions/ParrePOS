<?php

namespace App\Pos;

use App\Audit\AuditLogger;
use App\Core\Request;
use App\Shared\Exceptions\HttpException;
use App\Sync\SyncRepository;

class CashMovementController
{
    private CashMovementRepository $repository;
    private CashMovementService $service;
    private CashSessionRepository $sessionRepository;
    private SyncRepository $syncRepository;
    private AuditLogger $audit;

    public function __construct(
        CashMovementRepository $repository,
        CashMovementService $service,
        CashSessionRepository $sessionRepository,
        SyncRepository $syncRepository,
        AuditLogger $audit
    ) {
        $this->repository = $repository;
        $this->service = $service;
        $this->sessionRepository = $sessionRepository;
        $this->syncRepository = $syncRepository;
        $this->audit = $audit;
    }

    public function create(Request $request): array
    {
        $payload = $request->getJson();
        if (!is_array($payload)) {
            throw new HttpException(400, 'INVALID_BODY', 'Se requiere JSON');
        }

        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $userId = (int) $request->getAttribute('user_id', 0);
        $cashSessionId = (int) ($payload['cash_session_id'] ?? 0);

        if ($cashSessionId <= 0) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'cash_session_id requerido');
        }

        $session = $this->sessionRepository->findById($tenantId, $cashSessionId);
        if (!$session || $session['status'] !== 'OPEN') {
            throw new HttpException(409, 'INVALID_STATE', 'Caja no abierta');
        }

        $idempotencyKey = trim((string) ($request->getHeader('X-Idempotency-Key') ?? ''));
        if ($idempotencyKey !== '') {
            $deviceId = trim((string) ($request->getHeader('X-Device-Id') ?? ''));
            if ($deviceId === '') {
                $deviceId = 'online-user-' . $userId;
            }

            if ($this->syncRepository->existsIdempotency($tenantId, $deviceId, $idempotencyKey)) {
                return ['status' => 200, 'data' => ['duplicate' => true]];
            }

            try {
                $this->syncRepository->registerIdempotency($tenantId, $deviceId, $idempotencyKey);
            } catch (\Throwable $e) {
                return ['status' => 200, 'data' => ['duplicate' => true]];
            }
        }

        $data = $this->service->validate($payload);
        $id = $this->repository->create($tenantId, [
            'branch_id' => (int) $session['branch_id'],
            'register_id' => (int) $session['register_id'],
            'cash_session_id' => $cashSessionId,
            'type' => $data['type'],
            'amount' => $data['amount'],
            'reason_code' => $data['reason_code'],
            'description' => $data['description'],
            'reference' => $data['reference'],
            'created_by' => $userId,
        ]);

        $this->audit->log($tenantId, $userId, 'pos.cash.movement.create', [
            'movement_id' => $id,
            'cash_session_id' => $cashSessionId,
            'type' => $data['type'],
            'amount' => $data['amount'],
            'reason_code' => $data['reason_code'],
        ]);

        return ['status' => 201, 'data' => ['id' => $id]];
    }

    public function list(Request $request): array
    {
        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $cashSessionId = (int) ($request->getQuery()['cash_session_id'] ?? 0);

        if ($cashSessionId <= 0) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'cash_session_id requerido');
        }

        return ['status' => 200, 'data' => $this->repository->listBySession($tenantId, $cashSessionId)];
    }

    public function summary(Request $request): array
    {
        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $cashSessionId = (int) ($request->getQuery()['cash_session_id'] ?? 0);

        if ($cashSessionId <= 0) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'cash_session_id requerido');
        }

        $sum = $this->repository->sumBySession($tenantId, $cashSessionId);
        return ['status' => 200, 'data' => [
            'cash_session_id' => $cashSessionId,
            'in_total' => $sum['IN'],
            'out_total' => $sum['OUT'],
        ]];
    }

    public function reverse(Request $request): array
    {
        $payload = $request->getJson();
        if (!is_array($payload)) {
            $payload = [];
        }

        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $userId = (int) $request->getAttribute('user_id', 0);
        $id = (int) $request->getParam('id');

        $movement = $this->repository->findById($tenantId, $id);
        if (!$movement) {
            throw new HttpException(404, 'NOT_FOUND', 'Movimiento no encontrado');
        }

        $session = $this->sessionRepository->findById($tenantId, (int) $movement['cash_session_id']);
        if (!$session || $session['status'] !== 'OPEN') {
            throw new HttpException(409, 'INVALID_STATE', 'Caja no abierta');
        }

        $reverseType = $movement['type'] === 'IN' ? 'OUT' : 'IN';
        $description = trim((string) ($payload['description'] ?? 'Reverso de movimiento'));

        $reverseId = $this->repository->create($tenantId, [
            'branch_id' => (int) $movement['branch_id'],
            'register_id' => (int) $movement['register_id'],
            'cash_session_id' => (int) $movement['cash_session_id'],
            'type' => $reverseType,
            'amount' => (float) $movement['amount'],
            'reason_code' => 'REVERSAL',
            'description' => $description,
            'reference' => 'REVERSAL:' . $movement['id'],
            'created_by' => $userId,
        ]);

        $this->audit->log($tenantId, $userId, 'pos.cash.movement.reverse', [
            'movement_id' => $movement['id'],
            'reverse_id' => $reverseId,
            'cash_session_id' => $movement['cash_session_id'],
            'amount' => $movement['amount'],
        ]);

        return ['status' => 200, 'data' => ['id' => $reverseId]];
    }
}

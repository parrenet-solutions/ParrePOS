<?php

namespace App\Recurring;

use App\Audit\AuditLogger;
use App\Core\Request;
use App\Shared\Exceptions\HttpException;

class RecurringController
{
    private RecurringRepository $repository;
    private RecurringService $service;
    private AuditLogger $audit;

    public function __construct(RecurringRepository $repository, RecurringService $service, AuditLogger $audit)
    {
        $this->repository = $repository;
        $this->service = $service;
        $this->audit = $audit;
    }

    public function create(Request $request): array
    {
        $payload = $request->getJson();
        if (!is_array($payload)) {
            throw new HttpException(400, 'INVALID_BODY', 'Se requiere JSON');
        }

        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $data = $this->service->validate($payload);
        $id = $this->repository->create($tenantId, $data);

        $this->audit->log($tenantId, (int) $request->getAttribute('user_id', 0), 'recurring.create', ['rule_id' => $id]);

        return ['status' => 201, 'data' => ['id' => $id]];
    }

    public function list(Request $request): array
    {
        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        return ['status' => 200, 'data' => $this->repository->list($tenantId)];
    }

    public function pause(Request $request): array
    {
        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $id = (int) $request->getParam('id');

        $rule = $this->repository->findById($tenantId, $id);
        if (!$rule) {
            throw new HttpException(404, 'NOT_FOUND', 'Regla no encontrada');
        }

        $this->repository->updateStatus($tenantId, $id, 'PAUSED');
        $this->audit->log($tenantId, (int) $request->getAttribute('user_id', 0), 'recurring.pause', ['rule_id' => $id]);

        return ['status' => 200, 'data' => ['id' => $id, 'status' => 'PAUSED']];
    }

    public function resume(Request $request): array
    {
        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $id = (int) $request->getParam('id');

        $rule = $this->repository->findById($tenantId, $id);
        if (!$rule) {
            throw new HttpException(404, 'NOT_FOUND', 'Regla no encontrada');
        }

        $this->repository->updateStatus($tenantId, $id, 'ACTIVE');
        $this->audit->log($tenantId, (int) $request->getAttribute('user_id', 0), 'recurring.resume', ['rule_id' => $id]);

        return ['status' => 200, 'data' => ['id' => $id, 'status' => 'ACTIVE']];
    }
}

<?php

namespace App\Pos;

use App\Audit\AuditLogger;
use App\Core\Request;
use App\Shared\Exceptions\HttpException;

class BranchController
{
    private BranchRepository $repository;
    private BranchService $service;
    private AuditLogger $audit;

    public function __construct(BranchRepository $repository, BranchService $service, AuditLogger $audit)
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

        $this->audit->log($tenantId, (int) $request->getAttribute('user_id', 0), 'branches.create', ['branch_id' => $id]);

        return ['status' => 201, 'data' => ['id' => $id]];
    }

    public function update(Request $request): array
    {
        $payload = $request->getJson();
        if (!is_array($payload)) {
            throw new HttpException(400, 'INVALID_BODY', 'Se requiere JSON');
        }

        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $id = (int) $request->getParam('id');

        if (!$this->repository->findById($tenantId, $id)) {
            throw new HttpException(404, 'NOT_FOUND', 'Sucursal no encontrada');
        }

        $data = $this->service->validate($payload);
        $this->repository->update($tenantId, $id, $data);

        $this->audit->log($tenantId, (int) $request->getAttribute('user_id', 0), 'branches.update', ['branch_id' => $id]);

        return ['status' => 200, 'data' => ['id' => $id]];
    }

    public function get(Request $request): array
    {
        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $id = (int) $request->getParam('id');

        $branch = $this->repository->findById($tenantId, $id);
        if (!$branch) {
            throw new HttpException(404, 'NOT_FOUND', 'Sucursal no encontrada');
        }

        return ['status' => 200, 'data' => $branch];
    }

    public function list(Request $request): array
    {
        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $search = $request->getQuery()['q'] ?? null;

        return ['status' => 200, 'data' => $this->repository->list($tenantId, $search)];
    }
}

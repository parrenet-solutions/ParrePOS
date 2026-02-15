<?php

namespace App\Ops;

use App\Core\Request;

class OpsController
{
    private OpsRepository $repository;

    public function __construct(OpsRepository $repository)
    {
        $this->repository = $repository;
    }

    public function tenantMetrics(Request $request): array
    {
        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        return [
            'status' => 200,
            'data' => $this->repository->tenantMetrics($tenantId),
        ];
    }

    public function syncConflicts(Request $request): array
    {
        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $query = $request->getQuery();
        $limit = isset($query['limit']) ? (int) $query['limit'] : 100;

        $filters = [
            'resolved' => $query['resolved'] ?? '',
            'device_id' => $query['device_id'] ?? '',
            'type' => $query['type'] ?? '',
        ];

        return [
            'status' => 200,
            'data' => $this->repository->listSyncConflicts($tenantId, $filters, $limit),
        ];
    }
}

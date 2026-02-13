<?php

namespace App\Audit;

class AuditLogger
{
    private AuditRepository $repository;

    public function __construct(AuditRepository $repository)
    {
        $this->repository = $repository;
    }

    public function log(int $tenantId, int $userId, string $action, array $meta): void
    {
        if ($tenantId <= 0) {
            return;
        }
        $this->repository->insert($tenantId, $userId, $action, $meta);
    }

    public function hasAction(int $tenantId, int $userId, string $action): bool
    {
        if ($tenantId <= 0 || $userId <= 0) {
            return false;
        }

        return $this->repository->hasAction($tenantId, $userId, $action);
    }
}

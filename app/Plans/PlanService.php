<?php

namespace App\Plans;

use App\Shared\Exceptions\HttpException;

class PlanService
{
    private PlanRepository $repository;

    public function __construct(PlanRepository $repository)
    {
        $this->repository = $repository;
    }

    public function getTenantPlan(int $tenantId): array
    {
        $row = $this->repository->findTenantPlan($tenantId);
        if (!$row) {
            return [
                'code' => 'UNMANAGED',
                'name' => 'Sin plan',
                'status' => 'ACTIVE',
                'modules' => [],
                'limits' => [],
            ];
        }

        return [
            'code' => (string) ($row['code'] ?? 'UNMANAGED'),
            'name' => (string) ($row['name'] ?? 'Sin plan'),
            'status' => (string) ($row['subscription_status'] ?? 'ACTIVE'),
            'modules' => $this->decodeModules((string) ($row['modules_json'] ?? '[]')),
            'limits' => $this->decodeLimits((string) ($row['limits_json'] ?? '{}')),
        ];
    }

    public function getAllowedModules(int $tenantId): array
    {
        return $this->getTenantPlan($tenantId)['modules'];
    }

    public function enforceLimit(int $tenantId, string $limitKey, int $currentCount): void
    {
        $plan = $this->getTenantPlan($tenantId);
        $limit = $this->resolveLimit($plan['limits'], $limitKey);
        // Si la clave no existe, el recurso no tiene límite por plan.
        // Si existe y vale 0, significa "no permitido".
        if ($limit === null) {
            return;
        }

        if ($currentCount >= $limit) {
            throw new HttpException(409, 'PLAN_LIMIT_EXCEEDED', 'Límite de plan excedido', [
                'limit_key' => $limitKey,
                'current' => $currentCount,
                'max' => $limit,
                'plan_code' => $plan['code'],
            ]);
        }
    }

    private function decodeModules(string $json): array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, fn ($v) => is_string($v) && $v !== ''));
    }

    private function decodeLimits(string $json): array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }

        $out = [];
        foreach ($decoded as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            $out[$key] = max(0, (int) $value);
        }

        return $out;
    }

    private function resolveLimit(array $limits, string $limitKey): ?int
    {
        if (array_key_exists($limitKey, $limits)) {
            return (int) $limits[$limitKey];
        }

        return null;
    }
}

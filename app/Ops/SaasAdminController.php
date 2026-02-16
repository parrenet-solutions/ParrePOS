<?php

namespace App\Ops;

use App\Core\Request;
use App\Shared\Exceptions\HttpException;

class SaasAdminController
{
    private SaasAdminRepository $repository;
    private SaasAdminService $service;

    public function __construct(SaasAdminRepository $repository, SaasAdminService $service)
    {
        $this->repository = $repository;
        $this->service = $service;
    }

    public function listTenants(Request $request): array
    {
        $query = $request->getQuery();
        $status = isset($query['status']) ? trim((string) $query['status']) : null;
        $limit = isset($query['limit']) ? (int) $query['limit'] : 100;

        return [
            'status' => 200,
            'data' => $this->repository->listTenants($status, $limit),
        ];
    }

    public function getTenant(Request $request): array
    {
        $tenantId = (int) $request->getParam('id', 0);
        if ($tenantId <= 0) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'id inválido');
        }

        $tenant = $this->repository->findTenantById($tenantId);
        if (!$tenant) {
            throw new HttpException(404, 'NOT_FOUND', 'Tenant no encontrado');
        }

        return [
            'status' => 200,
            'data' => $tenant,
        ];
    }

    public function updateTenantStatus(Request $request): array
    {
        $payload = $request->getJson();
        if (!is_array($payload)) {
            throw new HttpException(400, 'INVALID_BODY', 'Se requiere JSON');
        }

        $tenantId = (int) $request->getParam('id', 0);
        if ($tenantId <= 0) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'id inválido');
        }

        $tenant = $this->repository->findTenantById($tenantId);
        if (!$tenant) {
            throw new HttpException(404, 'NOT_FOUND', 'Tenant no encontrado');
        }

        $status = $this->service->validateTenantStatusPayload($payload);
        $this->repository->updateTenantStatus($tenantId, $status);

        return [
            'status' => 200,
            'data' => $this->repository->findTenantById($tenantId),
        ];
    }

    public function updateTenantModules(Request $request): array
    {
        $payload = $request->getJson();
        if (!is_array($payload)) {
            throw new HttpException(400, 'INVALID_BODY', 'Se requiere JSON');
        }

        $tenantId = (int) $request->getParam('id', 0);
        if ($tenantId <= 0) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'id inválido');
        }

        $tenant = $this->repository->findTenantById($tenantId);
        if (!$tenant) {
            throw new HttpException(404, 'NOT_FOUND', 'Tenant no encontrado');
        }

        $normalizedModules = $this->service->validateModulesPayload($payload);
        $settings = $this->repository->getTenantSettingsRaw($tenantId);

        if (!isset($settings['modules']) || !is_array($settings['modules'])) {
            $settings['modules'] = [];
        }

        foreach ($normalizedModules as $module => $cfg) {
            $existing = $settings['modules'][$module] ?? [];
            if (!is_array($existing)) {
                $existing = [];
            }

            $settings['modules'][$module] = array_merge($existing, $cfg);
        }

        $this->repository->updateTenantSettingsRaw($tenantId, $settings);

        return [
            'status' => 200,
            'data' => [
                'tenant_id' => $tenantId,
                'modules' => array_keys(array_filter($settings['modules'], static function ($cfg): bool {
                    return is_array($cfg) && ($cfg['enabled'] ?? false) === true;
                })),
            ],
        ];
    }

    public function updateSubscription(Request $request): array
    {
        $payload = $request->getJson();
        if (!is_array($payload)) {
            throw new HttpException(400, 'INVALID_BODY', 'Se requiere JSON');
        }

        $tenantId = (int) $request->getParam('id', 0);
        if ($tenantId <= 0) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'id inválido');
        }

        $tenant = $this->repository->findTenantById($tenantId);
        if (!$tenant) {
            throw new HttpException(404, 'NOT_FOUND', 'Tenant no encontrado');
        }

        $data = $this->service->validateSubscriptionPayload($payload);
        $plan = $this->repository->findPlanByCode($data['plan_code']);
        if (!$plan) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'plan_code no existe');
        }

        if (strtoupper((string) ($plan['status'] ?? 'INACTIVE')) !== 'ACTIVE') {
            throw new HttpException(409, 'INVALID_STATE', 'Plan no activo');
        }

        $this->repository->upsertTenantSubscription($tenantId, (int) $plan['id'], $data['status']);

        return [
            'status' => 200,
            'data' => $this->repository->findTenantById($tenantId),
        ];
    }

    public function listPlans(Request $request): array
    {
        return [
            'status' => 200,
            'data' => $this->repository->listPlans(),
        ];
    }
}

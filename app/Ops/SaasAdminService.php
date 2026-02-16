<?php

namespace App\Ops;

use App\Shared\Exceptions\HttpException;

class SaasAdminService
{
    private const TENANT_STATUSES = ['ACTIVE', 'SUSPENDED'];
    private const SUBSCRIPTION_STATUSES = ['ACTIVE', 'PAST_DUE', 'CANCELLED'];

    public function validateTenantStatusPayload(array $payload): string
    {
        $status = strtoupper(trim((string) ($payload['status'] ?? '')));
        if (!in_array($status, self::TENANT_STATUSES, true)) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'status inválido', [
                'allowed' => self::TENANT_STATUSES,
            ]);
        }

        return $status;
    }

    public function validateModulesPayload(array $payload): array
    {
        $modules = $payload['modules'] ?? null;
        if (!is_array($modules) || $modules === []) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'modules requerido');
        }

        $normalized = [];
        foreach ($modules as $module => $value) {
            $name = strtolower(trim((string) $module));
            if ($name === '') {
                continue;
            }

            $enabled = false;
            if (is_bool($value)) {
                $enabled = $value;
            } elseif (is_array($value)) {
                $enabled = ($value['enabled'] ?? false) === true;
            } else {
                throw new HttpException(422, 'VALIDATION_ERROR', 'modules.' . $name . ' inválido');
            }

            $normalized[$name] = ['enabled' => $enabled];
        }

        if ($normalized === []) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'modules vacío');
        }

        return $normalized;
    }

    public function validateSubscriptionPayload(array $payload): array
    {
        $planCode = strtoupper(trim((string) ($payload['plan_code'] ?? '')));
        $status = strtoupper(trim((string) ($payload['status'] ?? 'ACTIVE')));

        if ($planCode === '') {
            throw new HttpException(422, 'VALIDATION_ERROR', 'plan_code requerido');
        }

        if (!in_array($status, self::SUBSCRIPTION_STATUSES, true)) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'status inválido', [
                'allowed' => self::SUBSCRIPTION_STATUSES,
            ]);
        }

        return [
            'plan_code' => $planCode,
            'status' => $status,
        ];
    }
}

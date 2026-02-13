<?php

namespace App\Pos;

use App\Settings\TenantSettingsRepository;

class RegisterHandshakeService
{
    private TenantSettingsRepository $settingsRepository;

    public function __construct(TenantSettingsRepository $settingsRepository)
    {
        $this->settingsRepository = $settingsRepository;
    }

    public function buildResponse(int $tenantId, array $register, array $branch): array
    {
        $modules = $this->settingsRepository->getModules($tenantId);

        return [
            'register_id' => (int) $register['id'],
            'branch' => [
                'id' => (int) $branch['id'],
                'name' => $branch['name'],
                'status' => $branch['status'],
            ],
            'tenant_status' => 'ACTIVE',
            'modules' => $modules,
            'sync' => [
                'events' => '/api/v1/sync/events',
                'status' => '/api/v1/sync/status',
            ],
        ];
    }
}

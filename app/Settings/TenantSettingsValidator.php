<?php

namespace App\Settings;

use App\Shared\Exceptions\HttpException;

class TenantSettingsValidator
{
    public function validateModules(array $modules): void
    {
        foreach ($modules as $module) {
            if (!is_string($module) || $module === '') {
                throw new HttpException(422, 'VALIDATION_ERROR', 'Modules inválidos');
            }
        }
    }

    public function validateFiscalConfig(array $fiscal): void
    {
        $enabled = $fiscal['enabled'] ?? false;
        $dgiiRegistered = $fiscal['dgii_registered'] ?? false;
        $ncfType = strtoupper(trim((string) ($fiscal['ncf_type'] ?? '')));
        $series = strtoupper(trim((string) ($fiscal['series'] ?? '')));
        $provider = strtoupper(trim((string) ($fiscal['provider'] ?? 'MOCK')));
        $providerUrl = trim((string) ($fiscal['provider_url'] ?? ''));
        $limits = is_array($fiscal['emission_limits'] ?? null) ? $fiscal['emission_limits'] : [];
        $dailyMax = (int) ($limits['daily_max'] ?? 0);
        $monthlyMax = (int) ($limits['monthly_max'] ?? 0);

        if (!is_bool($enabled)) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'fiscal.enabled inválido');
        }

        if (!is_bool($dgiiRegistered)) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'fiscal.dgii_registered inválido');
        }

        if ($enabled && !$dgiiRegistered) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'Fiscal habilitado requiere DGII registrado');
        }

        if ($enabled && !preg_match('/^[A-Z][0-9]{2}$/', $ncfType)) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'fiscal.ncf_type inválido');
        }

        if ($enabled && !preg_match('/^[A-Z0-9]{1,10}$/', $series)) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'fiscal.series inválido');
        }

        if (!in_array($provider, ['MOCK', 'DGII'], true)) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'fiscal.provider inválido');
        }

        if ($enabled && $provider === 'DGII' && $providerUrl !== '' && !filter_var($providerUrl, FILTER_VALIDATE_URL)) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'fiscal.provider_url inválido');
        }

        if ($dailyMax < 0 || $monthlyMax < 0) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'fiscal.emission_limits inválido');
        }
    }
}

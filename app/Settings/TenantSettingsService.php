<?php

namespace App\Settings;

class TenantSettingsService
{
    public function normalizeModules(array $modules): array
    {
        $normalized = [];
        foreach ($modules as $module) {
            if (!is_string($module)) {
                continue;
            }

            $module = strtolower(trim($module));
            if ($module === '') {
                continue;
            }

            $normalized[] = $module;
        }

        return array_values(array_unique($normalized));
    }

    public function normalizeFiscalConfig(array $fiscal): array
    {
        $enabled = (bool) ($fiscal['enabled'] ?? false);
        $dgiiRegistered = (bool) ($fiscal['dgii_registered'] ?? false);
        $ncfType = strtoupper(trim((string) ($fiscal['ncf_type'] ?? 'B01')));
        $series = strtoupper(trim((string) ($fiscal['series'] ?? $ncfType)));
        $provider = strtoupper(trim((string) ($fiscal['provider'] ?? 'MOCK')));
        $providerUrl = trim((string) ($fiscal['provider_url'] ?? ''));
        $signingSecret = trim((string) ($fiscal['signing_secret'] ?? ''));
        $limits = is_array($fiscal['emission_limits'] ?? null) ? $fiscal['emission_limits'] : [];
        $dailyMax = max(0, (int) ($limits['daily_max'] ?? 0));
        $monthlyMax = max(0, (int) ($limits['monthly_max'] ?? 0));

        if ($series === '') {
            $series = $ncfType;
        }

        return [
            'enabled' => $enabled,
            'dgii_registered' => $dgiiRegistered,
            'ncf_type' => $ncfType,
            'series' => $series,
            'provider' => $provider === '' ? 'MOCK' : $provider,
            'provider_url' => $providerUrl,
            'signing_secret' => $signingSecret,
            'emission_limits' => [
                'daily_max' => $dailyMax,
                'monthly_max' => $monthlyMax,
            ],
        ];
    }
}

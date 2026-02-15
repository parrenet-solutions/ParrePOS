<?php

namespace App\Settings;

use PDO;

class TenantSettingsRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getTenantStatus(int $tenantId): ?string
    {
        $stmt = $this->db->prepare('SELECT status FROM tenants WHERE id = ? LIMIT 1');
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch();

        return $row['status'] ?? null;
    }

    public function getModules(int $tenantId): array
    {
        $decoded = $this->getSettings($tenantId);
        if ($decoded === []) {
            return [];
        }

        if (isset($decoded['modules']) && is_array($decoded['modules'])) {
            $enabled = [];
            foreach ($decoded['modules'] as $key => $value) {
                if (is_array($value) && ($value['enabled'] ?? false) === true) {
                    $enabled[] = $key;
                }
            }
            return $enabled;
        }

        return array_values(array_filter($decoded, fn ($value) => is_string($value) && $value !== ''));
    }

    public function isFiscalEnabled(int $tenantId): bool
    {
        $fiscal = $this->getFiscalConfig($tenantId);
        return $fiscal['enabled'] === true;
    }

    public function getFiscalConfig(int $tenantId): array
    {
        $decoded = $this->getSettings($tenantId);

        $defaults = [
            'enabled' => false,
            'dgii_registered' => false,
            'ncf_type' => 'B01',
            'series' => 'B01',
            'provider' => 'MOCK',
            'provider_url' => '',
            'signing_secret' => '',
            'emission_limits' => [
                'daily_max' => 0,
                'monthly_max' => 0,
            ],
        ];

        $fiscal = [];
        if (isset($decoded['fiscal']) && is_array($decoded['fiscal'])) {
            $fiscal = $decoded['fiscal'];
        }

        if (!$fiscal && isset($decoded['modules']['fiscal']) && is_array($decoded['modules']['fiscal'])) {
            $fiscal['enabled'] = ($decoded['modules']['fiscal']['enabled'] ?? false) === true;
        }

        if (!$fiscal) {
            $legacyModules = $this->getModules($tenantId);
            if (in_array('fiscal', $legacyModules, true)) {
                $fiscal['enabled'] = true;
            }
        }

        $merged = array_merge($defaults, $fiscal);
        $merged['enabled'] = $this->normalizeBool($merged['enabled'] ?? false);
        $merged['dgii_registered'] = $this->normalizeBool($merged['dgii_registered'] ?? false);
        $merged['ncf_type'] = strtoupper(trim((string) ($merged['ncf_type'] ?? 'B01')));
        $merged['series'] = strtoupper(trim((string) ($merged['series'] ?? $merged['ncf_type'])));
        $merged['provider'] = strtoupper(trim((string) ($merged['provider'] ?? 'MOCK')));
        $merged['provider_url'] = trim((string) ($merged['provider_url'] ?? ''));
        $merged['signing_secret'] = trim((string) ($merged['signing_secret'] ?? ''));
        $limits = is_array($merged['emission_limits'] ?? null) ? $merged['emission_limits'] : [];
        $merged['emission_limits'] = [
            'daily_max' => max(0, (int) ($limits['daily_max'] ?? 0)),
            'monthly_max' => max(0, (int) ($limits['monthly_max'] ?? 0)),
        ];

        if ($merged['series'] === '') {
            $merged['series'] = $merged['ncf_type'];
        }

        if ($merged['provider'] === '') {
            $merged['provider'] = 'MOCK';
        }

        return $merged;
    }

    private function getSettings(int $tenantId): array
    {
        $stmt = $this->db->prepare('SELECT modules FROM tenant_settings WHERE tenant_id = ? LIMIT 1');
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch();

        if (!$row || !$row['modules']) {
            return [];
        }

        $decoded = json_decode($row['modules'], true);
        return is_array($decoded) ? $decoded : [];
    }

    private function normalizeBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }
}

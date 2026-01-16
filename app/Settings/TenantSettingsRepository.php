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
        $stmt = $this->db->prepare('SELECT modules FROM tenant_settings WHERE tenant_id = ? LIMIT 1');
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch();

        if (!$row || !$row['modules']) {
            return [];
        }

        $decoded = json_decode($row['modules'], true);
        if (!is_array($decoded)) {
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
}

<?php

namespace App\Ops;

use PDO;

class SaasAdminRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function listTenants(?string $status, int $limit): array
    {
        $limit = max(1, min(500, $limit));
        $sql = 'SELECT t.id, t.name, t.slug, t.status, t.created_at,
                       p.code AS plan_code, p.name AS plan_name,
                       ts.status AS subscription_status,
                       ts.starts_at, ts.ends_at
                FROM tenants t
                LEFT JOIN tenant_subscriptions ts ON ts.tenant_id = t.id
                LEFT JOIN plans p ON p.id = ts.plan_id';
        $params = [];

        if ($status !== null && $status !== '') {
            $sql .= ' WHERE t.status = ?';
            $params[] = strtoupper($status);
        }

        $sql .= ' ORDER BY t.id DESC LIMIT ' . $limit;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $tenantId = (int) ($row['id'] ?? 0);
            $row['modules'] = $this->listTenantEnabledModules($tenantId);
        }

        return $rows;
    }

    public function findTenantById(int $tenantId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT t.id, t.name, t.slug, t.status, t.created_at,
                    p.code AS plan_code, p.name AS plan_name,
                    ts.status AS subscription_status,
                    ts.starts_at, ts.ends_at
             FROM tenants t
             LEFT JOIN tenant_subscriptions ts ON ts.tenant_id = t.id
             LEFT JOIN plans p ON p.id = ts.plan_id
             WHERE t.id = ?
             LIMIT 1'
        );
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $row['modules'] = $this->listTenantEnabledModules($tenantId);
        $row['counters'] = $this->tenantCounters($tenantId);
        return $row;
    }

    public function updateTenantStatus(int $tenantId, string $status): void
    {
        $stmt = $this->db->prepare('UPDATE tenants SET status = ? WHERE id = ?');
        $stmt->execute([$status, $tenantId]);
    }

    public function getTenantSettingsRaw(int $tenantId): array
    {
        $stmt = $this->db->prepare('SELECT modules FROM tenant_settings WHERE tenant_id = ? LIMIT 1');
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch();

        if (!$row || !isset($row['modules'])) {
            return [];
        }

        $decoded = json_decode((string) $row['modules'], true);
        return is_array($decoded) ? $decoded : [];
    }

    public function updateTenantSettingsRaw(int $tenantId, array $settings): void
    {
        $json = json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $json = '{}';
        }

        $stmt = $this->db->prepare('UPDATE tenant_settings SET modules = ?, updated_at = NOW() WHERE tenant_id = ?');
        $stmt->execute([$json, $tenantId]);

        if ($stmt->rowCount() > 0) {
            return;
        }

        $insert = $this->db->prepare(
            'INSERT INTO tenant_settings (tenant_id, modules, created_at, updated_at) VALUES (?, ?, NOW(), NOW())'
        );
        $insert->execute([$tenantId, $json]);
    }

    public function findPlanByCode(string $planCode): ?array
    {
        $stmt = $this->db->prepare('SELECT id, code, name, status FROM plans WHERE code = ? LIMIT 1');
        $stmt->execute([$planCode]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function upsertTenantSubscription(int $tenantId, int $planId, string $status): void
    {
        $stmt = $this->db->prepare('SELECT id FROM tenant_subscriptions WHERE tenant_id = ? LIMIT 1');
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch();

        if ($row) {
            $update = $this->db->prepare(
                'UPDATE tenant_subscriptions
                 SET plan_id = ?, status = ?, starts_at = COALESCE(starts_at, NOW()), updated_at = NOW()
                 WHERE id = ?'
            );
            $update->execute([$planId, $status, (int) $row['id']]);
            return;
        }

        $insert = $this->db->prepare(
            'INSERT INTO tenant_subscriptions (tenant_id, plan_id, status, starts_at, created_at)
             VALUES (?, ?, ?, NOW(), NOW())'
        );
        $insert->execute([$tenantId, $planId, $status]);
    }

    public function listPlans(): array
    {
        $stmt = $this->db->query('SELECT id, code, name, status, modules_json, limits_json FROM plans ORDER BY id ASC');
        $rows = $stmt ? $stmt->fetchAll() : [];
        foreach ($rows as &$row) {
            $row['modules'] = $this->decodeJson((string) ($row['modules_json'] ?? '[]'), []);
            $row['limits'] = $this->decodeJson((string) ($row['limits_json'] ?? '{}'), []);
            unset($row['modules_json'], $row['limits_json']);
        }
        return $rows;
    }

    private function tenantCounters(int $tenantId): array
    {
        return [
            'users' => $this->countBySql('SELECT COUNT(*) AS cnt FROM users WHERE tenant_id = ?', [$tenantId]),
            'branches' => $this->countBySql('SELECT COUNT(*) AS cnt FROM branches WHERE tenant_id = ?', [$tenantId]),
            'registers' => $this->countBySql('SELECT COUNT(*) AS cnt FROM pos_registers WHERE tenant_id = ?', [$tenantId]),
            'customers' => $this->countBySql('SELECT COUNT(*) AS cnt FROM customers WHERE tenant_id = ?', [$tenantId]),
            'items' => $this->countBySql('SELECT COUNT(*) AS cnt FROM catalog_items WHERE tenant_id = ?', [$tenantId]),
            'invoices' => $this->countBySql('SELECT COUNT(*) AS cnt FROM invoices WHERE tenant_id = ?', [$tenantId]),
        ];
    }

    private function listTenantEnabledModules(int $tenantId): array
    {
        $settings = $this->getTenantSettingsRaw($tenantId);
        $enabled = [];

        if (!isset($settings['modules']) || !is_array($settings['modules'])) {
            return $enabled;
        }

        foreach ($settings['modules'] as $module => $config) {
            if (is_array($config) && ($config['enabled'] ?? false) === true) {
                $enabled[] = (string) $module;
            }
        }

        sort($enabled);
        return $enabled;
    }

    private function countBySql(string $sql, array $params): int
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return (int) ($row['cnt'] ?? 0);
    }

    private function decodeJson(string $json, array $fallback): array
    {
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : $fallback;
    }
}

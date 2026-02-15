<?php

namespace App\Plans;

use PDO;

class PlanRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function findTenantPlan(int $tenantId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                p.id,
                p.code,
                p.name,
                p.modules_json,
                p.limits_json,
                ts.status AS subscription_status
             FROM tenant_subscriptions ts
             INNER JOIN plans p ON p.id = ts.plan_id
             WHERE ts.tenant_id = ?
             LIMIT 1'
        );
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }
}

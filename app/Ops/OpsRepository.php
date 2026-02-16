<?php

namespace App\Ops;

use PDO;

class OpsRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function tenantMetrics(int $tenantId): array
    {
        $sync = $this->syncMetrics($tenantId);
        $conflicts = $this->syncConflictsMetrics($tenantId);
        $jobs = $this->jobsMetrics($tenantId);
        $fiscal = $this->fiscalMetrics($tenantId);

        return [
            'sync' => $sync,
            'conflicts' => $conflicts,
            'jobs' => $jobs,
            'fiscal' => $fiscal,
        ];
    }

    public function listSyncConflicts(int $tenantId, array $filters, int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $sql = 'SELECT id, device_id, type, op_id, event_id, existing_event_id, reason_code, resolved, resolved_at, created_at
                FROM sync_conflicts
                WHERE tenant_id = ?';
        $params = [$tenantId];

        if (isset($filters['resolved']) && $filters['resolved'] !== '') {
            $resolved = (int) $filters['resolved'] === 1 ? 1 : 0;
            $sql .= ' AND resolved = ?';
            $params[] = $resolved;
        }

        $deviceId = trim((string) ($filters['device_id'] ?? ''));
        if ($deviceId !== '') {
            $sql .= ' AND device_id = ?';
            $params[] = $deviceId;
        }

        $type = trim((string) ($filters['type'] ?? ''));
        if ($type !== '') {
            $sql .= ' AND type = ?';
            $params[] = $type;
        }

        $sql .= ' ORDER BY id DESC LIMIT ' . $limit;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function jobsQueues(int $tenantId): array
    {
        $sql = "
            SELECT queue_name,
                   SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) AS pending,
                   SUM(CASE WHEN status = 'RETRY' THEN 1 ELSE 0 END) AS retry,
                   SUM(CASE WHEN status = 'PROCESSING' THEN 1 ELSE 0 END) AS processing,
                   SUM(CASE WHEN status = 'FAILED' THEN 1 ELSE 0 END) AS failed,
                   SUM(CASE WHEN status = 'COMPLETED' THEN 1 ELSE 0 END) AS completed
            FROM jobs_queue
            WHERE JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.tenant_id')) = ?
            GROUP BY queue_name
            ORDER BY queue_name ASC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([(string) $tenantId]);
        $rows = $stmt->fetchAll();

        $dlqStmt = $this->db->prepare(
            "SELECT queue_name, COUNT(*) AS dlq_total
             FROM jobs_dlq
             WHERE JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.tenant_id')) = ?
               AND requeued_at IS NULL
             GROUP BY queue_name"
        );
        $dlqStmt->execute([(string) $tenantId]);
        $dlqRows = $dlqStmt->fetchAll();
        $dlqByQueue = [];
        foreach ($dlqRows as $dlq) {
            $dlqByQueue[(string) ($dlq['queue_name'] ?? '')] = (int) ($dlq['dlq_total'] ?? 0);
        }

        foreach ($rows as &$row) {
            $queueName = (string) ($row['queue_name'] ?? '');
            $row['pending'] = (int) ($row['pending'] ?? 0);
            $row['retry'] = (int) ($row['retry'] ?? 0);
            $row['processing'] = (int) ($row['processing'] ?? 0);
            $row['failed'] = (int) ($row['failed'] ?? 0);
            $row['completed'] = (int) ($row['completed'] ?? 0);
            $row['dlq_total'] = $dlqByQueue[$queueName] ?? 0;
        }

        return $rows;
    }

    public function listDlq(int $tenantId, ?string $queueName, int $limit = 50): array
    {
        $limit = max(1, min(500, $limit));
        $sql = "SELECT id, job_id, queue_name, attempts, max_attempts, last_error, failed_at, requeued_job_id, requeued_at
                FROM jobs_dlq
                WHERE JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.tenant_id')) = ?";
        $params = [(string) $tenantId];

        if ($queueName !== null && $queueName !== '') {
            $sql .= ' AND queue_name = ?';
            $params[] = $queueName;
        }

        $sql .= ' ORDER BY id DESC LIMIT ' . $limit;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function requeueDlq(int $tenantId, int $dlqId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id, queue_name, payload_json, max_attempts, requeued_job_id, requeued_at
             FROM jobs_dlq
             WHERE id = ?
               AND JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.tenant_id')) = ?
             LIMIT 1
             FOR UPDATE"
        );
        $stmt->execute([$dlqId, (string) $tenantId]);
        $dlq = $stmt->fetch();
        if (!$dlq) {
            return null;
        }

        if (!empty($dlq['requeued_at'])) {
            return [
                'already_requeued' => true,
                'job_id' => (int) ($dlq['requeued_job_id'] ?? 0),
            ];
        }

        $insert = $this->db->prepare(
            "INSERT INTO jobs_queue (queue_name, idempotency_key, payload_json, status, attempts, max_attempts, available_at, created_at)
             VALUES (?, NULL, ?, 'PENDING', 0, ?, NOW(), NOW())"
        );
        $insert->execute([
            (string) $dlq['queue_name'],
            (string) $dlq['payload_json'],
            max(1, (int) ($dlq['max_attempts'] ?? 5)),
        ]);
        $newJobId = (int) $this->db->lastInsertId();

        $update = $this->db->prepare(
            'UPDATE jobs_dlq
             SET requeued_job_id = ?, requeued_at = NOW()
             WHERE id = ?'
        );
        $update->execute([$newJobId, $dlqId]);

        return [
            'already_requeued' => false,
            'job_id' => $newJobId,
        ];
    }

    public function branchExists(int $tenantId, int $branchId): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM branches WHERE tenant_id = ? AND id = ? LIMIT 1');
        $stmt->execute([$tenantId, $branchId]);
        return (bool) $stmt->fetch();
    }

    public function computeDailyClosureSummary(int $tenantId, int $branchId, string $closeDate): array
    {
        $fromTs = $closeDate . ' 00:00:00';
        $toTs = $closeDate . ' 23:59:59';

        $sessionsStmt = $this->db->prepare(
            "SELECT
                COUNT(*) AS sessions_count,
                COALESCE(SUM(opening_amount), 0) AS opening_cash,
                COALESCE(SUM(expected_amount), 0) AS expected_sessions_total,
                COALESCE(SUM(COALESCE(closing_amount, 0)), 0) AS declared_sessions_total,
                COALESCE(SUM(COALESCE(difference, 0)), 0) AS sessions_difference_total
             FROM pos_cash_sessions
             WHERE tenant_id = ?
               AND branch_id = ?
               AND opened_at BETWEEN ? AND ?"
        );
        $sessionsStmt->execute([$tenantId, $branchId, $fromTs, $toTs]);
        $sessions = $sessionsStmt->fetch() ?: [];

        $movementsStmt = $this->db->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN type = 'IN' THEN amount ELSE 0 END), 0) AS cash_in,
                COALESCE(SUM(CASE WHEN type = 'OUT' THEN amount ELSE 0 END), 0) AS cash_out
             FROM pos_cash_movements
             WHERE tenant_id = ?
               AND branch_id = ?
               AND created_at BETWEEN ? AND ?"
        );
        $movementsStmt->execute([$tenantId, $branchId, $fromTs, $toTs]);
        $movements = $movementsStmt->fetch() ?: [];

        $salesStmt = $this->db->prepare(
            "SELECT
                COUNT(*) AS sales_count,
                COALESCE(SUM(total), 0) AS sales_total
             FROM pos_sales
             WHERE tenant_id = ?
               AND branch_id = ?
               AND status = 'PAID'
               AND created_at BETWEEN ? AND ?"
        );
        $salesStmt->execute([$tenantId, $branchId, $fromTs, $toTs]);
        $sales = $salesStmt->fetch() ?: [];

        $paymentsStmt = $this->db->prepare(
            "SELECT
                COUNT(*) AS payments_count,
                COALESCE(SUM(amount), 0) AS payments_total,
                COALESCE(SUM(CASE WHEN method = 'CASH' THEN amount ELSE 0 END), 0) AS cash_sales
             FROM pos_payments
             WHERE tenant_id = ?
               AND branch_id = ?
               AND created_at BETWEEN ? AND ?"
        );
        $paymentsStmt->execute([$tenantId, $branchId, $fromTs, $toTs]);
        $payments = $paymentsStmt->fetch() ?: [];

        $openingCash = (float) ($sessions['opening_cash'] ?? 0);
        $cashIn = (float) ($movements['cash_in'] ?? 0);
        $cashOut = (float) ($movements['cash_out'] ?? 0);
        $cashSales = (float) ($payments['cash_sales'] ?? 0);
        $expectedCash = round($openingCash + $cashIn - $cashOut + $cashSales, 2);

        return [
            'close_date' => $closeDate,
            'branch_id' => $branchId,
            'sessions_count' => (int) ($sessions['sessions_count'] ?? 0),
            'opening_cash' => $openingCash,
            'cash_in' => $cashIn,
            'cash_out' => $cashOut,
            'cash_sales' => $cashSales,
            'sales_count' => (int) ($sales['sales_count'] ?? 0),
            'sales_total' => (float) ($sales['sales_total'] ?? 0),
            'payments_count' => (int) ($payments['payments_count'] ?? 0),
            'payments_total' => (float) ($payments['payments_total'] ?? 0),
            'expected_cash' => $expectedCash,
            'expected_sessions_total' => (float) ($sessions['expected_sessions_total'] ?? 0),
            'declared_sessions_total' => (float) ($sessions['declared_sessions_total'] ?? 0),
            'sessions_difference_total' => (float) ($sessions['sessions_difference_total'] ?? 0),
        ];
    }

    public function upsertDailyClosure(int $tenantId, array $payload, int $userId): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO ops_daily_closures (
                tenant_id, branch_id, close_date, status, opening_cash, cash_in, cash_out, cash_sales, sales_total, sales_count,
                payments_total, payments_count, expected_cash, declared_cash, difference, summary_json, closed_by, closed_at, created_at, updated_at
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                opening_cash = VALUES(opening_cash),
                cash_in = VALUES(cash_in),
                cash_out = VALUES(cash_out),
                cash_sales = VALUES(cash_sales),
                sales_total = VALUES(sales_total),
                sales_count = VALUES(sales_count),
                payments_total = VALUES(payments_total),
                payments_count = VALUES(payments_count),
                expected_cash = VALUES(expected_cash),
                declared_cash = VALUES(declared_cash),
                difference = VALUES(difference),
                summary_json = VALUES(summary_json),
                closed_by = VALUES(closed_by),
                closed_at = NOW(),
                updated_at = NOW()'
        );
        $summaryJson = json_encode($payload['summary'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($summaryJson === false) {
            $summaryJson = '{}';
        }

        $stmt->execute([
            $tenantId,
            $payload['branch_id'],
            $payload['close_date'],
            'CLOSED',
            $payload['opening_cash'],
            $payload['cash_in'],
            $payload['cash_out'],
            $payload['cash_sales'],
            $payload['sales_total'],
            $payload['sales_count'],
            $payload['payments_total'],
            $payload['payments_count'],
            $payload['expected_cash'],
            $payload['declared_cash'],
            $payload['difference'],
            $summaryJson,
            $userId > 0 ? $userId : null,
        ]);

        $row = $this->findDailyClosureByDate($tenantId, (int) $payload['branch_id'], (string) $payload['close_date']);
        return (int) ($row['id'] ?? 0);
    }

    public function findDailyClosureByDate(int $tenantId, int $branchId, string $closeDate): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT dc.*, b.name AS branch_name
             FROM ops_daily_closures dc
             INNER JOIN branches b ON b.id = dc.branch_id AND b.tenant_id = dc.tenant_id
             WHERE dc.tenant_id = ? AND dc.branch_id = ? AND dc.close_date = ?
             LIMIT 1'
        );
        $stmt->execute([$tenantId, $branchId, $closeDate]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        return $this->hydrateDailyClosure($row);
    }

    public function findDailyClosureById(int $tenantId, int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT dc.*, b.name AS branch_name
             FROM ops_daily_closures dc
             INNER JOIN branches b ON b.id = dc.branch_id AND b.tenant_id = dc.tenant_id
             WHERE dc.tenant_id = ? AND dc.id = ?
             LIMIT 1'
        );
        $stmt->execute([$tenantId, $id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        return $this->hydrateDailyClosure($row);
    }

    public function listDailyClosures(int $tenantId, array $filters, int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $sql = 'SELECT dc.*, b.name AS branch_name
                FROM ops_daily_closures dc
                INNER JOIN branches b ON b.id = dc.branch_id AND b.tenant_id = dc.tenant_id
                WHERE dc.tenant_id = ?';
        $params = [$tenantId];

        $branchId = (int) ($filters['branch_id'] ?? 0);
        if ($branchId > 0) {
            $sql .= ' AND dc.branch_id = ?';
            $params[] = $branchId;
        }

        $status = strtoupper(trim((string) ($filters['status'] ?? '')));
        if ($status !== '') {
            $sql .= ' AND dc.status = ?';
            $params[] = $status;
        }

        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $sql .= ' AND dc.close_date >= ?';
            $params[] = $dateFrom;
        }

        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if ($dateTo !== '') {
            $sql .= ' AND dc.close_date <= ?';
            $params[] = $dateTo;
        }

        $sql .= ' ORDER BY dc.close_date DESC, dc.id DESC LIMIT ' . $limit;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $row = $this->hydrateDailyClosure($row);
        }

        return $rows;
    }

    public function reopenDailyClosure(int $tenantId, int $id, string $reason, int $userId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE ops_daily_closures
             SET status = 'REOPENED',
                 reason_reopen = ?,
                 reopened_by = ?,
                 reopened_at = NOW(),
                 updated_at = NOW()
             WHERE tenant_id = ? AND id = ? AND status = 'CLOSED'"
        );
        $stmt->execute([
            substr($reason, 0, 255),
            $userId > 0 ? $userId : null,
            $tenantId,
            $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function sliSloSnapshot(int $tenantId): array
    {
        $sync = $this->syncMetrics($tenantId);
        $jobs = $this->jobsMetrics($tenantId);
        $conflicts = $this->syncConflictsMetrics($tenantId);

        $syncTotal = max(1, (int) ($sync['total_events'] ?? 0));
        $syncFailed = (int) ($sync['failed'] ?? 0);
        $syncConflicts = (int) ($sync['conflicts'] ?? 0);
        $syncErrorRate = round((($syncFailed + $syncConflicts) / $syncTotal) * 100, 2);

        $jobsTotal = max(1, $jobs['pending'] + $jobs['retry'] + $jobs['processing'] + $jobs['failed'] + $jobs['completed']);
        $jobsFailedRate = round(((int) $jobs['failed'] / $jobsTotal) * 100, 2);

        $syncLagSeconds = $this->syncLagSeconds($tenantId);
        $queueLagSeconds = $this->queueLagSeconds($tenantId);

        return [
            'captured_at' => gmdate('c'),
            'sli' => [
                'sync.error_rate_pct' => $syncErrorRate,
                'sync.conflicts_open' => (int) ($conflicts['open'] ?? 0),
                'sync.lag_seconds' => $syncLagSeconds,
                'jobs.failed_rate_pct' => $jobsFailedRate,
                'jobs.pending_total' => (int) $jobs['pending'],
                'jobs.retry_total' => (int) $jobs['retry'],
                'jobs.dlq_total' => (int) $jobs['dlq_total'],
                'jobs.queue_lag_seconds' => $queueLagSeconds,
            ],
            'slo' => [
                'sync.error_rate_pct_target' => 5.0,
                'sync.lag_seconds_target' => 120.0,
                'jobs.failed_rate_pct_target' => 3.0,
                'jobs.queue_lag_seconds_target' => 180.0,
            ],
        ];
    }

    public function upsertAlertRule(int $tenantId, array $rule, int $userId): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO ops_alert_rules (
                tenant_id, code, metric_key, comparator, threshold_value, severity, channel, target, enabled, created_by, created_at, updated_at
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                metric_key = VALUES(metric_key),
                comparator = VALUES(comparator),
                threshold_value = VALUES(threshold_value),
                severity = VALUES(severity),
                channel = VALUES(channel),
                target = VALUES(target),
                enabled = VALUES(enabled),
                updated_at = NOW()'
        );
        $stmt->execute([
            $tenantId,
            $rule['code'],
            $rule['metric_key'],
            $rule['comparator'],
            $rule['threshold_value'],
            $rule['severity'],
            $rule['channel'],
            $rule['target'],
            $rule['enabled'] ? 1 : 0,
            $userId > 0 ? $userId : null,
        ]);

        $row = $this->findAlertRuleByCode($tenantId, (string) $rule['code']);
        return (int) ($row['id'] ?? 0);
    }

    public function listAlertRules(int $tenantId, bool $onlyEnabled = false): array
    {
        $sql = 'SELECT id, code, metric_key, comparator, threshold_value, severity, channel, target, enabled, last_triggered_at, updated_at
                FROM ops_alert_rules
                WHERE tenant_id = ?';
        $params = [$tenantId];

        if ($onlyEnabled) {
            $sql .= ' AND enabled = 1';
        }

        $sql .= ' ORDER BY id DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $row['id'] = (int) ($row['id'] ?? 0);
            $row['threshold_value'] = (float) ($row['threshold_value'] ?? 0);
            $row['enabled'] = (int) ($row['enabled'] ?? 0) === 1;
        }

        return $rows;
    }

    public function createAlertEvent(int $tenantId, int $ruleId, float $metricValue, float $thresholdValue, string $severity, array $payload): int
    {
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payloadJson === false) {
            $payloadJson = '{}';
        }

        $stmt = $this->db->prepare(
            'INSERT INTO ops_alert_events (
                tenant_id, rule_id, metric_value, threshold_value, severity, status, payload_json, triggered_at, created_at
             ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([$tenantId, $ruleId, $metricValue, $thresholdValue, $severity, 'OPEN', $payloadJson]);

        $this->db->prepare('UPDATE ops_alert_rules SET last_triggered_at = NOW(), updated_at = NOW() WHERE tenant_id = ? AND id = ?')
            ->execute([$tenantId, $ruleId]);

        return (int) $this->db->lastInsertId();
    }

    public function listIncidentEvents(int $tenantId, array $filters, int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $sql = 'SELECT
                    e.id, e.rule_id, e.metric_value, e.threshold_value, e.severity, e.status, e.triggered_at, e.acknowledged_at, e.resolved_at,
                    r.code AS rule_code, r.metric_key, r.channel, r.target, e.payload_json
                FROM ops_alert_events e
                INNER JOIN ops_alert_rules r ON r.id = e.rule_id AND r.tenant_id = e.tenant_id
                WHERE e.tenant_id = ?';
        $params = [$tenantId];

        $status = strtoupper(trim((string) ($filters['status'] ?? '')));
        if ($status !== '') {
            $sql .= ' AND e.status = ?';
            $params[] = $status;
        }

        $severity = strtoupper(trim((string) ($filters['severity'] ?? '')));
        if ($severity !== '') {
            $sql .= ' AND e.severity = ?';
            $params[] = $severity;
        }

        $sql .= ' ORDER BY e.id DESC LIMIT ' . $limit;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $row['id'] = (int) ($row['id'] ?? 0);
            $row['rule_id'] = (int) ($row['rule_id'] ?? 0);
            $row['metric_value'] = (float) ($row['metric_value'] ?? 0);
            $row['threshold_value'] = (float) ($row['threshold_value'] ?? 0);
            $row['payload'] = $this->decodeJson((string) ($row['payload_json'] ?? ''), []);
            unset($row['payload_json']);
        }

        return $rows;
    }

    public function diagnoseTenant(int $tenantId): array
    {
        $checks = [];

        $checks[] = [
            'key' => 'tenant.settings',
            'ok' => $this->hasTenantSettings($tenantId),
            'message' => 'Tenant settings cargado',
        ];
        $checks[] = [
            'key' => 'tenant.branches',
            'ok' => $this->countByTenant('branches', $tenantId) > 0,
            'message' => 'Existe al menos una sucursal',
        ];
        $checks[] = [
            'key' => 'tenant.registers',
            'ok' => $this->countByTenant('pos_registers', $tenantId) > 0,
            'message' => 'Existe al menos una caja',
        ];
        $checks[] = [
            'key' => 'tenant.items',
            'ok' => $this->countByTenant('catalog_items', $tenantId) > 0,
            'message' => 'Existe al menos un item en catálogo',
        ];
        $checks[] = [
            'key' => 'ops.sync.health',
            'ok' => $this->syncLagSeconds($tenantId) <= 120,
            'message' => 'Sync lag bajo control',
        ];

        $okCount = 0;
        foreach ($checks as $check) {
            if (($check['ok'] ?? false) === true) {
                $okCount++;
            }
        }

        return [
            'score' => count($checks) > 0 ? round(($okCount / count($checks)) * 100, 2) : 0,
            'checks' => $checks,
        ];
    }

    public function getOnboardingChecklist(int $tenantId, string $checklistCode): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, checklist_code, status, progress_pct, checklist_json, created_at, updated_at
             FROM ops_onboarding_checklists
             WHERE tenant_id = ? AND checklist_code = ?
             LIMIT 1'
        );
        $stmt->execute([$tenantId, $checklistCode]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'checklist_code' => (string) ($row['checklist_code'] ?? ''),
            'status' => (string) ($row['status'] ?? 'IN_PROGRESS'),
            'progress_pct' => (float) ($row['progress_pct'] ?? 0),
            'items' => $this->decodeJson((string) ($row['checklist_json'] ?? ''), []),
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    public function upsertOnboardingChecklist(int $tenantId, string $checklistCode, array $items, float $progress, int $userId): int
    {
        $status = $progress >= 100 ? 'COMPLETED' : 'IN_PROGRESS';
        $json = json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $json = '[]';
        }

        $stmt = $this->db->prepare(
            'INSERT INTO ops_onboarding_checklists (
                tenant_id, checklist_code, status, progress_pct, checklist_json, updated_by, created_at, updated_at
             ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                progress_pct = VALUES(progress_pct),
                checklist_json = VALUES(checklist_json),
                updated_by = VALUES(updated_by),
                updated_at = NOW()'
        );
        $stmt->execute([
            $tenantId,
            $checklistCode,
            $status,
            $progress,
            $json,
            $userId > 0 ? $userId : null,
        ]);

        $row = $this->getOnboardingChecklist($tenantId, $checklistCode);
        return (int) ($row['id'] ?? 0);
    }

    private function hydrateDailyClosure(array $row): array
    {
        $summary = [];
        $raw = (string) ($row['summary_json'] ?? '');
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $summary = $decoded;
            }
        }
        unset($row['summary_json']);
        $row['summary'] = $summary;
        $row['difference'] = (float) ($row['difference'] ?? 0);
        $row['expected_cash'] = (float) ($row['expected_cash'] ?? 0);
        $row['declared_cash'] = (float) ($row['declared_cash'] ?? 0);

        return $row;
    }

    private function syncMetrics(int $tenantId): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                COUNT(*) AS total_events,
                SUM(CASE WHEN status = 'APPLIED' THEN 1 ELSE 0 END) AS applied,
                SUM(CASE WHEN status = 'FAILED' THEN 1 ELSE 0 END) AS failed,
                SUM(CASE WHEN status = 'CONFLICT' THEN 1 ELSE 0 END) AS conflicts,
                MAX(created_at) AS last_event_at
             FROM sync_events
             WHERE tenant_id = ?"
        );
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch() ?: [];

        return [
            'total_events' => (int) ($row['total_events'] ?? 0),
            'applied' => (int) ($row['applied'] ?? 0),
            'failed' => (int) ($row['failed'] ?? 0),
            'conflicts' => (int) ($row['conflicts'] ?? 0),
            'last_event_at' => $row['last_event_at'] ?? null,
        ];
    }

    private function syncConflictsMetrics(int $tenantId): array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN resolved = 1 THEN 1 ELSE 0 END) AS resolved_count,
                    SUM(CASE WHEN resolved = 0 THEN 1 ELSE 0 END) AS open_count
                 FROM sync_conflicts
                 WHERE tenant_id = ?"
            );
            $stmt->execute([$tenantId]);
            $row = $stmt->fetch() ?: [];
        } catch (\Throwable $e) {
            return ['total' => 0, 'resolved' => 0, 'open' => 0];
        }

        return [
            'total' => (int) ($row['total'] ?? 0),
            'resolved' => (int) ($row['resolved_count'] ?? 0),
            'open' => (int) ($row['open_count'] ?? 0),
        ];
    }

    private function jobsMetrics(int $tenantId): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status = 'RETRY' THEN 1 ELSE 0 END) AS retry,
                SUM(CASE WHEN status = 'PROCESSING' THEN 1 ELSE 0 END) AS processing,
                SUM(CASE WHEN status = 'FAILED' THEN 1 ELSE 0 END) AS failed,
                SUM(CASE WHEN status = 'COMPLETED' THEN 1 ELSE 0 END) AS completed
             FROM jobs_queue
             WHERE JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.tenant_id')) = ?"
        );
        $stmt->execute([(string) $tenantId]);
        $row = $stmt ? ($stmt->fetch() ?: []) : [];

        $dlqStmt = $this->db->prepare(
            "SELECT COUNT(*) AS dlq_total
             FROM jobs_dlq
             WHERE JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.tenant_id')) = ?
               AND requeued_at IS NULL"
        );
        $dlqStmt->execute([(string) $tenantId]);
        $dlqRow = $dlqStmt->fetch() ?: [];

        return [
            'pending' => (int) ($row['pending'] ?? 0),
            'retry' => (int) ($row['retry'] ?? 0),
            'processing' => (int) ($row['processing'] ?? 0),
            'failed' => (int) ($row['failed'] ?? 0),
            'completed' => (int) ($row['completed'] ?? 0),
            'dlq_total' => (int) ($dlqRow['dlq_total'] ?? 0),
        ];
    }

    private function fiscalMetrics(int $tenantId): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'ACCEPTED' THEN 1 ELSE 0 END) AS accepted,
                SUM(CASE WHEN status = 'REJECTED' THEN 1 ELSE 0 END) AS rejected,
                SUM(CASE WHEN status = 'FAILED' THEN 1 ELSE 0 END) AS failed
             FROM fiscal_documents
             WHERE tenant_id = ?"
        );
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch() ?: [];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'accepted' => (int) ($row['accepted'] ?? 0),
            'rejected' => (int) ($row['rejected'] ?? 0),
            'failed' => (int) ($row['failed'] ?? 0),
        ];
    }

    private function findAlertRuleByCode(int $tenantId, string $code): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, code, metric_key, comparator, threshold_value, severity, channel, target, enabled, last_triggered_at
             FROM ops_alert_rules
             WHERE tenant_id = ? AND code = ?
             LIMIT 1'
        );
        $stmt->execute([$tenantId, $code]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $row['id'] = (int) ($row['id'] ?? 0);
        $row['threshold_value'] = (float) ($row['threshold_value'] ?? 0);
        $row['enabled'] = (int) ($row['enabled'] ?? 0) === 1;
        return $row;
    }

    private function syncLagSeconds(int $tenantId): float
    {
        $stmt = $this->db->prepare('SELECT MAX(created_at) AS last_event_at FROM sync_events WHERE tenant_id = ?');
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch() ?: [];
        $last = $row['last_event_at'] ?? null;
        if (!$last) {
            return 0.0;
        }

        $delta = time() - strtotime((string) $last);
        return (float) max(0, $delta);
    }

    private function queueLagSeconds(int $tenantId): float
    {
        $stmt = $this->db->prepare(
            "SELECT MIN(created_at) AS oldest_pending
             FROM jobs_queue
             WHERE status IN ('PENDING', 'RETRY')
               AND JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.tenant_id')) = ?"
        );
        $stmt->execute([(string) $tenantId]);
        $row = $stmt->fetch() ?: [];
        $oldest = $row['oldest_pending'] ?? null;
        if (!$oldest) {
            return 0.0;
        }

        $delta = time() - strtotime((string) $oldest);
        return (float) max(0, $delta);
    }

    private function hasTenantSettings(int $tenantId): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM tenant_settings WHERE tenant_id = ? LIMIT 1');
        $stmt->execute([$tenantId]);
        return (bool) $stmt->fetch();
    }

    private function countByTenant(string $table, int $tenantId): int
    {
        $allowed = ['branches', 'pos_registers', 'catalog_items'];
        if (!in_array($table, $allowed, true)) {
            return 0;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*) AS cnt FROM {$table} WHERE tenant_id = ?");
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch() ?: [];
        return (int) ($row['cnt'] ?? 0);
    }

    private function decodeJson(string $raw, array $fallback): array
    {
        if ($raw === '') {
            return $fallback;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : $fallback;
    }
}

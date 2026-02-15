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
        $jobs = $this->jobsMetrics();
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

    private function jobsMetrics(): array
    {
        $stmt = $this->db->query(
            "SELECT
                SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status = 'RETRY' THEN 1 ELSE 0 END) AS retry,
                SUM(CASE WHEN status = 'PROCESSING' THEN 1 ELSE 0 END) AS processing,
                SUM(CASE WHEN status = 'FAILED' THEN 1 ELSE 0 END) AS failed,
                SUM(CASE WHEN status = 'COMPLETED' THEN 1 ELSE 0 END) AS completed
             FROM jobs_queue"
        );
        $row = $stmt ? ($stmt->fetch() ?: []) : [];

        $dlqStmt = $this->db->query("SELECT COUNT(*) AS dlq_total FROM jobs_dlq");
        $dlqRow = $dlqStmt ? ($dlqStmt->fetch() ?: []) : [];

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
}

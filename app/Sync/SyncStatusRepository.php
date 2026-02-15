<?php

namespace App\Sync;

use PDO;

class SyncStatusRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getStatus(int $tenantId, string $deviceId): array
    {
        try {
            $pendingStmt = $this->db->prepare('SELECT COUNT(*) as cnt FROM sync_events WHERE tenant_id = ? AND device_id = ? AND status = ?');
            $pendingStmt->execute([$tenantId, $deviceId, 'PENDING']);
            $pending = (int) ($pendingStmt->fetch()['cnt'] ?? 0);

            $failedStmt = $this->db->prepare('SELECT COUNT(*) as cnt FROM sync_events WHERE tenant_id = ? AND device_id = ? AND status = ?');
            $failedStmt->execute([$tenantId, $deviceId, 'FAILED']);
            $failed = (int) ($failedStmt->fetch()['cnt'] ?? 0);

            $conflictStmt = $this->db->prepare('SELECT COUNT(*) as cnt FROM sync_events WHERE tenant_id = ? AND device_id = ? AND status = ?');
            $conflictStmt->execute([$tenantId, $deviceId, 'CONFLICT']);
            $conflicts = (int) ($conflictStmt->fetch()['cnt'] ?? 0);

            $lastAppliedStmt = $this->db->prepare('SELECT MAX(applied_at) as last_applied FROM sync_events WHERE tenant_id = ? AND device_id = ? AND status = ?');
            $lastAppliedStmt->execute([$tenantId, $deviceId, 'APPLIED']);
            $lastApplied = $lastAppliedStmt->fetch()['last_applied'] ?? null;

            $lastEventStmt = $this->db->prepare('SELECT MAX(created_at) as last_event FROM sync_events WHERE tenant_id = ? AND device_id = ?');
            $lastEventStmt->execute([$tenantId, $deviceId]);
            $lastEvent = $lastEventStmt->fetch()['last_event'] ?? null;
        } catch (\Throwable $e) {
            return $this->emptyStatus();
        }

        return [
            'pending_count' => $pending,
            'failed_count' => $failed,
            'conflict_count' => $conflicts,
            'last_applied_at' => $lastApplied,
            'last_event_at' => $lastEvent,
        ];
    }

    private function emptyStatus(): array
    {
        return [
            'pending_count' => 0,
            'failed_count' => 0,
            'conflict_count' => 0,
            'last_applied_at' => null,
            'last_event_at' => null,
        ];
    }
}

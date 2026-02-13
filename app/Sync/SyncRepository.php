<?php

namespace App\Sync;

use PDO;

class SyncRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function createEvent(int $tenantId, array $event, string $status, ?string $error = null): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO sync_events (tenant_id, device_id, event_id, type, idempotency_key, payload_json, status, error_message, created_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $tenantId,
            $event['device_id'],
            $event['event_id'],
            $event['type'],
            $event['idempotency_key'],
            json_encode($event['payload']),
            $status,
            $error,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function markAppliedAt(int $id): void
    {
        try {
            $stmt = $this->db->prepare('UPDATE sync_events SET applied_at = NOW() WHERE id = ?');
            $stmt->execute([$id]);
        } catch (\Throwable $e) {
            // Compatibilidad con esquemas antiguos sin columna applied_at.
            return;
        }
    }

    public function existsIdempotency(int $tenantId, string $deviceId, string $key): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM idempotency_keys WHERE tenant_id = ? AND device_id = ? AND idempotency_key = ? LIMIT 1');
        $stmt->execute([$tenantId, $deviceId, $key]);

        return (bool) $stmt->fetch();
    }

    public function registerIdempotency(int $tenantId, string $deviceId, string $key): void
    {
        $stmt = $this->db->prepare('INSERT INTO idempotency_keys (tenant_id, device_id, idempotency_key, status, created_at) VALUES (?, ?, ?, ?, NOW())');
        $stmt->execute([$tenantId, $deviceId, $key, 'APPLIED']);
    }
}

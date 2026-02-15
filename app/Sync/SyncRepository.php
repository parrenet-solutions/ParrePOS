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
        $payloadJson = json_encode($event['payload'], JSON_UNESCAPED_UNICODE);
        if ($payloadJson === false) {
            $payloadJson = '{}';
        }
        $payloadHash = hash('sha256', $payloadJson);
        $opId = isset($event['op_id']) ? trim((string) $event['op_id']) : null;
        if ($opId === '') {
            $opId = null;
        }
        $conflictCode = isset($event['conflict_code']) ? (string) $event['conflict_code'] : null;

        try {
            $stmt = $this->db->prepare(
                'INSERT INTO sync_events (tenant_id, device_id, event_id, op_id, type, idempotency_key, payload_json, payload_hash, status, error_message, conflict_code, created_at) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([
                $tenantId,
                $event['device_id'],
                $event['event_id'],
                $opId,
                $event['type'],
                $event['idempotency_key'],
                $payloadJson,
                $payloadHash,
                $status,
                $error,
                $conflictCode,
            ]);
        } catch (\Throwable $e) {
            // Compatibilidad con esquemas antiguos sin columnas v2.
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
                $payloadJson,
                $status,
                $error,
            ]);
        }

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

    public function findByOperation(int $tenantId, string $deviceId, string $type, string $opId): ?array
    {
        if ($opId === '') {
            return null;
        }

        try {
            $stmt = $this->db->prepare(
                'SELECT id, event_id, payload_hash, payload_json, status
                 FROM sync_events
                 WHERE tenant_id = ? AND device_id = ? AND type = ? AND op_id = ?
                 ORDER BY id DESC
                 LIMIT 1'
            );
            $stmt->execute([$tenantId, $deviceId, $type, $opId]);
            $row = $stmt->fetch();
            return $row ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function createConflict(int $tenantId, array $conflict): void
    {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO sync_conflicts (
                    tenant_id, device_id, type, op_id, event_id, existing_event_id, reason_code, payload_json, existing_payload_json, resolved, created_at
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())'
            );
            $stmt->execute([
                $tenantId,
                $conflict['device_id'],
                $conflict['type'],
                $conflict['op_id'],
                $conflict['event_id'],
                $conflict['existing_event_id'],
                $conflict['reason_code'],
                json_encode($conflict['payload_json'], JSON_UNESCAPED_UNICODE) ?: '{}',
                isset($conflict['existing_payload_json'])
                    ? (json_encode($conflict['existing_payload_json'], JSON_UNESCAPED_UNICODE) ?: '{}')
                    : null,
            ]);
        } catch (\Throwable $e) {
            return;
        }
    }
}

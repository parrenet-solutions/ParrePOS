<?php

namespace App\Fiscal;

use PDO;

class FiscalRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getProfile(int $tenantId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM fiscal_profiles WHERE tenant_id = ? LIMIT 1');
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function upsertProfile(int $tenantId, array $profile): void
    {
        $existing = $this->getProfile($tenantId);
        if ($existing) {
            $stmt = $this->db->prepare(
                'UPDATE fiscal_profiles
                 SET legal_name = ?, rnc = ?, dgii_registered = ?, environment = ?, status = ?, updated_at = NOW()
                 WHERE tenant_id = ?'
            );
            $stmt->execute([
                $profile['legal_name'],
                $profile['rnc'],
                $profile['dgii_registered'] ? 1 : 0,
                $profile['environment'],
                $profile['status'],
                $tenantId,
            ]);
            return;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO fiscal_profiles (tenant_id, legal_name, rnc, dgii_registered, environment, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $tenantId,
            $profile['legal_name'],
            $profile['rnc'],
            $profile['dgii_registered'] ? 1 : 0,
            $profile['environment'],
            $profile['status'],
        ]);
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

    public function getCurrentFiscalSequence(int $tenantId, string $ncfType, string $series): int
    {
        $stmt = $this->db->prepare(
            'SELECT current_number FROM fiscal_sequences WHERE tenant_id = ? AND ncf_type = ? AND series = ? LIMIT 1'
        );
        $stmt->execute([$tenantId, $ncfType, $series]);
        $row = $stmt->fetch();
        return (int) ($row['current_number'] ?? 0);
    }

    public function listDocuments(int $tenantId, ?string $status = null, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT id, invoice_id, status, ncf_type, series, sequence, ncf, track_id, error_message, created_at, updated_at
                FROM fiscal_documents
                WHERE tenant_id = ?';
        $params = [$tenantId];

        if ($status !== null && $status !== '') {
            $sql .= ' AND status = ?';
            $params[] = $status;
        }

        $sql .= ' ORDER BY id DESC LIMIT ' . $limit;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function searchDocuments(int $tenantId, array $filters, int $limit = 50): array
    {
        $limit = max(1, min(500, $limit));
        $sql = 'SELECT id, invoice_id, status, ncf_type, series, sequence, ncf, track_id, error_message, created_at, updated_at
                FROM fiscal_documents
                WHERE tenant_id = ?';
        $params = [$tenantId];

        $status = isset($filters['status']) ? strtoupper(trim((string) $filters['status'])) : '';
        if ($status !== '') {
            $sql .= ' AND status = ?';
            $params[] = $status;
        }

        $ncf = trim((string) ($filters['ncf'] ?? ''));
        if ($ncf !== '') {
            $sql .= ' AND ncf LIKE ?';
            $params[] = '%' . $ncf . '%';
        }

        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $sql .= ' AND created_at >= ?';
            $params[] = $dateFrom . ' 00:00:00';
        }

        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if ($dateTo !== '') {
            $sql .= ' AND created_at <= ?';
            $params[] = $dateTo . ' 23:59:59';
        }

        $sql .= ' ORDER BY id DESC LIMIT ' . $limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function summaryByStatus(int $tenantId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $sql = 'SELECT status, COUNT(*) AS cnt
                FROM fiscal_documents
                WHERE tenant_id = ?';
        $params = [$tenantId];

        if ($dateFrom !== null && trim($dateFrom) !== '') {
            $sql .= ' AND created_at >= ?';
            $params[] = trim($dateFrom) . ' 00:00:00';
        }

        if ($dateTo !== null && trim($dateTo) !== '') {
            $sql .= ' AND created_at <= ?';
            $params[] = trim($dateTo) . ' 23:59:59';
        }

        $sql .= ' GROUP BY status';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $summary = [
            'total' => 0,
            'pending' => 0,
            'processing' => 0,
            'sent' => 0,
            'accepted' => 0,
            'rejected' => 0,
            'failed' => 0,
            'cancelled' => 0,
        ];

        foreach ($rows as $row) {
            $status = strtolower((string) ($row['status'] ?? ''));
            $count = (int) ($row['cnt'] ?? 0);
            $summary['total'] += $count;

            if (array_key_exists($status, $summary)) {
                $summary[$status] = $count;
            }
        }

        return $summary;
    }

    public function metrics(int $tenantId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $where = 'fd.tenant_id = ?';
        $params = [$tenantId];

        if ($dateFrom !== null && trim($dateFrom) !== '') {
            $where .= ' AND fd.created_at >= ?';
            $params[] = trim($dateFrom) . ' 00:00:00';
        }

        if ($dateTo !== null && trim($dateTo) !== '') {
            $where .= ' AND fd.created_at <= ?';
            $params[] = trim($dateTo) . ' 23:59:59';
        }

        $totalsSql = "
            SELECT
                COUNT(*) AS total_docs,
                SUM(CASE WHEN fd.status = 'ACCEPTED' THEN 1 ELSE 0 END) AS accepted_docs,
                SUM(CASE WHEN fd.status = 'REJECTED' THEN 1 ELSE 0 END) AS rejected_docs,
                SUM(CASE WHEN fd.status = 'FAILED' THEN 1 ELSE 0 END) AS failed_docs,
                SUM(CASE WHEN fd.status = 'CANCELLED' THEN 1 ELSE 0 END) AS cancelled_docs
            FROM fiscal_documents fd
            WHERE {$where}
        ";
        $stmt = $this->db->prepare($totalsSql);
        $stmt->execute($params);
        $totals = $stmt->fetch() ?: [];

        $latencySql = "
            SELECT AVG(TIMESTAMPDIFF(SECOND, req.created_at, ack.created_at)) AS avg_latency_seconds
            FROM fiscal_documents fd
            INNER JOIN (
                SELECT fiscal_document_id, MIN(created_at) AS created_at
                FROM fiscal_events
                WHERE tenant_id = ? AND event_type = 'SUBMIT_REQUESTED'
                GROUP BY fiscal_document_id
            ) req ON req.fiscal_document_id = fd.id
            INNER JOIN (
                SELECT fiscal_document_id, MIN(created_at) AS created_at
                FROM fiscal_events
                WHERE tenant_id = ? AND event_type = 'ACK_RECEIVED'
                GROUP BY fiscal_document_id
            ) ack ON ack.fiscal_document_id = fd.id
            WHERE {$where}
        ";
        $latencyParams = array_merge([$tenantId, $tenantId], $params);
        $latencyStmt = $this->db->prepare($latencySql);
        $latencyStmt->execute($latencyParams);
        $latency = $latencyStmt->fetch() ?: [];

        $queueSql = "
            SELECT
                SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) AS queue_pending,
                SUM(CASE WHEN status = 'RETRY' THEN 1 ELSE 0 END) AS queue_retry,
                SUM(CASE WHEN status = 'PROCESSING' THEN 1 ELSE 0 END) AS queue_processing,
                SUM(CASE WHEN status = 'FAILED' THEN 1 ELSE 0 END) AS queue_failed,
                SUM(CASE WHEN status = 'COMPLETED' THEN 1 ELSE 0 END) AS queue_completed
            FROM jobs_queue
            WHERE queue_name = 'jobs:fiscal-submit'
        ";
        $queueStmt = $this->db->query($queueSql);
        $queue = $queueStmt ? ($queueStmt->fetch() ?: []) : [];

        $dlqSql = "SELECT COUNT(*) AS dlq_total FROM jobs_dlq WHERE queue_name = 'jobs:fiscal-submit'";
        $dlqStmt = $this->db->query($dlqSql);
        $dlq = $dlqStmt ? ($dlqStmt->fetch() ?: []) : [];

        $totalDocs = (int) ($totals['total_docs'] ?? 0);
        $acceptedDocs = (int) ($totals['accepted_docs'] ?? 0);
        $rejectedDocs = (int) ($totals['rejected_docs'] ?? 0);
        $failedDocs = (int) ($totals['failed_docs'] ?? 0);
        $acceptanceRate = $totalDocs > 0 ? round(($acceptedDocs / $totalDocs) * 100, 2) : 0.0;
        $errorRate = $totalDocs > 0 ? round((($rejectedDocs + $failedDocs) / $totalDocs) * 100, 2) : 0.0;

        return [
            'total_docs' => $totalDocs,
            'accepted_docs' => $acceptedDocs,
            'rejected_docs' => $rejectedDocs,
            'failed_docs' => $failedDocs,
            'cancelled_docs' => (int) ($totals['cancelled_docs'] ?? 0),
            'acceptance_rate' => $acceptanceRate,
            'error_rate' => $errorRate,
            'avg_latency_seconds' => isset($latency['avg_latency_seconds']) ? (float) $latency['avg_latency_seconds'] : null,
            'queue' => [
                'pending' => (int) ($queue['queue_pending'] ?? 0),
                'retry' => (int) ($queue['queue_retry'] ?? 0),
                'processing' => (int) ($queue['queue_processing'] ?? 0),
                'failed' => (int) ($queue['queue_failed'] ?? 0),
                'completed' => (int) ($queue['queue_completed'] ?? 0),
            ],
            'dlq_total' => (int) ($dlq['dlq_total'] ?? 0),
        ];
    }

    public function findDocumentById(int $tenantId, int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM fiscal_documents WHERE tenant_id = ? AND id = ? LIMIT 1');
        $stmt->execute([$tenantId, $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function listDocumentsByIds(int $tenantId, array $ids): array
    {
        $clean = [];
        foreach ($ids as $id) {
            $intId = (int) $id;
            if ($intId > 0) {
                $clean[] = $intId;
            }
        }

        if ($clean === []) {
            return [];
        }

        $clean = array_values(array_unique($clean));
        $placeholders = implode(',', array_fill(0, count($clean), '?'));

        $sql = 'SELECT * FROM fiscal_documents WHERE tenant_id = ? AND id IN (' . $placeholders . ') ORDER BY id DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge([$tenantId], $clean));
        return $stmt->fetchAll();
    }

    public function countIssuedToday(int $tenantId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS cnt
             FROM fiscal_documents
             WHERE tenant_id = ?
               AND DATE(created_at) = CURDATE()
               AND status NOT IN ('FAILED', 'REJECTED', 'CANCELLED')"
        );
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch();
        return (int) ($row['cnt'] ?? 0);
    }

    public function countIssuedMonth(int $tenantId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS cnt
             FROM fiscal_documents
             WHERE tenant_id = ?
               AND DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
               AND status NOT IN ('FAILED', 'REJECTED', 'CANCELLED')"
        );
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch();
        return (int) ($row['cnt'] ?? 0);
    }

    public function listEvents(int $tenantId, int $documentId, int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $sql = 'SELECT id, event_type, status, payload_json, created_at
                FROM fiscal_events
                WHERE tenant_id = ? AND fiscal_document_id = ?
                ORDER BY id DESC
                LIMIT ' . $limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$tenantId, $documentId]);
        return $stmt->fetchAll();
    }

    public function listAcks(int $tenantId, int $documentId, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT id, ack_code, ack_message, raw_payload, received_at
                FROM fiscal_acks
                WHERE tenant_id = ? AND fiscal_document_id = ?
                ORDER BY id DESC
                LIMIT ' . $limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$tenantId, $documentId]);
        return $stmt->fetchAll();
    }

    public function markDocumentProcessing(int $tenantId, int $documentId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE fiscal_documents SET status = ?, error_message = NULL, updated_at = NOW() WHERE tenant_id = ? AND id = ?'
        );
        $stmt->execute(['PROCESSING', $tenantId, $documentId]);
    }

    public function markDocumentSent(int $tenantId, int $documentId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE fiscal_documents SET status = ?, error_message = NULL, updated_at = NOW() WHERE tenant_id = ? AND id = ?'
        );
        $stmt->execute(['SENT', $tenantId, $documentId]);
    }

    public function markDocumentAccepted(int $tenantId, int $documentId, string $trackId, array $responsePayload): void
    {
        $responseJson = json_encode($responsePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($responseJson === false) {
            $responseJson = '{}';
        }

        $stmt = $this->db->prepare(
            'UPDATE fiscal_documents
             SET status = ?, track_id = ?, response_payload = ?, error_message = NULL, updated_at = NOW()
             WHERE tenant_id = ? AND id = ?'
        );
        $stmt->execute(['ACCEPTED', $trackId, $responseJson, $tenantId, $documentId]);
    }

    public function markDocumentFailed(int $tenantId, int $documentId, string $error): void
    {
        $stmt = $this->db->prepare(
            'UPDATE fiscal_documents SET status = ?, error_message = ?, updated_at = NOW() WHERE tenant_id = ? AND id = ?'
        );
        $stmt->execute(['FAILED', substr($error, 0, 255), $tenantId, $documentId]);
    }

    public function appendEvent(int $tenantId, int $documentId, string $eventType, string $status, array $payload): void
    {
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payloadJson === false) {
            $payloadJson = '{}';
        }

        $stmt = $this->db->prepare(
            'INSERT INTO fiscal_events (tenant_id, fiscal_document_id, event_type, status, payload_json, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$tenantId, $documentId, $eventType, $status, $payloadJson]);
    }

    public function insertAck(int $tenantId, int $documentId, string $ackCode, string $ackMessage, array $rawPayload): void
    {
        $rawJson = json_encode($rawPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($rawJson === false) {
            $rawJson = '{}';
        }

        $stmt = $this->db->prepare(
            'INSERT INTO fiscal_acks (tenant_id, fiscal_document_id, ack_code, ack_message, raw_payload, received_at)
             VALUES (?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE ack_message = VALUES(ack_message), raw_payload = VALUES(raw_payload), received_at = NOW()'
        );
        $stmt->execute([$tenantId, $documentId, $ackCode, $ackMessage, $rawJson]);
    }

    public function markDocumentFromAck(
        int $tenantId,
        int $documentId,
        string $ackCode,
        string $ackMessage,
        ?string $trackId,
        array $rawPayload
    ): void {
        $normalizedCode = strtoupper(trim($ackCode));
        $acceptedCodes = ['ACCEPTED', 'ACEPTADO', 'OK'];
        $rejectedCodes = ['REJECTED', 'RECHAZADO', 'INVALID', 'DENIED'];

        if (in_array($normalizedCode, $acceptedCodes, true)) {
            $status = 'ACCEPTED';
        } elseif (in_array($normalizedCode, $rejectedCodes, true)) {
            $status = 'REJECTED';
        } else {
            $status = 'FAILED';
        }

        $responseJson = json_encode($rawPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($responseJson === false) {
            $responseJson = '{}';
        }

        $stmt = $this->db->prepare(
            'UPDATE fiscal_documents
             SET status = ?, track_id = COALESCE(?, track_id), response_payload = ?, error_message = ?, updated_at = NOW()
             WHERE tenant_id = ? AND id = ?'
        );
        $stmt->execute([
            $status,
            $trackId,
            $responseJson,
            in_array($status, ['FAILED', 'REJECTED'], true) ? substr($ackMessage, 0, 255) : null,
            $tenantId,
            $documentId,
        ]);
    }

    public function cancelDocumentByInvoice(int $tenantId, int $invoiceId, string $reason): ?int
    {
        $stmt = $this->db->prepare(
            "SELECT id
             FROM fiscal_documents
             WHERE tenant_id = ?
               AND invoice_id = ?
               AND status IN ('PENDING', 'PROCESSING', 'SENT')
             ORDER BY id DESC
             LIMIT 1"
        );
        $stmt->execute([$tenantId, $invoiceId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $documentId = (int) $row['id'];
        $update = $this->db->prepare(
            "UPDATE fiscal_documents
             SET status = 'CANCELLED',
                 error_message = ?,
                 updated_at = NOW()
             WHERE tenant_id = ? AND id = ?"
        );
        $update->execute([substr($reason, 0, 255), $tenantId, $documentId]);

        $this->appendEvent($tenantId, $documentId, 'CANCELLED', 'CANCELLED', [
            'reason' => $reason,
            'cancelled_at' => gmdate('c'),
        ]);

        return $documentId;
    }
}

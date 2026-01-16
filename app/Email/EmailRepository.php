<?php

namespace App\Email;

use PDO;

class EmailRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(int $tenantId, string $to, string $subject, string $body): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO email_outbox (tenant_id, email_to, subject, body, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$tenantId, $to, $subject, $body, 'PENDING']);

        return (int) $this->db->lastInsertId();
    }

    public function listPending(): array
    {
        $stmt = $this->db->prepare('SELECT * FROM email_outbox WHERE status = ? ORDER BY id ASC');
        $stmt->execute(['PENDING']);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM email_outbox WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function markSent(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE email_outbox SET status = ?, sent_at = NOW() WHERE id = ?');
        $stmt->execute(['SENT', $id]);
    }
}

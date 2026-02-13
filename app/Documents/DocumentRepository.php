<?php

namespace App\Documents;

use PDO;

class DocumentRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function createInvoicePdf(int $tenantId, int $invoiceId, string $status): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO document_files (tenant_id, doc_type, ref_id, status, created_at) VALUES (?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$tenantId, 'INVOICE_PDF', $invoiceId, $status]);

        return (int) $this->db->lastInsertId();
    }

    public function findInvoicePdf(int $tenantId, int $invoiceId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM document_files WHERE tenant_id = ? AND doc_type = ? AND ref_id = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$tenantId, 'INVOICE_PDF', $invoiceId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM document_files WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function markReady(int $id, string $path): void
    {
        $stmt = $this->db->prepare('UPDATE document_files SET status = ?, file_path = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute(['READY', $path, $id]);
    }

    public function listPending(): array
    {
        $stmt = $this->db->prepare('SELECT * FROM document_files WHERE status = ? ORDER BY id ASC');
        $stmt->execute(['PENDING']);
        return $stmt->fetchAll();
    }
}

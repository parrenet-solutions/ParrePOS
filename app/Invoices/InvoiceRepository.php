<?php

namespace App\Invoices;

use PDO;

class InvoiceRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function createDraft(int $tenantId, array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO invoices (tenant_id, customer_id, status, subtotal, tax_total, total, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $tenantId,
            $data['customer_id'],
            'DRAFT',
            $data['subtotal'],
            $data['tax_total'],
            $data['total'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function addItems(int $tenantId, int $invoiceId, array $items): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO invoice_items (tenant_id, invoice_id, name, qty, unit_price, tax_rate, discount, line_total, tax_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        foreach ($items as $item) {
            $stmt->execute([
                $tenantId,
                $invoiceId,
                $item['name'],
                $item['qty'],
                $item['unit_price'],
                $item['tax_rate'],
                $item['discount'],
                $item['line_total'],
                $item['tax_amount'],
            ]);
        }
    }

    public function findById(int $tenantId, int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM invoices WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function list(int $tenantId, array $filters): array
    {
        $sql = 'SELECT * FROM invoices WHERE tenant_id = ?';
        $params = [$tenantId];

        if (!empty($filters['status'])) {
            $sql .= ' AND status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['customer_id'])) {
            $sql .= ' AND customer_id = ?';
            $params[] = $filters['customer_id'];
        }

        $sql .= ' ORDER BY id DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getItems(int $tenantId, int $invoiceId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM invoice_items WHERE invoice_id = ? AND tenant_id = ? ORDER BY id ASC');
        $stmt->execute([$invoiceId, $tenantId]);
        return $stmt->fetchAll();
    }

    public function issue(int $tenantId, int $invoiceId, string $series, int $sequence, string $invoiceNumber): void
    {
        $stmt = $this->db->prepare(
            'UPDATE invoices SET status = ?, series = ?, sequence = ?, invoice_number = ?, issued_at = NOW() WHERE id = ? AND tenant_id = ?'
        );
        $stmt->execute(['ISSUED', $series, $sequence, $invoiceNumber, $invoiceId, $tenantId]);
    }

    public function void(int $tenantId, int $invoiceId, string $reason): void
    {
        $stmt = $this->db->prepare('UPDATE invoices SET status = ?, void_reason = ?, voided_at = NOW() WHERE id = ? AND tenant_id = ?');
        $stmt->execute(['VOID', $reason, $invoiceId, $tenantId]);
    }

    public function nextSequence(int $tenantId, string $series): int
    {
        $stmt = $this->db->prepare('SELECT current_number FROM invoice_sequences WHERE tenant_id = ? AND series = ? LIMIT 1 FOR UPDATE');
        $stmt->execute([$tenantId, $series]);
        $row = $stmt->fetch();

        if (!$row) {
            $this->db->prepare('INSERT INTO invoice_sequences (tenant_id, series, current_number) VALUES (?, ?, ?)')
                ->execute([$tenantId, $series, 1]);
            return 1;
        }

        $next = (int) $row['current_number'] + 1;
        $this->db->prepare('UPDATE invoice_sequences SET current_number = ? WHERE tenant_id = ? AND series = ?')
            ->execute([$next, $tenantId, $series]);

        return $next;
    }
}

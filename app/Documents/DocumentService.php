<?php

namespace App\Documents;

use App\Invoices\InvoiceRepository;

class DocumentService
{
    private DocumentRepository $documentRepository;
    private InvoiceRepository $invoiceRepository;
    private PdfService $pdfService;

    public function __construct(
        DocumentRepository $documentRepository,
        InvoiceRepository $invoiceRepository,
        PdfService $pdfService
    ) {
        $this->documentRepository = $documentRepository;
        $this->invoiceRepository = $invoiceRepository;
        $this->pdfService = $pdfService;
    }

    public function generateInvoicePdf(int $documentId): void
    {
        $doc = $this->documentRepository->findById($documentId);
        if (!$doc || $doc['doc_type'] !== 'INVOICE_PDF') {
            return;
        }

        $tenantId = (int) $doc['tenant_id'];
        $invoiceId = (int) $doc['ref_id'];
        $invoice = $this->invoiceRepository->findById($tenantId, $invoiceId);
        if (!$invoice) {
            return;
        }

        $items = $this->invoiceRepository->getItems($tenantId, $invoiceId);
        $pdf = $this->pdfService->generateInvoicePdf($invoiceId, $invoice, $items);

        $dir = dirname(__DIR__, 2) . '/storage/pdf';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $path = $dir . '/invoice-' . $invoiceId . '.pdf';
        file_put_contents($path, $pdf);
        $this->documentRepository->markReady($documentId, $path);
    }
}

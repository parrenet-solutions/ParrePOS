<?php

namespace App\Invoices;

use App\Audit\AuditLogger;
use App\Core\Request;
use App\Customers\CustomerRepository;
use App\Documents\DocumentRepository;
use App\Documents\DocumentService;
use App\Email\EmailRepository;
use App\Jobs\JobQueue;
use App\Shared\Exceptions\HttpException;
use App\Shared\Helpers;
use PDO;

class InvoiceController
{
    private InvoiceRepository $repository;
    private InvoiceService $service;
    private CustomerRepository $customerRepository;
    private DocumentRepository $documentRepository;
    private DocumentService $documentService;
    private EmailRepository $emailRepository;
    private JobQueue $queue;
    private AuditLogger $audit;
    private PDO $db;

    public function __construct(
        InvoiceRepository $repository,
        InvoiceService $service,
        CustomerRepository $customerRepository,
        DocumentRepository $documentRepository,
        DocumentService $documentService,
        EmailRepository $emailRepository,
        JobQueue $queue,
        AuditLogger $audit,
        PDO $db
    ) {
        $this->repository = $repository;
        $this->service = $service;
        $this->customerRepository = $customerRepository;
        $this->documentRepository = $documentRepository;
        $this->documentService = $documentService;
        $this->emailRepository = $emailRepository;
        $this->queue = $queue;
        $this->audit = $audit;
        $this->db = $db;
    }

    public function create(Request $request): array
    {
        $payload = $request->getJson();
        if (!is_array($payload)) {
            throw new HttpException(400, 'INVALID_BODY', 'Se requiere JSON');
        }

        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $data = $this->service->validateCreate($payload);

        $customer = $this->customerRepository->findById($tenantId, $data['customer_id']);
        if (!$customer) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'Cliente inválido');
        }

        $totals = $this->service->totals($data['items']);

        $invoiceId = Helpers::transaction($this->db, function () use ($tenantId, $data, $totals) {
            $id = $this->repository->createDraft($tenantId, [
                'customer_id' => $data['customer_id'],
                'subtotal' => $totals['subtotal'],
                'tax_total' => $totals['tax_total'],
                'total' => $totals['total'],
            ]);
            $this->repository->addItems($tenantId, $id, $data['items']);
            return $id;
        });

        $this->audit->log($tenantId, (int) $request->getAttribute('user_id', 0), 'invoices.create', ['invoice_id' => $invoiceId]);

        return ['status' => 201, 'data' => ['id' => $invoiceId]];
    }

    public function list(Request $request): array
    {
        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $filters = [
            'status' => $request->getQuery()['status'] ?? null,
            'customer_id' => $request->getQuery()['customer_id'] ?? null,
        ];

        return ['status' => 200, 'data' => $this->repository->list($tenantId, $filters)];
    }

    public function get(Request $request): array
    {
        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $id = (int) $request->getParam('id');

        $invoice = $this->repository->findById($tenantId, $id);
        if (!$invoice) {
            throw new HttpException(404, 'NOT_FOUND', 'Factura no encontrada');
        }

        $items = $this->repository->getItems($tenantId, $id);
        $invoice['items'] = $items;

        return ['status' => 200, 'data' => $invoice];
    }

    public function issue(Request $request): array
    {
        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $id = (int) $request->getParam('id');

        $invoice = $this->repository->findById($tenantId, $id);
        if (!$invoice) {
            throw new HttpException(404, 'NOT_FOUND', 'Factura no encontrada');
        }

        if ($invoice['status'] !== 'DRAFT') {
            throw new HttpException(409, 'INVALID_STATE', 'Factura no está en borrador');
        }

        $series = 'A';

        Helpers::transaction($this->db, function () use ($tenantId, $id, $series) {
            $sequence = $this->repository->nextSequence($tenantId, $series);
            $invoiceNumber = $this->service->formatInvoiceNumber($series, $sequence);
            $this->repository->issue($tenantId, $id, $series, $sequence, $invoiceNumber);
        });

        $this->audit->log($tenantId, (int) $request->getAttribute('user_id', 0), 'invoices.issue', ['invoice_id' => $id]);

        $docId = $this->documentRepository->createInvoicePdf($tenantId, $id, 'PENDING');
        if ($this->queue->isAvailable()) {
            $this->queue->push('jobs:pdf', ['document_id' => $docId], 'pdf:document:' . $docId, 5);
        } else {
            $this->documentService->generateInvoicePdf($docId);
        }

        return ['status' => 200, 'data' => ['id' => $id, 'status' => 'ISSUED']];
    }

    public function void(Request $request): array
    {
        $payload = $request->getJson();
        if (!is_array($payload)) {
            throw new HttpException(400, 'INVALID_BODY', 'Se requiere JSON');
        }

        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $id = (int) $request->getParam('id');
        $reason = trim((string) ($payload['reason'] ?? ''));

        if ($reason === '') {
            throw new HttpException(422, 'VALIDATION_ERROR', 'reason requerido');
        }

        $invoice = $this->repository->findById($tenantId, $id);
        if (!$invoice) {
            throw new HttpException(404, 'NOT_FOUND', 'Factura no encontrada');
        }

        if ($invoice['status'] !== 'ISSUED') {
            throw new HttpException(409, 'INVALID_STATE', 'Factura no está emitida');
        }

        $this->repository->void($tenantId, $id, $reason);
        $this->audit->log($tenantId, (int) $request->getAttribute('user_id', 0), 'invoices.void', ['invoice_id' => $id]);

        return ['status' => 200, 'data' => ['id' => $id, 'status' => 'VOID']];
    }

    public function pdf(Request $request): array
    {
        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $id = (int) $request->getParam('id');

        $doc = $this->documentRepository->findInvoicePdf($tenantId, $id);
        if (!$doc) {
            return ['status' => 200, 'data' => ['status' => 'PENDING']];
        }

        if ($doc['status'] !== 'READY') {
            return ['status' => 200, 'data' => ['status' => $doc['status']]];
        }

        if (($request->getQuery()['stream'] ?? null) === '1') {
            $path = $doc['file_path'];
            if (!$path || !is_file($path)) {
                throw new HttpException(404, 'NOT_FOUND', 'PDF no encontrado');
            }
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="invoice-' . $id . '.pdf"');
            readfile($path);
            exit;
        }

        return [
            'status' => 200,
            'data' => [
                'status' => 'READY',
                'document_id' => $doc['id'],
                'url' => '/api/v1/invoices/' . $id . '/pdf?stream=1',
            ],
        ];
    }

    public function sendEmail(Request $request): array
    {
        $payload = $request->getJson();
        if (!is_array($payload)) {
            throw new HttpException(400, 'INVALID_BODY', 'Se requiere JSON');
        }

        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $id = (int) $request->getParam('id');
        $to = trim((string) ($payload['to'] ?? ''));

        if ($to === '') {
            throw new HttpException(422, 'VALIDATION_ERROR', 'to requerido');
        }

        $invoice = $this->repository->findById($tenantId, $id);
        if (!$invoice) {
            throw new HttpException(404, 'NOT_FOUND', 'Factura no encontrada');
        }

        if ($invoice['status'] !== 'ISSUED') {
            throw new HttpException(409, 'INVALID_STATE', 'Factura no está emitida');
        }

        $subject = 'Factura ' . ($invoice['invoice_number'] ?? $id);
        $body = 'Adjunto su factura. Gracias por su preferencia.';

        $emailId = $this->emailRepository->create($tenantId, $to, $subject, $body);
        if ($this->queue->isAvailable()) {
            $this->queue->push('jobs:email', ['email_id' => $emailId], 'email:' . $emailId, 5);
        }

        return ['status' => 200, 'data' => ['email_id' => $emailId, 'status' => 'PENDING']];
    }

}

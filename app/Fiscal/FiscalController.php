<?php

namespace App\Fiscal;

use App\Audit\AuditLogger;
use App\Bootstrap\Config;
use App\Core\Request;
use App\Core\Security\RateLimiter;
use App\Jobs\JobQueue;
use App\Settings\TenantSettingsRepository;
use App\Shared\Exceptions\HttpException;

class FiscalController
{
    private FiscalRepository $repository;
    private FiscalService $service;
    private TenantSettingsRepository $tenantSettingsRepository;
    private JobQueue $queue;
    private AuditLogger $audit;
    private RateLimiter $rateLimiter;
    private FiscalAlertService $alerts;

    public function __construct(
        FiscalRepository $repository,
        FiscalService $service,
        TenantSettingsRepository $tenantSettingsRepository,
        JobQueue $queue,
        AuditLogger $audit,
        RateLimiter $rateLimiter,
        FiscalAlertService $alerts
    ) {
        $this->repository = $repository;
        $this->service = $service;
        $this->tenantSettingsRepository = $tenantSettingsRepository;
        $this->queue = $queue;
        $this->audit = $audit;
        $this->rateLimiter = $rateLimiter;
        $this->alerts = $alerts;
    }

    public function status(Request $request): array
    {
        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $fiscal = $this->sanitizeFiscalOutput($this->tenantSettingsRepository->getFiscalConfig($tenantId));
        $profile = $this->repository->getProfile($tenantId);

        $current = $this->repository->getCurrentFiscalSequence(
            $tenantId,
            (string) ($fiscal['ncf_type'] ?? 'B01'),
            (string) ($fiscal['series'] ?? 'B01')
        );

        return [
            'status' => 200,
            'data' => [
                'fiscal' => $fiscal,
                'profile' => $profile,
                'sequence' => [
                    'current' => $current,
                    'next' => $current + 1,
                ],
            ],
        ];
    }

    public function updateConfig(Request $request): array
    {
        $payload = $request->getJson();
        if (!is_array($payload)) {
            throw new HttpException(400, 'INVALID_BODY', 'Se requiere JSON');
        }

        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $userId = (int) $request->getAttribute('user_id', 0);
        $this->throttle('fiscal:manage:' . $tenantId . ':' . $userId, 60, 60);

        $fiscal = $this->service->validateAndNormalizeConfig($payload);
        $profile = $this->service->validateAndNormalizeProfile($payload);

        $settings = $this->repository->getTenantSettingsRaw($tenantId);
        if (!isset($settings['modules']) || !is_array($settings['modules'])) {
            $settings['modules'] = [];
        }

        if (!isset($settings['modules']['fiscal']) || !is_array($settings['modules']['fiscal'])) {
            $settings['modules']['fiscal'] = [];
        }

        $settings['modules']['fiscal']['enabled'] = $fiscal['enabled'] === true;
        $settings['fiscal'] = $fiscal;

        $this->repository->updateTenantSettingsRaw($tenantId, $settings);

        if ($profile['legal_name'] !== '' || $profile['rnc'] !== '') {
            $profile['dgii_registered'] = $fiscal['dgii_registered'] === true;
            $this->repository->upsertProfile($tenantId, $profile);
        }

        $this->audit->log($tenantId, $userId, 'fiscal.config.update', [
            'enabled' => $fiscal['enabled'],
            'dgii_registered' => $fiscal['dgii_registered'],
            'ncf_type' => $fiscal['ncf_type'],
            'series' => $fiscal['series'],
        ]);

        return [
            'status' => 200,
            'data' => [
                'fiscal' => $this->sanitizeFiscalOutput($fiscal),
                'profile' => $this->repository->getProfile($tenantId),
            ],
        ];
    }

    public function listDocuments(Request $request): array
    {
        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $query = $request->getQuery();
        $status = isset($query['status']) ? strtoupper(trim((string) $query['status'])) : null;
        $limit = isset($query['limit']) ? (int) $query['limit'] : 50;

        return [
            'status' => 200,
            'data' => $this->repository->listDocuments($tenantId, $status, $limit),
        ];
    }

    public function retryDocument(Request $request): array
    {
        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $userId = (int) $request->getAttribute('user_id', 0);
        $this->throttle('fiscal:retry:' . $tenantId . ':' . $userId, 60, 60);

        $id = (int) $request->getParam('id');
        if ($id <= 0) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'id inválido');
        }

        $document = $this->repository->findDocumentById($tenantId, $id);
        if (!$document) {
            throw new HttpException(404, 'NOT_FOUND', 'Documento fiscal no encontrado');
        }

        $jobId = $this->queue->push(
            'jobs:fiscal-submit',
            ['tenant_id' => $tenantId, 'fiscal_document_id' => $id],
            'fiscal:document:' . $id,
            5
        );

        $this->repository->appendEvent($tenantId, $id, 'RETRY_REQUESTED', 'PENDING', [
            'job_id' => $jobId,
            'requested_at' => gmdate('c'),
        ]);
        $this->audit->log($tenantId, $userId, 'fiscal.document.retry', [
            'fiscal_document_id' => $id,
            'job_id' => $jobId,
        ]);

        return [
            'status' => 202,
            'data' => [
                'document_id' => $id,
                'job_id' => $jobId,
                'status' => 'QUEUED',
            ],
        ];
    }

    public function getDocument(Request $request): array
    {
        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $id = (int) $request->getParam('id');
        if ($id <= 0) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'id inválido');
        }

        $document = $this->repository->findDocumentById($tenantId, $id);
        if (!$document) {
            throw new HttpException(404, 'NOT_FOUND', 'Documento fiscal no encontrado');
        }

        return ['status' => 200, 'data' => $document];
    }

    public function searchDocuments(Request $request): array
    {
        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $query = $request->getQuery();
        $limit = isset($query['limit']) ? (int) $query['limit'] : 100;

        $filters = [
            'status' => $query['status'] ?? '',
            'ncf' => $query['ncf'] ?? '',
            'date_from' => $query['date_from'] ?? '',
            'date_to' => $query['date_to'] ?? '',
        ];

        return [
            'status' => 200,
            'data' => $this->repository->searchDocuments($tenantId, $filters, $limit),
        ];
    }

    public function summary(Request $request): array
    {
        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $query = $request->getQuery();
        $dateFrom = isset($query['date_from']) ? (string) $query['date_from'] : null;
        $dateTo = isset($query['date_to']) ? (string) $query['date_to'] : null;

        return [
            'status' => 200,
            'data' => $this->repository->summaryByStatus($tenantId, $dateFrom, $dateTo),
        ];
    }

    public function metrics(Request $request): array
    {
        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $query = $request->getQuery();
        $dateFrom = isset($query['date_from']) ? (string) $query['date_from'] : null;
        $dateTo = isset($query['date_to']) ? (string) $query['date_to'] : null;

        return [
            'status' => 200,
            'data' => $this->repository->metrics($tenantId, $dateFrom, $dateTo),
        ];
    }

    public function listDocumentEvents(Request $request): array
    {
        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $id = (int) $request->getParam('id');
        $limit = isset($request->getQuery()['limit']) ? (int) $request->getQuery()['limit'] : 100;

        $document = $this->repository->findDocumentById($tenantId, $id);
        if (!$document) {
            throw new HttpException(404, 'NOT_FOUND', 'Documento fiscal no encontrado');
        }

        return [
            'status' => 200,
            'data' => $this->repository->listEvents($tenantId, $id, $limit),
        ];
    }

    public function listDocumentAcks(Request $request): array
    {
        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $id = (int) $request->getParam('id');
        $limit = isset($request->getQuery()['limit']) ? (int) $request->getQuery()['limit'] : 50;

        $document = $this->repository->findDocumentById($tenantId, $id);
        if (!$document) {
            throw new HttpException(404, 'NOT_FOUND', 'Documento fiscal no encontrado');
        }

        return [
            'status' => 200,
            'data' => $this->repository->listAcks($tenantId, $id, $limit),
        ];
    }

    public function webhookAck(Request $request): array
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $this->throttle('fiscal:webhook:' . $ip, 120, 60);

        $incomingKey = trim((string) $request->getHeader('X-Fiscal-Webhook-Key'));
        $expectedKey = (string) Config::get('FISCAL_WEBHOOK_KEY', 'dev-fiscal-key');
        if ($incomingKey === '' || !hash_equals($expectedKey, $incomingKey)) {
            throw new HttpException(401, 'UNAUTHORIZED', 'Webhook key inválida');
        }

        $payload = $request->getJson();
        if (!is_array($payload)) {
            throw new HttpException(400, 'INVALID_BODY', 'Se requiere JSON');
        }

        $tenantId = (int) ($payload['tenant_id'] ?? 0);
        $documentId = (int) ($payload['fiscal_document_id'] ?? 0);
        $ackCode = strtoupper(trim((string) ($payload['ack_code'] ?? '')));
        $ackMessage = trim((string) ($payload['ack_message'] ?? ''));
        $trackId = isset($payload['track_id']) ? trim((string) $payload['track_id']) : null;

        if ($tenantId <= 0 || $documentId <= 0 || $ackCode === '') {
            throw new HttpException(422, 'VALIDATION_ERROR', 'tenant_id, fiscal_document_id y ack_code requeridos');
        }

        $document = $this->repository->findDocumentById($tenantId, $documentId);
        if (!$document) {
            throw new HttpException(404, 'NOT_FOUND', 'Documento fiscal no encontrado');
        }

        $this->repository->insertAck($tenantId, $documentId, $ackCode, $ackMessage, $payload);
        $this->repository->markDocumentFromAck($tenantId, $documentId, $ackCode, $ackMessage, $trackId, $payload);
        $this->repository->appendEvent($tenantId, $documentId, 'WEBHOOK_ACK', $ackCode, $payload);
        $this->audit->log($tenantId, 0, 'fiscal.webhook.ack', [
            'fiscal_document_id' => $documentId,
            'ack_code' => $ackCode,
            'track_id' => $trackId,
        ]);
        if (in_array($ackCode, ['REJECTED', 'RECHAZADO', 'FAILED', 'INVALID', 'DENIED'], true)) {
            $this->alerts->notifyFailure($tenantId, $documentId, $ackCode === 'FAILED' ? 'FAILED' : 'REJECTED', $ackMessage, [
                'source' => 'webhook',
                'track_id' => $trackId,
            ]);
        }

        return [
            'status' => 200,
            'data' => [
                'fiscal_document_id' => $documentId,
                'ack_code' => $ackCode,
                'processed' => true,
            ],
        ];
    }

    public function retryBulk(Request $request): array
    {
        $payload = $request->getJson();
        if (!is_array($payload)) {
            throw new HttpException(400, 'INVALID_BODY', 'Se requiere JSON');
        }

        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $userId = (int) $request->getAttribute('user_id', 0);
        $this->throttle('fiscal:retry-bulk:' . $tenantId . ':' . $userId, 30, 60);

        $ids = $payload['ids'] ?? null;
        if (!is_array($ids) || $ids === []) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'ids requerido');
        }

        $documents = $this->repository->listDocumentsByIds($tenantId, $ids);
        if ($documents === []) {
            throw new HttpException(404, 'NOT_FOUND', 'No se encontraron documentos');
        }

        $retryableStatuses = ['FAILED', 'REJECTED'];
        $queued = [];
        $skipped = [];

        foreach ($documents as $document) {
            $docId = (int) ($document['id'] ?? 0);
            $status = strtoupper((string) ($document['status'] ?? ''));

            if ($docId <= 0) {
                continue;
            }

            if (!in_array($status, $retryableStatuses, true)) {
                $skipped[] = ['id' => $docId, 'status' => $status, 'reason' => 'STATUS_NOT_RETRYABLE'];
                continue;
            }

            $jobId = $this->queue->push(
                'jobs:fiscal-submit',
                ['tenant_id' => $tenantId, 'fiscal_document_id' => $docId],
                'fiscal:document:' . $docId,
                5
            );

            $this->repository->appendEvent($tenantId, $docId, 'RETRY_BULK_REQUESTED', 'PENDING', [
                'job_id' => $jobId,
                'requested_at' => gmdate('c'),
            ]);

            $queued[] = ['id' => $docId, 'job_id' => $jobId];
        }

        $this->audit->log($tenantId, $userId, 'fiscal.documents.retry_bulk', [
            'requested' => count($ids),
            'queued' => count($queued),
            'skipped' => count($skipped),
        ]);

        return [
            'status' => 202,
            'data' => [
                'requested' => count($ids),
                'queued' => $queued,
                'skipped' => $skipped,
            ],
        ];
    }

    private function throttle(string $key, int $limit, int $ttlSeconds): void
    {
        if ($this->rateLimiter->tooManyAttempts($key, $limit, $ttlSeconds)) {
            throw new HttpException(429, 'RATE_LIMIT', 'Demasiadas solicitudes');
        }
    }

    private function sanitizeFiscalOutput(array $fiscal): array
    {
        unset($fiscal['signing_secret']);
        return $fiscal;
    }
}

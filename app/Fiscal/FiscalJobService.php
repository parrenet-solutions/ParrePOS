<?php

namespace App\Fiscal;

use App\Bootstrap\Config;
use App\Settings\TenantSettingsRepository;
use RuntimeException;

class FiscalJobService
{
    private FiscalRepository $repository;
    private TenantSettingsRepository $settingsRepository;
    private FiscalProviderFactory $providerFactory;
    private FiscalAlertService $alerts;

    public function __construct(
        FiscalRepository $repository,
        TenantSettingsRepository $settingsRepository,
        FiscalProviderFactory $providerFactory,
        FiscalAlertService $alerts
    )
    {
        $this->repository = $repository;
        $this->settingsRepository = $settingsRepository;
        $this->providerFactory = $providerFactory;
        $this->alerts = $alerts;
    }

    public function submitDocument(int $tenantId, int $fiscalDocumentId): void
    {
        $document = $this->repository->findDocumentById($tenantId, $fiscalDocumentId);
        if (!$document) {
            throw new RuntimeException('Documento fiscal no existe');
        }

        try {
            $fiscal = $this->settingsRepository->getFiscalConfig($tenantId);
            if (($fiscal['enabled'] ?? false) !== true) {
                throw new RuntimeException('[NON_RETRYABLE] Fiscal deshabilitado para tenant');
            }

            if (($fiscal['dgii_registered'] ?? false) !== true) {
                throw new RuntimeException('[NON_RETRYABLE] Tenant no registrado en DGII');
            }

            $requestPayload = $this->decodeRequestPayload($document);
            $provider = $this->providerFactory->make($fiscal);

            $submitPayload = [
                'tenant_id' => $tenantId,
                'fiscal_document_id' => $fiscalDocumentId,
                'invoice_id' => (int) ($document['invoice_id'] ?? 0),
                'ncf' => (string) ($document['ncf'] ?? ''),
                'ncf_type' => (string) ($document['ncf_type'] ?? ''),
                'series' => (string) ($document['series'] ?? ''),
                'sequence' => (int) ($document['sequence'] ?? 0),
                'payload' => $requestPayload,
                'meta' => ['submitted_at' => gmdate('c')],
            ];

            $canonical = json_encode($submitPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($canonical === false) {
                throw new RuntimeException('No se pudo serializar payload de envio fiscal');
            }

            $secret = $this->resolveSigningSecret($fiscal);
            $requestHash = hash('sha256', $canonical);
            $signature = hash_hmac('sha256', $canonical, $secret);

            $providerName = strtoupper(trim((string) ($fiscal['provider'] ?? 'MOCK')));
            $providerUrl = trim((string) ($fiscal['provider_url'] ?? ''));

            $this->repository->markDocumentProcessing($tenantId, $fiscalDocumentId);
            $this->repository->appendEvent($tenantId, $fiscalDocumentId, 'SUBMIT_REQUESTED', 'PROCESSING', [
                'requested_at' => gmdate('c'),
                'provider' => $providerName,
                'provider_url' => $providerUrl,
                'request_hash' => $requestHash,
                'signature_prefix' => substr($signature, 0, 12),
            ]);

            $result = $provider->submit($tenantId, $fiscalDocumentId, $submitPayload, [
                'provider' => $providerName,
                'provider_url' => $providerUrl,
                'request_hash' => $requestHash,
                'signature' => $signature,
            ]);
            $this->repository->markDocumentSent($tenantId, $fiscalDocumentId);
            $this->repository->appendEvent($tenantId, $fiscalDocumentId, 'SUBMIT_SENT', 'SENT', [
                'sent_at' => gmdate('c'),
                'provider' => $providerName,
            ]);

            $ackCode = strtoupper(trim((string) ($result['ack_code'] ?? 'FAILED')));
            $ackMessage = (string) ($result['ack_message'] ?? 'Respuesta fiscal sin mensaje');
            $trackId = isset($result['track_id']) && $result['track_id'] !== '' ? (string) $result['track_id'] : null;
            $retryable = (bool) ($result['retryable'] ?? false);
            $responsePayload = is_array($result['response_payload'] ?? null) ? $result['response_payload'] : [];
            $responseHash = hash(
                'sha256',
                json_encode($responsePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}'
            );

            $ackRaw = [
                'provider' => $providerName,
                'request_hash' => $requestHash,
                'response_hash' => $responseHash,
                'response_payload' => $responsePayload,
            ];

            $this->repository->insertAck($tenantId, $fiscalDocumentId, $ackCode, $ackMessage, $ackRaw);
            $this->repository->markDocumentFromAck($tenantId, $fiscalDocumentId, $ackCode, $ackMessage, $trackId, $ackRaw);
            $this->repository->appendEvent($tenantId, $fiscalDocumentId, 'ACK_RECEIVED', $ackCode, [
                'provider' => $providerName,
                'ack_code' => $ackCode,
                'ack_message' => $ackMessage,
                'track_id' => $trackId,
                'retryable' => $retryable,
                'response_hash' => $responseHash,
            ]);

            if ($ackCode === 'REJECTED' || $ackCode === 'RECHAZADO' || $ackCode === 'INVALID' || $ackCode === 'DENIED') {
                $this->alerts->notifyFailure($tenantId, $fiscalDocumentId, 'REJECTED', $ackMessage, [
                    'source' => 'provider',
                    'provider' => $providerName,
                    'track_id' => $trackId,
                ]);
            }

            if ($retryable) {
                throw new RuntimeException('[RETRYABLE] Provider fiscal retorno retryable=' . $ackCode . ': ' . $ackMessage);
            }

            if (in_array($ackCode, ['ACCEPTED', 'ACEPTADO', 'OK', 'REJECTED', 'RECHAZADO', 'INVALID', 'DENIED'], true)) {
                return;
            }

            throw new RuntimeException('[NON_RETRYABLE] Provider fiscal respondió ack no reconocido: ' . $ackCode);
        } catch (\Throwable $e) {
            $error = $this->normalizeErrorByPolicy($e);
            $this->repository->markDocumentFailed($tenantId, $fiscalDocumentId, $error->getMessage());
            $this->repository->appendEvent($tenantId, $fiscalDocumentId, 'SUBMIT_FAILED', 'FAILED', [
                'error' => $error->getMessage(),
                'failed_at' => gmdate('c'),
            ]);
            $this->alerts->notifyFailure($tenantId, $fiscalDocumentId, 'FAILED', $error->getMessage(), [
                'source' => 'worker',
            ]);
            throw $error;
        }
    }

    private function decodeRequestPayload(array $document): array
    {
        $raw = (string) ($document['request_payload'] ?? '');
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function resolveSigningSecret(array $fiscal): string
    {
        $tenantSecret = trim((string) ($fiscal['signing_secret'] ?? ''));
        if ($tenantSecret !== '') {
            return $tenantSecret;
        }

        $globalSecret = trim((string) Config::get('FISCAL_SIGNING_SECRET', ''));
        if ($globalSecret !== '') {
            return $globalSecret;
        }

        return 'dev-fiscal-signing-secret';
    }

    private function normalizeErrorByPolicy(\Throwable $e): RuntimeException
    {
        $message = trim($e->getMessage());
        if (str_starts_with($message, '[RETRYABLE]') || str_starts_with($message, '[NON_RETRYABLE]')) {
            return new RuntimeException($message);
        }

        $retryableHints = [
            'DGII no disponible',
            'error servidor HTTP 5',
            'timed out',
            'timeout',
            'Connection refused',
            'No route to host',
        ];

        foreach ($retryableHints as $hint) {
            if (stripos($message, $hint) !== false) {
                return new RuntimeException('[RETRYABLE] ' . $message);
            }
        }

        return new RuntimeException('[NON_RETRYABLE] ' . $message);
    }
}

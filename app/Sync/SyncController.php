<?php

namespace App\Sync;

use App\Audit\AuditLogger;
use App\Core\Request;
use App\Core\Security\RateLimiter;
use App\Pos\PosSaleController;
use App\Pos\CashMovementController;
use App\Shared\Exceptions\HttpException;
use App\Sync\SyncStatusRepository;

class SyncController
{
    private SyncRepository $repository;
    private PosSaleController $posSaleController;
    private CashMovementController $cashMovementController;
    private RateLimiter $rateLimiter;
    private AuditLogger $audit;
    private SyncStatusRepository $statusRepository;

    public function __construct(
        SyncRepository $repository,
        PosSaleController $posSaleController,
        CashMovementController $cashMovementController,
        RateLimiter $rateLimiter,
        AuditLogger $audit,
        SyncStatusRepository $statusRepository
    ) {
        $this->repository = $repository;
        $this->posSaleController = $posSaleController;
        $this->cashMovementController = $cashMovementController;
        $this->rateLimiter = $rateLimiter;
        $this->audit = $audit;
        $this->statusRepository = $statusRepository;
    }

    public function ingest(Request $request): array
    {
        $payload = $request->getJson();
        if (!is_array($payload) || !isset($payload['events']) || !is_array($payload['events'])) {
            throw new HttpException(400, 'INVALID_BODY', 'Se requiere JSON con events');
        }

        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $userId = (int) $request->getAttribute('user_id', 0);
        $deviceId = trim((string) ($payload['device_id'] ?? ''));
        $eventsCount = count($payload['events']);

        $rateKey = 'sync:' . ($deviceId !== '' ? $deviceId : ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        if ($this->rateLimiter->tooManyAttempts($rateKey, 60, 60)) {
            throw new HttpException(429, 'RATE_LIMIT', 'Demasiadas solicitudes');
        }

        $this->audit->log($tenantId, $userId, 'sync.ingest.request', [
            'device_id' => $deviceId,
            'events_count' => $eventsCount,
        ]);

        $responses = [];
        foreach ($payload['events'] as $event) {
            $responses[] = $this->applyEvent($tenantId, $userId, $event, $request);
        }

        $summary = ['applied' => 0, 'duplicate' => 0, 'failed' => 0];
        foreach ($responses as $response) {
            $status = (string) ($response['status'] ?? 'failed');
            if (!isset($summary[$status])) {
                $status = 'failed';
            }
            $summary[$status]++;
        }

        $this->audit->log($tenantId, $userId, 'sync.ingest', [
            'device_id' => $deviceId,
            'events_count' => $eventsCount,
            'summary' => $summary,
        ]);

        return ['status' => 200, 'data' => ['results' => $responses]];
    }

    public function status(Request $request): array
    {
        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $deviceId = trim((string) ($request->getQuery()['device_id'] ?? ''));
        if ($deviceId === '') {
            throw new HttpException(422, 'VALIDATION_ERROR', 'device_id requerido');
        }

        $rateKey = 'sync-status:' . $deviceId;
        if ($this->rateLimiter->tooManyAttempts($rateKey, 30, 60)) {
            throw new HttpException(429, 'RATE_LIMIT', 'Demasiadas solicitudes');
        }

        return ['status' => 200, 'data' => $this->statusRepository->getStatus($tenantId, $deviceId)];
    }

    private function applyEvent(int $tenantId, int $userId, mixed $event, Request $request): array
    {
        if (!is_array($event)) {
            return ['status' => 'failed', 'error' => 'Evento inválido'];
        }

        $eventId = (string) ($event['event_id'] ?? '');
        $deviceId = (string) ($event['device_id'] ?? '');
        $type = (string) ($event['type'] ?? '');
        $idempotencyKey = (string) ($event['idempotency_key'] ?? '');
        $payload = $event['payload'] ?? null;

        if ($eventId === '' || $deviceId === '' || $type === '' || $idempotencyKey === '' || !is_array($payload)) {
            $this->audit->log($tenantId, $userId, 'sync.failed', [
                'event_id' => $eventId,
                'error' => 'Campos requeridos faltantes',
            ]);
            return ['status' => 'failed', 'error' => 'Campos requeridos faltantes'];
        }

        if ($this->repository->existsIdempotency($tenantId, $deviceId, $idempotencyKey)) {
            $this->audit->log($tenantId, $userId, 'sync.duplicate', [
                'event_id' => $eventId,
                'device_id' => $deviceId,
                'type' => $type,
            ]);
            return ['status' => 'duplicate', 'event_id' => $eventId];
        }

        try {
            $this->repository->registerIdempotency($tenantId, $deviceId, $idempotencyKey);
        } catch (\Throwable $e) {
            $this->audit->log($tenantId, $userId, 'sync.duplicate', [
                'event_id' => $eventId,
                'device_id' => $deviceId,
                'type' => $type,
            ]);
            return ['status' => 'duplicate', 'event_id' => $eventId];
        }

        $status = 'APPLIED';
        $error = null;

        try {
            if ($type === 'pos.sale.paid') {
                $payloadRequest = $request->withJson($payload);
                $this->posSaleController->create($payloadRequest);
            } elseif ($type === 'cash_movement.created' || $type === 'pos.cash_movement.created') {
                $payloadRequest = $request->withJson($payload);
                $this->cashMovementController->create($payloadRequest);
            } else {
                throw new HttpException(422, 'VALIDATION_ERROR', 'Tipo de evento no soportado');
            }
        } catch (\Throwable $e) {
            $status = 'FAILED';
            $error = $e->getMessage();
            $this->audit->log($tenantId, $userId, 'sync.failed', ['event_id' => $eventId, 'error' => $error]);
        }

        $eventIdRecord = $this->repository->createEvent($tenantId, [
            'device_id' => $deviceId,
            'event_id' => $eventId,
            'type' => $type,
            'idempotency_key' => $idempotencyKey,
            'payload' => $payload,
        ], $status, $error);

        if ($status === 'APPLIED') {
            $this->repository->markAppliedAt($eventIdRecord);
            $this->audit->log($tenantId, $userId, 'sync.applied', [
                'event_id' => $eventId,
                'device_id' => $deviceId,
                'type' => $type,
            ]);
        }

        return [
            'event_id' => $eventId,
            'status' => strtolower($status),
            'error' => $error,
        ];
    }
}

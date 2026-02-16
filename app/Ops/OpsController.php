<?php

namespace App\Ops;

use App\Audit\AuditLogger;
use App\Core\Request;
use App\Shared\Exceptions\HttpException;
use App\Shared\Helpers;
use PDO;

class OpsController
{
    private OpsRepository $repository;
    private OpsDailyCloseService $dailyCloseService;
    private OpsObservabilityService $observabilityService;
    private OpsOnboardingService $onboardingService;
    private AuditLogger $audit;
    private PDO $db;

    public function __construct(
        OpsRepository $repository,
        OpsDailyCloseService $dailyCloseService,
        OpsObservabilityService $observabilityService,
        OpsOnboardingService $onboardingService,
        AuditLogger $audit,
        PDO $db
    )
    {
        $this->repository = $repository;
        $this->dailyCloseService = $dailyCloseService;
        $this->observabilityService = $observabilityService;
        $this->onboardingService = $onboardingService;
        $this->audit = $audit;
        $this->db = $db;
    }

    public function tenantMetrics(Request $request): array
    {
        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        return [
            'status' => 200,
            'data' => $this->repository->tenantMetrics($tenantId),
        ];
    }

    public function syncConflicts(Request $request): array
    {
        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $query = $request->getQuery();
        $limit = isset($query['limit']) ? (int) $query['limit'] : 100;

        $filters = [
            'resolved' => $query['resolved'] ?? '',
            'device_id' => $query['device_id'] ?? '',
            'type' => $query['type'] ?? '',
        ];

        return [
            'status' => 200,
            'data' => $this->repository->listSyncConflicts($tenantId, $filters, $limit),
        ];
    }

    public function jobsQueues(Request $request): array
    {
        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        return [
            'status' => 200,
            'data' => $this->repository->jobsQueues($tenantId),
        ];
    }

    public function dlq(Request $request): array
    {
        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $query = $request->getQuery();
        $limit = isset($query['limit']) ? (int) $query['limit'] : 50;
        $queueName = isset($query['queue_name']) ? trim((string) $query['queue_name']) : null;

        return [
            'status' => 200,
            'data' => $this->repository->listDlq($tenantId, $queueName, $limit),
        ];
    }

    public function requeueDlq(Request $request): array
    {
        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $dlqId = (int) $request->getParam('id', 0);
        if ($dlqId <= 0) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'id inválido');
        }

        $result = null;
        Helpers::transaction($this->db, function () use ($tenantId, $dlqId, &$result): void {
            $result = $this->repository->requeueDlq($tenantId, $dlqId);
        });

        if (!is_array($result)) {
            throw new HttpException(404, 'NOT_FOUND', 'Registro DLQ no encontrado');
        }

        return [
            'status' => 200,
            'data' => [
                'dlq_id' => $dlqId,
                'job_id' => (int) ($result['job_id'] ?? 0),
                'already_requeued' => (bool) ($result['already_requeued'] ?? false),
            ],
        ];
    }

    public function closeDaily(Request $request): array
    {
        $payload = $request->getJson();
        if (!is_array($payload)) {
            throw new HttpException(400, 'INVALID_BODY', 'Se requiere JSON');
        }

        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $userId = (int) $request->getAttribute('user_id', 0);
        $data = $this->dailyCloseService->validateClosePayload($payload);
        if (!$this->repository->branchExists($tenantId, (int) $data['branch_id'])) {
            throw new HttpException(404, 'NOT_FOUND', 'Sucursal no encontrada');
        }

        $summary = $this->repository->computeDailyClosureSummary($tenantId, $data['branch_id'], $data['close_date']);
        $difference = round($data['declared_cash'] - (float) ($summary['expected_cash'] ?? 0), 2);

        $id = $this->repository->upsertDailyClosure($tenantId, [
            'branch_id' => $data['branch_id'],
            'close_date' => $data['close_date'],
            'opening_cash' => (float) ($summary['opening_cash'] ?? 0),
            'cash_in' => (float) ($summary['cash_in'] ?? 0),
            'cash_out' => (float) ($summary['cash_out'] ?? 0),
            'cash_sales' => (float) ($summary['cash_sales'] ?? 0),
            'sales_total' => (float) ($summary['sales_total'] ?? 0),
            'sales_count' => (int) ($summary['sales_count'] ?? 0),
            'payments_total' => (float) ($summary['payments_total'] ?? 0),
            'payments_count' => (int) ($summary['payments_count'] ?? 0),
            'expected_cash' => (float) ($summary['expected_cash'] ?? 0),
            'declared_cash' => $data['declared_cash'],
            'difference' => $difference,
            'summary' => [
                'notes' => $data['notes'],
                'source' => 'ops.daily-close',
                'generated_at' => gmdate('c'),
                'metrics' => $summary,
            ],
        ], $userId);
        $closure = $this->repository->findDailyClosureById($tenantId, $id);

        $this->audit->log($tenantId, $userId, 'ops.daily_close.close', [
            'closure_id' => $id,
            'branch_id' => $data['branch_id'],
            'close_date' => $data['close_date'],
            'declared_cash' => $data['declared_cash'],
            'expected_cash' => (float) ($summary['expected_cash'] ?? 0),
            'difference' => $difference,
        ]);

        return [
            'status' => 200,
            'data' => $closure,
        ];
    }

    public function listDailyClosures(Request $request): array
    {
        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $query = $request->getQuery();
        $limit = isset($query['limit']) ? (int) $query['limit'] : 100;

        return [
            'status' => 200,
            'data' => $this->repository->listDailyClosures($tenantId, [
                'branch_id' => $query['branch_id'] ?? null,
                'status' => $query['status'] ?? null,
                'date_from' => $query['date_from'] ?? null,
                'date_to' => $query['date_to'] ?? null,
            ], $limit),
        ];
    }

    public function getDailyClosure(Request $request): array
    {
        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $id = (int) $request->getParam('id', 0);
        if ($id <= 0) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'id inválido');
        }

        $closure = $this->repository->findDailyClosureById($tenantId, $id);
        if (!$closure) {
            throw new HttpException(404, 'NOT_FOUND', 'Cierre diario no encontrado');
        }

        return [
            'status' => 200,
            'data' => $closure,
        ];
    }

    public function reopenDailyClosure(Request $request): array
    {
        $payload = $request->getJson();
        if (!is_array($payload)) {
            throw new HttpException(400, 'INVALID_BODY', 'Se requiere JSON');
        }

        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $userId = (int) $request->getAttribute('user_id', 0);
        $id = (int) $request->getParam('id', 0);
        if ($id <= 0) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'id inválido');
        }

        $data = $this->dailyCloseService->validateReopenPayload($payload);
        $ok = false;
        Helpers::transaction($this->db, function () use ($tenantId, $id, $data, $userId, &$ok): void {
            $ok = $this->repository->reopenDailyClosure($tenantId, $id, $data['reason'], $userId);
        });
        if (!$ok) {
            throw new HttpException(409, 'INVALID_STATE', 'No se pudo reabrir el cierre diario');
        }

        $closure = $this->repository->findDailyClosureById($tenantId, $id);
        $this->audit->log($tenantId, $userId, 'ops.daily_close.reopen', [
            'closure_id' => $id,
            'reason' => $data['reason'],
        ]);

        return [
            'status' => 200,
            'data' => $closure,
        ];
    }

    public function sliSlo(Request $request): array
    {
        $tenantId = (int) $request->getAttribute('tenant_id', 0);

        return [
            'status' => 200,
            'data' => $this->repository->sliSloSnapshot($tenantId),
        ];
    }

    public function listAlertRules(Request $request): array
    {
        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $query = $request->getQuery();
        $enabled = isset($query['enabled']) && (string) $query['enabled'] === '1';

        return [
            'status' => 200,
            'data' => $this->repository->listAlertRules($tenantId, $enabled),
        ];
    }

    public function upsertAlertRules(Request $request): array
    {
        $payload = $request->getJson();
        if (!is_array($payload)) {
            throw new HttpException(400, 'INVALID_BODY', 'Se requiere JSON');
        }

        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $userId = (int) $request->getAttribute('user_id', 0);
        $entries = $this->observabilityService->normalizeRulesPayload($payload);

        $ids = [];
        foreach ($entries as $entry) {
            $ids[] = $this->repository->upsertAlertRule($tenantId, $entry, $userId);
        }

        $this->audit->log($tenantId, $userId, 'ops.alert_rules.upsert', [
            'rules' => count($entries),
        ]);

        return [
            'status' => 200,
            'data' => [
                'ids' => $ids,
                'rules' => $this->repository->listAlertRules($tenantId, false),
            ],
        ];
    }

    public function evaluateAlerts(Request $request): array
    {
        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $userId = (int) $request->getAttribute('user_id', 0);
        $snapshot = $this->repository->sliSloSnapshot($tenantId);
        $sli = (array) ($snapshot['sli'] ?? []);
        $rules = $this->repository->listAlertRules($tenantId, true);

        $triggered = [];
        foreach ($rules as $rule) {
            $metricKey = (string) ($rule['metric_key'] ?? '');
            if ($metricKey === '' || !array_key_exists($metricKey, $sli)) {
                continue;
            }

            $metricValue = (float) $sli[$metricKey];
            $threshold = (float) ($rule['threshold_value'] ?? 0);
            $comparator = (string) ($rule['comparator'] ?? 'GT');
            if (!$this->observabilityService->compare($metricValue, $threshold, $comparator)) {
                continue;
            }

            $eventId = $this->repository->createAlertEvent(
                $tenantId,
                (int) ($rule['id'] ?? 0),
                $metricValue,
                $threshold,
                (string) ($rule['severity'] ?? 'WARN'),
                [
                    'rule_code' => (string) ($rule['code'] ?? ''),
                    'metric_key' => $metricKey,
                    'channel' => (string) ($rule['channel'] ?? 'INTERNAL'),
                    'target' => $rule['target'] ?? null,
                    'captured_at' => gmdate('c'),
                ]
            );

            $triggered[] = [
                'event_id' => $eventId,
                'rule_id' => (int) ($rule['id'] ?? 0),
                'rule_code' => (string) ($rule['code'] ?? ''),
                'metric_key' => $metricKey,
                'metric_value' => $metricValue,
                'threshold_value' => $threshold,
                'severity' => (string) ($rule['severity'] ?? 'WARN'),
            ];
        }

        $this->audit->log($tenantId, $userId, 'ops.alerts.evaluate', [
            'triggered' => count($triggered),
        ]);

        return [
            'status' => 200,
            'data' => [
                'captured_at' => gmdate('c'),
                'triggered' => $triggered,
                'triggered_count' => count($triggered),
            ],
        ];
    }

    public function incidents(Request $request): array
    {
        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $query = $request->getQuery();
        $limit = isset($query['limit']) ? (int) $query['limit'] : 100;

        return [
            'status' => 200,
            'data' => $this->repository->listIncidentEvents($tenantId, [
                'status' => $query['status'] ?? '',
                'severity' => $query['severity'] ?? '',
            ], $limit),
        ];
    }

    public function diagnostics(Request $request): array
    {
        $tenantId = (int) $request->getAttribute('tenant_id', 0);

        return [
            'status' => 200,
            'data' => $this->repository->diagnoseTenant($tenantId),
        ];
    }

    public function onboardingTemplates(Request $request): array
    {
        return [
            'status' => 200,
            'data' => $this->onboardingService->templates(),
        ];
    }

    public function onboardingChecklist(Request $request): array
    {
        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $query = $request->getQuery();
        $templateCode = strtolower(trim((string) ($query['template_code'] ?? 'retail_basic')));

        $row = $this->repository->getOnboardingChecklist($tenantId, $templateCode);
        if (!$row) {
            $items = $this->onboardingService->defaultChecklist($templateCode);
            $progress = $this->onboardingService->progress($items);
            $this->repository->upsertOnboardingChecklist($tenantId, $templateCode, $items, $progress, 0);
            $row = $this->repository->getOnboardingChecklist($tenantId, $templateCode);
        }

        return [
            'status' => 200,
            'data' => $row,
        ];
    }

    public function upsertOnboardingChecklist(Request $request): array
    {
        $payload = $request->getJson();
        if (!is_array($payload)) {
            throw new HttpException(400, 'INVALID_BODY', 'Se requiere JSON');
        }

        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $userId = (int) $request->getAttribute('user_id', 0);
        $templateCode = strtolower(trim((string) ($payload['template_code'] ?? '')));
        if ($templateCode === '') {
            throw new HttpException(422, 'VALIDATION_ERROR', 'template_code requerido');
        }

        $current = $this->repository->getOnboardingChecklist($tenantId, $templateCode);
        $currentItems = is_array($current['items'] ?? null) ? $current['items'] : $this->onboardingService->defaultChecklist($templateCode);
        $items = $this->onboardingService->normalizeChecklistPayload($payload, $currentItems);
        $progress = $this->onboardingService->progress($items);

        $id = $this->repository->upsertOnboardingChecklist($tenantId, $templateCode, $items, $progress, $userId);
        $row = $this->repository->getOnboardingChecklist($tenantId, $templateCode);

        $this->audit->log($tenantId, $userId, 'ops.onboarding.checklist.upsert', [
            'checklist_id' => $id,
            'template_code' => $templateCode,
            'progress_pct' => $progress,
        ]);

        return [
            'status' => 200,
            'data' => $row,
        ];
    }
}

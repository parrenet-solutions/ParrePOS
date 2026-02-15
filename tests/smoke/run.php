<?php

declare(strict_types=1);

/**
 * Smoke test minimo para API ParrePos.
 *
 * Uso:
 *   php tests/smoke/run.php
 *
 * Variables opcionales:
 *   SMOKE_BASE_URL=http://localhost
 *   SMOKE_TENANT_SLUG=demo
 *   SMOKE_EMAIL=admin@demo.local
 *   SMOKE_PASSWORD=Admin12345!
 *   SMOKE_MODE=positive|negative|all
 */

$baseUrl = rtrim(envValue('SMOKE_BASE_URL', 'http://localhost'), '/');
$tenantSlug = envValue('SMOKE_TENANT_SLUG', 'demo');
$email = envValue('SMOKE_EMAIL', 'admin@demo.local');
$password = envValue('SMOKE_PASSWORD', 'Admin12345!');
$mode = parseModeFromCli($argv ?? [], strtolower(envValue('SMOKE_MODE', 'all')));

if (!in_array($mode, ['positive', 'negative', 'all'], true)) {
    fail('SMOKE_MODE invalido. Usa: positive|negative|all');
}

$GLOBALS['SMOKE_STATS'] = [
    'mode' => $mode,
    'steps_total' => 0,
    'steps_ok' => 0,
    'current_step' => '',
];

$ctx = [
    'access_token' => '',
    'tenant_id' => 0,
    'branch_id' => 0,
    'register_id' => 0,
    'cash_session_id' => 0,
    'inventory_item_id' => 0,
    'device_id' => 'smoke-device-' . bin2hex(random_bytes(3)),
];

$db = null;
if ($mode === 'negative' || $mode === 'all') {
    $db = dbConnectionFromEnv();
}

runStep('Health', function () use ($baseUrl): void {
    $res = requestJson('GET', $baseUrl . '/health');
    assertStatus($res, 200, 'health');
    assertApiOk($res, 'health');
});

runStep('Login', function () use ($baseUrl, $tenantSlug, $email, $password, &$ctx): void {
    $res = requestJson('POST', $baseUrl . '/api/v1/auth/login', [
        'tenant_slug' => $tenantSlug,
        'email' => $email,
        'password' => $password,
    ]);

    assertStatus($res, 200, 'login');
    assertApiOk($res, 'login');

    $token = (string) ($res['json']['data']['access_token'] ?? '');
    if ($token === '') {
        fail('login: access_token no encontrado');
    }

    $ctx['access_token'] = $token;
});

runStep('Me', function () use ($baseUrl, &$ctx): void {
    $res = requestJson('GET', $baseUrl . '/api/v1/me', null, authHeaders($ctx['access_token']));
    assertStatus($res, 200, 'me');
    assertApiOk($res, 'me');

    $tenantId = (int) ($res['json']['data']['tenant_id'] ?? 0);
    if ($tenantId <= 0) {
        fail('me: tenant_id invalido');
    }

    $ctx['tenant_id'] = $tenantId;
});

if ($mode === 'positive' || $mode === 'all') {
    runPositiveFlow($baseUrl, $ctx);
}

if ($mode === 'negative' || $mode === 'all') {
    if (!$db instanceof PDO) {
        fail('modo negative/all requiere DB disponible');
    }
    runNegativeFlow($baseUrl, $ctx, $db);
}

fwrite(
    STDOUT,
    "\nSMOKE OK: modo={$GLOBALS['SMOKE_STATS']['mode']} pasos="
    . $GLOBALS['SMOKE_STATS']['steps_ok']
    . '/'
    . $GLOBALS['SMOKE_STATS']['steps_total']
    . "\n"
);
exit(0);

function runPositiveFlow(string $baseUrl, array &$ctx): void
{
    runStep('Crear Sucursal', function () use ($baseUrl, &$ctx): void {
        $res = requestJson(
            'POST',
            $baseUrl . '/api/v1/branches',
            [
                'name' => 'Sucursal Smoke ' . date('His'),
                'address' => 'Direccion smoke',
            ],
            authHeaders($ctx['access_token'])
        );

        assertStatus($res, 201, 'branches.create');
        assertApiOk($res, 'branches.create');

        $id = (int) ($res['json']['data']['id'] ?? 0);
        if ($id <= 0) {
            fail('branches.create: id invalido');
        }

        $ctx['branch_id'] = $id;
    });

    runStep('Crear Caja', function () use ($baseUrl, &$ctx): void {
        $res = requestJson(
            'POST',
            $baseUrl . '/api/v1/pos/registers',
            [
                'branch_id' => $ctx['branch_id'],
                'name' => 'Caja Smoke',
                'device_id' => $ctx['device_id'],
            ],
            authHeaders($ctx['access_token'])
        );

        assertStatus($res, 201, 'registers.create');
        assertApiOk($res, 'registers.create');

        $id = (int) ($res['json']['data']['id'] ?? 0);
        if ($id <= 0) {
            fail('registers.create: id invalido');
        }

        $ctx['register_id'] = $id;
    });

    runStep('Abrir Caja', function () use ($baseUrl, &$ctx): void {
        $res = requestJson(
            'POST',
            $baseUrl . '/api/v1/pos/cash-sessions/open',
            [
                'branch_id' => $ctx['branch_id'],
                'register_id' => $ctx['register_id'],
                'opening_amount' => 1500,
            ],
            authHeaders($ctx['access_token'])
        );

        assertStatus($res, 201, 'cash-sessions.open');
        assertApiOk($res, 'cash-sessions.open');

        $id = (int) ($res['json']['data']['id'] ?? 0);
        if ($id <= 0) {
            fail('cash-sessions.open: id invalido');
        }

        $ctx['cash_session_id'] = $id;
    });

    runStep('Cash Movement Idempotency Duplicate', function () use ($baseUrl, &$ctx): void {
        $key = 'cm-dup-' . bin2hex(random_bytes(4));
        $amount = 75.0;

        $headers = array_merge(authHeaders($ctx['access_token']), [
            'X-Idempotency-Key: ' . $key,
            'X-Device-Id: ' . $ctx['device_id'],
        ]);

        $payload = [
            'cash_session_id' => $ctx['cash_session_id'],
            'type' => 'OUT',
            'amount' => $amount,
            'reason_code' => 'EXPENSE',
            'description' => 'Gasto smoke',
            'reference' => 'SMK-' . date('His'),
        ];

        $before = getCashSummary($baseUrl, $ctx['access_token'], $ctx['cash_session_id']);

        $first = requestJson('POST', $baseUrl . '/api/v1/pos/cash-movements', $payload, $headers);
        assertStatus($first, 201, 'cash-movements.first');
        assertApiOk($first, 'cash-movements.first');

        $afterFirst = getCashSummary($baseUrl, $ctx['access_token'], $ctx['cash_session_id']);
        assertFloatEquals($afterFirst['out_total'], $before['out_total'] + $amount, 'cash-movements.first.effect');

        $second = requestJson('POST', $baseUrl . '/api/v1/pos/cash-movements', $payload, $headers);
        assertStatus($second, 200, 'cash-movements.second');
        assertApiOk($second, 'cash-movements.second');
        assertTrue((bool) ($second['json']['data']['duplicate'] ?? false), 'cash-movements.second.duplicate');

        $afterSecond = getCashSummary($baseUrl, $ctx['access_token'], $ctx['cash_session_id']);
        assertFloatEquals($afterSecond['out_total'], $afterFirst['out_total'], 'cash-movements.second.no-side-effect');
    });

    runStep('Cash Movement Idempotency Concurrent Basic', function () use ($baseUrl, &$ctx): void {
        $key = 'cm-conc-' . bin2hex(random_bytes(4));
        $amount = 33.0;

        $headers = array_merge(authHeaders($ctx['access_token']), [
            'X-Idempotency-Key: ' . $key,
            'X-Device-Id: ' . $ctx['device_id'],
        ]);

        $payload = [
            'cash_session_id' => $ctx['cash_session_id'],
            'type' => 'OUT',
            'amount' => $amount,
            'reason_code' => 'EXPENSE',
            'description' => 'Gasto concurrente smoke',
            'reference' => 'SMK-C-' . date('His'),
        ];

        $before = getCashSummary($baseUrl, $ctx['access_token'], $ctx['cash_session_id']);
        $responses = requestJsonConcurrentPair('POST', $baseUrl . '/api/v1/pos/cash-movements', $payload, $headers);

        $statuses = [$responses[0]['status'], $responses[1]['status']];
        sort($statuses);
        if ($statuses !== [200, 201]) {
            fail('cash-movements.concurrent: se esperaba [200,201], recibido [' . implode(',', $statuses) . ']');
        }

        $duplicateCount = 0;
        foreach ($responses as $res) {
            assertApiOk($res, 'cash-movements.concurrent.response');
            if ($res['status'] === 200 && (bool) ($res['json']['data']['duplicate'] ?? false) === true) {
                $duplicateCount++;
            }
        }

        if ($duplicateCount !== 1) {
            fail('cash-movements.concurrent: se esperaba 1 respuesta duplicate');
        }

        $after = getCashSummary($baseUrl, $ctx['access_token'], $ctx['cash_session_id']);
        assertFloatEquals($after['out_total'], $before['out_total'] + $amount, 'cash-movements.concurrent.single-side-effect');
    });

    runStep('Sync Status', function () use ($baseUrl, &$ctx): void {
        $res = requestJson(
            'GET',
            $baseUrl . '/api/v1/sync/status?device_id=' . rawurlencode($ctx['device_id']),
            null,
            authHeaders($ctx['access_token'])
        );

        assertStatus($res, 200, 'sync.status');
        assertApiOk($res, 'sync.status');

        $data = $res['json']['data'] ?? null;
        if (!is_array($data)) {
            fail('sync.status: data invalido');
        }

        assertArrayHasKeys($data, [
            'pending_count',
            'failed_count',
            'conflict_count',
            'last_applied_at',
            'last_event_at',
        ], 'sync.status.schema');
    });

    runStep('Ops Tenant Metrics', function () use ($baseUrl, &$ctx): void {
        $res = requestJson(
            'GET',
            $baseUrl . '/api/v1/ops/tenant-metrics',
            null,
            authHeaders($ctx['access_token'])
        );

        assertStatus($res, 200, 'ops.tenant-metrics');
        assertApiOk($res, 'ops.tenant-metrics');

        $data = $res['json']['data'] ?? null;
        if (!is_array($data)) {
            fail('ops.tenant-metrics: data invalido');
        }

        assertArrayHasKeys($data, ['sync', 'conflicts', 'jobs', 'fiscal'], 'ops.tenant-metrics.sections');
        assertArrayHasKeys((array) ($data['sync'] ?? []), ['total_events', 'applied', 'failed', 'conflicts', 'last_event_at'], 'ops.tenant-metrics.sync');
        assertArrayHasKeys((array) ($data['conflicts'] ?? []), ['total', 'resolved', 'open'], 'ops.tenant-metrics.conflicts');
        assertArrayHasKeys((array) ($data['jobs'] ?? []), ['pending', 'retry', 'processing', 'failed', 'completed', 'dlq_total'], 'ops.tenant-metrics.jobs');
        assertArrayHasKeys((array) ($data['fiscal'] ?? []), ['total', 'accepted', 'rejected', 'failed'], 'ops.tenant-metrics.fiscal');
    });

    runStep('Ops Sync Conflicts', function () use ($baseUrl, &$ctx): void {
        $res = requestJson(
            'GET',
            $baseUrl . '/api/v1/ops/sync-conflicts?resolved=0&limit=10',
            null,
            authHeaders($ctx['access_token'])
        );

        assertStatus($res, 200, 'ops.sync-conflicts');
        assertApiOk($res, 'ops.sync-conflicts');

        $rows = $res['json']['data'] ?? null;
        if (!is_array($rows)) {
            fail('ops.sync-conflicts: data invalido');
        }

        if ($rows !== []) {
            $first = $rows[0];
            if (!is_array($first)) {
                fail('ops.sync-conflicts: row invalido');
            }
            assertArrayHasKeys($first, ['id', 'device_id', 'type', 'op_id', 'event_id', 'existing_event_id', 'reason_code', 'resolved', 'created_at'], 'ops.sync-conflicts.row');
        }
    });

    runStep('Fiscal Status', function () use ($baseUrl, &$ctx): void {
        $res = requestJson(
            'GET',
            $baseUrl . '/api/v1/fiscal/status',
            null,
            authHeaders($ctx['access_token'])
        );

        assertStatus($res, 200, 'fiscal.status');
        assertApiOk($res, 'fiscal.status');
    });

    runStep('Fiscal Documents List', function () use ($baseUrl, &$ctx): void {
        $res = requestJson(
            'GET',
            $baseUrl . '/api/v1/fiscal/documents?limit=5',
            null,
            authHeaders($ctx['access_token'])
        );

        assertStatus($res, 200, 'fiscal.documents.list');
        assertApiOk($res, 'fiscal.documents.list');
    });

    runStep('Fiscal Documents Summary', function () use ($baseUrl, &$ctx): void {
        $res = requestJson(
            'GET',
            $baseUrl . '/api/v1/fiscal/documents/summary',
            null,
            authHeaders($ctx['access_token'])
        );

        assertStatus($res, 200, 'fiscal.documents.summary');
        assertApiOk($res, 'fiscal.documents.summary');
    });

    runStep('Fiscal Documents Search', function () use ($baseUrl, &$ctx): void {
        $res = requestJson(
            'GET',
            $baseUrl . '/api/v1/fiscal/documents/search?limit=5',
            null,
            authHeaders($ctx['access_token'])
        );

        assertStatus($res, 200, 'fiscal.documents.search');
        assertApiOk($res, 'fiscal.documents.search');
    });

    runStep('Fiscal Metrics', function () use ($baseUrl, &$ctx): void {
        $res = requestJson(
            'GET',
            $baseUrl . '/api/v1/fiscal/metrics',
            null,
            authHeaders($ctx['access_token'])
        );

        assertStatus($res, 200, 'fiscal.metrics');
        assertApiOk($res, 'fiscal.metrics');
    });

    runStep('Fiscal Retry Concurrent Idempotency Basic', function () use ($baseUrl, &$ctx): void {
        $list = requestJson(
            'GET',
            $baseUrl . '/api/v1/fiscal/documents?limit=1',
            null,
            authHeaders($ctx['access_token'])
        );
        assertStatus($list, 200, 'fiscal.retry.concurrent.list');
        assertApiOk($list, 'fiscal.retry.concurrent.list');

        $first = $list['json']['data'][0] ?? null;
        if (!is_array($first) || (int) ($first['id'] ?? 0) <= 0) {
            smokeInfo('fiscal.retry.concurrent: sin documentos fiscales, se omite verificación.');
            return;
        }

        $docId = (int) $first['id'];
        $responses = requestJsonConcurrentPair(
            'POST',
            $baseUrl . '/api/v1/fiscal/documents/' . $docId . '/retry',
            [],
            authHeaders($ctx['access_token'])
        );

        foreach ($responses as $res) {
            assertStatus($res, 202, 'fiscal.retry.concurrent.status');
            assertApiOk($res, 'fiscal.retry.concurrent.ok');
        }

        $jobA = (int) ($responses[0]['json']['data']['job_id'] ?? 0);
        $jobB = (int) ($responses[1]['json']['data']['job_id'] ?? 0);
        if ($jobA <= 0 || $jobB <= 0) {
            fail('fiscal.retry.concurrent: job_id inválido');
        }

        if ($jobA !== $jobB) {
            fail('fiscal.retry.concurrent: se esperaba mismo job_id por idempotencia. recibidos=' . $jobA . ',' . $jobB);
        }
    });

    runStep('Inventory Manual IN and Stock', function () use ($baseUrl, &$ctx): void {
        $itemRes = requestJson(
            'POST',
            $baseUrl . '/api/v1/items',
            [
                'type' => 'PRODUCT',
                'name' => 'Item Inv Smoke ' . date('His'),
                'price' => 150,
                'itbis_rate' => 0.18,
            ],
            authHeaders($ctx['access_token'])
        );
        assertStatus($itemRes, 201, 'inventory.item.create');
        assertApiOk($itemRes, 'inventory.item.create');

        $itemId = (int) ($itemRes['json']['data']['id'] ?? 0);
        if ($itemId <= 0) {
            fail('inventory.item.create: id inválido');
        }
        $ctx['inventory_item_id'] = $itemId;

        $movementRes = requestJson(
            'POST',
            $baseUrl . '/api/v1/inventory/movements',
            [
                'branch_id' => $ctx['branch_id'],
                'item_id' => $itemId,
                'movement_type' => 'IN',
                'qty' => 10,
                'reason_code' => 'ADJUST',
            ],
            authHeaders($ctx['access_token'])
        );
        assertStatus($movementRes, 201, 'inventory.movement.in');
        assertApiOk($movementRes, 'inventory.movement.in');

        $stock = getInventoryStock($baseUrl, $ctx['access_token'], $ctx['branch_id'], $itemId);
        if ($stock < 10) {
            fail('inventory.stock: esperado >= 10, recibido ' . $stock);
        }
    });

    runStep('POS Sale Inventory Deduct and Void Reverse', function () use ($baseUrl, &$ctx): void {
        $itemId = (int) ($ctx['inventory_item_id'] ?? 0);
        if ($itemId <= 0) {
            fail('pos.inventory.sale: item de inventario no inicializado');
        }

        $before = getInventoryStock($baseUrl, $ctx['access_token'], $ctx['branch_id'], $itemId);
        $saleRes = requestJson(
            'POST',
            $baseUrl . '/api/v1/pos/sales',
            [
                'branch_id' => $ctx['branch_id'],
                'register_id' => $ctx['register_id'],
                'cash_session_id' => $ctx['cash_session_id'],
                'items' => [[
                    'item_id' => $itemId,
                    'name' => 'Item Inv Smoke',
                    'qty' => 1,
                    'unit_price' => 150,
                    'discount' => 0,
                    'tax_rate' => 0.18,
                ]],
                'payments' => [[
                    'method' => 'CASH',
                    'amount' => 177,
                ]],
            ],
            authHeaders($ctx['access_token'])
        );
        assertStatus($saleRes, 201, 'pos.inventory.sale');
        assertApiOk($saleRes, 'pos.inventory.sale');

        $saleId = (int) ($saleRes['json']['data']['id'] ?? 0);
        if ($saleId <= 0) {
            fail('pos.inventory.sale: id inválido');
        }

        $afterSale = getInventoryStock($baseUrl, $ctx['access_token'], $ctx['branch_id'], $itemId);
        assertFloatEquals($afterSale, $before - 1, 'pos.inventory.sale.deduct');

        $voidRes = requestJson(
            'POST',
            $baseUrl . '/api/v1/pos/sales/' . $saleId . '/void',
            ['reason' => 'Smoke inventory reverse'],
            authHeaders($ctx['access_token'])
        );
        assertStatus($voidRes, 200, 'pos.inventory.sale.void');
        assertApiOk($voidRes, 'pos.inventory.sale.void');

        $afterVoid = getInventoryStock($baseUrl, $ctx['access_token'], $ctx['branch_id'], $itemId);
        assertFloatEquals($afterVoid, $before, 'pos.inventory.sale.void.reverse');
    });

    runStep('Sync Ingest Idempotency Duplicate', function () use ($baseUrl, &$ctx): void {
        $idempotencyKey = 'sync-idem-' . bin2hex(random_bytes(4));
        $eventId = uuidV4();
        $amount = 25.0;

        $payload = [
            'device_id' => $ctx['device_id'],
            'events' => [[
                'event_id' => $eventId,
                'device_id' => $ctx['device_id'],
                'type' => 'cash_movement.created',
                'idempotency_key' => $idempotencyKey,
                'payload' => [
                    'cash_session_id' => $ctx['cash_session_id'],
                    'type' => 'IN',
                    'amount' => $amount,
                    'reason_code' => 'DEPOSIT',
                    'description' => 'Ingreso smoke',
                    'reference' => 'SYNC-' . date('His'),
                ],
                'ts' => gmdate('c'),
            ]],
        ];

        $before = getCashSummary($baseUrl, $ctx['access_token'], $ctx['cash_session_id']);

        $first = requestJson('POST', $baseUrl . '/api/v1/sync/events', $payload, authHeaders($ctx['access_token']));
        assertStatus($first, 200, 'sync.ingest.first');
        assertApiOk($first, 'sync.ingest.first');

        $firstResult = $first['json']['data']['results'][0] ?? null;
        if (!is_array($firstResult) || ($firstResult['status'] ?? '') !== 'applied') {
            fail('sync.ingest.first: resultado no aplicado');
        }

        $afterFirst = getCashSummary($baseUrl, $ctx['access_token'], $ctx['cash_session_id']);
        assertFloatEquals($afterFirst['in_total'], $before['in_total'] + $amount, 'sync.ingest.first.effect');

        $second = requestJson('POST', $baseUrl . '/api/v1/sync/events', $payload, authHeaders($ctx['access_token']));
        assertStatus($second, 200, 'sync.ingest.second');
        assertApiOk($second, 'sync.ingest.second');

        $secondResult = $second['json']['data']['results'][0] ?? null;
        if (!is_array($secondResult) || ($secondResult['status'] ?? '') !== 'duplicate') {
            fail('sync.ingest.second: se esperaba duplicate');
        }

        $afterSecond = getCashSummary($baseUrl, $ctx['access_token'], $ctx['cash_session_id']);
        assertFloatEquals($afterSecond['in_total'], $afterFirst['in_total'], 'sync.ingest.second.no-side-effect');
    });

    runStep('Sync Conflict by OpId', function () use ($baseUrl, &$ctx): void {
        $opId = 'sync-op-' . bin2hex(random_bytes(4));
        $deviceId = $ctx['device_id'];
        $cashSessionId = $ctx['cash_session_id'];

        $before = getCashSummary($baseUrl, $ctx['access_token'], $cashSessionId);

        $first = requestJson('POST', $baseUrl . '/api/v1/sync/events', [
            'device_id' => $deviceId,
            'events' => [[
                'event_id' => uuidV4(),
                'op_id' => $opId,
                'device_id' => $deviceId,
                'type' => 'cash_movement.created',
                'idempotency_key' => 'sync-op-idem-a-' . bin2hex(random_bytes(4)),
                'payload' => [
                    'cash_session_id' => $cashSessionId,
                    'type' => 'IN',
                    'amount' => 11.0,
                    'reason_code' => 'DEPOSIT',
                    'description' => 'Sync op first',
                    'reference' => 'SYNC-OP-A',
                ],
                'ts' => gmdate('c'),
            ]],
        ], authHeaders($ctx['access_token']));
        assertStatus($first, 200, 'sync.conflict.first');
        assertApiOk($first, 'sync.conflict.first');
        $firstResult = $first['json']['data']['results'][0] ?? null;
        if (!is_array($firstResult) || ($firstResult['status'] ?? '') !== 'applied') {
            fail('sync.conflict.first: esperado applied');
        }

        $afterFirst = getCashSummary($baseUrl, $ctx['access_token'], $cashSessionId);
        assertFloatEquals($afterFirst['in_total'], $before['in_total'] + 11.0, 'sync.conflict.first.effect');

        $second = requestJson('POST', $baseUrl . '/api/v1/sync/events', [
            'device_id' => $deviceId,
            'events' => [[
                'event_id' => uuidV4(),
                'op_id' => $opId,
                'device_id' => $deviceId,
                'type' => 'cash_movement.created',
                'idempotency_key' => 'sync-op-idem-b-' . bin2hex(random_bytes(4)),
                'payload' => [
                    'cash_session_id' => $cashSessionId,
                    'type' => 'IN',
                    'amount' => 99.0,
                    'reason_code' => 'DEPOSIT',
                    'description' => 'Sync op conflict',
                    'reference' => 'SYNC-OP-B',
                ],
                'ts' => gmdate('c'),
            ]],
        ], authHeaders($ctx['access_token']));
        assertStatus($second, 200, 'sync.conflict.second');
        assertApiOk($second, 'sync.conflict.second');
        $secondResult = $second['json']['data']['results'][0] ?? null;
        if (!is_array($secondResult) || ($secondResult['status'] ?? '') !== 'conflict') {
            fail('sync.conflict.second: esperado conflict');
        }

        $afterSecond = getCashSummary($baseUrl, $ctx['access_token'], $cashSessionId);
        assertFloatEquals($afterSecond['in_total'], $afterFirst['in_total'], 'sync.conflict.second.no-side-effect');

        $statusRes = requestJson(
            'GET',
            $baseUrl . '/api/v1/sync/status?device_id=' . rawurlencode($deviceId),
            null,
            authHeaders($ctx['access_token'])
        );
        assertStatus($statusRes, 200, 'sync.conflict.status');
        assertApiOk($statusRes, 'sync.conflict.status');
        $conflictCount = (int) (($statusRes['json']['data']['conflict_count'] ?? 0));
        if ($conflictCount < 1) {
            fail('sync.conflict.status: conflict_count esperado >= 1, recibido ' . $conflictCount);
        }
    });
}

function runNegativeFlow(string $baseUrl, array $ctx, PDO $db): void
{
    runStep('TenantGuard TENANT_SUSPENDED', function () use ($baseUrl, $ctx, $db): void {
        $tenantId = (int) $ctx['tenant_id'];
        $previousStatus = getTenantStatus($db, $tenantId);
        if ($previousStatus === null) {
            fail('tenant.suspended: tenant no encontrado');
        }

        try {
            setTenantStatus($db, $tenantId, 'SUSPENDED');

            $res = requestJson(
                'POST',
                $baseUrl . '/api/v1/pos/registers/handshake',
                [
                    'device_id' => 'negative-device',
                    'app_version' => '2.2.0',
                    'capabilities' => ['offline', 'sync'],
                ],
                authHeaders($ctx['access_token'])
            );

            assertStatus($res, 403, 'tenant.suspended');
            assertApiError($res, 'TENANT_SUSPENDED', 'tenant.suspended');
        } finally {
            setTenantStatus($db, $tenantId, $previousStatus);
        }
    });

    runStep('TenantGuard MODULE_DISABLED', function () use ($baseUrl, $ctx, $db): void {
        $tenantId = (int) $ctx['tenant_id'];
        $previousModules = getTenantModulesRaw($db, $tenantId);
        if ($previousModules === null) {
            fail('module.disabled: tenant_settings no encontrado');
        }

        try {
            $disabled = disableModulePos($previousModules);
            setTenantModulesRaw($db, $tenantId, $disabled);

            $res = requestJson(
                'POST',
                $baseUrl . '/api/v1/pos/registers/handshake',
                [
                    'device_id' => 'negative-device',
                    'app_version' => '2.2.0',
                    'capabilities' => ['offline', 'sync'],
                ],
                authHeaders($ctx['access_token'])
            );

            assertStatus($res, 403, 'module.disabled');
            assertApiError($res, 'MODULE_DISABLED', 'module.disabled');
        } finally {
            setTenantModulesRaw($db, $tenantId, $previousModules);
        }
    });

    runStep('PlanGuard MODULE_DISABLED_BY_PLAN', function () use ($baseUrl, $ctx, $db): void {
        $tenantId = (int) $ctx['tenant_id'];
        $previousPlanModules = getTenantPlanModulesRaw($db, $tenantId);
        if ($previousPlanModules === null) {
            fail('plan.module.disabled: subscription/plan no encontrado');
        }

        try {
            $disabled = disableModuleFromPlan($previousPlanModules, 'pos');
            setTenantPlanModulesRaw($db, $tenantId, $disabled);

            $res = requestJson(
                'POST',
                $baseUrl . '/api/v1/pos/registers/handshake',
                [
                    'device_id' => 'negative-plan-device',
                    'app_version' => '2.2.0',
                    'capabilities' => ['offline', 'sync'],
                ],
                authHeaders($ctx['access_token'])
            );

            assertStatus($res, 403, 'plan.module.disabled');
            assertApiError($res, 'MODULE_DISABLED', 'plan.module.disabled');
        } finally {
            setTenantPlanModulesRaw($db, $tenantId, $previousPlanModules);
        }
    });

    runStep('PlanGuard LIMIT_EXCEEDED', function () use ($baseUrl, $ctx, $db): void {
        $tenantId = (int) $ctx['tenant_id'];
        $previousPlanLimits = getTenantPlanLimitsRaw($db, $tenantId);
        if ($previousPlanLimits === null) {
            fail('plan.limit.exceeded: subscription/plan no encontrado');
        }

        try {
            $zeroBranches = forcePlanLimit($previousPlanLimits, 'branches.max', 0);
            setTenantPlanLimitsRaw($db, $tenantId, $zeroBranches);

            $res = requestJson(
                'POST',
                $baseUrl . '/api/v1/branches',
                [
                    'name' => 'Sucursal Limite ' . date('His'),
                    'address' => 'Prueba limite plan',
                ],
                authHeaders($ctx['access_token'])
            );

            assertStatus($res, 409, 'plan.limit.exceeded');
            assertApiError($res, 'PLAN_LIMIT_EXCEEDED', 'plan.limit.exceeded');
        } finally {
            setTenantPlanLimitsRaw($db, $tenantId, $previousPlanLimits);
        }
    });

    runStep('Fiscal Config Validation', function () use ($baseUrl, $ctx): void {
        $res = requestJson(
            'PUT',
            $baseUrl . '/api/v1/fiscal/config',
            [
                'fiscal' => [
                    'enabled' => true,
                    'dgii_registered' => false,
                    'ncf_type' => 'B01',
                    'series' => 'B01',
                ],
            ],
            authHeaders($ctx['access_token'])
        );

        assertStatus($res, 422, 'fiscal.config.validation');
        assertApiError($res, 'VALIDATION_ERROR', 'fiscal.config.validation');
    });

    runStep('Fiscal Webhook Unauthorized', function () use ($baseUrl): void {
        $res = requestJson(
            'POST',
            $baseUrl . '/api/v1/fiscal/webhook/ack',
            [
                'tenant_id' => 1,
                'fiscal_document_id' => 1,
                'ack_code' => 'ACCEPTED',
                'ack_message' => 'Ack de prueba',
            ],
            []
        );

        assertStatus($res, 401, 'fiscal.webhook.unauthorized');
        assertApiError($res, 'UNAUTHORIZED', 'fiscal.webhook.unauthorized');
    });

    runStep('Ops Tenant Metrics Unauthorized', function () use ($baseUrl): void {
        $res = requestJson(
            'GET',
            $baseUrl . '/api/v1/ops/tenant-metrics',
            null,
            []
        );

        assertStatus($res, 401, 'ops.tenant-metrics.unauthorized');
        assertApiError($res, 'UNAUTHORIZED', 'ops.tenant-metrics.unauthorized');
    });
}

function dbConnectionFromEnv(): PDO
{
    $dotenv = loadDotEnv(dirname(__DIR__, 2) . '/.env');

    $host = envValue('DB_HOST', (string) ($dotenv['DB_HOST'] ?? '127.0.0.1'));
    $port = envValue('DB_PORT', (string) ($dotenv['DB_PORT'] ?? '3306'));
    $name = envValue('DB_NAME', (string) ($dotenv['DB_NAME'] ?? 'parrepos'));
    $user = envValue('DB_USER', (string) ($dotenv['DB_USER'] ?? 'root'));
    $pass = envValue('DB_PASS', (string) ($dotenv['DB_PASS'] ?? ''));

    return new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name),
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
}

function getTenantStatus(PDO $db, int $tenantId): ?string
{
    $stmt = $db->prepare('SELECT status FROM tenants WHERE id = ? LIMIT 1');
    $stmt->execute([$tenantId]);
    $row = $stmt->fetch();
    return $row['status'] ?? null;
}

function setTenantStatus(PDO $db, int $tenantId, string $status): void
{
    $stmt = $db->prepare('UPDATE tenants SET status = ? WHERE id = ?');
    $stmt->execute([$status, $tenantId]);
}

function getTenantModulesRaw(PDO $db, int $tenantId): ?string
{
    $stmt = $db->prepare('SELECT modules FROM tenant_settings WHERE tenant_id = ? LIMIT 1');
    $stmt->execute([$tenantId]);
    $row = $stmt->fetch();
    return isset($row['modules']) ? (string) $row['modules'] : null;
}

function setTenantModulesRaw(PDO $db, int $tenantId, string $raw): void
{
    $stmt = $db->prepare('UPDATE tenant_settings SET modules = ?, updated_at = NOW() WHERE tenant_id = ?');
    $stmt->execute([$raw, $tenantId]);
}

function disableModulePos(string $modulesRaw): string
{
    $decoded = json_decode($modulesRaw, true);
    if (!is_array($decoded)) {
        return json_encode([], JSON_UNESCAPED_UNICODE) ?: '[]';
    }

    if (isset($decoded['modules']) && is_array($decoded['modules'])) {
        $decoded['modules']['pos']['enabled'] = false;
        return json_encode($decoded, JSON_UNESCAPED_UNICODE) ?: $modulesRaw;
    }

    $filtered = [];
    foreach ($decoded as $value) {
        if (is_string($value) && $value !== 'pos') {
            $filtered[] = $value;
        }
    }

    return json_encode($filtered, JSON_UNESCAPED_UNICODE) ?: $modulesRaw;
}

function getTenantPlanModulesRaw(PDO $db, int $tenantId): ?string
{
    $stmt = $db->prepare(
        'SELECT p.modules_json
         FROM tenant_subscriptions ts
         INNER JOIN plans p ON p.id = ts.plan_id
         WHERE ts.tenant_id = ?
         LIMIT 1'
    );
    $stmt->execute([$tenantId]);
    $row = $stmt->fetch();
    return isset($row['modules_json']) ? (string) $row['modules_json'] : null;
}

function setTenantPlanModulesRaw(PDO $db, int $tenantId, string $raw): void
{
    $stmt = $db->prepare(
        'UPDATE plans p
         INNER JOIN tenant_subscriptions ts ON ts.plan_id = p.id
         SET p.modules_json = ?, p.updated_at = NOW()
         WHERE ts.tenant_id = ?'
    );
    $stmt->execute([$raw, $tenantId]);
}

function disableModuleFromPlan(string $modulesRaw, string $module): string
{
    $decoded = json_decode($modulesRaw, true);
    if (!is_array($decoded)) {
        return json_encode([], JSON_UNESCAPED_UNICODE) ?: '[]';
    }

    $filtered = [];
    foreach ($decoded as $value) {
        if (is_string($value) && $value !== $module) {
            $filtered[] = $value;
        }
    }

    return json_encode(array_values($filtered), JSON_UNESCAPED_UNICODE) ?: $modulesRaw;
}

function getTenantPlanLimitsRaw(PDO $db, int $tenantId): ?string
{
    $stmt = $db->prepare(
        'SELECT p.limits_json
         FROM tenant_subscriptions ts
         INNER JOIN plans p ON p.id = ts.plan_id
         WHERE ts.tenant_id = ?
         LIMIT 1'
    );
    $stmt->execute([$tenantId]);
    $row = $stmt->fetch();
    return isset($row['limits_json']) ? (string) $row['limits_json'] : null;
}

function setTenantPlanLimitsRaw(PDO $db, int $tenantId, string $raw): void
{
    $stmt = $db->prepare(
        'UPDATE plans p
         INNER JOIN tenant_subscriptions ts ON ts.plan_id = p.id
         SET p.limits_json = ?, p.updated_at = NOW()
         WHERE ts.tenant_id = ?'
    );
    $stmt->execute([$raw, $tenantId]);
}

function forcePlanLimit(string $limitsRaw, string $key, int $value): string
{
    $decoded = json_decode($limitsRaw, true);
    if (!is_array($decoded)) {
        $decoded = [];
    }

    $decoded[$key] = max(0, $value);
    return json_encode($decoded, JSON_UNESCAPED_UNICODE) ?: $limitsRaw;
}

function getCashSummary(string $baseUrl, string $accessToken, int $cashSessionId): array
{
    $res = requestJson(
        'GET',
        $baseUrl . '/api/v1/pos/cash-movements/summary?cash_session_id=' . $cashSessionId,
        null,
        authHeaders($accessToken)
    );

    assertStatus($res, 200, 'cash.summary');
    assertApiOk($res, 'cash.summary');

    return [
        'in_total' => (float) ($res['json']['data']['in_total'] ?? 0),
        'out_total' => (float) ($res['json']['data']['out_total'] ?? 0),
    ];
}

function getInventoryStock(string $baseUrl, string $accessToken, int $branchId, int $itemId): float
{
    $res = requestJson(
        'GET',
        $baseUrl . '/api/v1/inventory/stock?branch_id=' . $branchId . '&item_id=' . $itemId,
        null,
        authHeaders($accessToken)
    );

    assertStatus($res, 200, 'inventory.stock');
    assertApiOk($res, 'inventory.stock');

    $rows = $res['json']['data'] ?? [];
    if (!is_array($rows) || $rows === []) {
        return 0.0;
    }

    $stock = (float) ($rows[0]['stock'] ?? 0);
    return $stock;
}

function requestJsonConcurrentPair(string $method, string $url, array $payload, array $headers): array
{
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($body === false) {
        fail('No se pudo serializar payload concurrente');
    }

    $requests = [];
    for ($i = 0; $i < 2; $i++) {
        $ch = curl_init();
        if ($ch === false) {
            fail('No se pudo inicializar cURL concurrente');
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => array_merge(['Accept: application/json', 'Content-Type: application/json'], $headers),
            CURLOPT_POSTFIELDS => $body,
        ]);

        $requests[] = $ch;
    }

    $mh = curl_multi_init();
    if ($mh === false) {
        fail('No se pudo inicializar curl_multi');
    }

    foreach ($requests as $ch) {
        curl_multi_add_handle($mh, $ch);
    }

    do {
        $status = curl_multi_exec($mh, $running);
        if ($running) {
            curl_multi_select($mh, 1.0);
        }
    } while ($running && $status === CURLM_OK);

    $responses = [];
    foreach ($requests as $ch) {
        $raw = curl_multi_getcontent($ch);
        if ($raw === false) {
            $error = curl_error($ch);
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
            curl_multi_close($mh);
            fail('Error HTTP concurrente: ' . $error);
        }

        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $bodyRaw = (string) substr($raw, $headerSize);
        $json = json_decode($bodyRaw, true);

        $responses[] = [
            'status' => $httpCode,
            'body_raw' => $bodyRaw,
            'json' => is_array($json) ? $json : null,
        ];

        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }

    curl_multi_close($mh);
    return $responses;
}

function requestJson(string $method, string $url, ?array $payload = null, array $headers = []): array
{
    $ch = curl_init();
    if ($ch === false) {
        fail('No se pudo inicializar cURL');
    }

    $defaultHeaders = ['Accept: application/json'];
    $body = null;

    if ($payload !== null) {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            fail('No se pudo serializar payload JSON');
        }
        $defaultHeaders[] = 'Content-Type: application/json';
    }

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => array_merge($defaultHeaders, $headers),
    ]);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    $raw = curl_exec($ch);
    if ($raw === false) {
        $error = curl_error($ch);
        curl_close($ch);
        fail('Error HTTP: ' . $error);
    }

    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $bodyRaw = (string) substr($raw, $headerSize);
    curl_close($ch);

    $json = json_decode($bodyRaw, true);
    return [
        'status' => $status,
        'body_raw' => $bodyRaw,
        'json' => is_array($json) ? $json : null,
    ];
}

function authHeaders(string $accessToken): array
{
    return ['Authorization: Bearer ' . $accessToken];
}

function assertStatus(array $res, int $expected, string $scope): void
{
    if ($res['status'] !== $expected) {
        fail($scope . ': status esperado ' . $expected . ', recibido ' . $res['status'] . '. Body=' . $res['body_raw']);
    }
}

function assertApiOk(array $res, string $scope): void
{
    if (!is_array($res['json'])) {
        fail($scope . ': respuesta no es JSON valido. Body=' . $res['body_raw']);
    }

    if (($res['json']['ok'] ?? false) !== true) {
        fail($scope . ': ok=false. Body=' . $res['body_raw']);
    }
}

function assertApiError(array $res, string $expectedCode, string $scope): void
{
    if (!is_array($res['json'])) {
        fail($scope . ': respuesta no es JSON valido. Body=' . $res['body_raw']);
    }

    if (($res['json']['ok'] ?? true) !== false) {
        fail($scope . ': se esperaba ok=false. Body=' . $res['body_raw']);
    }

    $code = (string) ($res['json']['error']['code'] ?? '');
    if ($code !== $expectedCode) {
        fail($scope . ': error.code esperado ' . $expectedCode . ', recibido ' . $code . '. Body=' . $res['body_raw']);
    }
}

function assertTrue(bool $value, string $scope): void
{
    if (!$value) {
        fail($scope . ': se esperaba true');
    }
}

function assertFloatEquals(float $actual, float $expected, string $scope, float $eps = 0.0001): void
{
    if (abs($actual - $expected) > $eps) {
        fail($scope . ': esperado ' . $expected . ', recibido ' . $actual);
    }
}

function assertArrayHasKeys(array $data, array $keys, string $scope): void
{
    foreach ($keys as $key) {
        if (!array_key_exists((string) $key, $data)) {
            fail($scope . ': falta key ' . $key);
        }
    }
}

function runStep(string $name, callable $fn): void
{
    $GLOBALS['SMOKE_STATS']['steps_total']++;
    $GLOBALS['SMOKE_STATS']['current_step'] = $name;
    fwrite(STDOUT, '[STEP] ' . $name . "\n");
    $fn();
    $GLOBALS['SMOKE_STATS']['steps_ok']++;
    $GLOBALS['SMOKE_STATS']['current_step'] = '';
    fwrite(STDOUT, '[OK] ' . $name . "\n");
}

function smokeInfo(string $message): void
{
    fwrite(STDOUT, '[INFO] ' . $message . "\n");
}

function fail(string $message): void
{
    $currentStep = (string) ($GLOBALS['SMOKE_STATS']['current_step'] ?? '');
    $mode = (string) ($GLOBALS['SMOKE_STATS']['mode'] ?? 'unknown');
    $ok = (int) ($GLOBALS['SMOKE_STATS']['steps_ok'] ?? 0);
    $total = (int) ($GLOBALS['SMOKE_STATS']['steps_total'] ?? 0);

    fwrite(STDERR, '[FAIL] ' . $message . "\n");
    fwrite(
        STDERR,
        '[REPORT] mode=' . $mode
        . ' step=' . ($currentStep !== '' ? $currentStep : 'n/a')
        . ' progress=' . $ok . '/' . $total
        . "\n"
    );
    exit(1);
}

function envValue(string $key, string $default): string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }

    return (string) $value;
}

function loadDotEnv(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $values = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return [];
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $k = trim($parts[0]);
        $v = trim($parts[1]);
        $values[$k] = trim($v, "\"'");
    }

    return $values;
}

function parseModeFromCli(array $argv, string $fallback): string
{
    foreach ($argv as $arg) {
        if ($arg === '--positive') {
            return 'positive';
        }
        if ($arg === '--negative') {
            return 'negative';
        }
        if ($arg === '--all') {
            return 'all';
        }
        if (str_starts_with($arg, '--mode=')) {
            return strtolower((string) substr($arg, 7));
        }
    }

    return $fallback;
}

function uuidV4(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

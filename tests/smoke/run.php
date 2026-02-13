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

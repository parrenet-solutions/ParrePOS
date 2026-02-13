<?php

http_response_code(200);
header('Content-Type: application/json');
echo json_encode([
    'ok' => true,
    'data' => ['status' => 'ok'],
    'meta' => [
        'request_id' => $_SERVER['HTTP_X_REQUEST_ID'] ?? 'n/a',
        'ts' => gmdate('c'),
    ],
]);

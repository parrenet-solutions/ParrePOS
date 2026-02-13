<?php

namespace App\Core;

class Response
{
    public static function ok(mixed $data, string $requestId, int $status = 200): void
    {
        self::send($status, [
            'ok' => true,
            'data' => $data,
            'meta' => self::meta($requestId),
        ], $requestId);
    }

    public static function error(string $code, string $message, string $requestId, int $status = 400, array $details = []): void
    {
        $payload = [
            'ok' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
            'meta' => self::meta($requestId),
        ];

        if (!empty($details)) {
            $payload['error']['details'] = $details;
        }

        self::send($status, $payload, $requestId);
    }

    private static function meta(string $requestId): array
    {
        return [
            'request_id' => $requestId,
            'ts' => gmdate('c'),
        ];
    }

    private static function send(int $status, array $payload, string $requestId): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        header('X-Request-Id: ' . $requestId);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    }
}

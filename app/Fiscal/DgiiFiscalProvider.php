<?php

namespace App\Fiscal;

use RuntimeException;

class DgiiFiscalProvider implements FiscalProviderInterface
{
    private ?string $endpoint;
    private int $timeoutSeconds;

    public function __construct(?string $endpoint, int $timeoutSeconds = 20)
    {
        $this->endpoint = $endpoint !== null ? trim($endpoint) : null;
        $this->timeoutSeconds = max(5, $timeoutSeconds);
    }

    public function submit(int $tenantId, int $fiscalDocumentId, array $payload, array $context): array
    {
        if ($this->endpoint === null || $this->endpoint === '') {
            throw new RuntimeException('DGII endpoint no configurado');
        }

        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL no disponible para provider DGII');
        }

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            throw new RuntimeException('No se pudo serializar payload fiscal');
        }

        $signature = (string) ($context['signature'] ?? '');
        $requestHash = (string) ($context['request_hash'] ?? '');

        $ch = curl_init($this->endpoint);
        if ($ch === false) {
            throw new RuntimeException('No se pudo inicializar cURL');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'X-Fiscal-Signature: ' . $signature,
                'X-Fiscal-Hash: ' . $requestHash,
            ],
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_HEADER => false,
        ]);

        $raw = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException('DGII no disponible: ' . $curlError);
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            $decoded = ['raw' => (string) $raw];
        }

        if ($httpCode >= 500) {
            throw new RuntimeException('DGII error servidor HTTP ' . $httpCode);
        }

        if ($httpCode >= 400) {
            return [
                'ack_code' => strtoupper((string) ($decoded['ack_code'] ?? 'REJECTED')),
                'ack_message' => (string) ($decoded['ack_message'] ?? ('DGII rechazo request HTTP ' . $httpCode)),
                'track_id' => isset($decoded['track_id']) ? (string) $decoded['track_id'] : null,
                'retryable' => false,
                'response_payload' => $decoded,
            ];
        }

        return [
            'ack_code' => strtoupper((string) ($decoded['ack_code'] ?? 'ACCEPTED')),
            'ack_message' => (string) ($decoded['ack_message'] ?? 'Documento procesado por DGII'),
            'track_id' => isset($decoded['track_id']) ? (string) $decoded['track_id'] : null,
            'retryable' => false,
            'response_payload' => $decoded,
        ];
    }
}

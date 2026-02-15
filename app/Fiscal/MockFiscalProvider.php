<?php

namespace App\Fiscal;

class MockFiscalProvider implements FiscalProviderInterface
{
    public function submit(int $tenantId, int $fiscalDocumentId, array $payload, array $context): array
    {
        $trackId = 'MOCK-' . strtoupper(bin2hex(random_bytes(6)));

        return [
            'ack_code' => 'ACCEPTED',
            'ack_message' => 'Documento aceptado por mock provider',
            'track_id' => $trackId,
            'retryable' => false,
            'response_payload' => [
                'provider' => 'MOCK',
                'track_id' => $trackId,
                'status' => 'ACCEPTED',
                'received_at' => gmdate('c'),
            ],
        ];
    }
}

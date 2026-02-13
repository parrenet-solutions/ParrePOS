<?php

namespace App\Pos;

use App\Shared\Exceptions\HttpException;

class CashMovementService
{
    public function validate(array $payload): array
    {
        $type = strtoupper(trim((string) ($payload['type'] ?? '')));
        $amount = isset($payload['amount']) ? (float) $payload['amount'] : 0.0;
        $description = trim((string) ($payload['description'] ?? ''));

        if (!in_array($type, ['IN', 'OUT'], true)) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'type inválido');
        }

        if ($amount <= 0) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'amount inválido');
        }

        if ($description === '') {
            throw new HttpException(422, 'VALIDATION_ERROR', 'description requerido');
        }

        return [
            'type' => $type,
            'amount' => $amount,
            'reason_code' => isset($payload['reason_code']) ? trim((string) $payload['reason_code']) : null,
            'description' => $description,
            'reference' => isset($payload['reference']) ? trim((string) $payload['reference']) : null,
        ];
    }
}

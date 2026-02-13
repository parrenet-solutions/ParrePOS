<?php

namespace App\Pos;

use App\Shared\Exceptions\HttpException;

class CashSessionService
{
    public function validateOpen(array $payload): array
    {
        $opening = isset($payload['opening_amount']) ? (float) $payload['opening_amount'] : null;
        $registerId = isset($payload['register_id']) ? (int) $payload['register_id'] : 0;
        $branchId = isset($payload['branch_id']) ? (int) $payload['branch_id'] : 0;

        if ($opening === null || $opening < 0) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'opening_amount inválido');
        }

        if ($registerId <= 0 || $branchId <= 0) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'register_id/branch_id inválidos');
        }

        return [
            'opening_amount' => $opening,
            'register_id' => $registerId,
            'branch_id' => $branchId,
        ];
    }

    public function validateClose(array $payload): float
    {
        $closing = isset($payload['closing_amount']) ? (float) $payload['closing_amount'] : null;
        if ($closing === null || $closing < 0) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'closing_amount inválido');
        }

        return $closing;
    }
}

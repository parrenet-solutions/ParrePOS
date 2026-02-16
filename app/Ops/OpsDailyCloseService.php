<?php

namespace App\Ops;

use App\Shared\Exceptions\HttpException;

class OpsDailyCloseService
{
    public function validateClosePayload(array $payload): array
    {
        $branchId = (int) ($payload['branch_id'] ?? 0);
        $closeDate = trim((string) ($payload['close_date'] ?? gmdate('Y-m-d')));
        $declaredCash = (float) ($payload['declared_cash'] ?? 0);
        $notes = trim((string) ($payload['notes'] ?? ''));

        if ($branchId <= 0) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'branch_id inválido');
        }

        if (!$this->isDate($closeDate)) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'close_date inválida (YYYY-MM-DD)');
        }

        if ($declaredCash < 0) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'declared_cash inválido');
        }

        return [
            'branch_id' => $branchId,
            'close_date' => $closeDate,
            'declared_cash' => round($declaredCash, 2),
            'notes' => $notes,
        ];
    }

    public function validateReopenPayload(array $payload): array
    {
        $reason = trim((string) ($payload['reason'] ?? ''));
        if ($reason === '') {
            throw new HttpException(422, 'VALIDATION_ERROR', 'reason es requerido');
        }

        return [
            'reason' => $reason,
        ];
    }

    private function isDate(string $value): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));
        return checkdate($month, $day, $year);
    }
}

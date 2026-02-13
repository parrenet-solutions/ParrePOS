<?php

namespace App\Recurring;

use App\Core\Validation\Validator;
use App\Shared\Exceptions\HttpException;

class RecurringService
{
    private Validator $validator;

    public function __construct(Validator $validator)
    {
        $this->validator = $validator;
    }

    public function validate(array $payload): array
    {
        $this->validator->required($payload, ['customer_id', 'items', 'interval_days']);

        if (!is_array($payload['items']) || count($payload['items']) === 0) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'Items requeridos');
        }

        $interval = (int) $payload['interval_days'];
        if ($interval <= 0) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'interval_days inválido');
        }

        $nextRunAt = date('Y-m-d H:i:s', strtotime('+'.$interval.' days'));

        return [
            'customer_id' => (int) $payload['customer_id'],
            'items' => $payload['items'],
            'interval_days' => $interval,
            'next_run_at' => $nextRunAt,
        ];
    }
}

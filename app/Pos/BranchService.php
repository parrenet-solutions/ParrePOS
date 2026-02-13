<?php

namespace App\Pos;

use App\Core\Validation\Validator;

class BranchService
{
    private Validator $validator;

    public function __construct(Validator $validator)
    {
        $this->validator = $validator;
    }

    public function validate(array $payload): array
    {
        $this->validator->required($payload, ['name']);

        return [
            'name' => trim((string) $payload['name']),
            'address' => isset($payload['address']) ? trim((string) $payload['address']) : null,
            'status' => isset($payload['status']) ? strtoupper((string) $payload['status']) : 'ACTIVE',
        ];
    }
}

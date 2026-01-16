<?php

namespace App\Templates;

use App\Core\Validation\Validator;
use App\Shared\Exceptions\HttpException;

class TemplateService
{
    private Validator $validator;

    public function __construct(Validator $validator)
    {
        $this->validator = $validator;
    }

    public function validate(array $payload): array
    {
        $this->validator->required($payload, ['name', 'config']);

        if (!is_array($payload['config'])) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'config inválido');
        }

        return [
            'name' => trim((string) $payload['name']),
            'config' => $payload['config'],
            'is_default' => !empty($payload['is_default']) ? 1 : 0,
        ];
    }
}

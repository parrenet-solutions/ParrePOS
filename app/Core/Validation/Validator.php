<?php

namespace App\Core\Validation;

use App\Shared\Exceptions\HttpException;

class Validator
{
    public function required(array $data, array $fields): void
    {
        foreach ($fields as $field) {
            if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
                throw new HttpException(422, 'VALIDATION_ERROR', 'Campo requerido: ' . $field);
            }
        }
    }

    public function enum(string $value, array $allowed, string $field): void
    {
        if (!in_array($value, $allowed, true)) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'Valor inválido en ' . $field);
        }
    }

    public function uniqueDocNumber(?string $docNumber): void
    {
        if ($docNumber !== null && trim($docNumber) === '') {
            throw new HttpException(422, 'VALIDATION_ERROR', 'doc_number inválido');
        }
    }
}

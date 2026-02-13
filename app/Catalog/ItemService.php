<?php

namespace App\Catalog;

use App\Core\Validation\Validator;
use App\Shared\Exceptions\HttpException;

class ItemService
{
    private Validator $validator;

    public function __construct(Validator $validator)
    {
        $this->validator = $validator;
    }

    public function validate(array $payload): array
    {
        $this->validator->required($payload, ['type', 'name', 'price', 'itbis_rate']);

        $type = strtoupper((string) $payload['type']);
        $this->validator->enum($type, ['PRODUCT', 'SERVICE'], 'type');

        $itbis = (float) $payload['itbis_rate'];
        if (!in_array($itbis, [0.0, 0.16, 0.18], true)) {
            $this->validator->enum((string) $itbis, ['0', '0.16', '0.18'], 'itbis_rate');
        }

        $price = (float) $payload['price'];
        if ($price < 0) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'price inválido');
        }

        return [
            'type' => $type,
            'name' => trim((string) $payload['name']),
            'price' => $price,
            'itbis_rate' => $itbis,
            'status' => isset($payload['status']) ? strtoupper((string) $payload['status']) : 'ACTIVE',
        ];
    }
}

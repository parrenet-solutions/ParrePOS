<?php

namespace App\Customers;

use App\Core\Validation\Validator;
use App\Shared\Exceptions\HttpException;

class CustomerService
{
    private Validator $validator;

    public function __construct(Validator $validator)
    {
        $this->validator = $validator;
    }

    public function validate(array $payload): array
    {
        $this->validator->required($payload, ['name', 'type']);

        $type = strtoupper((string) $payload['type']);
        $this->validator->enum($type, ['PERSON', 'BUSINESS'], 'type');

        $docNumber = $payload['doc_number'] ?? null;
        if ($docNumber !== null) {
            $docNumber = trim((string) $docNumber);
        }

        return [
            'name' => trim((string) $payload['name']),
            'type' => $type,
            'doc_number' => $docNumber === '' ? null : $docNumber,
            'email' => isset($payload['email']) ? trim((string) $payload['email']) : null,
            'phone' => isset($payload['phone']) ? trim((string) $payload['phone']) : null,
            'status' => isset($payload['status']) ? strtoupper((string) $payload['status']) : 'ACTIVE',
        ];
    }

    public function assertUniqueDocNumber(CustomerRepository $repo, int $tenantId, ?string $docNumber, ?int $excludeId = null): void
    {
        if ($repo->existsDocNumber($tenantId, $docNumber, $excludeId)) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'doc_number duplicado');
        }
    }
}

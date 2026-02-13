<?php

namespace App\Pos;

use App\Core\Validation\Validator;
use App\Shared\Exceptions\HttpException;

class RegisterService
{
    private Validator $validator;

    public function __construct(Validator $validator)
    {
        $this->validator = $validator;
    }

    public function validate(array $payload): array
    {
        $this->validator->required($payload, ['branch_id', 'name', 'device_id']);

        $deviceId = trim((string) $payload['device_id']);
        if ($deviceId === '') {
            throw new HttpException(422, 'VALIDATION_ERROR', 'device_id requerido');
        }

        return [
            'branch_id' => (int) $payload['branch_id'],
            'name' => trim((string) $payload['name']),
            'device_id' => $deviceId,
            'status' => isset($payload['status']) ? strtoupper((string) $payload['status']) : 'ACTIVE',
        ];
    }

    public function assertDeviceUnique(RegisterRepository $repo, int $tenantId, string $deviceId, ?int $excludeId = null): void
    {
        if ($repo->existsDevice($tenantId, $deviceId, $excludeId)) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'device_id duplicado');
        }
    }
}

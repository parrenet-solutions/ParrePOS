<?php

namespace App\Customers;

use App\Audit\AuditLogger;
use App\Core\Request;
use App\Shared\Exceptions\HttpException;

class CustomerController
{
    private CustomerRepository $repository;
    private CustomerService $service;
    private AuditLogger $audit;

    public function __construct(CustomerRepository $repository, CustomerService $service, AuditLogger $audit)
    {
        $this->repository = $repository;
        $this->service = $service;
        $this->audit = $audit;
    }

    public function create(Request $request): array
    {
        $payload = $request->getJson();
        if (!is_array($payload)) {
            throw new HttpException(400, 'INVALID_BODY', 'Se requiere JSON');
        }

        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $data = $this->service->validate($payload);
        $this->service->assertUniqueDocNumber($this->repository, $tenantId, $data['doc_number']);

        $id = $this->repository->create($tenantId, $data);
        $this->audit->log($tenantId, (int) $request->getAttribute('user_id', 0), 'customers.create', ['customer_id' => $id]);

        return ['status' => 201, 'data' => ['id' => $id]];
    }

    public function update(Request $request): array
    {
        $payload = $request->getJson();
        if (!is_array($payload)) {
            throw new HttpException(400, 'INVALID_BODY', 'Se requiere JSON');
        }

        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $id = (int) $request->getParam('id');

        $existing = $this->repository->findById($tenantId, $id);
        if (!$existing) {
            throw new HttpException(404, 'NOT_FOUND', 'Cliente no encontrado');
        }

        $data = $this->service->validate($payload);
        $this->service->assertUniqueDocNumber($this->repository, $tenantId, $data['doc_number'], $id);

        $this->repository->update($tenantId, $id, $data);
        $this->audit->log($tenantId, (int) $request->getAttribute('user_id', 0), 'customers.update', ['customer_id' => $id]);

        return ['status' => 200, 'data' => ['id' => $id]];
    }

    public function get(Request $request): array
    {
        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $id = (int) $request->getParam('id');

        $customer = $this->repository->findById($tenantId, $id);
        if (!$customer) {
            throw new HttpException(404, 'NOT_FOUND', 'Cliente no encontrado');
        }

        return ['status' => 200, 'data' => $customer];
    }

    public function list(Request $request): array
    {
        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $search = $request->getQuery()['q'] ?? null;

        $data = $this->repository->list($tenantId, $search);
        return ['status' => 200, 'data' => $data];
    }
}

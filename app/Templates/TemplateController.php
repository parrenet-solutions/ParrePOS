<?php

namespace App\Templates;

use App\Audit\AuditLogger;
use App\Core\Request;
use App\Shared\Exceptions\HttpException;

class TemplateController
{
    private TemplateRepository $repository;
    private TemplateService $service;
    private AuditLogger $audit;

    public function __construct(TemplateRepository $repository, TemplateService $service, AuditLogger $audit)
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
        $id = $this->repository->create($tenantId, $data);

        if ($data['is_default']) {
            $this->repository->setDefault($tenantId, $id);
        }

        $this->audit->log($tenantId, (int) $request->getAttribute('user_id', 0), 'templates.create', ['template_id' => $id]);

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
            throw new HttpException(404, 'NOT_FOUND', 'Template no encontrado');
        }

        $data = $this->service->validate($payload);
        $this->repository->update($tenantId, $id, $data);
        if ($data['is_default']) {
            $this->repository->setDefault($tenantId, $id);
        }

        $this->audit->log($tenantId, (int) $request->getAttribute('user_id', 0), 'templates.update', ['template_id' => $id]);

        return ['status' => 200, 'data' => ['id' => $id]];
    }

    public function get(Request $request): array
    {
        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $id = (int) $request->getParam('id');

        $template = $this->repository->findById($tenantId, $id);
        if (!$template) {
            throw new HttpException(404, 'NOT_FOUND', 'Template no encontrado');
        }

        return ['status' => 200, 'data' => $template];
    }

    public function list(Request $request): array
    {
        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        return ['status' => 200, 'data' => $this->repository->list($tenantId)];
    }

    public function setDefault(Request $request): array
    {
        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $id = (int) $request->getParam('id');

        $template = $this->repository->findById($tenantId, $id);
        if (!$template) {
            throw new HttpException(404, 'NOT_FOUND', 'Template no encontrado');
        }

        $this->repository->setDefault($tenantId, $id);
        $this->audit->log($tenantId, (int) $request->getAttribute('user_id', 0), 'templates.set_default', ['template_id' => $id]);

        return ['status' => 200, 'data' => ['id' => $id, 'is_default' => true]];
    }
}

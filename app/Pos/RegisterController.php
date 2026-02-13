<?php

namespace App\Pos;

use App\Audit\AuditLogger;
use App\Core\Request;
use App\Core\Security\RateLimiter;
use App\Shared\Exceptions\HttpException;
use App\Settings\TenantSettingsRepository;

class RegisterController
{
    private RegisterRepository $repository;
    private RegisterService $service;
    private BranchRepository $branchRepository;
    private AuditLogger $audit;
    private TenantSettingsRepository $settingsRepository;
    private RegisterHandshakeService $handshakeService;
    private RateLimiter $rateLimiter;

    public function __construct(
        RegisterRepository $repository,
        RegisterService $service,
        BranchRepository $branchRepository,
        AuditLogger $audit,
        TenantSettingsRepository $settingsRepository,
        RegisterHandshakeService $handshakeService,
        RateLimiter $rateLimiter
    ) {
        $this->repository = $repository;
        $this->service = $service;
        $this->branchRepository = $branchRepository;
        $this->audit = $audit;
        $this->settingsRepository = $settingsRepository;
        $this->handshakeService = $handshakeService;
        $this->rateLimiter = $rateLimiter;
    }

    public function create(Request $request): array
    {
        $payload = $request->getJson();
        if (!is_array($payload)) {
            throw new HttpException(400, 'INVALID_BODY', 'Se requiere JSON');
        }

        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $data = $this->service->validate($payload);
        $this->service->assertDeviceUnique($this->repository, $tenantId, $data['device_id']);

        if (!$this->branchRepository->findById($tenantId, $data['branch_id'])) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'Sucursal inválida');
        }

        $id = $this->repository->create($tenantId, $data);
        $this->audit->log($tenantId, (int) $request->getAttribute('user_id', 0), 'registers.create', ['register_id' => $id]);

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

        if (!$this->repository->findById($tenantId, $id)) {
            throw new HttpException(404, 'NOT_FOUND', 'Caja no encontrada');
        }

        $data = $this->service->validate($payload);
        $this->service->assertDeviceUnique($this->repository, $tenantId, $data['device_id'], $id);

        if (!$this->branchRepository->findById($tenantId, $data['branch_id'])) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'Sucursal inválida');
        }

        $this->repository->update($tenantId, $id, $data);
        $this->audit->log($tenantId, (int) $request->getAttribute('user_id', 0), 'registers.update', ['register_id' => $id]);

        return ['status' => 200, 'data' => ['id' => $id]];
    }

    public function get(Request $request): array
    {
        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $id = (int) $request->getParam('id');

        $register = $this->repository->findById($tenantId, $id);
        if (!$register) {
            throw new HttpException(404, 'NOT_FOUND', 'Caja no encontrada');
        }

        return ['status' => 200, 'data' => $register];
    }

    public function list(Request $request): array
    {
        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        $search = $request->getQuery()['q'] ?? null;

        return ['status' => 200, 'data' => $this->repository->list($tenantId, $search)];
    }

    public function handshake(Request $request): array
    {
        $payload = $request->getJson();
        if (!is_array($payload)) {
            throw new HttpException(400, 'INVALID_BODY', 'Se requiere JSON');
        }

        $deviceId = trim((string) ($payload['device_id'] ?? ''));
        if ($deviceId === '') {
            throw new HttpException(422, 'VALIDATION_ERROR', 'device_id requerido');
        }

        $tenantId = (int) $request->getAttribute('tenant_id', 0);

        $rateKey = 'handshake:' . $tenantId . ':' . $deviceId;
        if ($this->rateLimiter->tooManyAttempts($rateKey, 30, 60)) {
            throw new HttpException(429, 'RATE_LIMIT', 'Demasiadas solicitudes');
        }

        $register = $this->repository->findByDevice($tenantId, $deviceId);
        if (!$register) {
            throw new HttpException(404, 'REGISTER_NOT_FOUND', 'Caja no encontrada');
        }

        $branch = $this->branchRepository->findById($tenantId, (int) $register['branch_id']);
        if (!$branch) {
            throw new HttpException(404, 'REGISTER_NOT_FOUND', 'Sucursal no encontrada');
        }

        $auditKey = 'audit:handshake:' . $tenantId . ':' . $deviceId . ':' . date('Ymd');
        if ($this->rateLimiter->allowOnce($auditKey, 86400)) {
            $this->audit->log($tenantId, (int) $request->getAttribute('user_id', 0), 'pos.register.handshake', [
                'register_id' => $register['id'],
                'device_id' => $deviceId,
                'app_version' => $payload['app_version'] ?? null,
            ]);
        }

        return ['status' => 200, 'data' => $this->handshakeService->buildResponse($tenantId, $register, $branch)];
    }
}

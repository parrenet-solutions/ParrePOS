<?php

namespace App\Tenancy;

use App\Core\Middleware\MiddlewareInterface;
use App\Core\Request;
use App\Settings\TenantSettingsRepository;
use App\Shared\Exceptions\HttpException;

class TenantGuardMiddleware implements MiddlewareInterface
{
    private TenantSettingsRepository $settingsRepository;
    private ?string $requiredModule;

    public function __construct(TenantSettingsRepository $settingsRepository, ?string $requiredModule = null)
    {
        $this->settingsRepository = $settingsRepository;
        $this->requiredModule = $requiredModule;
    }

    public function requireModule(string $module): self
    {
        return new self($this->settingsRepository, $module);
    }

    public function handle(Request $request, callable $next): array
    {
        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        if ($tenantId <= 0) {
            throw new HttpException(401, 'UNAUTHORIZED', 'Tenant requerido');
        }

        $status = $this->settingsRepository->getTenantStatus($tenantId);
        if ($status !== 'ACTIVE') {
            throw new HttpException(403, 'TENANT_SUSPENDED', 'Tenant suspendido');
        }

        if ($this->requiredModule) {
            $modules = $this->settingsRepository->getModules($tenantId);
            if (!in_array($this->requiredModule, $modules, true)) {
                throw new HttpException(403, 'MODULE_DISABLED', 'Módulo no habilitado');
            }
        }

        return $next($request);
    }
}

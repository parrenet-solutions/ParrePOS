<?php

namespace App\Tenancy;

use App\Core\Middleware\MiddlewareInterface;
use App\Core\Request;
use App\Plans\PlanService;
use App\Settings\TenantSettingsRepository;
use App\Shared\Exceptions\HttpException;

class TenantGuardMiddleware implements MiddlewareInterface
{
    private TenantSettingsRepository $settingsRepository;
    private PlanService $planService;
    private ?string $requiredModule;
    private ?string $requiredLimitKey;

    public function __construct(
        TenantSettingsRepository $settingsRepository,
        PlanService $planService,
        ?string $requiredModule = null,
        ?string $requiredLimitKey = null
    )
    {
        $this->settingsRepository = $settingsRepository;
        $this->planService = $planService;
        $this->requiredModule = $requiredModule;
        $this->requiredLimitKey = $requiredLimitKey;
    }

    public function requireModule(string $module): self
    {
        return new self($this->settingsRepository, $this->planService, $module, $this->requiredLimitKey);
    }

    public function requireLimit(string $limitKey): self
    {
        return new self($this->settingsRepository, $this->planService, $this->requiredModule, $limitKey);
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
            $planModules = $this->planService->getAllowedModules($tenantId);

            $moduleAllowedByPlan = $planModules === [] || in_array($this->requiredModule, $planModules, true);
            if (!in_array($this->requiredModule, $modules, true) || !$moduleAllowedByPlan) {
                throw new HttpException(403, 'MODULE_DISABLED', 'Módulo no habilitado');
            }
        }

        if ($this->requiredLimitKey) {
            $current = $this->settingsRepository->countForLimit($tenantId, $this->requiredLimitKey);
            $this->planService->enforceLimit($tenantId, $this->requiredLimitKey, $current);
        }

        return $next($request);
    }
}

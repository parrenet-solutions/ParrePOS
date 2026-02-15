<?php

namespace App\Tenancy;

use App\Core\Middleware\MiddlewareInterface;
use App\Core\Request;
use App\Plans\PlanService;
use App\Settings\TenantSettingsRepository;
use App\Shared\Exceptions\HttpException;

class FiscalGuardMiddleware implements MiddlewareInterface
{
    private TenantSettingsRepository $settingsRepository;
    private PlanService $planService;

    public function __construct(TenantSettingsRepository $settingsRepository, PlanService $planService)
    {
        $this->settingsRepository = $settingsRepository;
        $this->planService = $planService;
    }

    public function handle(Request $request, callable $next): array
    {
        $tenantId = (int) $request->getAttribute('tenant_id', 0);
        if ($tenantId <= 0) {
            throw new HttpException(401, 'UNAUTHORIZED', 'Tenant requerido');
        }

        $fiscal = $this->settingsRepository->getFiscalConfig($tenantId);
        if (($fiscal['enabled'] ?? false) !== true) {
            return $next($request);
        }

        $modules = $this->settingsRepository->getModules($tenantId);
        $planModules = $this->planService->getAllowedModules($tenantId);
        $moduleAllowedByPlan = $planModules === [] || in_array('fiscal', $planModules, true);

        if (!in_array('fiscal', $modules, true) || !$moduleAllowedByPlan) {
            throw new HttpException(403, 'MODULE_DISABLED', 'Módulo fiscal no habilitado');
        }

        if (($fiscal['dgii_registered'] ?? false) !== true) {
            throw new HttpException(403, 'FISCAL_DGII_REQUIRED', 'Tenant no registrado en DGII');
        }

        $ncfType = strtoupper(trim((string) ($fiscal['ncf_type'] ?? '')));
        if (!preg_match('/^[A-Z][0-9]{2}$/', $ncfType)) {
            throw new HttpException(422, 'FISCAL_CONFIG_INVALID', 'Configuración fiscal inválida');
        }

        return $next($request);
    }
}

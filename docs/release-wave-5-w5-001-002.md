# Entrega Técnica Wave 5 (W5-001 y W5-002)

## Resumen
Se implementó la base reusable de planes y límites por tenant, junto con catálogo de módulos por plan para bloquear funcionalidades de forma consistente sin duplicar lógica.

## W5-001 Planes y límites por tenant

Implementado:
- Migración `database/migrations/005_plans_base.sql`:
  - `plans`
  - `tenant_subscriptions`
- Seed de desarrollo:
  - plan `STARTER`
  - suscripción activa para tenant demo
- Servicio reusable:
  - `app/Plans/PlanRepository.php`
  - `app/Plans/PlanService.php`
- Enforcement de límites en middleware tenant:
  - `PLAN_LIMIT_EXCEEDED` con detalles (`limit_key`, `current`, `max`, `plan_code`).
- Límites aplicados en endpoints create:
  - `customers.max`
  - `items.max`
  - `invoices.max`
  - `branches.max`
  - `registers.max`

## W5-002 Catálogo de módulos por plan

Implementado:
- Resolución de módulos permitidos desde plan activo.
- Validación combinada:
  - módulo habilitado en `tenant_settings`
  - módulo permitido por plan
- Aplicado en:
  - `TenantGuardMiddleware`
  - `FiscalGuardMiddleware`

## Smoke / validación incorporada

`tests/smoke/run.php` añade validaciones negativas:
- `PlanGuard MODULE_DISABLED_BY_PLAN`
- `PlanGuard LIMIT_EXCEEDED`

Además, `GET /api/v1/me` ahora retorna `plan` resuelto para observabilidad del tenant autenticado.

## Archivos clave
- `app/Plans/PlanRepository.php`
- `app/Plans/PlanService.php`
- `app/Tenancy/TenantGuardMiddleware.php`
- `app/Tenancy/FiscalGuardMiddleware.php`
- `app/Users/UserController.php`
- `app/Settings/TenantSettingsRepository.php`
- `app/Bootstrap/App.php`
- `database/migrations/005_plans_base.sql`
- `database/schema.sql`
- `bin/console.php`
- `tests/smoke/run.php`

## Validación sugerida
```bash
php bin/console.php migrate
php bin/console.php seed:dev
php tests/smoke/run.php --negative
php tests/smoke/run.php --all
```

## Nota
- Se mantiene el principio de **fiscal opcional** por tenant y el enfoque modular del proyecto.

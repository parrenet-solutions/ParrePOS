# Entrega Técnica Wave 7 (W7-001 y W7-002)

Fecha: `2026-02-16`
Estado: `COMPLETADO`

## W7-001 Matriz modular extendida

Implementación:
- Extensión de módulos opcionales en `tenant_settings` seed:
  - `payments_plus`, `accounting`, `hardware_bridge`, `backup_ops`, `security_plus`.
- `payments_plus` queda **deshabilitado por defecto** por tenant.
- Plan `STARTER` actualizado para permitir activación gradual de módulos nuevos (sin forzar habilitación).
- Permisos nuevos RBAC:
  - `payments_plus.read`
  - `payments_plus.manage`

Archivos clave:
- `bin/console.php`
- `app/Settings/TenantSettingsRepository.php`
- `tests/smoke/run.php`

## W7-002 Payments Plus opcional

Implementación:
- Migración base:
  - `database/migrations/014_payments_plus_base.sql`
- Esquema consolidado:
  - `database/schema.sql`
- Módulo backend:
  - `app/PaymentsPlus/PaymentsPlusController.php`
  - `app/PaymentsPlus/PaymentsPlusService.php`
  - `app/PaymentsPlus/PaymentsPlusRepository.php`
  - `app/PaymentsPlus/PaymentProviderInterface.php`
  - `app/PaymentsPlus/MockPaymentProvider.php`
  - `app/PaymentsPlus/PaymentProviderFactory.php`
- Rutas nuevas:
  - `POST /api/v1/payments-plus/webhook` (pública con key)
  - `GET /api/v1/payments-plus/status`
  - `POST /api/v1/payments-plus/transactions/intent`
  - `GET /api/v1/payments-plus/transactions`
  - `GET /api/v1/payments-plus/transactions/{id}`
  - `POST /api/v1/payments-plus/reconcile/daily`
  - `GET /api/v1/payments-plus/reconciliations`

Criterios cubiertos:
- idempotencia en intents por (`tenant_id`, `provider`, `idempotency_key`).
- idempotencia en webhook por (`tenant_id`, `provider`, `webhook_event_id`).
- conciliación diaria por tenant/proveedor.
- módulo opcional protegido por guard de módulo + plan.

## Documentación actualizada
- `docs/api.md`
- `docs/api-modules.md`
- `docs/requests.http`
- `docs/context.md`
- `.env.example` (`PAYMENTS_PLUS_WEBHOOK_KEY`)

## QA recomendado
1. `php bin/console.php migrate`
2. `php bin/console.php migrate:status`
3. `php bin/console.php seed:dev`
4. `php tests/smoke/run.php --negative`
5. Activar módulo `payments_plus` para tenant demo y validar requests nuevos.
6. `php tests/smoke/run.php --all`

Nota:
- En este entorno no se pudo ejecutar `php` (binario no disponible), por lo que QA queda pendiente en tu entorno Windows/XAMPP.

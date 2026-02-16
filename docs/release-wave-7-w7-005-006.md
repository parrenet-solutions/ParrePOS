# Entrega Técnica Wave 7 (W7-005 y W7-006)

Fecha: `2026-02-16`  
Estado: `COMPLETADO`

## W7-005 Inventario comercial (mínimos/máximos y alertas)

Implementación:
- Migración:
  - `database/migrations/017_inventory_minmax_alerts.sql`
- Nuevas capacidades:
  - políticas `min/max/reorder` por `tenant_id + branch_id + item_id`
  - alertas de stock (`LOW_STOCK`, `OVERSTOCK`) consultables por API
  - sugerencia de reposición basada en `reorder_qty`/brecha contra `max_qty`
- Cambios de backend:
  - `app/Inventory/InventoryAlertService.php`
  - `app/Inventory/InventoryRepository.php`
  - `app/Inventory/InventoryController.php`
- Endpoints nuevos:
  - `PUT /api/v1/inventory/policies/minmax`
  - `GET /api/v1/inventory/policies/minmax`
  - `GET /api/v1/inventory/alerts`

## W7-006 Cierre operativo diario (caja/ventas/cobros)

Implementación:
- Migración:
  - `database/migrations/018_ops_daily_closure.sql`
- Bitácora de cierre diario por sucursal:
  - tabla `ops_daily_closures` con estado `CLOSED/REOPENED`
  - reapertura controlada con motivo obligatorio
- Consolidado operativo diario:
  - apertura, movimientos IN/OUT, ventas, pagos, efectivo esperado, efectivo declarado y diferencia
- Cambios de backend:
  - `app/Ops/OpsDailyCloseService.php`
  - `app/Ops/OpsRepository.php`
  - `app/Ops/OpsController.php`
- Endpoints nuevos:
  - `POST /api/v1/ops/daily-close`
  - `GET /api/v1/ops/daily-close`
  - `GET /api/v1/ops/daily-close/{id}`
  - `POST /api/v1/ops/daily-close/{id}/reopen`
- Permisos nuevos:
  - `ops.daily_close.read`
  - `ops.daily_close.manage`

## Integración y guardas
- Rutas y DI actualizados en `app/Bootstrap/App.php`.
- Seed/permisos actualizados en `bin/console.php`.
- Esquema consolidado actualizado en `database/schema.sql`.
- Multi-tenant estricto preservado (`tenant_id` en todas las consultas nuevas).

## QA recomendado
1. `php bin/console.php migrate`
2. `php bin/console.php migrate:status`
3. `php bin/console.php seed:dev`
4. `php tests/smoke/run.php --negative`
5. Pruebas manuales API (`docs/requests.http`) para inventory policies/alerts y ops daily close.
6. `php tests/smoke/run.php --all`

Resultado esperado:
- Validaciones en entorno XAMPP con smoke y migrate en verde.

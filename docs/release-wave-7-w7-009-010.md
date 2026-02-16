# Entrega Técnica Wave 7 (W7-009 y W7-010)

Fecha: `2026-02-16`  
Estado: `COMPLETADO`

## W7-009 Observabilidad avanzada y alertamiento

Implementación:
- Migración:
  - `database/migrations/021_ops_observability_alerts.sql`
- Extensión de módulo `ops`:
  - SLI/SLO snapshot por tenant
  - reglas de alertas por umbral
  - evaluación manual de alertas
  - incidentes consultables
- Endpoints nuevos:
  - `GET /api/v1/ops/sli-slo`
  - `GET /api/v1/ops/alerts/rules`
  - `PUT /api/v1/ops/alerts/rules`
  - `POST /api/v1/ops/alerts/evaluate`
  - `GET /api/v1/ops/incidents`
- Permisos nuevos:
  - `ops.alerts.manage`

## W7-010 Onboarding y toolkit de soporte

Implementación:
- Migración:
  - `database/migrations/022_ops_onboarding_toolkit.sql`
- Extensión de módulo `ops`:
  - diagnóstico de instalación/configuración por tenant
  - plantillas de onboarding por tipo de cliente
  - checklist versionado por tenant
- Endpoints nuevos:
  - `GET /api/v1/ops/diagnostics`
  - `GET /api/v1/ops/onboarding/templates`
  - `GET /api/v1/ops/onboarding/checklist`
  - `PUT /api/v1/ops/onboarding/checklist`
- Permisos nuevos:
  - `ops.onboarding.manage`

## Integración y guardas
- Rutas y DI actualizados en `app/Bootstrap/App.php`.
- Seed/permisos actualizados en `bin/console.php`.
- Multi-tenant estricto preservado en todas las consultas nuevas (`tenant_id`).

## QA recomendado
1. `php bin/console.php migrate`
2. `php bin/console.php migrate:status`
3. `php bin/console.php seed:dev`
4. `php tests/smoke/run.php --positive`
5. `php tests/smoke/run.php --negative`
6. `php tests/smoke/run.php --all`

Nota:
- En este entorno no se ejecutó `php` (binario no disponible), por lo que la validación queda pendiente en tu entorno XAMPP.

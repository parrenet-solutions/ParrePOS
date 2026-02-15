# Backlog Ejecutable - Wave 5

Objetivo: consolidar operación SaaS con planes/límites, inventario integrado, offline POS robusto, observabilidad y hardening.

## Estado general
- `W5-001`: COMPLETADO
- `W5-002`: COMPLETADO
- `W5-003`: COMPLETADO
- `W5-004`: COMPLETADO
- `W5-005`: COMPLETADO
- `W5-006`: COMPLETADO
- `W5-007`: COMPLETADO
- `W5-008`: COMPLETADO
- `W5-009`: COMPLETADO
- `W5-010`: COMPLETADO

## W5-001 Planes y límites por tenant
- Base de planes (`plans`) + suscripción por tenant (`tenant_subscriptions`).
- Enforcement de límites por recurso con `PLAN_LIMIT_EXCEEDED`.

## W5-002 Catálogo de módulos por plan
- Módulos permitidos definidos por plan.
- Enforzados junto con `tenant_settings.modules` en guards de módulo.

## W5-003 Inventario MVP multi-sucursal
- Kardex base por sucursal/item.

## W5-004 POS <-> Inventario
- Descuento/reverso automático de stock.

## W5-005 Offline POS v2
- Cola local robusta + replay seguro.

## W5-006 Resolución de conflictos sync
- Política por tipo de evento + endpoint de soporte.

## W5-007 Observabilidad SaaS
- KPIs por tenant y tableros operativos.

## W5-008 Hardening seguridad
- Revisión de rate limiting/permisos críticos/sesiones.

## W5-009 Smoke/regresión ampliada
- Gate de calidad extendido.

## W5-010 Cierre Wave 5
- Acta final + release notes + tag baseline `wave-5-baseline`.

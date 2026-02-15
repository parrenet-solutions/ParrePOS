# Release Notes - Wave 5 (Final)

Fecha: `2026-02-15`

## Resumen
Wave 5 consolida la operación SaaS con control de planes, inventario transaccional en POS, offline sync robusto, observabilidad por tenant y hardening de seguridad, manteniendo fiscal como módulo opcional.

## Completado

### W5-001 Planes y límites
- Planes base y suscripciones por tenant.
- Límites por recurso con respuesta estándar `PLAN_LIMIT_EXCEEDED`.

### W5-002 Módulos por plan
- Catálogo de módulos permitidos por plan.
- Enforcement combinado: plan + `tenant_settings.modules`.

### W5-003 Inventario multi-sucursal
- Kardex base por sucursal/item.
- Movimientos manuales de inventario (`IN/OUT`) con stock consultable.

### W5-004 POS integrado a inventario
- Venta POS descuenta stock en la sucursal.
- Void de venta revierte stock de forma transaccional.

### W5-005 Offline POS v2
- Outbox local con estado (`PENDING`, `SYNCING`, `FAILED`, `CONFLICT`) y reintentos.
- Replay seguro con idempotencia.

### W5-006 Conflictos de sync
- Detección de conflicto por `op_id` + hash de payload.
- Persistencia en `sync_conflicts`.
- Conteo de conflictos en `sync/status`.

### W5-007 Observabilidad operativa
- `GET /api/v1/ops/tenant-metrics` para KPIs de sync/conflictos/jobs/fiscal.
- `GET /api/v1/ops/sync-conflicts` para soporte operativo.

### W5-008 Hardening seguridad
- Rate limit reforzado en auth (login/refresh/logout).
- Rate limit reforzado en sync (`sync/events`, `sync/status`).

### W5-009 Smoke/regresión ampliada
- Suite smoke con validación de contratos (`sync` y `ops`).
- Caso negativo adicional de seguridad en endpoint `ops`.

### W5-010 Cierre de wave
- Actas por bloque y acta final consolidadas.
- Checklist de baseline/tag para congelamiento de release.

## Resultado operativo
- Wave 5 queda lista para congelar baseline con tag `wave-5-baseline`.
- Siguiente foco natural: arranque de Wave 6 según `docs/roadmap-wave-5-6.md`.

## Nota de producto
- Fiscal continúa siendo opcional por tenant.
- Clientes no regularizados pueden operar en flujo no fiscal sin bloqueo.

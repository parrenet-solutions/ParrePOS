# Release Notes - Wave 2.2

Fecha: `2026-02-13`

## Resumen
Wave 2.2 cierra el hardening operativo de POS + Sync + Worker con foco en seguridad, idempotencia y estabilidad de ejecucion.

## Cambios principales

### Seguridad y tenancy
- Login multi-tenant estricto por `tenant_slug + email`.
- Validacion de tenant activo en login (`TENANT_SUSPENDED`).
- Tenant guard aplicado en rutas de negocio.
- Validacion de modulo habilitado por tenant (`MODULE_DISABLED`).

### RBAC y Sync
- Separacion de permisos:
  - `sync.write` para `POST /api/v1/sync/events`
  - `sync.read` para `GET /api/v1/sync/status`
- Documentacion API y requests alineadas al contrato real.

### Idempotencia
- Cash movements: duplicate key devuelve `data.duplicate=true` sin side effects.
- Sync ingest: eventos repetidos devuelven `status=duplicate` por evento.

### Cola, retries y DLQ
- Cola persistente en BD (`jobs_queue`).
- Reintentos con backoff simple.
- DLQ para jobs agotados (`jobs_dlq`).
- Worker resiliente con `complete/fail` por job y recuperacion de jobs estancados.

### Auditoria
- Eventos criticos con audit append-only en POS y Sync:
  - `pos.cash.open`, `pos.cash.close`
  - `pos.cash.movement.create`, `pos.cash.movement.reverse`
  - `sync.ingest.request`, `sync.ingest`, `sync.applied`, `sync.duplicate`, `sync.failed`

### Migraciones
- `000_schema_migrations.sql`
- `001_initial_schema.sql`
- `002_jobs_queue.sql`
- `003_sync_events_applied_at.sql`

### Calidad y validacion
- Smoke suite ampliada:
  - `--positive`
  - `--negative`
  - `--all`
- Reporte de progreso y fallo en smoke (`[REPORT] mode=... step=... progress=x/y`).

## Estado de release
- Wave 2.2: **APROBADA**
- Baseline congelada con tag: `wave-2.2`

## Siguiente recomendado
- Inicio de Wave 3 (Fiscal) con modulo `fiscal` por feature flag, contrato inicial y tablas base.

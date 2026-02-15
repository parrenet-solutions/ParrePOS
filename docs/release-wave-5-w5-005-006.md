# Entrega Técnica Wave 5 (W5-005 y W5-006)

## Resumen
Se implementó una base de outbox offline v2 en PWA con estados/reintentos, y detección de conflictos en `sync/events` por `op_id` con trazabilidad para soporte.

## W5-005 Offline POS v2

Implementado:
- Outbox PWA con metadatos de estado:
  - `PENDING`
  - `SYNCING`
  - `FAILED`
  - `CONFLICT`
- Reintentos controlados (`MAX_RETRIES=3`).
- Manejo diferencial de resultados:
  - `applied`/`duplicate` => limpia outbox
  - `failed` => incrementa reintentos
  - `conflict` => conserva en outbox con estado conflicto

Archivos:
- `public/pwa/pos-demo.js`
- `public/pwa/pos-demo.html`

## W5-006 Resolución de conflictos sync

Implementado:
- `sync/events` soporta `op_id` opcional por evento.
- Detección de conflicto:
  - mismo `device_id + type + op_id`
  - payload distinto (`payload_hash` diferente)
- Resultado por evento:
  - `status=conflict`
  - sin side effect
- Persistencia de conflicto en `sync_conflicts`.
- Métrica de estado:
  - `sync/status` incluye `conflict_count`.

Archivos:
- `database/migrations/008_sync_conflicts_v2.sql`
- `database/schema.sql`
- `app/Sync/SyncRepository.php`
- `app/Sync/SyncController.php`
- `app/Sync/SyncStatusRepository.php`

## Smoke / validación

Se añadió caso automático:
- `Sync Conflict by OpId`

Archivo:
- `tests/smoke/run.php`

## Validación sugerida
```bash
php bin/console.php migrate
php bin/console.php seed:dev
php tests/smoke/run.php --positive
php tests/smoke/run.php --all
```

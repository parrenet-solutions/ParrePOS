# Entrega Técnica Wave 5 (W5-007 y W5-008)

## Resumen
Se implementa base de observabilidad operativa por tenant y hardening de seguridad sobre rate limiting en auth/sync.

## W5-007 Observabilidad operativa SaaS

Implementado:
- Endpoint `GET /api/v1/ops/tenant-metrics`:
  - métricas sync
  - métricas conflictos
  - métricas jobs/DLQ
  - métricas fiscales
- Endpoint `GET /api/v1/ops/sync-conflicts`:
  - listado de conflictos con filtros (`resolved`, `device_id`, `type`)
- Capa reusable:
  - `app/Ops/OpsRepository.php`
  - `app/Ops/OpsController.php`

## W5-008 Hardening de seguridad operativa

Implementado:
- Auth rate limit endurecido:
  - login por IP
  - login por tenant+IP
  - login por tenant+email
  - refresh por IP y fingerprint de token
  - logout por IP
- Sync rate limit endurecido:
  - ingest por IP y por tenant+device
  - status por IP y por tenant+device

Archivos clave:
- `app/Auth/AuthController.php`
- `app/Sync/SyncController.php`

## Integración API/Router
- `app/Bootstrap/App.php`
- permisos para ops apoyados en `audit.read`.

## Validación sugerida
```bash
php bin/console.php migrate
php bin/console.php seed:dev
php tests/smoke/run.php --positive
php tests/smoke/run.php --all
```

Validación manual adicional:
```bash
GET /api/v1/ops/tenant-metrics
GET /api/v1/ops/sync-conflicts?resolved=0&limit=50
```

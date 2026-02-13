# Acta Release Wave 2.2

**Fecha:** `2026-02-13`  
**Ambiente:** `local`  
**Commit:** `78dc28b`  
**Responsable:** `Equipo POS SaaS`

## 1. Migraciones
- Comando: `php bin/console.php migrate:status`
- Resultado: `OK`
- Evidencia:
```text
Pendientes: 0
Migraciones aplicadas:
- 000_schema_migrations.sql
- 001_initial_schema.sql
- 002_jobs_queue.sql
- 003_sync_events_applied_at.sql
```

## 2. Seed
- Comando: `php bin/console.php seed:dev`
- Resultado: `OK`
- Evidencia:
```text
Seed de desarrollo completado.
```

## 3. Smoke positivo
- Comando: `php tests/smoke/run.php --positive`
- Resultado: `OK`
- Evidencia:
```text
SMOKE OK: modo=positive pasos=10/10
```

## 4. Smoke negativo
- Comando: `php tests/smoke/run.php --negative`
- Resultado: `OK`
- Evidencia:
```text
SMOKE OK: modo=negative pasos=5/5
```

## 5. Smoke completo
- Comando: `php tests/smoke/run.php --all`
- Resultado: `OK`
- Evidencia:
```text
SMOKE OK: modo=all pasos=12/12
```

## 6. Permisos Sync (RBAC)
- SQL ejecutado: `sync.read` + `sync.write` para rol `OWNER`
- Resultado: `OK`
- Evidencia:
```text
sync.read
sync.write
```

## 7. Auditoria critica
- SQL ejecutado sobre `audit_log` (`pos.cash.*`, `sync.*`)
- Resultado: `OK`
- Evidencia:
```text
Se registran eventos de:
- pos.cash.open
- pos.cash.close
- pos.cash.movement.create
- pos.cash.movement.reverse
- sync.ingest.request
- sync.ingest
- sync.applied
- sync.duplicate
- sync.failed
```

## 8. Cola y DLQ
- SQL ejecutado sobre `jobs_queue` y `jobs_dlq`
- Resultado: `OK`
- Evidencia:
```text
jobs_queue con actividad (COMPLETED/RETRY según pruebas)
jobs_dlq sin bloqueantes para release
```

## 9. Guard de endpoints POS/Sync
- Comando: `rg -n "'/api/v1/(pos|sync)" app/Bootstrap/App.php`
- Resultado: `OK`
- Evidencia:
```text
Rutas POS/Sync protegidas con Auth + TenantGuard + AuthorizationMiddleware
```

## Checklist final
- [x] Migraciones sin pendientes
- [x] Seed exitoso
- [x] Smoke tests OK
- [x] Guards negativos validados
- [x] Permisos sync validados
- [x] Auditoria critica con registros
- [x] Cola/DLQ revisada
- [x] Evidencia adjunta

## Dictamen
- **Estado:** `APROBADO`
- **Notas / bloqueos:**
```text
Wave 2.2 validada y congelada como baseline.
```

# Acta Release Wave 2.2

**Fecha:** `YYYY-MM-DD`
**Ambiente:** `local/staging/prod`
**Commit:** `<hash>`
**Responsable:** `<nombre>`

## 1. Migraciones
- Comando: `php bin/console.php migrate:status`
- Resultado: `OK/FAIL`
- Evidencia:
- [PENDING] 000_schema_migrations.sql
- [PENDING] 001_initial_schema.sql
- [PENDING] 002_jobs_queue.sql
Pendientes: 3
```

## 2. Seed
- Comando: `php bin/console.php seed:dev`
- Resultado: `OK/FAIL`
- Evidencia:
Seed de desarrollo completado.
```

## 3. Smoke positivo
- Comando: `php tests/smoke/run.php --positive`
- Resultado: `OK/FAIL`
- Evidencia:
[STEP] Health
[OK] Health
[STEP] Login
[OK] Login
[STEP] Me
[OK] Me
[STEP] Crear Sucursal
[OK] Crear Sucursal
[STEP] Crear Caja
[OK] Crear Caja
[STEP] Abrir Caja
[OK] Abrir Caja
[STEP] Cash Movement Idempotency Duplicate
[OK] Cash Movement Idempotency Duplicate
[STEP] Cash Movement Idempotency Concurrent Basic
[OK] Cash Movement Idempotency Concurrent Basic
[STEP] Sync Status
[FAIL] sync.status: status esperado 200, recibido 500. Body={"ok":false,"error":{"code":"SERVER_ERROR","message":"Error interno"},"meta":{"request_id":"2fb4b93092cb526275d8ca4e96cc53e4","ts":"2026-02-13T22:03:58+00:00"}}
[REPORT] mode=positive step=Sync Status progress=8/9
```

## 4. Smoke negativo
- Comando: `php tests/smoke/run.php --negative`
- Resultado: `OK/FAIL`
- Evidencia:
[STEP] Health
[OK] Health
[STEP] Login
[OK] Login
[STEP] Me
[OK] Me
[STEP] TenantGuard TENANT_SUSPENDED
[OK] TenantGuard TENANT_SUSPENDED
[STEP] TenantGuard MODULE_DISABLED
[OK] TenantGuard MODULE_DISABLED

SMOKE OK: modo=negative pasos=5/5
```

## 5. Smoke completo
- Comando: `php tests/smoke/run.php --all`
- Resultado: `OK/FAIL`
- Evidencia:
[STEP] Health
[OK] Health
[STEP] Login
[OK] Login
[STEP] Me
[OK] Me
[STEP] Crear Sucursal
[OK] Crear Sucursal
[STEP] Crear Caja
[OK] Crear Caja
[STEP] Abrir Caja
[OK] Abrir Caja
[STEP] Cash Movement Idempotency Duplicate
[OK] Cash Movement Idempotency Duplicate
[STEP] Cash Movement Idempotency Concurrent Basic
[OK] Cash Movement Idempotency Concurrent Basic
[STEP] Sync Status
[FAIL] sync.status: status esperado 200, recibido 500. Body={"ok":false,"error":{"code":"SERVER_ERROR","message":"Error interno"},"meta":{"request_id":"b7f7e816b053d8fb1e0f890f89b0e468","ts":"2026-02-13T22:08:23+00:00"}}
[REPORT] mode=all step=Sync Status progress=8/9
```

## 6. Permisos Sync (RBAC)
- SQL ejecutado: `sync.read` + `sync.write` para rol `OWNER`
- Resultado: `OK/FAIL`
- Evidencia:
```text
<pegar resultado SQL>
```

## 7. Auditoria critica
- SQL ejecutado sobre `audit_log` (`pos.cash.*`, `sync.*`)
- Resultado: `OK/FAIL`
- Evidencia:
```text
<pegar resultado SQL>
```

## 8. Cola y DLQ
- SQL ejecutado sobre `jobs_queue` y `jobs_dlq`
- Resultado: `OK/FAIL`
- Evidencia:
```text
<pegar resultado SQL>
```

## 9. Guard de endpoints POS/Sync
- Comando: `rg -n "'/api/v1/(pos|sync)" app/Bootstrap/App.php`
- Resultado: `OK/FAIL`
- Evidencia:
```text
<pegar salida>
```

## Checklist final
- [ ] Migraciones sin pendientes
- [ ] Seed exitoso
- [ ] Smoke tests OK
- [ ] Guards negativos validados
- [ ] Permisos sync validados
- [ ] Auditoria critica con registros
- [ ] Cola/DLQ revisada
- [ ] Evidencia adjunta

## Dictamen
- **Estado:** `APROBADO / BLOQUEADO`
- **Notas / bloqueos:**
```text
<detalle>
```

# Acta Release Wave 4 (W4-001 y W4-002)

**Fecha:** `2026-02-15`  
**Ambiente:** `local`  
**Responsable:** `Equipo POS SaaS`

## 1. Seed
- Comando: `php bin/console.php seed:dev`
- Resultado: `OK`
- Evidencia:
```text
Seed de desarrollo completado.
```

## 2. Smoke positivo
- Comando: `php tests/smoke/run.php --positive`
- Resultado: `OK`
- Evidencia:
```text
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
[OK] Sync Status
[STEP] Fiscal Status
[OK] Fiscal Status
[STEP] Fiscal Documents List
[OK] Fiscal Documents List
[STEP] Sync Ingest Idempotency Duplicate
[OK] Sync Ingest Idempotency Duplicate

SMOKE OK: modo=positive pasos=12/12
```

## 3. Smoke negativo
- Comando: `php tests/smoke/run.php --negative`
- Resultado: `OK`
- Evidencia:
```text
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
[STEP] Fiscal Config Validation
[OK] Fiscal Config Validation
[STEP] Fiscal Webhook Unauthorized
[OK] Fiscal Webhook Unauthorized

SMOKE OK: modo=negative pasos=7/7
```

## 4. Estado por ticket
- `W4-001` Adapter fiscal desacoplado (`MOCK`/`DGII`): `COMPLETADO`
- `W4-002` Firma/hash y secretos de envío fiscal: `COMPLETADO`

## Checklist final
- [x] Seed exitoso
- [x] Smoke positivo OK
- [x] Smoke negativo OK
- [x] Fiscal mantiene no-obligatoriedad
- [x] Seguridad webhook negativo validada
- [x] Evidencia adjunta

## Dictamen
- **Estado:** `APROBADO`
- **Notas:**
```text
W4-001 y W4-002 cerrados.
Se mantiene compatibilidad con tenants no fiscales.
```

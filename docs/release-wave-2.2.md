# Release Gate - Wave 2.2

Objetivo: validar que Wave 2.2 esta lista para salida con evidencia minima reproducible.

## Alcance

Cubre:
- Migraciones y seed
- Smoke tests positivos y negativos
- Guards multi-tenant/modulos
- Permisos sync (`sync.read`/`sync.write`)
- Auditoria de eventos criticos POS/sync
- Cola de jobs con reintentos y DLQ

## Prerrequisitos

- `.env` configurado
- Base de datos accesible
- API levantada en `http://localhost`
- PHP 8.2+ disponible

## Gate de salida

### 1) Migraciones

Comando:
```bash
php bin/console.php migrate:status
```

Criterio:
- `Pendientes: 0`
- Incluye al menos:
  - `000_schema_migrations.sql`
  - `001_initial_schema.sql`
  - `002_jobs_queue.sql`

### 2) Seed de desarrollo

Comando:
```bash
php bin/console.php seed:dev
```

Criterio:
- finaliza sin error
- usuario demo operativo para smoke

### 3) Smoke test positivo

Comando:
```bash
php tests/smoke/run.php --positive
```

Criterio:
- salida final `SMOKE OK`
- incluye pasos de POS y sync sin fallos

### 4) Smoke test negativo

Comando:
```bash
php tests/smoke/run.php --negative
```

Criterio:
- salida final `SMOKE OK`
- valida:
  - `TENANT_SUSPENDED`
  - `MODULE_DISABLED`

### 5) Smoke test completo

Comando:
```bash
php tests/smoke/run.php --all
```

Criterio:
- salida final `SMOKE OK`

### 6) Permisos Sync por rol demo

SQL:
```sql
SELECT p.code
FROM permissions p
INNER JOIN role_permissions rp ON rp.permission_id = p.id
INNER JOIN roles r ON r.id = rp.role_id
WHERE r.code = 'OWNER'
  AND p.code IN ('sync.read', 'sync.write');
```

Criterio:
- devuelve ambas filas: `sync.read`, `sync.write`

### 7) Auditoria de eventos criticos

SQL:
```sql
SELECT action, COUNT(*) AS cnt
FROM audit_log
WHERE action IN (
  'pos.cash.open',
  'pos.cash.close',
  'pos.cash.movement.create',
  'pos.cash.movement.reverse',
  'sync.ingest.request',
  'sync.ingest',
  'sync.applied',
  'sync.duplicate',
  'sync.failed'
)
GROUP BY action
ORDER BY action;
```

Criterio:
- existen registros para los eventos ejecutados durante pruebas

### 8) Cola y DLQ

SQL:
```sql
SELECT status, COUNT(*) AS cnt
FROM jobs_queue
GROUP BY status
ORDER BY status;
```

```sql
SELECT queue_name, COUNT(*) AS cnt
FROM jobs_dlq
GROUP BY queue_name
ORDER BY queue_name;
```

Criterio:
- `jobs_queue` muestra actividad (al menos `COMPLETED`)
- `jobs_dlq` vacia o con fallos conocidos/aceptados

### 9) Endpoint guards (inspeccion rapida)

Comando:
```bash
rg -n "'/api/v1/(pos|sync)" app/Bootstrap/App.php
```

Criterio:
- rutas de POS/sync tienen `AuthMiddleware` + `TenantGuard` + `AuthorizationMiddleware`

## Evidencia minima a guardar

Guardar en ticket/PR:
- salida de `migrate:status`
- salida de `seed:dev`
- salida de smoke (`--positive`, `--negative` o `--all`)
- resultados SQL de auditoria y cola
- hash del commit validado

## Cierre de gate

Checklist final:
- [ ] Migraciones sin pendientes
- [ ] Seed exitoso
- [ ] Smoke tests OK
- [ ] Guards negativos validados
- [ ] Permisos sync validados
- [ ] Auditoria critica con registros
- [ ] Cola/DLQ revisada
- [ ] Evidencia adjunta

Resultado:
- [ ] APROBADO para salida Wave 2.2
- [ ] BLOQUEADO (documentar causa)

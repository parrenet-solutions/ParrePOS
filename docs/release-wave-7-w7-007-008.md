# Entrega Técnica Wave 7 (W7-007 y W7-008)

Fecha: `2026-02-16`  
Estado: `COMPLETADO`

## W7-007 Backup/restore operativo (opcional por plan)

Implementación:
- Migración:
  - `database/migrations/019_backup_ops_base.sql`
- Módulo:
  - `app/BackupOps/BackupOpsController.php`
  - `app/BackupOps/BackupOpsService.php`
  - `app/BackupOps/BackupOpsRepository.php`
- Endpoints nuevos:
  - `POST /api/v1/backup/snapshots`
  - `GET /api/v1/backup/snapshots`
  - `POST /api/v1/backup/restores`
  - `GET /api/v1/backup/restores`
  - `GET /api/v1/backup/runbook`
- Permisos nuevos:
  - `backup_ops.read`
  - `backup_ops.manage`

## W7-008 Seguridad empresarial (opcional)

Implementación:
- Migración:
  - `database/migrations/020_security_plus_base.sql`
- Módulo:
  - `app/SecurityPlus/SecurityPlusController.php`
  - `app/SecurityPlus/SecurityPlusService.php`
  - `app/SecurityPlus/SecurityPlusRepository.php`
- Endpoints nuevos:
  - `GET /api/v1/security-plus/status`
  - `PUT /api/v1/security-plus/mfa/totp`
  - `POST /api/v1/security-plus/mfa/verify`
  - `GET /api/v1/security-plus/mfa/methods`
  - `POST /api/v1/security-plus/secrets/rotate`
  - `GET /api/v1/security-plus/secrets/rotations`
- Permisos nuevos:
  - `security_plus.read`
  - `security_plus.manage`

## Integración y guardas
- Rutas y DI actualizados en `app/Bootstrap/App.php`.
- Seed/permisos y módulos por plan actualizados en `bin/console.php`.
- Esquema consolidado actualizado en `database/schema.sql`.
- Multi-tenant estricto preservado (`tenant_id` en consultas nuevas).

## QA recomendado
1. `php bin/console.php migrate`
2. `php bin/console.php migrate:status`
3. `php bin/console.php seed:dev`
4. `php tests/smoke/run.php --negative`
5. Pruebas manuales API (`docs/requests.http`) para backup/security.
6. `php tests/smoke/run.php --all`

Nota:
- En este entorno no se ejecutó `php` (binario no disponible), por lo que la validación queda pendiente en tu entorno XAMPP.

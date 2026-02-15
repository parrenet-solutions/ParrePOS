# ParrePos (Wave 4 - Cerrada)

Bootstrap base de un SaaS POS multi-tenant en PHP 8.2 puro.

## Requisitos
- PHP 8.2+
- MariaDB
- (Opcional) Redis + extensión `redis`
- Apache (XAMPP) apuntando DocumentRoot a `/public`

## Instalación rápida
1) Copiar `.env.example` a `.env` y ajustar credenciales.
2) Crear base de datos:
```sql
CREATE DATABASE parrepos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```
3) Ejecutar migraciones:
```bash
php bin/console.php migrate
```
4) Configurar Apache/XAMPP:
- DocumentRoot -> `/mnt/c/xampp/htdocs/pos-saas/public`
- Habilitar `mod_rewrite`

## Migraciones
- Estado de migraciones:
```bash
php bin/console.php migrate:status
```
- Aplicar pendientes:
```bash
php bin/console.php migrate
```
- Bootstrap de migraciones:
`database/migrations/000_schema_migrations.sql`
- Migracion inicial creada desde `database/schema.sql`:
`database/migrations/001_initial_schema.sql`

## Seed de desarrollo
Ejecuta el seed para crear tenant demo + admin:
```bash
php bin/console.php seed:dev
```

## Worker (PDF / Email / Recurrentes)
Ejecuta el worker para procesar cola persistente en BD (señal opcional por Redis):
```bash
php bin/worker.php run
```
- Cola persistente en BD (`jobs_queue`) con reintentos + backoff simple.
- Jobs agotados pasan a `jobs_dlq` con `last_error`.

## PWA POS (demo)
- URL: `http://localhost/pwa/pos-demo.html`
- Requiere `modules.pos.enabled=true` en tenant_settings.
- La UI demo guarda eventos en IndexedDB y los sincroniza por `/api/v1/sync/events`.

## Backoffice Fiscal (Wave 4)
- URL: `http://localhost/fiscal-backoffice.html`
- Requiere token Bearer del tenant.
- Permite búsqueda de documentos, consulta de eventos/acuses y retry (individual/masivo).

## Operativa de caja
- Los movimientos manuales (IN/OUT) se registran en `pos_cash_movements`.
- El arqueo usa: apertura + ventas en efectivo + entradas - salidas (change_total futuro).

## Variables de entorno
Ver `.env.example` para los valores base.
- `FISCAL_WEBHOOK_KEY`: llave para `POST /api/v1/fiscal/webhook/ack`.
- `FISCAL_PROVIDER_DEFAULT`: provider fiscal por defecto (`MOCK` o `DGII`).
- `FISCAL_DGII_URL`: endpoint DGII para provider `DGII`.
- `FISCAL_DGII_TIMEOUT`: timeout HTTP de envío fiscal.
- `FISCAL_SIGNING_SECRET`: secreto global fallback para firma HMAC fiscal.
- `FISCAL_ALERT_EMAILS`: lista de correos para alertas fiscales (`a@x.com,b@y.com`).
- `FISCAL_ALERT_WEBHOOK_URL`: webhook interno opcional para alertas fiscales.

## Endpoints
Ver `docs/api.md`.

## Estado actual
- Estado consolidado: `docs/project-status.md`
- Contexto operativo: `context.md`
- Checklist salida Wave 2.2: `docs/release-wave-2.2.md`
- Cierre Wave 3:
  - `docs/release-wave-3-acta.md`
  - `docs/release-wave-3-acta-w3-006-010.md`
  - `docs/release-wave-3-acta-w3-011-017.md`
- Wave 4 (cerrada):
  - `docs/release-wave-4-acta-w4-001-002.md`
  - `docs/release-wave-4-acta-w4-003-004.md`
  - `docs/release-wave-4-acta-w4-005.md`
  - `docs/release-wave-4-acta-w4-006-007.md`
  - `docs/release-wave-4-acta-w4-008-009.md`
  - `docs/release-wave-4-w4-005.md`
  - `docs/release-wave-4-w4-006-007.md`
  - `docs/release-wave-4-w4-008-009.md`
  - `docs/release-wave-4-w4-010.md`
  - `docs/release-wave-4-checklist-w4-010.md`
  - `docs/release-wave-4-acta-final.md`
  - `docs/release-notes-wave-4.md`
  - `docs/release-wave-4-backlog.md`

## Probar con Rest Client
Usa `docs/requests.http` (VS Code REST Client) y completa `{{access_token}}` y `{{refresh_token}}`.

## Smoke tests
Suite minima automatizada para flujo base API (auth + POS + sync):
```bash
php tests/smoke/run.php
```

Variables opcionales:
- `SMOKE_BASE_URL` (default: `http://localhost`)
- `SMOKE_TENANT_SLUG` (default: `demo`)
- `SMOKE_EMAIL` (default: `admin@demo.local`)
- `SMOKE_PASSWORD` (default: `Admin12345!`)
- `SMOKE_MODE` (default: `all`)
: `positive` (happy path), `negative` (guards/idempotencia negativa), `all` (ambos)
- `negative/all` actualiza temporalmente `tenants.status` y `tenant_settings.modules` y luego restaura los valores originales.

Atajos por CLI:
- `php tests/smoke/run.php --positive`
- `php tests/smoke/run.php --negative`
- `php tests/smoke/run.php --all`

## Notas
- Respuestas siguen el formato `ok/error` definido en `AGENTS.md`.
- Los tokens refresh se guardan hashed y se rotan en cada refresh.
- Facturacion fiscal se mantiene opcional por tenant.

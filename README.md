# ParrePos (Wave 0)

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

## Operativa de caja
- Los movimientos manuales (IN/OUT) se registran en `pos_cash_movements`.
- El arqueo usa: apertura + ventas en efectivo + entradas - salidas (change_total futuro).

## Variables de entorno
Ver `.env.example` para los valores base.

## Endpoints
Ver `docs/api.md`.
Checklist de salida Wave 2.2: `docs/release-wave-2.2.md`

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

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
3) Ejecutar el esquema:
```bash
mysql -u root -p parrepos < database/schema.sql
```
4) Configurar Apache/XAMPP:
- DocumentRoot -> `/mnt/c/xampp/htdocs/pos-saas/public`
- Habilitar `mod_rewrite`

## Seed de desarrollo
Ejecuta el seed para crear tenant demo + admin:
```bash
php bin/console.php seed:dev
```

## Worker (PDF / Email / Recurrentes)
Ejecuta el worker para procesar colas (Redis) o pendientes en BD:
```bash
php bin/worker.php run
```

## Variables de entorno
Ver `.env.example` para los valores base.

## Endpoints
Ver `docs/api.md`.

## Probar con Rest Client
Usa `docs/requests.http` (VS Code REST Client) y completa `{{access_token}}` y `{{refresh_token}}`.

## Notas
- Respuestas siguen el formato `ok/error` definido en `AGENTS.md`.
- Los tokens refresh se guardan hashed y se rotan en cada refresh.

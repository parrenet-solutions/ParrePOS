# ParrePOS

Plataforma SaaS multi-tenant en PHP 8.2 para facturacion, POS offline-first y operacion modular por tenant en Republica Dominicana.

## Indice
- [1. Estado actual](#1-estado-actual)
- [2. Requisitos](#2-requisitos)
- [3. Instalacion rapida](#3-instalacion-rapida)
- [4. Comandos operativos](#4-comandos-operativos)
- [5. Documentacion clave](#5-documentacion-clave)
- [6. Como contribuir](#6-como-contribuir)
- [7. Notas de producto](#7-notas-de-producto)

## 1. Estado actual
Estado de ejecucion global: **Wave 5 cerrada**.

Estado bandera historico:
- **Wave 2.2 completado** con POS, pagos mixtos, HOLD/RESUME, movimientos de caja, sync offline, handshake y sync status.

Estado consolidado por etapas:
- `Wave 0`: Fundaciones - cerrada.
- `Wave 1`: Facturacion tradicional 8.5x11 - cerrada.
- `Wave 2` (incluye 2.1/2.2): POS offline-first - cerrada.
- `Wave 3`: Fiscal opcional base - cerrada.
- `Wave 4`: Robustez fiscal/operativa - cerrada.
- `Wave 5`: Operacion SaaS (planes, inventario, observabilidad, hardening) - cerrada.
- `Wave 6`: planificada.

## 2. Requisitos
- PHP 8.2+
- MariaDB
- Redis + extension `redis` (opcional)
- Apache (XAMPP) con DocumentRoot en `/public`

## 3. Instalacion rapida
1. Copiar `.env.example` a `.env` y ajustar credenciales.
2. Crear base de datos:
```sql
CREATE DATABASE parrepos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```
3. Ejecutar migraciones:
```bash
php bin/console.php migrate
```
4. Seed de desarrollo:
```bash
php bin/console.php seed:dev
```

## 4. Comandos operativos
Estado de migraciones:
```bash
php bin/console.php migrate:status
```

Aplicar migraciones:
```bash
php bin/console.php migrate
```

Worker:
```bash
php bin/worker.php run
```

Smoke tests:
```bash
php tests/smoke/run.php --positive
php tests/smoke/run.php --negative
php tests/smoke/run.php --all
```

## 5. Documentacion clave
- Contexto maestro de trabajo: `AGENTS.md`
- Contexto canonico tecnico: `docs/context.md`
- Roadmap ejecutivo por waves: `docs/project-roadmap.md`
- Estado consolidado: `docs/project-status.md`
- Contrato API: `docs/api.md`
- Requests operativos: `docs/requests.http`
- Roadmap tecnico W5->W6: `docs/roadmap-wave-5-6.md`
- Acta final Wave 5: `docs/release-wave-5-acta-final.md`
- Release notes Wave 5: `docs/release-notes-wave-5.md`

## 6. Como contribuir
Forma de trabajo por waves:
1. Definir ticket con alcance, dependencias y criterio de aceptacion.
2. Implementar por capas (`Controller/Service/Repository`) respetando tenancy y seguridad.
3. Ejecutar gate minimo (`migrate`, `seed`, smoke `--positive/--negative/--all`).
4. Actualizar documentacion (`api.md`, `requests.http`, acta/release segun aplique).
5. Cerrar con commit claro, push y, cuando corresponda, tag de baseline.

Convenciones recomendadas:
- Ramas: `wave-X` (ejemplo: `wave-1`).
- Tags de baseline: `wave-X-baseline`.
- Commits: `tipo(scope): descripcion`.
  - Ejemplos: `feat(sync): agregar deteccion de conflictos por op_id`, `docs(w5): cerrar acta final`.

## 7. Notas de producto
- Fiscal es **opcional** por tenant.
- Clientes no regularizados pueden operar en flujo no fiscal.
- Multi-tenant estricto y seguridad first-class son obligatorios.

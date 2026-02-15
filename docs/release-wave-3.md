# Release Gate - Wave 3 (W3-006 a W3-010)

Objetivo: validar operacion fiscal opcional (feature flag) sin romper facturacion no fiscal.

## Alcance

Cubre:
- Configuracion fiscal por tenant (opcional)
- Estado fiscal y perfil DGII
- Cola de envio fiscal y reintentos
- Listado de documentos fiscales
- Smoke minimo de endpoints fiscales

## Tickets

- `W3-006`: API de configuracion fiscal por tenant (`/fiscal/config`)
- `W3-007`: API de estado fiscal (`/fiscal/status`)
- `W3-008`: pipeline async fiscal (`jobs:fiscal-submit` + worker)
- `W3-009`: observabilidad de documentos (`/fiscal/documents` + retry)
- `W3-010`: docs y smoke actualizados

## Prerrequisitos

- `.env` configurado
- DB accesible
- API levantada en `http://localhost`
- PHP 8.2+

## Gate de salida

### 1) Migraciones

```bash
php bin/console.php migrate
php bin/console.php migrate:status
```

Criterio:
- `Pendientes: 0`
- incluye `004_fiscal_base.sql`

### 2) Seed y permisos

```bash
php bin/console.php seed:dev
```

Criterio:
- finaliza sin error
- rol `OWNER` con `fiscal.read` y `fiscal.manage`

### 3) Smoke positivo

```bash
php tests/smoke/run.php --positive
```

Criterio:
- `SMOKE OK`
- paso `Fiscal Status` exitoso

### 4) Smoke negativo

```bash
php tests/smoke/run.php --negative
```

Criterio:
- `SMOKE OK`
- paso `Fiscal Config Validation` responde `422 VALIDATION_ERROR`

### 5) Flujo fiscal (manual)

1) Activar fiscal:
```http
PUT /api/v1/fiscal/config
```
con:
```json
{"fiscal":{"enabled":true,"dgii_registered":true,"ncf_type":"B01","series":"B01"}}
```

2) Emitir invoice (`POST /api/v1/invoices/{id}/issue`)

3) Verificar documento:
```http
GET /api/v1/fiscal/documents
```

4) Procesar cola:
```bash
php bin/worker.php run
```

Criterio:
- documento fiscal pasa a `ACCEPTED` (simulado)
- existe `fiscal_acks` y eventos en `fiscal_events`

## Checklist final

- [ ] Migraciones sin pendientes
- [ ] Seed con permisos fiscales
- [ ] Smoke positivo OK
- [ ] Smoke negativo OK
- [ ] Facturacion no fiscal sigue operativa
- [ ] Emision fiscal opcional operativa
- [ ] Worker fiscal procesa/reintenta
- [ ] Evidencia adjunta

## Evidencia minima

- salida de `migrate:status`
- salida de `seed:dev`
- salida smoke (`--positive`, `--negative`)
- consulta SQL de `fiscal_documents`, `fiscal_events`, `fiscal_acks`
- commit hash validado

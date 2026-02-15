# Acta Técnica Parcial - Wave 4 (W4-005)

## Objetivo
API operativa fiscal para soporte diario sin depender de SQL manual.

## Implementado

### 1) Búsqueda avanzada
- Endpoint: `GET /api/v1/fiscal/documents/search`
- Filtros:
  - `status`
  - `ncf` (LIKE)
  - `date_from` / `date_to`
  - `limit`

### 2) Resumen operativo por estado
- Endpoint: `GET /api/v1/fiscal/documents/summary`
- Soporta rango de fechas (`date_from`, `date_to`)
- Responde conteos:
  - `total`
  - `pending`
  - `processing`
  - `sent`
  - `accepted`
  - `rejected`
  - `failed`
  - `cancelled`

### 3) Reintento masivo
- Endpoint: `POST /api/v1/fiscal/documents/retry-bulk`
- Body: `{ "ids": [..] }`
- Regla:
  - encola solo `FAILED`/`REJECTED`
  - reporta `queued` y `skipped`
- Auditoría: `fiscal.documents.retry_bulk`

## Archivos
- `app/Fiscal/FiscalController.php`
- `app/Fiscal/FiscalRepository.php`
- `app/Bootstrap/App.php`
- `docs/api.md`
- `docs/requests.http`
- `tests/smoke/run.php`

## Validación recomendada
```bash
php tests/smoke/run.php --positive
php tests/smoke/run.php --negative
```

Pruebas manuales clave:
- `GET /api/v1/fiscal/documents/summary`
- `GET /api/v1/fiscal/documents/search?status=FAILED&limit=20`
- `POST /api/v1/fiscal/documents/retry-bulk`

## Nota
- Se mantiene no-obligatoriedad fiscal para tenants sin módulo fiscal habilitado.

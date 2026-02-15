# Acta Técnica Parcial - Wave 4 (W4-003 y W4-004)

## Alcance ejecutado

### W4-003 Reintentos avanzados + DLQ fiscal
- Clasificación de errores en worker fiscal:
  - `[RETRYABLE]`
  - `[NON_RETRYABLE]`
- `jobs:fiscal-submit` con backoff exponencial.
- Errores non-retryables pasan a `jobs_dlq` inmediatamente.

Archivos:
- `app/Jobs/JobQueue.php`
- `app/Fiscal/FiscalJobService.php`

### W4-004 Estado fiscal expandido
- Estados activos en flujo:
  - `PENDING`, `PROCESSING`, `SENT`, `ACCEPTED`, `REJECTED`, `FAILED`, `CANCELLED`
- Flujo actualizado:
  - `PROCESSING` -> `SENT` -> `ACCEPTED/REJECTED/FAILED`
- Al anular invoice (`void`) se cancela documento fiscal en curso (`CANCELLED`) cuando aplica.

Archivos:
- `app/Fiscal/FiscalRepository.php`
- `app/Fiscal/FiscalJobService.php`
- `app/Invoices/InvoiceController.php`
- `app/Bootstrap/App.php`

## Validación recomendada

1) Seed + smoke base
```bash
php bin/console.php seed:dev
php tests/smoke/run.php --positive
php tests/smoke/run.php --negative
```

2) Verificar estados fiscales
- Emitir factura fiscal (`provider=MOCK`) y correr worker.

SQL:
```sql
SELECT id, status, track_id, updated_at
FROM fiscal_documents
ORDER BY id DESC
LIMIT 10;
```

3) Verificar timeline de eventos
```sql
SELECT fiscal_document_id, event_type, status, created_at
FROM fiscal_events
ORDER BY id DESC
LIMIT 30;
```

4) Verificar retry/DLQ
- Forzar error no-retryable (ej: fiscal deshabilitado con job encolado) y validar DLQ.
- Forzar error retryable (ej: provider DGII sin endpoint accesible) y validar retry/backoff.

SQL:
```sql
SELECT id, queue_name, status, attempts, max_attempts, last_error, available_at
FROM jobs_queue
WHERE queue_name='jobs:fiscal-submit'
ORDER BY id DESC
LIMIT 20;
```

```sql
SELECT id, queue_name, attempts, max_attempts, last_error, failed_at
FROM jobs_dlq
WHERE queue_name='jobs:fiscal-submit'
ORDER BY id DESC
LIMIT 20;
```

## Nota
- Se mantiene no-obligatoriedad fiscal: tenants no fiscales continúan con facturación no fiscal sin bloqueo.

# Entrega Técnica Wave 4 (W4-008 y W4-009)

## W4-008 Backoffice mínimo fiscal

Implementado:
- Pantalla estática: `public/fiscal-backoffice.html`
- Funciones:
  - filtros por `status`, `ncf`, `date_from`, `date_to`, `limit`
  - listado de documentos fiscales
  - detalle por documento (eventos + acuses)
  - retry individual y retry masivo
  - panel de KPIs operativos

Dependencias API usadas por la UI:
- `GET /api/v1/fiscal/documents/search`
- `GET /api/v1/fiscal/documents/{id}/events`
- `GET /api/v1/fiscal/documents/{id}/acks`
- `POST /api/v1/fiscal/documents/{id}/retry`
- `POST /api/v1/fiscal/documents/retry-bulk`
- `GET /api/v1/fiscal/metrics`

## W4-009 Observabilidad y métricas

Implementado:
- Endpoint `GET /api/v1/fiscal/metrics`
- Métricas incluidas:
  - `total_docs`, `accepted_docs`, `rejected_docs`, `failed_docs`, `cancelled_docs`
  - `acceptance_rate`, `error_rate`
  - `avg_latency_seconds` (SUBMIT_REQUESTED -> ACK_RECEIVED)
  - estado de cola fiscal (`pending`, `retry`, `processing`, `failed`, `completed`)
  - `dlq_total`

Archivos clave:
- `app/Fiscal/FiscalRepository.php`
- `app/Fiscal/FiscalController.php`
- `app/Bootstrap/App.php`
- `public/fiscal-backoffice.html`
- `docs/api.md`
- `docs/requests.http`
- `tests/smoke/run.php`

## Validación sugerida

```bash
php tests/smoke/run.php --positive
php tests/smoke/run.php --negative
```

Validación manual adicional:
1. Abrir `http://localhost/fiscal-backoffice.html`.
2. Pegar `access_token`.
3. Ejecutar búsqueda y abrir detalle de documento.
4. Ejecutar retry individual y bulk (solo docs `FAILED/REJECTED`).
5. Confirmar panel KPI con datos de `/api/v1/fiscal/metrics`.

## Nota
- Fiscal sigue opcional por tenant.
- Si fiscal está deshabilitado, el backoffice puede mostrar KPIs en cero sin bloquear operación no fiscal.

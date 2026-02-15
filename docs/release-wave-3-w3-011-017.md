# Release Gate - Wave 3 (W3-011 a W3-017)

Objetivo: completar observabilidad fiscal, webhook de acuses y hardening operativo.

## Tickets ejecutados

- `W3-011`: `GET /fiscal/documents/{id}`
- `W3-012`: `GET /fiscal/documents/{id}/events`
- `W3-013`: `GET /fiscal/documents/{id}/acks`
- `W3-014`: `POST /fiscal/webhook/ack` con llave `X-Fiscal-Webhook-Key`
- `W3-015`: auditoria fiscal (`fiscal.config.update`, `fiscal.document.retry`, `fiscal.webhook.ack`)
- `W3-016`: rate limit en config/retry/webhook fiscal
- `W3-017`: actualización de docs + smoke

## Archivos clave

- `app/Fiscal/FiscalController.php`
- `app/Fiscal/FiscalRepository.php`
- `app/Bootstrap/App.php`
- `.env.example`
- `docs/api.md`
- `docs/requests.http`
- `tests/smoke/run.php`

## Validación sugerida

1) Smoke
```bash
php tests/smoke/run.php --positive
php tests/smoke/run.php --negative
```

2) Webhook sin llave (debe fallar)
```http
POST /api/v1/fiscal/webhook/ack
```
Esperado: `401 UNAUTHORIZED`.

3) Webhook con llave
```http
POST /api/v1/fiscal/webhook/ack
X-Fiscal-Webhook-Key: <FISCAL_WEBHOOK_KEY>
```
Esperado: `200` y actualización de `fiscal_documents`.

4) Evidencia SQL
```sql
SELECT id, status, ncf, track_id, updated_at
FROM fiscal_documents
ORDER BY id DESC
LIMIT 10;
```

```sql
SELECT fiscal_document_id, event_type, status, created_at
FROM fiscal_events
ORDER BY id DESC
LIMIT 20;
```

```sql
SELECT fiscal_document_id, ack_code, ack_message, received_at
FROM fiscal_acks
ORDER BY id DESC
LIMIT 20;
```

## Checklist

- [ ] Endpoints detalle/eventos/acks responden 200
- [ ] Webhook invalido responde 401
- [ ] Webhook valido registra ack y evento
- [ ] Auditoria fiscal con registros
- [ ] Rate limit activo en endpoints fiscales sensibles
- [ ] Smoke actualizado en verde

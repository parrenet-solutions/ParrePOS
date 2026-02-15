# Acta Consolidada Final - Wave 3

**Fecha:** `YYYY-MM-DD`  
**Ambiente:** `local/staging/prod`  
**Commit:** `<hash>`  
**Responsable:** `<nombre/equipo>`

## 0. Alcance consolidado
Incluye cierre de:
- `W3-001..W3-005` (base fiscal opcional)
- `W3-006..W3-010` (config/estado/pipeline/docs)
- `W3-011..W3-017` (detalle/eventos/acks/webhook/auditoria/rate-limit)

## 1. Migraciones
- Comando: `php bin/console.php migrate`
- Comando: `php bin/console.php migrate:status`
- Resultado: `OK/FAIL`
- Evidencia:
```text
<pegar salida>
```

## 2. Seed
- Comando: `php bin/console.php seed:dev`
- Resultado: `OK/FAIL`
- Evidencia:
```text
<pegar salida>
```

## 3. Smoke positivo
- Comando: `php tests/smoke/run.php --positive`
- Resultado: `OK/FAIL`
- Evidencia:
```text
<pegar salida>
```

## 4. Smoke negativo
- Comando: `php tests/smoke/run.php --negative`
- Resultado: `OK/FAIL`
- Evidencia:
```text
<pegar salida>
```

## 5. Worker
- Comando: `php bin/worker.php run`
- Resultado: `OK/FAIL`
- Evidencia:
```text
<pegar salida>
```

## 6. Fiscal opcional (feature flag)
- Validación: facturación no fiscal operativa con `fiscal.enabled=false`
- Validación: flujo fiscal operativa con `fiscal.enabled=true` + `dgii_registered=true`
- Resultado: `OK/FAIL`
- Evidencia:
```text
<pegar evidencia funcional>
```

## 7. Webhook fiscal inválido (seguridad)
- Prueba: `POST /api/v1/fiscal/webhook/ack` sin key o key inválida
- Esperado: `401 UNAUTHORIZED`
- Resultado: `OK/FAIL`
- Evidencia:
```text
<pegar request/response>
```

## 8. Webhook fiscal válido (cierre obligatorio Wave 3)
- Prueba: `POST /api/v1/fiscal/webhook/ack` con `X-Fiscal-Webhook-Key` correcto
- Resultado: `OK/FAIL`
- Evidencia HTTP:
```text
<pegar request/response>
```

Evidencia SQL mínima:
```sql
SELECT id, tenant_id, status, ncf, track_id, updated_at
FROM fiscal_documents
ORDER BY id DESC
LIMIT 10;
```

```sql
SELECT id, tenant_id, fiscal_document_id, ack_code, ack_message, received_at
FROM fiscal_acks
ORDER BY id DESC
LIMIT 10;
```

```sql
SELECT id, tenant_id, fiscal_document_id, event_type, status, created_at
FROM fiscal_events
ORDER BY id DESC
LIMIT 20;
```

Resultado SQL: `OK/FAIL`
Evidencia:
```text
<pegar salida SQL>
```

## 9. Auditoría fiscal
Eventos esperados (mínimo):
- `fiscal.config.update`
- `fiscal.document.retry` (si aplica)
- `fiscal.webhook.ack`

SQL sugerido:
```sql
SELECT action, COUNT(*) AS cnt
FROM audit_log
WHERE action IN ('fiscal.config.update', 'fiscal.document.retry', 'fiscal.webhook.ack')
GROUP BY action
ORDER BY action;
```

Resultado: `OK/FAIL`
Evidencia:
```text
<pegar salida SQL>
```

## 10. Estado por bloques
- `W3-001..W3-005`: `OK/FAIL`
- `W3-006..W3-010`: `OK/FAIL`
- `W3-011..W3-017`: `OK/FAIL`

## Checklist final
- [ ] Migraciones sin pendientes
- [ ] Seed exitoso
- [ ] Smoke positivo OK
- [ ] Smoke negativo OK
- [ ] Worker OK
- [ ] Fiscal opcional validado
- [ ] Webhook inválido validado
- [ ] Webhook válido validado con datos reales
- [ ] Evidencia SQL fiscal adjunta
- [ ] Auditoría fiscal adjunta

## Dictamen final
- **Estado:** `APROBADO / BLOQUEADO`
- **Notas / bloqueos:**
```text
<detalle final>
```

## Congelación baseline
- Tag sugerido: `wave-3`
- Release notes: `docs/release-notes-wave-3.md`
- Acta consolidada final: `docs/release-wave-3-acta-final.md`

# Checklist de Cierre - Wave 4 (W4-010)

Fecha de ejecución: `2026-02-15`  
Ambiente: `local`  
Responsable: `Equipo POS SaaS`

## 1) Base técnica
- [x] `php bin/console.php migrate`
- [x] `php bin/console.php migrate:status` => pendientes `0`
- [x] `php bin/console.php seed:dev`
- [x] `php bin/worker.php run` sin errores críticos

## 2) Smoke extendido
- [x] `php tests/smoke/run.php --positive`
- [x] `php tests/smoke/run.php --negative`
- [x] `php tests/smoke/run.php --all`
- [x] Paso `Fiscal Metrics` en verde
- [x] Paso `Fiscal Retry Concurrent Idempotency Basic` en verde

## 3) Backoffice fiscal (W4-008)
- [x] Abrir `http://localhost/fiscal-backoffice.html`
- [x] Pegar token y cargar documentos
- [x] Ver detalle de un documento (eventos y acuses)
- [x] Ejecutar retry individual
- [x] Ejecutar retry bulk

## 4) Métricas y observabilidad (W4-009)
- [x] `GET /api/v1/fiscal/metrics` responde `200`
- [x] Campos esperados presentes:
  - [x] `total_docs`
  - [x] `acceptance_rate`
  - [x] `error_rate`
  - [x] `avg_latency_seconds`
  - [x] `queue.pending|retry|processing|failed|completed`
  - [x] `dlq_total`

## 5) Hardening fiscal
- [x] Se valida idempotencia de cola fiscal (mismo `job_id` en retry concurrente)
- [x] Reintentos transitorios se quedan en `RETRY` y no saturan DLQ
- [x] Fallos no recuperables van a `jobs_dlq` con `last_error`
- [x] Alertas en `FAILED/REJECTED` (email/webhook interno opcional)

## 6) Evidencias (pegar salida)
```text
Migrate status:

Smoke positive:

Smoke negative:

Smoke all:

Worker:

Notas backoffice/metrics:
```

## 7) Cierre formal
- [x] `docs/release-notes-wave-4.md` actualizado final
- [x] `docs/release-wave-4-acta-final.md` completada
- [ ] Tag sugerido creado: `wave-4-baseline`

## Dictamen
- Estado: `OK`
- Bloqueantes:
```text
Pendiente solo operación de git tag/push de baseline.
```

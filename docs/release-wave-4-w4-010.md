# Acta Técnica Parcial - Wave 4 (W4-010)

## Objetivo
Hardening final de Wave 4: smoke fiscal extendida, casos de concurrencia, checklist de cierre y preparación de baseline.

## Avance implementado
- Smoke extendida:
  - nuevo paso `Fiscal Metrics`.
  - nuevo paso `Fiscal Retry Concurrent Idempotency Basic` con validación de `job_id` idéntico en solicitudes concurrentes.
- Checklist ejecutable de cierre:
  - `docs/release-wave-4-checklist-w4-010.md`
- Acta de cierre W4-008/009:
  - `docs/release-wave-4-acta-w4-008-009.md`

## Archivos tocados
- `tests/smoke/run.php`
- `docs/release-wave-4-checklist-w4-010.md`
- `docs/release-wave-4-acta-w4-008-009.md`

## Validación sugerida
```bash
php tests/smoke/run.php --positive
php tests/smoke/run.php --negative
php tests/smoke/run.php --all
```

Nota:
- El paso de concurrencia fiscal se omite con `[INFO]` si no existen documentos fiscales en el tenant.

## Cierre
- Checklist de cierre preparado en `docs/release-wave-4-checklist-w4-010.md`.
- Acta final consolidada completada en `docs/release-wave-4-acta-final.md`.
- Pendiente operativo externo: generar tag baseline (`wave-4-baseline`) y push al remoto.

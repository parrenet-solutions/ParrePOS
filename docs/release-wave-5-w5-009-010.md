# Entrega Técnica Wave 5 (W5-009 y W5-010)

## Resumen
Se completa el gate de calidad de Wave 5 y se deja preparado el cierre formal de release con baseline.

## W5-009 Smoke/regresión ampliada

Implementado en `tests/smoke/run.php`:
- Validación de contrato en `GET /api/v1/sync/status`:
  - `pending_count`, `failed_count`, `conflict_count`, `last_applied_at`, `last_event_at`.
- Validación estructural de `GET /api/v1/ops/tenant-metrics`:
  - secciones `sync`, `conflicts`, `jobs`, `fiscal`.
  - llaves críticas por sección.
- Validación de `GET /api/v1/ops/sync-conflicts`:
  - lista y esquema mínimo de filas cuando hay resultados.
- Verificación funcional post-conflicto:
  - tras `Sync Conflict by OpId`, `sync.status.conflict_count >= 1`.
- Caso negativo de seguridad:
  - `GET /api/v1/ops/tenant-metrics` sin token retorna `401 UNAUTHORIZED`.

## W5-010 Cierre Wave 5

Generado:
- Acta final: `docs/release-wave-5-acta-final.md`
- Release notes final: `docs/release-notes-wave-5.md`
- Checklist baseline/tag: `docs/release-wave-5-checklist-baseline.md`

## Validación sugerida
```bash
php bin/console.php migrate
php bin/console.php migrate:status
php bin/console.php seed:dev
php tests/smoke/run.php --positive
php tests/smoke/run.php --negative
php tests/smoke/run.php --all
```

## Nota de producto
Se mantiene la regla de negocio: fiscal es opcional por tenant y no bloquea operación no fiscal.

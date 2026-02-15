# Estado del Proyecto (Actualizado)

Fecha de corte: `2026-02-15`

## Resumen ejecutivo
- Wave 2.2: **Cerrada y aprobada**.
- Wave 3 (W3-001..W3-017): **Cerrada** (fiscal opcional consolidado).
- Wave 4:
  - `W4-001` y `W4-002`: **Aprobados**.
  - `W4-003` y `W4-004`: **Aprobados**.
  - `W4-005`: **Aprobado**.
  - `W4-006` y `W4-007`: **Aprobados**.
  - `W4-008` y `W4-009`: **Aprobados**.
  - `W4-010`: **Aprobado**.
- Wave 5:
  - `W5-001` a `W5-010`: **Aprobados**.

## Alcance funcional alcanzado

### Multi-tenant y seguridad
- Tenancy estricto y guard por módulos habilitados.
- RBAC con permisos por endpoint.
- Rate limiting en endpoints sensibles (incluye fiscal).

### POS + Sync (Wave 2.2)
- Flujo POS estable, idempotencia y sync robusto.
- Worker con cola persistente, retries y DLQ.

### Fiscal opcional (Wave 3 + Wave 4)
- Config fiscal por tenant (`enabled`, `dgii_registered`, serie, tipo NCF).
- Emisión dual:
  - no fiscal por defecto
  - fiscal cuando está habilitado.
- Documentos fiscales, eventos y acuses.
- Webhook de acuses protegido por llave.
- Provider desacoplado (`MOCK`/`DGII`) con hash/firma de payload.
- Retry policy avanzada y DLQ fiscal.
- Estado fiscal extendido:
  - `PENDING`, `PROCESSING`, `SENT`, `ACCEPTED`, `REJECTED`, `FAILED`, `CANCELLED`.
- API operativa fiscal base (`search`, `summary`, `retry-bulk`).

## Documentos de referencia
- API: `docs/api.md`
- Requests manuales: `docs/requests.http`
- Actas Wave 3:
  - `docs/release-wave-3-acta.md`
  - `docs/release-wave-3-acta-w3-006-010.md`
  - `docs/release-wave-3-acta-w3-011-017.md`
- Actas Wave 4:
  - `docs/release-wave-4-acta-w4-001-002.md`
  - `docs/release-wave-4-acta-w4-003-004.md`
  - `docs/release-wave-4-acta-w4-005.md`
  - `docs/release-wave-4-acta-w4-006-007.md`
  - `docs/release-wave-4-acta-w4-008-009.md`
  - `docs/release-wave-4-acta-final.md`
- Backlog Wave 4: `docs/release-wave-4-backlog.md`
- Backlog Wave 5: `docs/release-wave-5-backlog.md`
- Entrega técnica W5-001/002: `docs/release-wave-5-w5-001-002.md`
- Entrega técnica W5-003/004: `docs/release-wave-5-w5-003-004.md`
- Entrega técnica W5-005/006: `docs/release-wave-5-w5-005-006.md`
- Acta W5-001..004: `docs/release-wave-5-acta-w5-001-004.md`
- Acta W5-005..006: `docs/release-wave-5-acta-w5-005-006.md`
- Entrega técnica W5-007/008: `docs/release-wave-5-w5-007-008.md`
- Acta W5-007..008: `docs/release-wave-5-acta-w5-007-008.md`
- Entrega técnica W5-009/010: `docs/release-wave-5-w5-009-010.md`
- Acta final Wave 5: `docs/release-wave-5-acta-final.md`
- Release notes Wave 5: `docs/release-notes-wave-5.md`
- Checklist baseline W5: `docs/release-wave-5-checklist-baseline.md`

## Etapas siguientes (resumen)

### Post Wave 5
- Baseline congelada con tag: `wave-5-baseline`.
- Wave 5 cerrada funcional y documentalmente.
- Mantener fiscal opcional por tenant como política de producto.
- Roadmap oficial siguiente etapa: `docs/project-roadmap.md`.

## Nota de producto
- La facturación fiscal **no es obligatoria** en el sistema.
- El producto sigue soportando clientes no regularizados, operando en modo no fiscal.

# Contexto Operativo del Proyecto

Fecha de actualización: `2026-02-15`

## Estado actual
- Wave 2.2: cerrada.
- Wave 3: cerrada.
- Wave 4: cerrada y aprobada.
- Wave 5: cerrada (W5-001..W5-010 aprobados).
- Fiscal: opcional por tenant (no obligatoria para clientes no regularizados).

## Reglas obligatorias (resumen)
1. Multi-tenant estricto:
- Toda entidad de negocio usa `tenant_id`.
- Toda consulta/operación de negocio filtra por `tenant_id`.
- Prohibido acceso cruzado entre tenants.

2. Modularidad por tenant:
- Módulos (`pos`, `inventory`, `recurring`, `fiscal`) se habilitan/deshabilitan por tenant.
- Si módulo está deshabilitado: API rechaza operación y UI no lo expone.

3. Seguridad:
- JWT access + refresh con rotación.
- Rate limit en login/endpoints sensibles.
- Validaciones estrictas y errores JSON estándar.
- Auditoría append-only en acciones críticas.

4. Arquitectura:
- PHP puro 8.2+, sin framework.
- Capas: Controller / Service / Repository.
- Migraciones incrementales en `database/migrations`.

## Documentos clave
- Reglas de trabajo: `AGENTS.md`
- Guía de proyecto: `README.md`
- Estado consolidado: `docs/project-status.md`
- Acta final Wave 4: `docs/release-wave-4-acta-final.md`
- Release notes Wave 4: `docs/release-notes-wave-4.md`
- Backlog Wave 5: `docs/release-wave-5-backlog.md`
- Roadmap W5->W6: `docs/roadmap-wave-5-6.md`
- Actas W5:
  - `docs/release-wave-5-acta-w5-001-004.md`
  - `docs/release-wave-5-acta-w5-005-006.md`
  - `docs/release-wave-5-acta-w5-007-008.md`
  - `docs/release-wave-5-acta-final.md`
- Release notes W5:
  - `docs/release-notes-wave-5.md`
  - `docs/release-wave-5-checklist-baseline.md`

## Siguiente paso recomendado
- Congelar baseline con tag `wave-5-baseline` y luego arrancar Wave 6 según roadmap.

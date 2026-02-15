# Acta Release Wave 4 (W4-008 y W4-009)

**Fecha:** `2026-02-15`  
**Ambiente:** `local`  
**Responsable:** `Equipo POS SaaS`

## 1. Alcance validado
- `W4-008` Backoffice fiscal mínimo.
- `W4-009` Observabilidad y métricas fiscales.

## 2. Entregables
- UI operativa en `public/fiscal-backoffice.html`.
- Endpoint `GET /api/v1/fiscal/metrics`.
- Integración UI/API para:
  - búsqueda/listado de documentos
  - eventos y acuses
  - retry individual y masivo
  - KPIs operativos.

## 3. Estado por ticket
- `W4-008`: `COMPLETADO`
- `W4-009`: `COMPLETADO`

## Checklist final
- [x] Backoffice fiscal operativo con token Bearer.
- [x] Filtros por estado/NCF/fecha/límite.
- [x] Detalle por documento (eventos + acuses).
- [x] Retry individual y retry bulk desde UI.
- [x] Métricas fiscales operativas expuestas por API.
- [x] Fiscal continúa opcional por tenant.

## Dictamen
- **Estado:** `APROBADO`
- **Notas:**
```text
W4-008 y W4-009 cerrados a nivel implementación.
Validación funcional queda soportada por smoke y checklist de W4-010.
```

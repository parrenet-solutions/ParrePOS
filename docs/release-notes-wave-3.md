# Release Notes - Wave 3 (W3-006 a W3-010)

Fecha: `2026-02-13`

## Resumen
Wave 3 extiende el modulo fiscal como capacidad opcional por tenant. El sistema mantiene el flujo no fiscal por defecto para clientes no regularizados.

## Cambios principales

### Configuracion fiscal opcional
- Nuevo endpoint `PUT /api/v1/fiscal/config`.
- Valida reglas minimas:
  - `fiscal.enabled=true` requiere `dgii_registered=true`.
  - `ncf_type` y `series` validos.
- Sin fiscal habilitado, la facturacion sigue en modo normal.

### Estado fiscal y observabilidad
- Nuevo endpoint `GET /api/v1/fiscal/status`.
- Nuevo endpoint `GET /api/v1/fiscal/documents` para monitoreo.
- Nuevo endpoint `POST /api/v1/fiscal/documents/{id}/retry` para reintentos.

### Pipeline async fiscal
- Nuevo worker queue `jobs:fiscal-submit`.
- Al emitir invoice en modo fiscal:
  - genera `fiscal_documents`
  - encola envio fiscal
- Worker procesa envio y acuse simulado:
  - actualiza estado del documento
  - registra `fiscal_events`
  - registra `fiscal_acks`

### Seguridad / RBAC
- Nuevos permisos:
  - `fiscal.read`
  - `fiscal.manage`
- Asignados al rol `OWNER` en seed dev.

### Docs y smoke
- `docs/api.md` y `docs/requests.http` actualizados con endpoints fiscales.
- Smoke agrega:
  - `Fiscal Status` (positivo)
  - `Fiscal Config Validation` (negativo)
  - `Fiscal Documents List` (positivo)
  - `Fiscal Webhook Unauthorized` (negativo)

### Extensión W3-011..W3-017
- Endpoints nuevos:
  - `GET /fiscal/documents/{id}`
  - `GET /fiscal/documents/{id}/events`
  - `GET /fiscal/documents/{id}/acks`
  - `POST /fiscal/webhook/ack`
- Auditoría fiscal en cambios críticos y webhook.
- Rate limit aplicado en config/retry/webhook.

## Estado de release
- Wave 3 (`W3-006..W3-010`): **APROBADA**

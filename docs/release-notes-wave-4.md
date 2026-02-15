# Release Notes - Wave 4 (Final)

Fecha: `2026-02-15`

## Resumen
Wave 4 avanza la capa fiscal hacia integración externa robusta manteniendo la no-obligatoriedad fiscal para tenants que operan en modo no fiscal.

## Completado

### W4-001 Adapter fiscal
- Provider desacoplado:
  - `MOCK`
  - `DGII`
- Factory de selección por configuración.

### W4-002 Seguridad de envío
- Hash SHA-256 de request/response.
- Firma HMAC SHA-256 con secreto por tenant o global.
- Trazabilidad en eventos fiscales.

### W4-003 Retry avanzado + DLQ
- Política `RETRYABLE` / `NON_RETRYABLE`.
- Backoff exponencial para `jobs:fiscal-submit`.
- Envío inmediato a DLQ en no-retryable.

### W4-004 Estado fiscal expandido
- Estados operativos:
  - `PENDING`, `PROCESSING`, `SENT`, `ACCEPTED`, `REJECTED`, `FAILED`, `CANCELLED`.
- Cancelación de documento fiscal al anular invoice (cuando aplica).

### W4-005 API operativa fiscal
- `GET /fiscal/documents/search`
- `GET /fiscal/documents/summary`
- `POST /fiscal/documents/retry-bulk`

### W4-006 Validaciones fiscales RD
- Validación por tipo NCF (`B01`, `B02`, `B14`, `B15`).
- Validación básica de RNC/Cédula para emisión fiscal.
- Límites de emisión por tenant (`daily_max`, `monthly_max`).

### W4-007 Alertas fiscales operativas
- Alertas por `FAILED/REJECTED` vía email interno.
- Webhook interno opcional para incidentes fiscales.

### W4-008 Backoffice fiscal mínimo
- UI estática `public/fiscal-backoffice.html`.
- Filtros por estado/NCF/fecha, detalle de eventos y acuses.
- Retry individual y masivo desde backoffice.

### W4-009 Métricas y observabilidad
- Endpoint `GET /fiscal/metrics`.
- KPIs: total docs, aceptación/error, latencia promedio, cola y DLQ.

### W4-010 Hardening y cierre
- Smoke extendida con validación de concurrencia fiscal (`retry` concurrente).
- Checklist formal de cierre para evidencias y baseline.
- Acta final consolidada completada.

## Siguiente (resumen)
- Wave 4 cerrada y aprobada.
- Pendiente operativo: crear tag baseline (`wave-4-baseline`) y push al remoto.

## Nota de producto
- Fiscal sigue siendo opcional por tenant.
- Clientes no regularizados pueden operar en flujo no fiscal.

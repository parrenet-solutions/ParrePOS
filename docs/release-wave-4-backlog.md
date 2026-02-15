# Backlog Ejecutable - Wave 4

Objetivo propuesto: llevar Fiscal de “simulado/operativo interno” a “integración externa robusta” sin hacerla obligatoria para todos los tenants.

## Estado general
- `W4-001`: COMPLETADO
- `W4-002`: COMPLETADO
- `W4-003`: COMPLETADO
- `W4-004`: COMPLETADO
- `W4-005`: COMPLETADO
- `W4-006`: COMPLETADO
- `W4-007`: COMPLETADO
- `W4-008`: COMPLETADO
- `W4-009`: COMPLETADO
- `W4-010`: COMPLETADO

## W4-001 Integrador DGII (adapter)
- Crear `FiscalProviderInterface` + `DgiiProvider` + `MockProvider`.
- Selección por configuración (`CERT/PROD`) y feature flag.
- Criterio: envío fiscal desacoplado del proveedor concreto.

## W4-002 Firma/seguridad de payload fiscal
- Implementar firma HMAC/cert según contrato definitivo.
- Validar request/response con trazabilidad.
- Criterio: cada envío guarda hash/firma y resultado de validación.

## W4-003 Reintentos avanzados + DLQ fiscal
- Backoff exponencial y umbral por tipo de error.
- Cola dedicada `jobs:fiscal-submit` con monitoreo de fallos.
- Criterio: errores transitorios reintentan; permanentes a DLQ con causa.

## W4-004 Estado fiscal expandido
- Estados sugeridos: `PENDING`, `PROCESSING`, `SENT`, `ACCEPTED`, `REJECTED`, `FAILED`, `CANCELLED`.
- Timeline consistente en `fiscal_events`.
- Criterio: estado auditable extremo a extremo.

## W4-005 Portal operativo fiscal (API)
- Endpoints: reenvío masivo, búsqueda por NCF/rango fechas, resumen de estado.
- Criterio: soporte operativo para equipo de facturación.

## W4-006 Validaciones fiscales RD más completas
- Reglas por tipo NCF, RNC/Cédula y límites de emisión.
- Criterio: rechaza temprano payload inválido con `VALIDATION_ERROR` claro.

## W4-007 Notificaciones de errores fiscales
- Alertas internas para `FAILED/REJECTED` (email/webhook interno).
- Criterio: incidente fiscal visible sin revisar DB manualmente.

## W4-008 UI/Backoffice mínimo fiscal
- Pantalla de documentos fiscales: filtros, detalle, eventos, acuses, retry.
- Criterio: operación sin SQL para soporte diario.

## W4-009 Observabilidad y métricas
- KPIs: latencia, tasa de aceptación, fallos por código, retries.
- Criterio: dashboard básico o endpoint `/fiscal/metrics`.

## W4-010 Hardening y cierre
- Suite smoke fiscal extendida + casos de concurrencia.
- Acta/release notes/tag de Wave 4.
- Criterio: gate en verde y baseline congelada.

## Dependencias clave antes de arrancar W4
- Confirmar contrato técnico oficial DGII (campos, auth, errores).
- Definir estrategia de certificados/secrets por tenant.
- Definir ambiente de pruebas CERT para validación real.

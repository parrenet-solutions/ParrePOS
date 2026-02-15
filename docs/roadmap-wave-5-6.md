# Roadmap Ejecutable Wave 5 -> Wave 6

Fecha base: `2026-02-15`  
Estado: `Planificado`  
Baseline actual: `wave-4-baseline`

## 1) Objetivo estratégico
- **Wave 5:** consolidar operación real SaaS (planes/límites, inventario integrado a POS offline, observabilidad y seguridad operativa).
- **Wave 6:** escalar comercialmente (compras/proveedores, inventario avanzado, cobranza/reportes e integraciones externas), manteniendo compliance y resiliencia.

## 2) Reglas permanentes (no negociables)
- Multi-tenant estricto (`tenant_id` en datos de negocio + filtro obligatorio en consultas).
- Modularidad por tenant/plan (`pos`, `inventory`, `recurring`, `fiscal`) con bloqueo API/UI si deshabilitado.
- Seguridad first-class (JWT + rotación refresh + rate limiting + validación estricta + auditoría append-only).
- Arquitectura PHP puro 8.2+ por capas (Controller / Service / Repository).
- **Fiscal opcional** por tenant: nunca bloquear operación no fiscal para clientes no regularizados.

## 3) Tickets Wave 5 (ejecutables)

### W5-001 Planes y límites por tenant
- Alcance:
  - Definir estructura de planes y límites por recurso.
  - Middleware de enforcement de límites.
- Dependencias: ninguna.
- Entregable: límites activos con error estándar `PLAN_LIMIT_EXCEEDED`.
- DoD:
  - migraciones aplicadas.
  - endpoints críticos protegidos.
  - smoke de límites en verde.

### W5-002 Catálogo de módulos por plan
- Alcance:
  - Matriz plan->módulos.
  - Sincronización de módulos habilitados en tenant settings.
- Dependencias: `W5-001`.
- Entregable: control centralizado de módulos por plan.
- DoD:
  - API bloquea módulos no contratados.
  - UI no expone módulos no habilitados.

### W5-003 Inventario MVP multi-sucursal
- Alcance:
  - kardex básico.
  - movimientos entrada/salida/ajuste.
  - stock por sucursal e ítem.
- Dependencias: `W5-001`.
- Entregable: inventario transaccional base.
- DoD:
  - operaciones auditadas.
  - consultas con `tenant_id` y `branch_id`.

### W5-004 POS <-> Inventario transaccional
- Alcance:
  - descontar stock al cobrar venta.
  - reversar stock en anulaciones/devoluciones.
- Dependencias: `W5-003`.
- Entregable: sincronía de stock con POS.
- DoD:
  - no hay descuadres en casos base.
  - smoke de venta/anulación en verde.

### W5-005 Offline POS v2
- Alcance:
  - cola local robusta en PWA.
  - reintentos y replay seguro.
- Dependencias: `W5-004`.
- Entregable: operación offline estable.
- DoD:
  - no duplicados por reconexión.
  - idempotencia validada.

### W5-006 Resolución de conflictos sync
- Alcance:
  - estrategia por tipo de evento.
  - log de conflictos + endpoint soporte.
- Dependencias: `W5-005`.
- Entregable: conflictos observables y resolubles.
- DoD:
  - reglas documentadas.
  - métricas de conflicto disponibles.

### W5-007 Observabilidad operativa SaaS
- Alcance:
  - KPIs por tenant (sync lag, errores, DLQ, latencia).
  - endpoint/dashboard operativo mínimo.
- Dependencias: `W5-006`.
- Entregable: visibilidad operativa central.
- DoD:
  - métricas consumibles por soporte.
  - trazabilidad por `request_id`.

### W5-008 Hardening de seguridad operativa
- Alcance:
  - endurecer rate limiting.
  - revisión de permisos críticos.
  - controles de sesiones/tokens.
- Dependencias: `W5-001`, `W5-002`.
- Entregable: superficie de ataque reducida.
- DoD:
  - pruebas negativas en verde.
  - checklist seguridad actualizado.

### W5-009 Smoke/regresión ampliada
- Alcance:
  - pruebas automáticas inventario + offline + límites plan.
  - casos de concurrencia.
- Dependencias: `W5-004`, `W5-006`, `W5-008`.
- Entregable: gate de calidad Wave 5.
- DoD:
  - `--positive`, `--negative`, `--all` en verde.

### W5-010 Cierre Wave 5
- Alcance:
  - acta final, release notes, tag baseline.
- Dependencias: `W5-009`.
- Entregable: baseline `wave-5-baseline`.
- DoD:
  - acta aprobada.
  - tag publicado en remoto.

## 4) Tickets Wave 6 (ejecutables)

### W6-001 Compras y proveedores (MVP)
- Dependencias: `W5-003`.
- Resultado: ciclo compra/recepción con impacto en stock.

### W6-002 Inventario avanzado
- Dependencias: `W6-001`.
- Resultado: transferencias entre sucursales + conteos cíclicos.

### W6-003 CRM comercial básico
- Dependencias: `W5-001`, `W5-002`.
- Resultado: segmentación y listas de precios.

### W6-004 Reportería ejecutiva multi-tenant
- Dependencias: `W5-007`.
- Resultado: KPIs negocio (ventas, margen, rotación).

### W6-005 Cuentas por cobrar y recaudo
- Dependencias: `W6-003`.
- Resultado: estados de cuenta, abonos, aging.

### W6-006 Integraciones externas
- Dependencias: `W6-004`, `W6-005`.
- Resultado: conectores pagos/mensajería/contable.

### W6-007 Fiscal opcional avanzado (producción)
- Dependencias: `W5-010`.
- Resultado: operación fiscal robusta en CERT/PROD sin forzar a todos los tenants.

### W6-008 Admin SaaS central
- Dependencias: `W5-001`, `W5-007`.
- Resultado: gobierno central de tenants/planes/módulos.

### W6-009 Performance y resiliencia
- Dependencias: `W6-004`, `W6-006`.
- Resultado: tuning y pruebas de carga con umbrales objetivos.

### W6-010 Cierre Wave 6
- Dependencias: `W6-009`.
- Resultado: baseline `wave-6-baseline`.

## 5) Ejecución por bloques (para no pisar tickets)

### Bloque A (Fundación Wave 5)
- `W5-001`, `W5-002`
- Riesgo principal: romper permisos/módulos existentes.
- Mitigación: smoke negativa de guards antes de merge.

### Bloque B (Core operativo inventario + POS)
- `W5-003`, `W5-004`
- Riesgo principal: inconsistencia de stock.
- Mitigación: transacciones + pruebas de reverso.

### Bloque C (Offline y sincronización avanzada)
- `W5-005`, `W5-006`
- Riesgo principal: duplicidad/conflicto silencioso.
- Mitigación: idempotencia estricta + log de conflictos.

### Bloque D (Observabilidad y seguridad)
- `W5-007`, `W5-008`
- Riesgo principal: falta de trazabilidad o falsos positivos de rate limit.
- Mitigación: métricas por tenant + calibración de límites.

### Bloque E (Calidad y cierre)
- `W5-009`, `W5-010`
- Riesgo principal: regresiones tardías.
- Mitigación: gate smoke obligatorio + acta con evidencias.

### Bloque F (Wave 6 fase 1)
- `W6-001`, `W6-002`, `W6-003`

### Bloque G (Wave 6 fase 2)
- `W6-004`, `W6-005`, `W6-006`

### Bloque H (Wave 6 fase 3)
- `W6-007`, `W6-008`, `W6-009`, `W6-010`

## 6) Plantilla de contexto por ticket (usar en cada arranque)

Copiar y completar:

```md
## Contexto Ticket: W?-???
- Objetivo:
- Dependencias cumplidas:
- Riesgo principal:
- Endpoints/Flujos impactados:
- Migraciones requeridas:
- Smoke mínima requerida:
- Evidencia esperada:
- Criterio de aceptación (DoD):
```

## 7) Gating estándar por ticket
- `php bin/console.php migrate:status` sin pendientes inesperados.
- `php bin/console.php seed:dev` (si aplica).
- `php tests/smoke/run.php --positive` y `--negative` en verde.
- Actualización de docs (`api.md`, `requests.http`, acta parcial).

## 8) Salida esperada al finalizar cada wave
- Acta final aprobada.
- Release notes cerradas.
- Tag baseline publicado en remoto.
- `project-status.md` y `context.md` actualizados.

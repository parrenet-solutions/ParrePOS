# Release Notes - Wave 7 (Final)

Fecha: `2026-02-16`

## Resumen
Wave 7 fortalece la operación comercial y la capa opcional por módulo en RD, manteniendo multi-tenant estricto, guardas de plan/módulo y seguridad first-class.

## Completado

### W7-001 Matriz modular extendida
- Consolidación de módulos opcionales por tenant/plan.
- Guardas consistentes para activación/desactivación sin cambios de código.

### W7-002 Payments Plus (opcional)
- Intents de pago, webhook idempotente y conciliación diaria.
- Permisos dedicados y fallback al core cuando módulo deshabilitado.

### W7-003 Accounting (opcional)
- Exportaciones contables por rango y cierre de periodo.
- Mapeo de cuentas por tenant.

### W7-004 Hardware Bridge (opcional)
- Gestión de dispositivos POS y cola de impresión idempotente.
- Eventos operativos de hardware por tenant.

### W7-005 Inventario comercial
- Políticas min/max por sucursal+ítem.
- Alertas de quiebre/sobrestock y sugerencias básicas de reposición.

### W7-006 Cierre operativo diario
- Consolidado diario de caja/ventas/cobros por sucursal.
- Reapertura controlada con auditoría.

### W7-007 Backup Ops (opcional)
- Snapshot lógico por tenant.
- Restore run guiado (`DRY_RUN`/`APPLY`) con trazabilidad.

### W7-008 Security Plus (opcional)
- Enrolamiento y verificación MFA (TOTP) por usuario.
- Rotación de secretos operativos por tenant.

### W7-009 Observabilidad avanzada
- Snapshot SLI/SLO tenant-safe.
- Reglas de alerta por umbral + evaluación + incidentes.

### W7-010 Onboarding toolkit
- Diagnóstico de instalación por tenant.
- Plantillas de onboarding y checklist versionado.

### W7-011 Smoke/regresión ampliada
- Cobertura ampliada para módulos opcionales on/off.
- Validación de flujos ops avanzados (SLI/SLO, alertas, onboarding).

### W7-012 Cierre de wave
- Actas por bloque, release notes y checklist baseline preparados.

## Resultado operativo
- Wave 7 queda lista para validación final y congelamiento de baseline con tag `wave-7-baseline`.
- Se preserva la política de producto: fiscal y módulos avanzados son opcionales por tenant.

## Nota de producto
- La facturación fiscal **no es obligatoria**.
- Clientes no regularizados continúan operando en modo no fiscal sin bloqueo.

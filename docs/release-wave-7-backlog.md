# Backlog Ejecutable - Wave 7

Fecha base: `2026-02-16`  
Estado: `Planificado`  
Dependencia de entrada: cierre Wave 6 (`wave-6-baseline`)

## 1) Objetivo
Convertir ParrePOS en una operación comercial más robusta para RD, manteniendo el producto simple en uso diario y respetando:
- multi-tenant estricto,
- modularidad real por tenant/plan,
- seguridad first-class,
- fiscal opcional (no obligatoria).

## 2) Regla clave de producto (obligatoria)
Todo lo nuevo que no sea base operativa debe salir como **módulo opcional** o **feature flag** por tenant.

Aplicación práctica en Wave 7:
- `fiscal`: opcional (ya vigente).
- `payments_plus` (pagos locales avanzados): opcional.
- `accounting` (exportación contable/libros): opcional.
- `hardware_bridge` (impresora/gaveta/scanner): opcional.
- `backup_ops` (backup/restore autoservicio): opcional por plan.
- `security_plus` (2FA y hardening empresarial): opcional por tenant/rol.

## 3) Estado general
- `W7-001`: COMPLETADO
- `W7-002`: COMPLETADO
- `W7-003`: COMPLETADO
- `W7-004`: COMPLETADO
- `W7-005`: COMPLETADO
- `W7-006`: COMPLETADO
- `W7-007`: COMPLETADO
- `W7-008`: COMPLETADO
- `W7-009`: COMPLETADO
- `W7-010`: COMPLETADO
- `W7-011`: COMPLETADO
- `W7-012`: COMPLETADO

## 4) Tickets (ejecutables)

### W7-001 Matriz modular extendida y feature flags por tenant
- Tipo: base plataforma.
- Dependencias: Wave 6 cerrada.
- Alcance:
  - Extender `tenant_settings.modules` para nuevos módulos opcionales.
  - Añadir guardas backend para cada módulo nuevo.
  - Semillas/planes con módulos por defecto (sin forzar activación).
- Resultado esperado:
  - cada módulo nuevo se puede activar/desactivar sin tocar código.
- DoD:
  - endpoints nuevos protegidos por módulo/plan,
  - smoke negativa para módulo deshabilitado en verde.

### W7-002 Pagos locales avanzados y conciliación (opcional)
- Tipo: módulo opcional (`payments_plus`).
- Dependencias: `W7-001`.
- Alcance:
  - Adapter de pasarela/adquiriente local (mock + proveedor real configurable).
  - Registro de intentos de cobro, estados y conciliación básica.
  - Webhook seguro de confirmación de pago.
- Resultado esperado:
  - flujo de pago trazable y conciliable sin romper POS base.
- DoD:
  - idempotencia en callbacks,
  - conciliación diaria por tenant,
  - fallback a pago manual si módulo deshabilitado.

### W7-003 Contabilidad base y exportaciones (opcional)
- Tipo: módulo opcional (`accounting`).
- Dependencias: `W6-004`, `W6-005`, `W7-001`.
- Alcance:
  - Exportación CSV/Excel de libro de ventas/cobros/compras.
  - Mapeo simple de cuentas contables por tenant.
  - Endpoint de cierre de período (solo lectura/export).
- Resultado esperado:
  - salida contable usable para despacho contable externo.
- DoD:
  - reportes por rango de fechas,
  - consistencia entre reportes y transacciones fuente,
  - módulo deshabilitado => API bloqueada.

### W7-004 Hardware POS bridge (opcional)
- Tipo: módulo opcional (`hardware_bridge`).
- Dependencias: `W7-001`.
- Alcance:
  - cola de impresión de tickets (ESC/POS) y reintento.
  - abstracción para apertura de gaveta y lectura scanner (cuando aplique).
  - estado de dispositivos por caja/sucursal.
- Resultado esperado:
  - operación estable con hardware común de POS.
- DoD:
  - impresión idempotente,
  - manejo de fallos sin detener la venta,
  - logs operativos por dispositivo.

### W7-005 Inventario comercial (mínimos/máximos y alertas)
- Tipo: mejora core inventory.
- Dependencias: `W6-002`.
- Alcance:
  - parámetros min/max por item+sucursal.
  - alertas de quiebre y sobrestock.
  - sugerencias de reposición básicas.
- Resultado esperado:
  - inventario más accionable para operaciones pequeñas/medianas.
- DoD:
  - alertas consultables por API,
  - sin impacto negativo en performance de stock/kardex.

### W7-006 Cierre operativo diario (caja/ventas/cobros)
- Tipo: mejora core operación.
- Dependencias: `W6-005`, `W7-002`.
- Alcance:
  - endpoint de cierre diario por sucursal.
  - consolidado de caja, ventas, abonos y diferencias.
  - bitácora de cierre y re-apertura controlada.
- Resultado esperado:
  - rutina diaria auditable para supervisor.
- DoD:
  - reporte reproducible,
  - acciones críticas auditadas,
  - control por permisos.

### W7-007 Backup/restore operativo (opcional por plan)
- Tipo: módulo opcional (`backup_ops`).
- Dependencias: `W7-001`.
- Alcance:
  - orquestación de backups (snapshot lógico) por tenant.
  - catálogo de backups disponibles y restore guiado (entorno controlado).
  - runbook técnico de recuperación.
- Resultado esperado:
  - continuidad operativa ante incidentes.
- DoD:
  - backup verificable,
  - restore probado en ambiente de prueba,
  - trazabilidad de quién/cuándo ejecutó.

### W7-008 Seguridad empresarial (opcional)
- Tipo: módulo opcional (`security_plus`).
- Dependencias: `W7-001`.
- Alcance:
  - 2FA opcional para roles críticos.
  - rotación de secretos/claves operativas.
  - hardening de sesión para admin central.
- Resultado esperado:
  - mayor seguridad para tenants con exigencia empresarial.
- DoD:
  - flujos críticos protegidos,
  - pruebas negativas de acceso en verde,
  - rollback seguro si tenant desactiva módulo.

### W7-009 Observabilidad avanzada y alertamiento
- Tipo: mejora plataforma.
- Dependencias: `W6-009`, `W7-002`, `W7-007`.
- Alcance:
  - SLIs/SLOs por tenant (latencia, errores, colas, sync lag).
  - alertas por umbral (email/webhook interno).
  - tablero operativo de incidentes.
- Resultado esperado:
  - soporte proactivo en vez de reactivo.
- DoD:
  - métricas de salud por módulo,
  - alertas con contexto accionable.

### W7-010 Onboarding y toolkit de soporte
- Tipo: mejora operativa/comercial.
- Dependencias: `W7-001`.
- Alcance:
  - endpoint de diagnóstico de instalación/config.
  - plantilla de onboarding por tipo de cliente.
  - checklist guiado de salida a producción.
- Resultado esperado:
  - menor fricción en implementaciones nuevas.
- DoD:
  - diagnóstico ejecutable,
  - documentación y checklist versionados.

### W7-011 Smoke/regresión ampliada Wave 7
- Tipo: calidad.
- Dependencias: `W7-002`..`W7-010`.
- Alcance:
  - ampliar suite smoke con módulos opcionales (on/off).
  - casos de concurrencia en pagos/cierre/colas.
- Resultado esperado:
  - gate confiable previo a baseline.
- DoD:
  - `--positive`, `--negative`, `--all` en verde,
  - cobertura de rutas nuevas críticas.

### W7-012 Cierre Wave 7
- Tipo: release.
- Dependencias: `W7-011`.
- Alcance:
  - acta final + release notes + checklist baseline + tag.
- Resultado esperado:
  - baseline `wave-7-baseline` lista.
- DoD:
  - documentación cerrada,
  - tag publicado,
  - estado proyecto actualizado.

## 5) Ejecución por bloques (sin pisar tickets)

### Bloque A (Fundación modular)
- `W7-001`, `W7-002`
- Riesgo: romper control de módulos/planes.
- Mitigación: pruebas negativas de guards + idempotencia en pagos.

### Bloque B (Operación comercial)
- `W7-003`, `W7-004`, `W7-005`, `W7-006`
- Riesgo: inconsistencias entre ventas/inventario/cobros/cierre.
- Mitigación: transacciones + auditoría + reconciliación diaria.

### Bloque C (Resiliencia y seguridad)
- `W7-007`, `W7-008`, `W7-009`, `W7-010`
- Riesgo: complejidad operativa alta.
- Mitigación: feature flags por tenant + rollout gradual.

### Bloque D (Calidad y release)
- `W7-011`, `W7-012`
- Riesgo: regresiones tardías.
- Mitigación: smoke obligatoria + acta de cierre con evidencias.

## 6) Gate mínimo por ticket
- `php bin/console.php migrate:status`
- `php bin/console.php seed:dev` (si aplica)
- `php tests/smoke/run.php --positive`
- `php tests/smoke/run.php --negative`
- `php tests/smoke/run.php --all`
- Actualización documental mínima:
  - `docs/api.md`
  - `docs/api-modules.md`
  - `docs/requests.http`
  - acta/entrega técnica de bloque

## 7) Criterio de salida de Wave 7
- módulos opcionales operativos sin afectar core,
- cierre diario y observabilidad en verde,
- seguridad reforzada configurable por tenant,
- acta final aprobada + tag `wave-7-baseline`.

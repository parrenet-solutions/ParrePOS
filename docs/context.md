# Contexto Canonico del Proyecto ParrePOS

Fecha de actualizacion: `2026-02-16`

## Indice
- [1. Proposito de este documento](#1-proposito-de-este-documento)
- [2. Estado consolidado por wave](#2-estado-consolidado-por-wave)
- [3. Principios obligatorios](#3-principios-obligatorios)
- [4. Definicion de modulos](#4-definicion-de-modulos)
- [5. Seguridad y RBAC](#5-seguridad-y-rbac)
- [6. Route manifest (alto nivel)](#6-route-manifest-alto-nivel)
- [7. Reglas de datos y migraciones](#7-reglas-de-datos-y-migraciones)
- [8. Gate minimo de calidad por ticket](#8-gate-minimo-de-calidad-por-ticket)
- [9. Fuentes de verdad relacionadas](#9-fuentes-de-verdad-relacionadas)

## 1. Proposito de este documento
Este archivo define la fuente de verdad tecnica y operativa para desarrollo, QA y colaboracion con ChatGPT.

## 2. Estado consolidado por wave
- `Wave 0` Fundaciones: cerrada.
- `Wave 1` Facturacion tradicional 8.5x11: cerrada.
- `Wave 2` POS offline-first (incluye hitos 2.1 y 2.2): cerrada.
- `Wave 3` Fiscal opcional (base funcional): cerrada.
- `Wave 4` Robustez fiscal y operativa: cerrada.
- `Wave 5` Operacion SaaS (planes, inventario, observabilidad, hardening): cerrada.
- `Wave 6` Expansiones comerciales (CxC, compras, reporteria ejecutiva, integraciones): cerrada.
- `Wave 7` Módulos opcionales comerciales/operativos (payments_plus, accounting, hardware, backup_ops, security_plus): en ejecución.

## 3. Principios obligatorios
1. Multi-tenant estricto.
- Toda tabla de negocio con `tenant_id`.
- Toda consulta de negocio filtrada por `tenant_id`.
- Cero acceso cruzado entre tenants.

2. Modularidad por tenant y plan.
- Modulos habilitables: `pos`, `inventory`, `recurring`, `fiscal`, `integrations`, `payments_plus`, `accounting`, `hardware_bridge`, `backup_ops`, `security_plus`.
- Bloqueo por modulo deshabilitado en API y ocultamiento en UI.

3. Seguridad first-class.
- JWT access/refresh con rotacion.
- Rate limiting en login y endpoints sensibles.
- Validacion estricta.
- Sin stack traces en respuestas.

4. Fiscal opcional.
- No bloquear operacion no fiscal para clientes no regularizados.
- `fiscal.enabled` y `fiscal.dgii_registered` controlan emision fiscal.

5. Auditoria append-only.
- Registrar acciones criticas (auth, settings, emision, sync critico).

## 4. Definicion de modulos
- `Core`: auth, tenancy, RBAC, auditoria, health, utilidades.
- `Billing`: clientes, items, facturas 8.5x11, PDF, email, recurrentes.
- `POS`: sucursales, cajas, sesiones de caja, ventas, pagos, hold/resume.
- `Sync`: ingest/status/eventos, idempotencia, conflictos por `op_id`.
- `Inventory`: movimientos, stock por sucursal, integracion con POS.
- `Inventory`: movimientos, stock por sucursal, políticas min/max y alertas de reposición.
- `Fiscal`: configuracion, documentos, eventos/acuses, retry, metricas, webhook.
- `Integrations`: conectores externos por tenant (pagos, mensajería, contable) con cola y trazabilidad.
- `PaymentsPlus`: pasarela de pagos opcional, intents, webhooks idempotentes y conciliación diaria.
- `Accounting`: exportaciones contables y cierre de período opcional por tenant.
- `HardwareBridge`: gestión de dispositivos POS, cola de impresión y eventos de hardware.
- `AdminSaaS`: gobierno central de tenants/planes/módulos por llave de plataforma.
- `Ops`: metricas por tenant y soporte operacional.
- `Ops`: métricas por tenant, soporte operacional y cierre diario de operación.

## 5. Seguridad y RBAC
- Contexto minimo por request autenticado:
  - `user_id`
  - `tenant_id`
  - permisos/roles
- Middleware base esperados:
  - `RequestIdMiddleware`
  - `JsonBodyMiddleware`
  - `AuthMiddleware`
  - `TenantGuardMiddleware`
  - Middleware de autorizacion
- Endpoints publicos permitidos:
  - `GET /health`

## 6. Route manifest (alto nivel)
Prefijo general: `/api/v1`

- `Auth`
  - `/auth/login`, `/auth/refresh`, `/auth/logout`, `/me`
- `Billing`
  - `/customers`, `/items`, `/invoices`
- `POS`
  - `/branches`, `/pos/registers`, `/pos/cash-sessions`, `/pos/sales`, `/pos/cash-movements`
- `Sync`
  - `/sync/events`, `/sync/status`
- `Inventory`
  - `/inventory/movements`, `/inventory/stock`
  - `/inventory/policies/minmax`, `/inventory/alerts`
  - `/inventory/transfers`, `/inventory/transfers/{id}/dispatch`, `/inventory/transfers/{id}/receive`
  - `/inventory/counts`, `/inventory/counts/{id}/close`
  - `/suppliers`, `/purchases/orders`, `/purchases/orders/{id}/receive`
- `CRM/Pricing`
  - `/crm/segments`, `/crm/segments/{id}/customers`
  - `/pricing/lists`, `/pricing/lists/{id}/items`
- `Reports`
  - `/reports/executive`
- `Receivables`
  - `/receivables/accounts`, `/receivables/accounts/{id}/payments`, `/receivables/aging`
- `Integrations`
  - `/integrations/connectors`, `/integrations/connectors/{code}`, `/integrations/connectors/{code}/test`
  - `/integrations/events/publish`, `/integrations/deliveries`
- `PaymentsPlus`
  - `/payments-plus/status`, `/payments-plus/transactions/intent`
  - `/payments-plus/transactions`, `/payments-plus/transactions/{id}`
  - `/payments-plus/reconcile/daily`, `/payments-plus/reconciliations`
  - `/payments-plus/webhook` (público con key)
- `Accounting`
  - `/accounting/exports/sales`, `/accounting/exports/collections`, `/accounting/exports/purchases`
  - `/accounting/account-map`, `/accounting/periods/close`
- `HardwareBridge`
  - `/hardware/devices`, `/hardware/print-jobs`
  - `/hardware/devices/{id}/drawer/open`, `/hardware/devices/{id}/health`
- `BackupOps`
  - `/backup/snapshots`, `/backup/restores`, `/backup/runbook`
- `SecurityPlus`
  - `/security-plus/status`, `/security-plus/mfa/totp`, `/security-plus/mfa/verify`
  - `/security-plus/mfa/methods`, `/security-plus/secrets/rotate`, `/security-plus/secrets/rotations`
- `Fiscal`
  - `/fiscal/status`, `/fiscal/preflight`, `/fiscal/config`, `/fiscal/environment/prod/activate`, `/fiscal/documents*`, `/fiscal/metrics`, `/fiscal/webhook/ack`
- `Ops`
  - `/ops/tenant-metrics`, `/ops/sync-conflicts`, `/ops/jobs/queues`, `/ops/jobs/dlq`, `/ops/jobs/dlq/{id}/requeue`
  - `/ops/daily-close`, `/ops/daily-close/{id}`, `/ops/daily-close/{id}/reopen`
  - `/ops/sli-slo`, `/ops/alerts/rules`, `/ops/alerts/evaluate`, `/ops/incidents`
  - `/ops/diagnostics`, `/ops/onboarding/templates`, `/ops/onboarding/checklist`
- `Admin SaaS` (llave de plataforma)
  - `/admin/tenants`, `/admin/tenants/{id}`, `/admin/tenants/{id}/status`
  - `/admin/tenants/{id}/modules`, `/admin/tenants/{id}/subscription`, `/admin/plans`

## 7. Reglas de datos y migraciones
- Motor: MariaDB + InnoDB + utf8mb4.
- Migraciones incrementales en `database/migrations`.
- Convencion recomendada: scripts idempotentes y auditables.
- Toda llave unica de negocio en tablas multi-tenant debe incluir `tenant_id` cuando corresponda.

## 8. Gate minimo de calidad por ticket
Comandos base:
```bash
php bin/console.php migrate:status
php bin/console.php seed:dev
php tests/smoke/run.php --positive
php tests/smoke/run.php --negative
php tests/smoke/run.php --all
```

Criterios:
- Sin regresiones en rutas existentes.
- Documentacion actualizada (`docs/api.md`, `docs/requests.http`, acta/release segun aplique).
- Evidencia registrada para cierre de ticket/wave.

## 9. Fuentes de verdad relacionadas
- Reglas maestras: `AGENTS.md`
- Roadmap ejecutivo: `docs/project-roadmap.md`
- Estado consolidado: `docs/project-status.md`
- API: `docs/api.md`
- Requests operativos: `docs/requests.http`

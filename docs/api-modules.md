# API Backend por Módulo (Exhaustiva)

Fecha de actualización: `2026-02-16`
Fuente: rutas activas en `app/Bootstrap/App.php`.

## Índice
- [1. Convenciones globales](#1-convenciones-globales)
- [2. Salud y autenticación](#2-salud-y-autenticación)
- [3. Admin SaaS central](#3-admin-saas-central)
- [4. Usuarios / contexto](#4-usuarios--contexto)
- [5. Billing (clientes, ítems, facturas, templates)](#5-billing-clientes-ítems-facturas-templates)
- [6. Fiscal opcional](#6-fiscal-opcional)
- [7. POS](#7-pos)
- [8. Inventario y compras](#8-inventario-y-compras)
- [9. CRM y pricing](#9-crm-y-pricing)
- [10. Cuentas por cobrar](#10-cuentas-por-cobrar)
- [11. Integraciones externas](#11-integraciones-externas)
- [12. Sync offline-first](#12-sync-offline-first)
- [13. Ops / observabilidad](#13-ops--observabilidad)
- [14. Recurrentes](#14-recurrentes)
- [15. Payments Plus (opcional)](#15-payments-plus-opcional)
- [16. Accounting (opcional)](#16-accounting-opcional)
- [17. Hardware Bridge (opcional)](#17-hardware-bridge-opcional)
- [18. Backup Ops (opcional)](#18-backup-ops-opcional)
- [19. Security Plus (opcional)](#19-security-plus-opcional)

## 1. Convenciones globales
- Prefijo de API: `/api/v1`
- Respuesta éxito:
```json
{"ok": true, "data": {}, "meta": {"request_id": "...", "ts": "..."}}
```
- Respuesta error:
```json
{"ok": false, "error": {"code": "...", "message": "...", "details": {}}, "meta": {"request_id": "...", "ts": "..."}}
```
- Seguridad base (según endpoint):
  - JWT (`Authorization: Bearer ...`)
  - `TenantGuardMiddleware` (tenant activo + módulo/plan)
  - RBAC por permisos
  - Admin central: `X-Platform-Admin-Key`

## 2. Salud y autenticación
| Método | Ruta | Auth | Tenant | Permiso |
|---|---|---|---|---|
| GET | `/health` | No | No | - |
| POST | `/auth/login` | No | No | - |
| POST | `/auth/refresh` | No | No | - |
| POST | `/auth/logout` | No | No | - |

## 3. Admin SaaS central
Requiere `X-Platform-Admin-Key` (`PLATFORM_ADMIN_KEY`).

| Método | Ruta | Auth JWT | TenantGuard | Permiso |
|---|---|---|---|---|
| GET | `/admin/tenants` | No | No | Admin key |
| GET | `/admin/tenants/{id}` | No | No | Admin key |
| PUT | `/admin/tenants/{id}/status` | No | No | Admin key |
| PUT | `/admin/tenants/{id}/modules` | No | No | Admin key |
| PUT | `/admin/tenants/{id}/subscription` | No | No | Admin key |
| GET | `/admin/plans` | No | No | Admin key |

## 4. Usuarios / contexto
| Método | Ruta | Auth | Tenant | Permiso |
|---|---|---|---|---|
| GET | `/me` | Sí | Sí | - |

## 5. Billing (clientes, ítems, facturas, templates)
Módulo requerido: `invoicing`.

### Clientes
| Método | Ruta | Permiso |
|---|---|---|
| POST | `/customers` | `customers.write` |
| PUT | `/customers/{id}` | `customers.write` |
| GET | `/customers/{id}` | `customers.read` |
| GET | `/customers` | `customers.read` |

### Ítems
| Método | Ruta | Permiso |
|---|---|---|
| POST | `/items` | `items.write` |
| PUT | `/items/{id}` | `items.write` |
| GET | `/items/{id}` | `items.read` |
| GET | `/items` | `items.read` |

### Facturas
| Método | Ruta | Permiso |
|---|---|---|
| POST | `/invoices` | `invoices.write` |
| GET | `/invoices` | `invoices.read` |
| GET | `/invoices/{id}` | `invoices.read` |
| POST | `/invoices/{id}/issue` | `invoices.issue` |
| POST | `/invoices/{id}/void` | `invoices.void` |
| GET | `/invoices/{id}/pdf` | `invoices.read` |
| POST | `/invoices/{id}/send-email` | `invoices.write` |

### Templates de factura
| Método | Ruta | Permiso |
|---|---|---|
| POST | `/invoice-templates` | `templates.manage` |
| PUT | `/invoice-templates/{id}` | `templates.manage` |
| GET | `/invoice-templates/{id}` | `templates.manage` |
| GET | `/invoice-templates` | `templates.manage` |
| POST | `/invoice-templates/{id}/set-default` | `templates.manage` |

## 6. Fiscal opcional
- Webhook público (sin JWT) con key fiscal:
  - `POST /fiscal/webhook/ack`
- El resto requiere JWT + TenantGuard.

| Método | Ruta | Permiso |
|---|---|---|
| GET | `/fiscal/status` | `fiscal.read` |
| GET | `/fiscal/preflight` | `fiscal.read` |
| PUT | `/fiscal/config` | `fiscal.manage` |
| POST | `/fiscal/environment/prod/activate` | `fiscal.manage` |
| GET | `/fiscal/documents` | `fiscal.read` |
| GET | `/fiscal/documents/search` | `fiscal.read` |
| GET | `/fiscal/documents/summary` | `fiscal.read` |
| GET | `/fiscal/metrics` | `fiscal.read` |
| GET | `/fiscal/documents/{id}` | `fiscal.read` |
| GET | `/fiscal/documents/{id}/events` | `fiscal.read` |
| GET | `/fiscal/documents/{id}/acks` | `fiscal.read` |
| POST | `/fiscal/documents/{id}/retry` | `fiscal.manage` |
| POST | `/fiscal/documents/retry-bulk` | `fiscal.manage` |

## 7. POS
Módulo requerido: `pos`.

### Sucursales
| Método | Ruta | Permiso |
|---|---|---|
| POST | `/branches` | `pos.access` + `branches.manage` |
| PUT | `/branches/{id}` | `pos.access` + `branches.manage` |
| GET | `/branches/{id}` | `pos.access` + `branches.manage` |
| GET | `/branches` | `pos.access` + `branches.manage` |

### Cajas (registers)
| Método | Ruta | Permiso |
|---|---|---|
| POST | `/pos/registers` | `pos.access` + `pos.registers.manage` |
| POST | `/pos/registers/handshake` | `pos.access` |
| PUT | `/pos/registers/{id}` | `pos.access` + `pos.registers.manage` |
| GET | `/pos/registers/{id}` | `pos.access` + `pos.registers.manage` |
| GET | `/pos/registers` | `pos.access` + `pos.registers.manage` |

### Sesiones de caja
| Método | Ruta | Permiso |
|---|---|---|
| POST | `/pos/cash-sessions/open` | `pos.access` + `pos.cash.open` |
| POST | `/pos/cash-sessions/{id}/close` | `pos.access` + `pos.cash.close` |

### Movimientos de caja
| Método | Ruta | Permiso |
|---|---|---|
| POST | `/pos/cash-movements` | `pos.access` + `pos.cash.adjust` |
| GET | `/pos/cash-movements` | `pos.access` + `pos.cash.movements.read` |
| GET | `/pos/cash-movements/summary` | `pos.access` + `pos.cash.movements.read` |
| POST | `/pos/cash-movements/{id}/reverse` | `pos.access` + `pos.cash.movements.reverse` |

### Ventas POS
| Método | Ruta | Permiso |
|---|---|---|
| POST | `/pos/sales` | `pos.access` + `pos.sales.pay` |
| POST | `/pos/sales/{id}/void` | `pos.access` + `pos.sales.void` |
| POST | `/pos/sales/{id}/hold` | `pos.access` + `pos.sales.write` |
| POST | `/pos/sales/{id}/resume` | `pos.access` + `pos.sales.write` |
| GET | `/pos/sales/{id}` | `pos.access` + `pos.sales.read` |
| GET | `/pos/sales` | `pos.access` + `pos.sales.read` |

## 8. Inventario y compras
Módulo requerido: `inventory`.

### Inventario base
| Método | Ruta | Permiso |
|---|---|---|
| POST | `/inventory/movements` | `inventory.write` |
| GET | `/inventory/stock` | `inventory.read` |
| GET | `/inventory/kardex` | `inventory.read` |
| PUT | `/inventory/policies/minmax` | `inventory.write` |
| GET | `/inventory/policies/minmax` | `inventory.read` |
| GET | `/inventory/alerts` | `inventory.read` |

### Inventario avanzado
| Método | Ruta | Permiso |
|---|---|---|
| POST | `/inventory/transfers` | `inventory.write` |
| POST | `/inventory/transfers/{id}/dispatch` | `inventory.write` |
| POST | `/inventory/transfers/{id}/receive` | `inventory.write` |
| POST | `/inventory/counts` | `inventory.write` |
| POST | `/inventory/counts/{id}/close` | `inventory.write` |

### Compras y proveedores
| Método | Ruta | Permiso |
|---|---|---|
| POST | `/suppliers` | `inventory.write` |
| GET | `/suppliers` | `inventory.read` |
| POST | `/purchases/orders` | `inventory.write` |
| POST | `/purchases/orders/{id}/receive` | `inventory.write` |

## 9. CRM y pricing
Usa guard POS + `pos.access`.

| Método | Ruta | Permiso |
|---|---|---|
| POST | `/crm/segments` | `customers.write` |
| POST | `/crm/segments/{id}/customers` | `customers.write` |
| POST | `/pricing/lists` | `items.write` |
| POST | `/pricing/lists/{id}/items` | `items.write` |

## 10. Cuentas por cobrar
Módulo requerido: `invoicing`.

| Método | Ruta | Permiso |
|---|---|---|
| POST | `/receivables/accounts` | `invoices.write` |
| GET | `/receivables/accounts` | `invoices.read` |
| POST | `/receivables/accounts/{id}/payments` | `invoices.write` |
| GET | `/receivables/aging` | `invoices.read` |

## 11. Integraciones externas
Módulo requerido: `integrations`.

| Método | Ruta | Permiso |
|---|---|---|
| GET | `/integrations/connectors` | `integration.read` |
| PUT | `/integrations/connectors/{code}` | `integration.manage` |
| POST | `/integrations/connectors/{code}/test` | `integration.manage` |
| POST | `/integrations/events/publish` | `integration.manage` |
| GET | `/integrations/deliveries` | `integration.read` |

## 12. Sync offline-first
Usa guard POS + `pos.access`.

| Método | Ruta | Permiso |
|---|---|---|
| POST | `/sync/events` | `sync.write` |
| GET | `/sync/status` | `sync.read` |

## 13. Ops / observabilidad
Requiere permisos `audit.read` y/o `ops.daily_close.*` según endpoint.

| Método | Ruta | Permiso |
|---|---|---|
| GET | `/ops/tenant-metrics` | `audit.read` |
| GET | `/ops/sync-conflicts` | `audit.read` |
| GET | `/ops/jobs/queues` | `audit.read` |
| GET | `/ops/jobs/dlq` | `audit.read` |
| POST | `/ops/jobs/dlq/{id}/requeue` | `audit.read` |
| GET | `/reports/executive` | `audit.read` |
| POST | `/ops/daily-close` | `ops.daily_close.manage` |
| GET | `/ops/daily-close` | `ops.daily_close.read` |
| GET | `/ops/daily-close/{id}` | `ops.daily_close.read` |
| POST | `/ops/daily-close/{id}/reopen` | `ops.daily_close.manage` |
| GET | `/ops/sli-slo` | `audit.read` |
| GET | `/ops/alerts/rules` | `audit.read` |
| PUT | `/ops/alerts/rules` | `ops.alerts.manage` |
| POST | `/ops/alerts/evaluate` | `ops.alerts.manage` |
| GET | `/ops/incidents` | `audit.read` |
| GET | `/ops/diagnostics` | `audit.read` |
| GET | `/ops/onboarding/templates` | `audit.read` |
| GET | `/ops/onboarding/checklist` | `audit.read` |
| PUT | `/ops/onboarding/checklist` | `ops.onboarding.manage` |

## 14. Recurrentes
Módulo requerido: `recurring`.

| Método | Ruta | Permiso |
|---|---|---|
| POST | `/recurring-rules` | `recurring.manage` |
| GET | `/recurring-rules` | `recurring.manage` |
| POST | `/recurring-rules/{id}/pause` | `recurring.manage` |
| POST | `/recurring-rules/{id}/resume` | `recurring.manage` |

## 15. Payments Plus (opcional)
Módulo requerido: `payments_plus`.

- Webhook público con firma por header:
  - `POST /payments-plus/webhook`
  - Header: `X-Payment-Webhook-Key`

| Método | Ruta | Permiso |
|---|---|---|
| GET | `/payments-plus/status` | `payments_plus.read` |
| POST | `/payments-plus/transactions/intent` | `payments_plus.manage` |
| GET | `/payments-plus/transactions` | `payments_plus.read` |
| GET | `/payments-plus/transactions/{id}` | `payments_plus.read` |
| POST | `/payments-plus/reconcile/daily` | `payments_plus.manage` |
| GET | `/payments-plus/reconciliations` | `payments_plus.read` |

## 16. Accounting (opcional)
Módulo requerido: `accounting`.

| Método | Ruta | Permiso |
|---|---|---|
| GET | `/accounting/exports/sales` | `accounting.read` |
| GET | `/accounting/exports/collections` | `accounting.read` |
| GET | `/accounting/exports/purchases` | `accounting.read` |
| GET | `/accounting/account-map` | `accounting.read` |
| PUT | `/accounting/account-map` | `accounting.manage` |
| POST | `/accounting/periods/close` | `accounting.manage` |

## 17. Hardware Bridge (opcional)
Módulo requerido: `hardware_bridge`.

| Método | Ruta | Permiso |
|---|---|---|
| POST | `/hardware/devices` | `hardware_bridge.manage` |
| GET | `/hardware/devices` | `hardware_bridge.read` |
| POST | `/hardware/print-jobs` | `hardware_bridge.manage` |
| GET | `/hardware/print-jobs` | `hardware_bridge.read` |
| POST | `/hardware/devices/{id}/drawer/open` | `hardware_bridge.manage` |
| GET | `/hardware/devices/{id}/health` | `hardware_bridge.read` |

## 18. Backup Ops (opcional)
Módulo requerido: `backup_ops`.

| Método | Ruta | Permiso |
|---|---|---|
| POST | `/backup/snapshots` | `backup_ops.manage` |
| GET | `/backup/snapshots` | `backup_ops.read` |
| POST | `/backup/restores` | `backup_ops.manage` |
| GET | `/backup/restores` | `backup_ops.read` |
| GET | `/backup/runbook` | `backup_ops.read` |

## 19. Security Plus (opcional)
Módulo requerido: `security_plus`.

| Método | Ruta | Permiso |
|---|---|---|
| GET | `/security-plus/status` | `security_plus.read` |
| PUT | `/security-plus/mfa/totp` | `security_plus.manage` |
| POST | `/security-plus/mfa/verify` | `security_plus.manage` |
| GET | `/security-plus/mfa/methods` | `security_plus.read` |
| POST | `/security-plus/secrets/rotate` | `security_plus.manage` |
| GET | `/security-plus/secrets/rotations` | `security_plus.read` |

## Referencias
- Contrato API extendido y ejemplos: `docs/api.md`
- Requests ejecutables: `docs/requests.http`
- Contexto canónico: `docs/context.md`

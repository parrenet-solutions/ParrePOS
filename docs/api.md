# API ParrePos (Wave 5)

Base URL: `/api/v1`

Referencia complementaria:
- Catálogo exhaustivo por módulo: `docs/api-modules.md`

## Formato de respuesta

Exito:
```json
{
  "ok": true,
  "data": {},
  "meta": {
    "request_id": "...",
    "ts": "2026-02-13T00:00:00Z"
  }
}
```

Error:
```json
{
  "ok": false,
  "error": {
    "code": "SOME_CODE",
    "message": "Mensaje legible"
  },
  "meta": {
    "request_id": "...",
    "ts": "2026-02-13T00:00:00Z"
  }
}
```

## Auth

### Login
`POST /auth/login`

Body requerido:
```json
{
  "tenant_slug": "demo",
  "email": "admin@demo.local",
  "password": "Admin12345!"
}
```

Respuesta:
```json
{
  "ok": true,
  "data": {
    "access_token": "...",
    "refresh_token": "...",
    "token_type": "Bearer",
    "expires_in": 900
  },
  "meta": {"request_id": "...", "ts": "..."}
}
```

### Refresh
`POST /auth/refresh`

Body:
```json
{
  "refresh_token": "..."
}
```

### Logout
`POST /auth/logout`

Body opcional:
```json
{
  "refresh_token": "..."
}
```

## Seguridad y guards

Todos los endpoints de negocio requieren:
- `Authorization: Bearer <access_token>`
- Tenant activo (`TENANT_SUSPENDED` si no aplica)
- Modulo habilitado por tenant/plan (`MODULE_DISABLED` si no aplica)
- Permiso RBAC por endpoint (`FORBIDDEN` si falta)
- Límite de plan por recurso cuando aplique (`PLAN_LIMIT_EXCEEDED`)

Regla de límites por plan:
- si la clave de límite no existe: se considera sin límite.
- si la clave existe y vale `0`: recurso no permitido.

## Sesión actual

### Me
`GET /me`

Incluye metadatos de plan del tenant autenticado:
- `plan.code`
- `plan.name`
- `plan.status`
- `plan.modules`
- `plan.limits`

## Permisos clave de Sync

- `POST /sync/events` requiere `sync.write`
- `GET /sync/status` requiere `sync.read`

## Fiscal (opcional por tenant)

Regla:
- Si `fiscal.enabled=false`, el sistema opera facturacion no fiscal.
- Si `fiscal.enabled=true`, la emision de invoices genera `fiscal_documents` y cola de envio.
- Para habilitar fiscal se exige `dgii_registered=true`.

Permisos:
- `GET /fiscal/status` requiere `fiscal.read`
- `PUT /fiscal/config` requiere `fiscal.manage`
- `GET /fiscal/documents` requiere `fiscal.read`
- `POST /fiscal/documents/{id}/retry` requiere `fiscal.manage`

### Fiscal status
`GET /fiscal/status`

Respuesta ejemplo:
```json
{
  "ok": true,
  "data": {
    "fiscal": {
      "enabled": false,
      "dgii_registered": false,
      "ncf_type": "B01",
      "series": "B01"
    },
    "profile": null,
    "sequence": {
      "current": 0,
      "next": 1
    }
  },
  "meta": {"request_id": "...", "ts": "..."}
}
```

### Actualizar config fiscal
`PUT /fiscal/config`

Body ejemplo:
```json
{
  "fiscal": {
    "enabled": true,
    "dgii_registered": true,
    "ncf_type": "B01",
    "series": "B01",
    "provider": "MOCK",
    "provider_url": "",
    "signing_secret": "tenant-secret-opcional",
    "emission_limits": {
      "daily_max": 0,
      "monthly_max": 0
    }
  },
  "profile": {
    "legal_name": "Demo SRL",
    "rnc": "131452987",
    "environment": "CERT",
    "status": "ACTIVE"
  }
}
```

### Listar documentos fiscales
`GET /fiscal/documents?status=PENDING&limit=20`

### Buscar documentos fiscales
`GET /fiscal/documents/search?status=FAILED&ncf=B010000001&date_from=2026-02-01&date_to=2026-02-15&limit=50`

### Resumen de documentos fiscales
`GET /fiscal/documents/summary?date_from=2026-02-01&date_to=2026-02-15`

### Métricas fiscales operativas
`GET /fiscal/metrics?date_from=2026-02-01&date_to=2026-02-15`

### Reintentar envio fiscal
`POST /fiscal/documents/{id}/retry`

### Reintento masivo fiscal
`POST /fiscal/documents/retry-bulk`

Body ejemplo:
```json
{
  "ids": [10, 12, 15]
}
```

### Obtener documento fiscal por id
`GET /fiscal/documents/{id}`

### Eventos de documento fiscal
`GET /fiscal/documents/{id}/events?limit=100`

### Acuses de documento fiscal
`GET /fiscal/documents/{id}/acks?limit=50`

### Webhook de acuse fiscal
`POST /fiscal/webhook/ack`

Headers:
- `X-Fiscal-Webhook-Key: <key>`

Body ejemplo:
```json
{
  "tenant_id": 1,
  "fiscal_document_id": 10,
  "ack_code": "ACCEPTED",
  "ack_message": "Aceptado por DGII",
  "track_id": "DGII-ABC123"
}
```

## Provider fiscal (Wave 4)

- `MOCK` (default): no requiere integración externa.
- `DGII`: usa endpoint externo (`fiscal.provider_url` o `FISCAL_DGII_URL`).
- En envíos fiscales se genera:
  - `request_hash` (SHA-256)
  - `signature` HMAC SHA-256 con `fiscal.signing_secret` o `FISCAL_SIGNING_SECRET`

## Estados fiscales (Wave 4)

Estados principales de `fiscal_documents`:
- `PENDING`: creado y pendiente de envío
- `PROCESSING`: worker tomó el documento
- `SENT`: request enviado al provider
- `ACCEPTED`: aceptado por provider/DGII
- `REJECTED`: rechazado por reglas de negocio/provider
- `FAILED`: fallo técnico/procesamiento
- `CANCELLED`: cancelado por anulación de factura mientras estaba en curso

## Retry y DLQ fiscal (Wave 4)

- Errores `RETRYABLE`: reintento automático con backoff exponencial en `jobs:fiscal-submit`.
- Errores `NON_RETRYABLE`: pasan a `jobs_dlq` de inmediato.
- Causa y trazabilidad quedan en `jobs_queue.last_error`, `jobs_dlq.last_error` y `fiscal_events`.

## Validaciones fiscales RD (Wave 4)

- NCF permitidos en emisión fiscal: `B01`, `B02`, `B14`, `B15`.
- Para `B01` y `B14` se exige documento de cliente (RNC/Cédula).
- Validación de documento:
  - RNC: 9 dígitos (checksum básico)
  - Cédula: 11 dígitos (checksum básico)
- Límites de emisión opcionales por tenant:
  - `fiscal.emission_limits.daily_max`
  - `fiscal.emission_limits.monthly_max`
- Si se excede límite: `409 FISCAL_LIMIT_EXCEEDED`.

## Alertas fiscales (Wave 4)

Cuando un documento queda en `FAILED` o `REJECTED`, se dispara alerta operativa:
- email interno (si `FISCAL_ALERT_EMAILS` está configurado)
- webhook interno opcional (`FISCAL_ALERT_WEBHOOK_URL`)

## Backoffice Fiscal (Wave 4)

- URL estática operativa: `/fiscal-backoffice.html`
- Permite:
  - listar/buscar documentos (`/fiscal/documents/search`)
  - revisar eventos y acuses por documento
  - reintentar individual (`/fiscal/documents/{id}/retry`)
  - reintento masivo (`/fiscal/documents/retry-bulk`)
  - visualizar KPIs (`/fiscal/metrics`)

## POS (requiere modulo `pos` habilitado)

### Handshake
`POST /pos/registers/handshake`

Body:
```json
{
  "device_id": "demo-device-1",
  "app_version": "2.2.0",
  "capabilities": ["offline", "sync"]
}
```

### Branches
- `POST /branches`
- `PUT /branches/{id}`
- `GET /branches/{id}`
- `GET /branches`

### Registers
- `POST /pos/registers`
- `PUT /pos/registers/{id}`
- `GET /pos/registers/{id}`
- `GET /pos/registers`

### Cash Sessions
- `POST /pos/cash-sessions/open`
- `POST /pos/cash-sessions/{id}/close`

### Cash Movements
- `POST /pos/cash-movements`
- `GET /pos/cash-movements?cash_session_id=1`
- `GET /pos/cash-movements/summary?cash_session_id=1`
- `POST /pos/cash-movements/{id}/reverse`

Idempotencia opcional:
- `X-Idempotency-Key: <uuid-o-key>`
- `X-Device-Id: <device-id>`

Si se repite la misma llave de idempotencia:
- HTTP `200`
- `data.duplicate = true`
- sin efectos colaterales

### Sales
- `POST /pos/sales`
- `GET /pos/sales`
- `GET /pos/sales/{id}`
- `POST /pos/sales/{id}/hold`
- `POST /pos/sales/{id}/resume`
- `POST /pos/sales/{id}/void`

Pago mixto ejemplo:
```json
{
  "branch_id": 1,
  "register_id": 1,
  "cash_session_id": 1,
  "items": [
    {"item_id": 1, "name": "Cafe", "qty": 1, "unit_price": 1000, "discount": 0, "tax_rate": 0.18}
  ],
  "payments": [
    {"method": "CASH", "amount": 1000},
    {"method": "CARD", "amount": 180, "reference": "POS-001"}
  ]
}
```

Nota inventario:
- Si módulo `inventory` está habilitado, `item_id` es requerido por línea para descontar y revertir stock.

## Inventory (Wave 5)

Requiere módulo `inventory` habilitado + permisos:
- `inventory.read`
- `inventory.write`

Endpoints:
- `POST /inventory/movements` (manual `IN/OUT`)
- `GET /inventory/stock?branch_id=1&item_id=1`
- `GET /inventory/kardex?branch_id=1&item_id=1&date_from=2026-02-01&date_to=2026-02-28&limit=100`

## Inventario avanzado (Wave 6 - W6-002)

Endpoints:
- `POST /inventory/transfers`
- `POST /inventory/transfers/{id}/dispatch`
- `POST /inventory/transfers/{id}/receive`
- `POST /inventory/counts`
- `POST /inventory/counts/{id}/close`

Transferencia (crear):
```json
{
  "from_branch_id": 1,
  "to_branch_id": 2,
  "notes": "Reposicion sucursal 2",
  "items": [
    {"item_id": 1, "qty": 3},
    {"item_id": 2, "qty": 5}
  ]
}
```

Reglas:
- `dispatch`: descuenta stock de origen (`TRANSFER_OUT`).
- `receive`: acredita stock de destino (`TRANSFER_IN`).
- No se permite despachar sin stock suficiente.

Conteo (crear):
```json
{
  "branch_id": 1,
  "notes": "Conteo ciclico semanal",
  "items": [
    {"item_id": 1, "counted_qty": 8},
    {"item_id": 2, "counted_qty": 11}
  ]
}
```

Al cerrar conteo:
- genera ajustes en `inventory_movements` con `reason_code=COUNT_ADJUST`.

## CRM y pricing comercial (Wave 6 - W6-003)

Endpoints:
- `POST /crm/segments`
- `POST /crm/segments/{id}/customers`
- `POST /pricing/lists`
- `POST /pricing/lists/{id}/items`

Crear segmento:
```json
{
  "code": "VIP",
  "name": "Clientes VIP"
}
```

Asignar clientes a segmento:
```json
{
  "customer_ids": [1, 2]
}
```

Crear lista de precios:
```json
{
  "segment_id": 1,
  "code": "VIP-2026",
  "name": "Lista VIP 2026",
  "valid_from": "2026-02-16",
  "valid_to": null
}
```

Upsert de items de lista:
```json
{
  "items": [
    {"item_id": 1, "price": 95.00, "tax_rate": 0.18},
    {"item_id": 2, "price": 120.00}
  ]
}
```

Integración POS:
- Si la venta incluye `customer_id` y `item_id`, el sistema intenta resolver precio por segmento/lista activa.
- Si no hay regla comercial activa, mantiene el precio enviado en payload.

## Compras y Proveedores (Wave 6 - W6-001)

Requiere módulo `inventory` habilitado + permisos:
- `inventory.read`
- `inventory.write`

Endpoints:
- `POST /suppliers`
- `GET /suppliers?q=texto`
- `POST /purchases/orders`
- `POST /purchases/orders/{id}/receive`

### Crear proveedor
`POST /suppliers`

Body ejemplo:
```json
{
  "name": "Proveedor Demo SRL",
  "doc_number": "101123456",
  "email": "compras@proveedor.demo",
  "phone": "809-555-0101"
}
```

### Crear orden de compra
`POST /purchases/orders`

Body ejemplo:
```json
{
  "supplier_id": 1,
  "branch_id": 1,
  "notes": "Orden inicial de reposicion",
  "items": [
    {"item_id": 1, "qty": 10, "unit_cost": 85.50},
    {"item_id": 2, "qty": 6, "unit_cost": 120.00}
  ]
}
```

### Recibir orden de compra
`POST /purchases/orders/{id}/receive`

Body opcional:
```json
{
  "notes": "Recibido completo sin incidencias"
}
```

Comportamiento:
- Primera recepción: `200` y registra `purchase_receipt`.
- Reintento de la misma orden: `409 PURCHASE_ALREADY_RECEIVED`.
- Al recibir, incrementa stock en `inventory_movements` con:
  - `movement_type = IN`
  - `reason_code = PURCHASE`
  - `reference_type = PURCHASE_RECEIPT`

## Sync

### Ingest events
`POST /sync/events`

Body:
```json
{
  "device_id": "demo-device-1",
  "events": [
    {
      "event_id": "uuid",
      "op_id": "op-uuid-opcional",
      "device_id": "demo-device-1",
      "type": "cash_movement.created",
      "idempotency_key": "idem-002",
      "payload": {
        "cash_session_id": 1,
        "type": "OUT",
        "amount": 100,
        "description": "Gasto menor",
        "reason_code": "EXPENSE"
      },
      "ts": "2026-01-16T00:00:00Z"
    }
  ]
}
```

Resultados por evento en `data.results`:
- `applied`
- `duplicate`
- `conflict`
- `failed`

Conflictos (`W5-006`):
- Si llega el mismo `op_id` (por `device_id` + `type`) con payload distinto, el evento se marca `conflict`.
- El conflicto no aplica side effects y se registra para soporte.

### Sync status
`GET /sync/status?device_id=demo-device-1`

Respuesta ejemplo:
```json
{
  "ok": true,
  "data": {
    "pending_count": 0,
    "failed_count": 0,
    "conflict_count": 0,
    "last_applied_at": "2026-01-16 00:00:00",
    "last_event_at": "2026-01-16 00:00:00"
  },
  "meta": {"request_id": "...", "ts": "..."}
}
```

## Ops / Observabilidad (Wave 5)

Requiere permiso `audit.read`.

Endpoints:
- `GET /ops/tenant-metrics`
- `GET /ops/sync-conflicts?resolved=0&limit=50`

`tenant-metrics` agrega KPIs de:
- sync (`total_events`, `applied`, `failed`, `conflicts`)
- conflictos (`total`, `open`, `resolved`)
- jobs (`pending`, `retry`, `processing`, `failed`, `completed`, `dlq_total`)
- fiscal (`total`, `accepted`, `rejected`, `failed`)

## Reportería ejecutiva (Wave 6 - W6-004)

Endpoint:
- `GET /reports/executive?date_from=2026-02-01&date_to=2026-02-28`

Requiere:
- auth + tenant guard
- permiso `audit.read`

Incluye KPIs:
- ventas (`invoices`, `pos`, `combined_total`)
- recaudo por método de pago POS
- snapshot de cuentas por cobrar (`amount_total`, `amount_paid`, `amount_due`, `open_accounts`)
- margen estimado con costo promedio de compras recibidas
- índice de rotación de inventario (aproximado)

## Cuentas por cobrar (Wave 6 - W6-005)

Endpoints:
- `POST /receivables/accounts`
- `GET /receivables/accounts?customer_id=1&status=OPEN`
- `POST /receivables/accounts/{id}/payments`
- `GET /receivables/aging?as_of=2026-02-16`

Crear cuenta por cobrar:
```json
{
  "customer_id": 1,
  "origin_type": "MANUAL",
  "amount_total": 1500,
  "due_date": "2026-03-01",
  "notes": "Crédito 15 días"
}
```

Registrar abono:
```json
{
  "amount": 500,
  "payment_method": "TRANSFER",
  "reference": "TRX-001",
  "notes": "Primer abono"
}
```

Reglas:
- no permite abono mayor al saldo (`PAYMENT_EXCEEDS_DUE`).
- al saldar totalmente, estado pasa a `PAID`.
- aging devuelve buckets: `current`, `days_1_30`, `days_31_60`, `days_61_plus`.

## Integraciones externas (Wave 6 - W6-006)

Requiere módulo `integrations` habilitado por tenant/plan.

Permisos:
- `integration.read`
- `integration.manage`

Endpoints:
- `GET /integrations/connectors`
- `PUT /integrations/connectors/{code}` (`PAYMENTS|MESSAGING|ACCOUNTING`)
- `POST /integrations/connectors/{code}/test`
- `POST /integrations/events/publish`
- `GET /integrations/deliveries?connector_code=MESSAGING&status=QUEUED&limit=50`

Configurar conector:
```json
{
  "enabled": true,
  "provider": "MOCK",
  "endpoint_url": "",
  "auth": {"token": ""},
  "settings": {"channel": "default"}
}
```

Publicar evento:
```json
{
  "connector_code": "MESSAGING",
  "event_type": "customer.payment.received",
  "idempotency_key": "ext-001",
  "payload": {
    "customer_id": 10,
    "amount": 500.00
  }
}
```

Notas:
- Se encola en `jobs:external-delivery`.
- Entregas quedan trazadas en `integration_deliveries` con estados `PENDING|QUEUED|PROCESSING|SENT|FAILED`.
- Idempotencia por (`tenant_id`, `connector_code`, `idempotency_key`).

## Fiscal opcional avanzado (Wave 6 - W6-007)

Endpoints nuevos:
- `GET /fiscal/preflight` (`fiscal.read`)
- `POST /fiscal/environment/prod/activate` (`fiscal.manage`)

Activar PROD:
```json
{
  "confirm": "ACTIVAR_FISCAL_PROD"
}
```

Reglas de preflight en `PROD`:
- fiscal habilitado y `dgii_registered=true`.
- provider `DGII`.
- `provider_url` HTTPS válido.
- `signing_secret` robusto (>= 24).
- perfil fiscal completo y activo.

Si falla, devuelve `FISCAL_PRODUCTION_NOT_READY` con detalle de checks bloqueantes.

## Admin SaaS central (Wave 6 - W6-008)

Acceso por llave de plataforma:
- Header requerido: `X-Platform-Admin-Key`
- Variable de entorno: `PLATFORM_ADMIN_KEY`

Endpoints:
- `GET /admin/tenants?status=ACTIVE&limit=100`
- `GET /admin/tenants/{id}`
- `PUT /admin/tenants/{id}/status`
- `PUT /admin/tenants/{id}/modules`
- `PUT /admin/tenants/{id}/subscription`
- `GET /admin/plans`

Actualizar estado tenant:
```json
{
  "status": "SUSPENDED"
}
```

Actualizar módulos tenant:
```json
{
  "modules": {
    "pos": {"enabled": true},
    "fiscal": {"enabled": false}
  }
}
```

Actualizar suscripción:
```json
{
  "plan_code": "STARTER",
  "status": "ACTIVE"
}
```

## Performance y resiliencia operativa (Wave 6 - W6-009)

Requiere auth + tenant guard + permiso `audit.read`.

Endpoints:
- `GET /ops/jobs/queues`
- `GET /ops/jobs/dlq?queue_name=jobs:fiscal-submit&limit=50`
- `POST /ops/jobs/dlq/{id}/requeue`

Notas:
- Métricas de jobs y DLQ ahora se calculan tenant-safe (sin mezclar otros tenants).
- Requeue DLQ crea nuevo job en `jobs_queue` y marca el registro DLQ como reenviado.

## Payments Plus opcional (Wave 7 - W7-001/W7-002)

Reglas:
- Módulo: `payments_plus` (opcional por tenant y por plan).
- Si está deshabilitado responde `MODULE_DISABLED`.
- Webhook con idempotencia por (`tenant_id`, `provider`, `webhook_event_id`).

Permisos:
- `GET /payments-plus/status` requiere `payments_plus.read`
- `POST /payments-plus/transactions/intent` requiere `payments_plus.manage`
- `GET /payments-plus/transactions` requiere `payments_plus.read`
- `GET /payments-plus/transactions/{id}` requiere `payments_plus.read`
- `POST /payments-plus/reconcile/daily` requiere `payments_plus.manage`
- `GET /payments-plus/reconciliations` requiere `payments_plus.read`

Webhook público:
- `POST /payments-plus/webhook`
- Header: `X-Payment-Webhook-Key: <PAYMENTS_PLUS_WEBHOOK_KEY>`

Crear intento de pago:
```json
{
  "sale_id": 10,
  "channel": "CARD",
  "amount": 1200.00,
  "currency": "DOP",
  "idempotency_key": "pp-intent-001"
}
```

Reconciliación diaria:
```json
{
  "date": "2026-02-16",
  "provider": "MOCK"
}
```

## Accounting opcional (Wave 7 - W7-003)

Módulo: `accounting`.

Permisos:
- `GET /accounting/exports/sales` requiere `accounting.read`
- `GET /accounting/exports/collections` requiere `accounting.read`
- `GET /accounting/exports/purchases` requiere `accounting.read`
- `GET /accounting/account-map` requiere `accounting.read`
- `PUT /accounting/account-map` requiere `accounting.manage`
- `POST /accounting/periods/close` requiere `accounting.manage`

Mapeo contable (upsert):
```json
{
  "entries": [
    {"map_key": "sales.revenue", "account_code": "4100", "description": "Ingresos por ventas", "active": true},
    {"map_key": "sales.itbis", "account_code": "2101", "description": "ITBIS por pagar", "active": true}
  ]
}
```

Cierre de periodo:
```json
{
  "period_ym": "2026-02"
}
```

## Hardware Bridge opcional (Wave 7 - W7-004)

Módulo: `hardware_bridge`.

Permisos:
- `POST /hardware/devices` requiere `hardware_bridge.manage`
- `GET /hardware/devices` requiere `hardware_bridge.read`
- `POST /hardware/print-jobs` requiere `hardware_bridge.manage`
- `GET /hardware/print-jobs` requiere `hardware_bridge.read`
- `POST /hardware/devices/{id}/drawer/open` requiere `hardware_bridge.manage`
- `GET /hardware/devices/{id}/health` requiere `hardware_bridge.read`

Registrar dispositivo:
```json
{
  "branch_id": 1,
  "register_id": 1,
  "device_code": "PRN-CAJA-01",
  "name": "Impresora Caja 1",
  "type": "PRINTER",
  "connection": {"driver": "mock", "host": "127.0.0.1", "port": 9100}
}
```

Crear print job:
```json
{
  "device_id": 1,
  "sale_id": 1,
  "template": "POS_TICKET",
  "idempotency_key": "print-001",
  "payload": {
    "ticket_number": "T-1001",
    "total": 450.00
  }
}
```

## Inventario comercial (Wave 7 - W7-005)

Módulo: `inventory`.

Objetivo:
- parametrizar `min/max/reorder` por `branch_id + item_id`.
- alertas de `LOW_STOCK` y `OVERSTOCK` consultables por API.

Endpoints:
- `PUT /inventory/policies/minmax` (`inventory.write`)
- `GET /inventory/policies/minmax` (`inventory.read`)
- `GET /inventory/alerts` (`inventory.read`)

Upsert de políticas:
```json
{
  "entries": [
    {
      "branch_id": 1,
      "item_id": 10,
      "min_qty": 5,
      "max_qty": 25,
      "reorder_qty": 20,
      "lead_time_days": 2,
      "status": "ACTIVE"
    }
  ]
}
```

## Cierre operativo diario (Wave 7 - W7-006)

Módulo: `pos`.

Permisos:
- `POST /ops/daily-close` requiere `ops.daily_close.manage`
- `GET /ops/daily-close` requiere `ops.daily_close.read`
- `GET /ops/daily-close/{id}` requiere `ops.daily_close.read`
- `POST /ops/daily-close/{id}/reopen` requiere `ops.daily_close.manage`

Cerrar día:
```json
{
  "branch_id": 1,
  "close_date": "2026-02-16",
  "declared_cash": 12500.00,
  "notes": "Cierre supervisor turno noche"
}
```

Reabrir cierre:
```json
{
  "reason": "Ajuste por arqueo pendiente"
}
```

## Backup/restore operativo opcional (Wave 7 - W7-007)

Módulo: `backup_ops`.

Permisos:
- `POST /backup/snapshots` requiere `backup_ops.manage`
- `GET /backup/snapshots` requiere `backup_ops.read`
- `POST /backup/restores` requiere `backup_ops.manage`
- `GET /backup/restores` requiere `backup_ops.read`
- `GET /backup/runbook` requiere `backup_ops.read`

Crear snapshot:
```json
{
  "snapshot_type": "LOGICAL",
  "metadata": {
    "scope": "tenant_full",
    "notes": "Snapshot previo a mantenimiento"
  }
}
```

Crear restore run:
```json
{
  "backup_snapshot_id": 1,
  "mode": "DRY_RUN",
  "summary": {
    "requested_reason": "Validación de recuperación"
  }
}
```

## Seguridad empresarial opcional (Wave 7 - W7-008)

Módulo: `security_plus`.

Permisos:
- `GET /security-plus/status` requiere `security_plus.read`
- `PUT /security-plus/mfa/totp` requiere `security_plus.manage`
- `POST /security-plus/mfa/verify` requiere `security_plus.manage`
- `GET /security-plus/mfa/methods` requiere `security_plus.read`
- `POST /security-plus/secrets/rotate` requiere `security_plus.manage`
- `GET /security-plus/secrets/rotations` requiere `security_plus.read`

Enrolar MFA:
```json
{
  "method": "TOTP",
  "secret": "demo-super-secret"
}
```

Verificar MFA:
```json
{
  "method": "TOTP",
  "code": "123456"
}
```

Rotar secreto:
```json
{
  "secret_key": "platform.api_key",
  "new_secret": "nuevo-secreto-seguro-2026"
}
```

## Observabilidad avanzada y alertamiento (Wave 7 - W7-009)

Módulo: `ops` (plataforma tenant-safe).

Permisos:
- `GET /ops/sli-slo` requiere `audit.read`
- `GET /ops/alerts/rules` requiere `audit.read`
- `PUT /ops/alerts/rules` requiere `ops.alerts.manage`
- `POST /ops/alerts/evaluate` requiere `ops.alerts.manage`
- `GET /ops/incidents` requiere `audit.read`

Upsert reglas de alerta:
```json
{
  "entries": [
    {
      "code": "sync_error_high",
      "metric_key": "sync.error_rate_pct",
      "comparator": "GTE",
      "threshold_value": 5,
      "severity": "WARN",
      "channel": "INTERNAL",
      "enabled": true
    }
  ]
}
```

Evaluar alertas:
```json
{}
```

## Onboarding y toolkit de soporte (Wave 7 - W7-010)

Módulo: `ops` (plataforma tenant-safe).

Permisos:
- `GET /ops/diagnostics` requiere `audit.read`
- `GET /ops/onboarding/templates` requiere `audit.read`
- `GET /ops/onboarding/checklist` requiere `audit.read`
- `PUT /ops/onboarding/checklist` requiere `ops.onboarding.manage`

Actualizar checklist:
```json
{
  "template_code": "retail_basic",
  "items": [
    {"step": "Configurar tenant (datos fiscales opcionales).", "done": true},
    {"step": "Crear sucursal principal y caja.", "done": true},
    {"step": "Configurar catálogo inicial de ítems.", "done": false}
  ]
}
```

## Errores comunes

- `UNAUTHORIZED` (401): token faltante/invalido
- `FORBIDDEN` (403): permiso RBAC faltante
- `TENANT_SUSPENDED` (403): tenant inactivo para operar
- `MODULE_DISABLED` (403): modulo no habilitado en tenant
- `VALIDATION_ERROR` (422): payload invalido
- `INVALID_STATE` (409): estado de negocio incompatible
- `RATE_LIMIT` (429): exceso de solicitudes
- `SERVER_ERROR` (500): error interno sin traza expuesta

## Otros modulos

Customers, Items, Invoices, Templates y Recurring continúan disponibles bajo `/api/v1` con el mismo formato estandar de respuesta.

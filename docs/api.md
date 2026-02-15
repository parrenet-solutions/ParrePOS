# API ParrePos (Wave 5)

Base URL: `/api/v1`

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

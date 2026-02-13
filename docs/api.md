# API ParrePos (Wave 2.2)

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
- Modulo habilitado por tenant (`MODULE_DISABLED` si no aplica)
- Permiso RBAC por endpoint (`FORBIDDEN` si falta)

## Permisos clave de Sync

- `POST /sync/events` requiere `sync.write`
- `GET /sync/status` requiere `sync.read`

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
    {"name": "Cafe", "qty": 1, "unit_price": 1000, "discount": 0, "tax_rate": 0.18}
  ],
  "payments": [
    {"method": "CASH", "amount": 1000},
    {"method": "CARD", "amount": 180, "reference": "POS-001"}
  ]
}
```

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
- `failed`

### Sync status
`GET /sync/status?device_id=demo-device-1`

Respuesta ejemplo:
```json
{
  "ok": true,
  "data": {
    "pending_count": 0,
    "failed_count": 0,
    "last_applied_at": "2026-01-16 00:00:00",
    "last_event_at": "2026-01-16 00:00:00"
  },
  "meta": {"request_id": "...", "ts": "..."}
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

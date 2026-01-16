# API ParrePos (Wave 1)

Base URL: `/api/v1`

## Auth

### POST /auth/login
Body:
```json
{
  "email": "admin@demo.local",
  "password": "Admin12345!"
}
```

### POST /auth/refresh
Body:
```json
{
  "refresh_token": "..."
}
```

### POST /auth/logout
Body:
```json
{
  "refresh_token": "..."
}
```

## Health

### GET /health
Sin autenticación. Alternativa directa: `public/health.php`.

## Sesión

### GET /me
Headers:
```
Authorization: Bearer <access_token>
```

Respuesta:
```json
{
  "ok": true,
  "data": {
    "user_id": 1,
    "email": "admin@demo.local",
    "tenant_id": 1,
    "roles": ["OWNER"],
    "permissions": ["invoices.read"]
  },
  "meta": {"request_id": "...", "ts": "..."}
}
```

## Customers

### POST /customers
### PUT /customers/{id}
### GET /customers/{id}
### GET /customers?q=demo

Campos: `name` (req), `type` (PERSON|BUSINESS), `doc_number` (único por tenant si existe), `email`, `phone`, `status`.

## Catalog Items

### POST /items
### PUT /items/{id}
### GET /items/{id}
### GET /items?q=servicio

Campos: `type` (PRODUCT|SERVICE), `name`, `price`, `itbis_rate` (0|0.16|0.18), `status`.

## Invoices

### POST /invoices
Body:
```json
{
  "customer_id": 1,
  "items": [
    {"name": "Servicio", "qty": 1, "unit_price": 1000, "discount": 0, "tax_rate": 0.18}
  ]
}
```

### GET /invoices?status=DRAFT
### GET /invoices/{id}

### POST /invoices/{id}/issue
Emite la factura, asigna `invoice_number` y encola PDF.

### POST /invoices/{id}/void
Body:
```json
{"reason": "Error en datos"}
```

### GET /invoices/{id}/pdf
Respuesta si listo:
```json
{
  "ok": true,
  "data": {
    "status": "READY",
    "document_id": 10,
    "url": "/api/v1/invoices/1/pdf?stream=1"
  },
  "meta": {"request_id": "...", "ts": "..."}
}
```

### POST /invoices/{id}/send-email
Body:
```json
{"to": "cliente@email.com"}
```

## Templates

### POST /invoice-templates
### PUT /invoice-templates/{id}
### GET /invoice-templates/{id}
### GET /invoice-templates
### POST /invoice-templates/{id}/set-default

`config` es JSON libre para plantilla 8.5x11.

## Recurring (si modules.recurring.enabled)

### POST /recurring-rules
### GET /recurring-rules
### POST /recurring-rules/{id}/pause
### POST /recurring-rules/{id}/resume

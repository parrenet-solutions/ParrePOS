# API ParrePos (Wave 0)

Base URL: `/api/v1`

## Auth

### POST /auth/login
Body:
```json
{
  "email": "admin@tenant.com",
  "password": "secret"
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
Sin autenticación.
Alternativa directa: `public/health.php`.

## Ejemplo protegido

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
    "permissions": ["auth.*"]
  },
  "meta": {"request_id": "...", "ts": "..."}
}
```

### GET /secure-example
Headers:
```
Authorization: Bearer <access_token>
```

Requiere módulo `pos` habilitado y permiso `pos.access`.

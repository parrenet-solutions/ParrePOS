# Checklist de seguridad (Wave 0)

- Password hashing con `password_hash`.
- JWT access + refresh con rotación.
- Refresh tokens almacenados hashed.
- Rate limiting en login (Redis opcional).
- Auditoría append-only para login/logout y settings.
- Respuestas de error sin stack trace.
- Middleware de tenant y módulos habilitados.
- Hardening rate limit:
  - login por IP + tenant/IP + tenant/email
  - refresh por IP + fingerprint de token
  - logout por IP
  - sync ingest/status por IP + tenant/device

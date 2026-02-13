# Checklist de seguridad (Wave 0)

- Password hashing con `password_hash`.
- JWT access + refresh con rotación.
- Refresh tokens almacenados hashed.
- Rate limiting en login (Redis opcional).
- Auditoría append-only para login/logout y settings.
- Respuestas de error sin stack trace.
- Middleware de tenant y módulos habilitados.

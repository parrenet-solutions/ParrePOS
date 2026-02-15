# AGENTS.md — POS SaaS RD (PHP puro + Multi-tenant + Seguridad)

Este repositorio implementa una plataforma SaaS multi-tenant para República Dominicana:
- Facturación en formato 8.5x11 (módulo base)
- POS ticket (módulo opcional, offline-first en waves posteriores)
- Motor fiscal e-CF (waves posteriores)
- Operación modular por tenant y por plan
- Seguridad first-class

## 1) Reglas NO negociables
1. Multi-tenant estricto:
   - TODAS las tablas llevan tenant_id (excepto catálogos globales explícitos).
   - TODA consulta de negocio filtra por tenant_id obligatoriamente.
   - NUNCA se permite leer/escribir datos de otro tenant.
2. Modularidad real:
   - POS, Inventario, Recurrentes y Fiscal son módulos activables por tenant.
   - Si un módulo está deshabilitado, la API debe rechazarlo (403/404 según corresponda) y la UI no debe mostrarlo.
3. Seguridad:
   - Password hashing: password_hash() con algoritmo fuerte (bcrypt/argon2i/argon2id según soporte).
   - JWT Access + Refresh con rotación de refresh.
   - Rate limiting (Redis) en login y endpoints sensibles.
   - Validación estricta de inputs; respuestas consistentes; nunca exponer trazas.
   - Auditoría append-only de acciones críticas.
4. Arquitectura:
   - PHP 8.2+ puro, sin frameworks.
   - API REST JSON.
   - Separación Controller / Service / Repository.
   - Nada de duplicación: usar clases base y helpers compartidos.

## 2) Estándares de código
- Comentarios en español (breves y útiles).
- Estilo: PSR-12 en lo posible.
- Errores: exceptions propias + Response JSON estandarizada.
- Configuración: .env (no se versiona), .env.example sí.
- Logs: storage/logs (no al stdout en producción).

## 3) Convenciones de API
- Prefijo: /api/v1
- Respuesta exitosa:
  {
    "ok": true,
    "data": ...,
    "meta": { "request_id": "...", "ts": "..." }
  }
- Respuesta de error:
  {
    "ok": false,
    "error": { "code": "SOME_CODE", "message": "Mensaje legible", "details": {...} },
    "meta": { "request_id": "...", "ts": "..." }
  }

## 4) Tenancy & Seguridad de Request
- Cada request autenticado debe resolver:
  - user_id
  - tenant_id
  - roles/permisos
- TenantContext se obtiene por:
  - JWT claim tenant_id (modo principal)
  - (Opcional futuro) subdominio tenant_slug

Middleware obligatorios:
- RequestIdMiddleware (correlation id)
- JsonBodyMiddleware
- AuthMiddleware (JWT)
- TenantGuardMiddleware:
  - valida tenant.status != SUSPENDED para endpoints de emisión
  - valida módulos habilitados (tenant_settings.modules)
  - valida límites por plan si aplica

## 5) Redis / Colas / Locks
- Redis se usa para:
  - rate limiting
  - locks (idempotencia)
  - colas (jobs)
- Todo job debe ser idempotente:
  - usar idempotency_key
  - reintentos con backoff simple
  - dead-letter queue (DLQ) para fallos

## 6) Base de datos (MariaDB)
- Charset: utf8mb4
- Engine: InnoDB
- Índices:
  - tenant_id siempre indexado en tablas grandes
  - claves únicas compuestas incluyen tenant_id
- Migraciones:
  - scripts incrementales en database/migrations

## 7) Checklist mínimo antes de merge
- No hay endpoints sin auth/tenant guard (excepto health).
- Validaciones presentes en create/update.
- Auditoría en acciones críticas (login, cambios settings, emisión).
- Rate limit en login.
- Pruebas smoke básicas o requests .http en /docs.

## 8) Estado por Wave
- Wave 0: Fundaciones (auth, RBAC, tenant_settings, audit, base API, colas base)
- Wave 1: Facturación 8.5x11 (clientes, items, invoices, templates, pdf job, envío email job)
- Wave 2: POS + Offline-First (PWA + Ticket + Caja + Sync por eventos) **cerrada**.
- Wave 3: Fiscal opcional (config, provider, webhook, observabilidad base) **cerrada**.
- Wave 4: Robustez fiscal (retry/DLQ, estados, backoffice, métricas, hardening) **cerrada**.
- Próxima etapa: Wave 5 (pendiente de definición ejecutable).

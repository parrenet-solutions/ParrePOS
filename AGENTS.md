# AGENTS.md - Contexto Maestro de ParrePOS

## Indice
- [1. Vision del producto](#1-vision-del-producto)
- [2. Principios no negociables](#2-principios-no-negociables)
- [3. Stack tecnologico obligatorio](#3-stack-tecnologico-obligatorio)
- [4. Arquitectura y contratos](#4-arquitectura-y-contratos)
- [5. Seguridad, tenancy y auditoria](#5-seguridad-tenancy-y-auditoria)
- [6. Forma de trabajo por waves](#6-forma-de-trabajo-por-waves)
- [7. Reglas de colaboracion con ChatGPT](#7-reglas-de-colaboracion-con-chatgpt)
- [8. Fuentes de verdad documental](#8-fuentes-de-verdad-documental)

## 1. Vision del producto
ParrePOS es una plataforma SaaS multi-tenant para Republica Dominicana con enfoque en:
- Facturacion tradicional 8.5x11.
- POS offline-first para operacion de caja.
- Fiscal electronico opcional por tenant (no obligatorio para clientes no regularizados).
- Operacion modular por plan y por tenant.

Objetivo de producto: permitir operacion comercial estable para negocios pequenos y medianos, con seguridad y trazabilidad desde el primer dia.

## 2. Principios no negociables
1. Multi-tenant estricto.
- Toda entidad de negocio usa `tenant_id`.
- Toda consulta de negocio filtra por `tenant_id`.
- Prohibido acceso cruzado entre tenants.

2. Modularidad real.
- Modulos: `pos`, `inventory`, `recurring`, `fiscal`.
- Si un modulo esta deshabilitado por plan o tenant, API rechaza y UI no lo expone.

3. Seguridad first-class.
- Hash de password con `password_hash()`.
- JWT access + refresh con rotacion.
- Rate limiting en login y endpoints sensibles.
- Validacion estricta de entrada y errores JSON estandar.
- Sin trazas sensibles en respuestas publicas.

4. Auditoria y trazabilidad.
- Auditoria append-only en acciones criticas.
- Uso de `request_id` para correlacion.

5. Offline-first en POS.
- Cola local/outbox, idempotencia y sync tolerante a reconexion.

## 3. Stack tecnologico obligatorio
- PHP 8.2+ puro (sin framework).
- MariaDB/InnoDB utf8mb4.
- Redis opcional para rate limiting, locks e integracion de colas.
- API REST JSON prefijo `/api/v1`.
- Frontend operativo minimo en HTML/JS para backoffice y demo PWA.

## 4. Arquitectura y contratos
- Capas obligatorias: `Controller / Service / Repository`.
- Reuso de componentes compartidos; evitar duplicacion.
- Convencion de respuesta exitosa:
```json
{
  "ok": true,
  "data": {},
  "meta": { "request_id": "...", "ts": "..." }
}
```
- Convencion de respuesta de error:
```json
{
  "ok": false,
  "error": { "code": "SOME_CODE", "message": "Mensaje legible", "details": {} },
  "meta": { "request_id": "...", "ts": "..." }
}
```

## 5. Seguridad, tenancy y auditoria
Middleware obligatorios en endpoints protegidos:
- `RequestIdMiddleware`
- `JsonBodyMiddleware`
- `AuthMiddleware`
- `TenantGuardMiddleware`
- Middleware de autorizacion RBAC/permisos

TenantGuard debe validar:
- `tenant.status` (evitar emision con tenant suspendido).
- Modulos habilitados por tenant.
- Restricciones de plan (cuando aplique).

## 6. Forma de trabajo por waves
Estado consolidado real del proyecto:
- `Wave 0` Fundaciones: cerrada.
- `Wave 1` Facturacion 8.5x11: cerrada.
- `Wave 2` POS offline-first (incluye 2.1 y 2.2): cerrada.
- `Wave 3` Fiscal opcional (base): cerrada.
- `Wave 4` Robustez fiscal/operativa: cerrada.
- `Wave 5` Operacion SaaS (planes, inventario, observabilidad, hardening): cerrada.
- `Wave 6` Escalamiento comercial y funcional: planificada.

Reglas de ejecucion por wave:
- No cerrar wave sin acta, evidencia de smoke y estado documental actualizado.
- Mantener tickets pequenos, ordenados por dependencias para evitar pisarse.
- Congelar baseline con tag al cierre de wave.

## 7. Reglas de colaboracion con ChatGPT
- Toda decision debe respetar multi-tenant estricto.
- No introducir cambios que rompan fiscal opcional por tenant.
- Priorizar cambios modulares y reutilizables.
- Antes de editar documentos estrategicos, validar consistencia con:
  - estado real del repo,
  - roadmap canonicamente aprobado,
  - actas de release.

## 8. Fuentes de verdad documental
Orden de referencia recomendado:
1. `docs/context.md` (fuente de verdad operativa y tecnica).
2. `docs/project-roadmap.md` (roadmap ejecutivo y brechas).
3. `README.md` (overview + onboarding + contribucion).
4. `docs/api.md` (contrato API).
5. `docs/requests.http` (ejemplos operativos por flujo).

# Roadmap Ejecutivo del Proyecto ParrePOS

Fecha de actualizacion: `2026-02-16`

## Indice
- [1. Objetivo](#1-objetivo)
- [2. Waves consolidadas y planificadas](#2-waves-consolidadas-y-planificadas)
- [3. Triggers de aceptacion por wave](#3-triggers-de-aceptacion-por-wave)
- [4. Checkpoints y responsables](#4-checkpoints-y-responsables)
- [5. Brechas del Roadmap Oficial y reencauce](#5-brechas-del-roadmap-oficial-y-reencauce)
- [6. Enlaces por wave](#6-enlaces-por-wave)

## 1. Objetivo
Unificar el roadmap real del proyecto con lo ejecutado en codigo y documentacion, incorporando modulos pendientes del roadmap historico sin perder continuidad tecnica.

## 2. Waves consolidadas y planificadas

| Wave | Nombre | Estado | Fecha cierre | Branch/Tag | Nota ejecutiva |
|---|---|---|---|---|---|
| 0 | Fundaciones | Cerrada | 2026 | `wave-0` | Base auth, tenancy, RBAC, audit, health |
| 1 | Facturacion tradicional 8.5x11 | Cerrada | 2026 | `wave-1` | Facturas, PDF, email, recurrentes |
| 2 | POS offline-first (incluye 2.1 y 2.2) | Cerrada | 2026 | `wave-2.2.0` | POS, pagos mixtos, hold/resume, caja, sync |
| 3 | Fiscal opcional (base) | Cerrada | 2026-02-15 | `wave-1` | Config fiscal opcional + provider desacoplado |
| 4 | Robustez fiscal y operativa | Cerrada | 2026-02-15 | `wave-4-baseline` | Retry/DLQ, estados, backoffice, metricas |
| 5 | Operacion SaaS | Cerrada | 2026-02-15 | `wave-5-baseline` | Planes, limites, inventario, ops, hardening |
| 6 | Expansion comercial y financiera | En ejecucion | - | `wave-1` | CxC, compras, contabilidad base, reporteria |
| 7 | Escalamiento comercial RD (opcional por módulo) | Cerrada (tag pendiente) | 2026-02-16 | `wave-1` | Pagos locales, contabilidad exportable, hardware POS, seguridad y resiliencia |

## 3. Triggers de aceptacion por wave

### Trigger comun (todas las waves)
- Migraciones en estado consistente.
- Smoke `--positive`, `--negative`, `--all` en verde.
- Acta y release notes actualizadas.
- Tag baseline publicado para wave cerrada.

### Trigger especifico Wave 6 (propuesta)
- Cuentas por cobrar operativas (venta a credito, abonos, aging).
- Compras/proveedores con impacto en inventario.
- Reportes ejecutivos y exportacion contable base.
- Integraciones externas iniciales (fase MVP) sin romper tenancy ni seguridad.

## 4. Checkpoints y responsables
- `Arquitectura`: valida diseno modular y dependencias entre tickets.
- `Backend`: implementa API/servicios/repositorios por ticket.
- `QA`: valida smoke y casos negativos.
- `Release`: consolida acta, release notes, tag baseline.

Checkpoints por bloque:
1. Diseno y alcance del ticket.
2. Migraciones + API + pruebas.
3. Smoke verde y evidencias.
4. Cierre documental.

## 5. Brechas del Roadmap Oficial y reencauce
Fuente comparada: `ParrePOS – Roadmap Oficial del Pr.txt`.

| Modulo del roadmap historico | Estado real hoy | Reencauce propuesto |
|---|---|---|
| Inventario completo | Parcial (MVP en Wave 5) | Completar en Wave 6 (compras, transferencias, conteos) |
| Cuentas por cobrar (credito, abonos, saldo cliente) | No iniciado formalmente | Wave 6 bloque financiero-comercial |
| Envio DGII productivo completo | Parcial (base fiscal opcional y robustez) | Wave 6/7 segun prioridad comercial |
| Contabilidad basica (libro, exportacion) | No iniciado formalmente | Wave 6 bloque contable |
| Frontend profesional integral | Parcial (PWA demo + backoffice minimo) | Wave 6+ (UX, dashboard ejecutivo, admin SaaS) |

## 6. Enlaces por wave
- Estado consolidado: `docs/project-status.md`
- Contexto canonico: `docs/context.md`
- Contrato API: `docs/api.md`
- API exhaustiva por módulo: `docs/api-modules.md`
- Requests de validacion: `docs/requests.http`
- Roadmap operativo W5->W6 (detalle tecnico): `docs/roadmap-wave-5-6.md`
- Backlog Wave 7: `docs/release-wave-7-backlog.md`
- Checklist de arranque Wave 6: `docs/release-wave-6-checklist-arranque.md`
- Backlog bloque A Wave 6: `docs/release-wave-6-backlog-bloque-a.md`
- Backlog bloque B Wave 6: `docs/release-wave-6-backlog-bloque-b.md`
- Backlog bloque C Wave 6: `docs/release-wave-6-backlog-bloque-c.md`
- Acta W6-001: `docs/release-wave-6-acta-w6-001.md`
- Entrega tecnica W6-002/003: `docs/release-wave-6-w6-002-003.md`
- Acta W6-002/003: `docs/release-wave-6-acta-w6-002-003.md`
- Entrega tecnica W6-004/005: `docs/release-wave-6-w6-004-005.md`
- Acta W6-004/005: `docs/release-wave-6-acta-w6-004-005.md`
- Entrega tecnica W6-006/007: `docs/release-wave-6-w6-006-007.md`
- Entrega tecnica W6-008/009: `docs/release-wave-6-w6-008-009.md`
- Acta W6-006/007: `docs/release-wave-6-acta-w6-006-007.md`
- Acta W6-008/009: `docs/release-wave-6-acta-w6-008-009.md`
- Acta final W6 (plantilla): `docs/release-wave-6-acta-final.md`
- Checklist baseline W6: `docs/release-wave-6-checklist-baseline.md`
- Release notes W6: `docs/release-notes-wave-6.md`
- Acta final Wave 5: `docs/release-wave-5-acta-final.md`
- Release notes Wave 5: `docs/release-notes-wave-5.md`

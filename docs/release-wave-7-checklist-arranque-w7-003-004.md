# Checklist Arranque - Wave 7 (W7-003 y W7-004)

Fecha: `2026-02-16`

## 1) Precondiciones
- [ ] `W7-001` cerrado.
- [ ] `W7-002` cerrado.
- [ ] `database/migrations/014_payments_plus_base.sql` en `APPLIED`.
- [ ] `seed:dev` ejecutado sin errores.

## 2) Gate técnico inicial
- [ ] Confirmar módulos en `tenant_settings.modules`:
  - `accounting` deshabilitado por defecto.
  - `hardware_bridge` deshabilitado por defecto.
- [ ] Definir permisos nuevos en seed para bloque B.
- [ ] Definir tablas y llaves únicas multi-tenant para ambos módulos.

## 3) Smoke mínima esperada
- [ ] Negativa `MODULE_DISABLED` para `accounting`.
- [ ] Negativa `MODULE_DISABLED` para `hardware_bridge`.
- [ ] Flujo positivo mínimo `accounting` (export).
- [ ] Flujo positivo mínimo `hardware_bridge` (print job mock).

## 4) Evidencia documental
- [ ] `docs/release-wave-7-w7-003-004.md` actualizado a `IMPLEMENTADO`.
- [ ] Acta de bloque creada.
- [ ] API y requests actualizados.

## 5) Criterio de salida del bloque
- [ ] `W7-003` COMPLETADO
- [ ] `W7-004` COMPLETADO
- [ ] autorización para continuar con `W7-005/006`.

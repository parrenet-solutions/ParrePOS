# Acta Release Wave 4 (W4-006 y W4-007)

**Fecha:** `2026-02-15`  
**Ambiente:** `local`  
**Responsable:** `Equipo POS SaaS`

## 1. Alcance validado
- `W4-006` Validaciones fiscales RD más completas.
- `W4-007` Notificaciones de errores fiscales.

## 2. Evidencia funcional esperada
- Emisión fiscal rechaza NCF no permitido.
- Para `B01/B14` exige documento de cliente válido.
- Al exceder `emission_limits` retorna `FISCAL_LIMIT_EXCEEDED`.
- Al producir `FAILED/REJECTED` se generan alertas operativas (email/webhook interno opcional).

## 3. Estado por ticket
- `W4-006`: `COMPLETADO`
- `W4-007`: `COMPLETADO`

## Checklist final
- [x] Reglas NCF implementadas.
- [x] Validación RNC/Cédula implementada.
- [x] Límites de emisión por tenant implementados.
- [x] Alertas operativas para `FAILED/REJECTED` implementadas.
- [x] Fiscal continúa opcional por tenant.

## Dictamen
- **Estado:** `APROBADO`
- **Notas:**
```text
W4-006 y W4-007 cerrados. La validación fiscal aplica solo cuando el módulo fiscal
está habilitado en el tenant, preservando operación no fiscal para clientes no regularizados.
```

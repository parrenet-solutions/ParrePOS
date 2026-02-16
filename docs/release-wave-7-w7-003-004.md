# Entrega Técnica Wave 7 (W7-003 y W7-004)

Fecha: `2026-02-16`  
Estado: `COMPLETADO`

## Objetivo del bloque
Implementar dos módulos opcionales orientados a operación comercial real:
- `W7-003` Contabilidad base y exportaciones (`accounting`).
- `W7-004` Hardware POS bridge (`hardware_bridge`).

Ambos deben respetar:
- `tenant_id` estricto en toda transacción,
- guard de módulo por tenant/plan,
- RBAC por endpoint,
- no afectar el flujo base POS cuando estén deshabilitados.

## W7-003 Contabilidad base y exportaciones (opcional)

### Alcance
- Exportaciones contables por rango de fecha:
  - libro de ventas,
  - libro de cobros,
  - libro de compras.
- Mapeo simple de cuentas por tenant:
  - catálogo mínimo de cuentas (ventas, ITBIS, CxC, caja/banco, compras, inventario).
- Cierre de período contable (solo lectura/export en esta fase).

### Implementado
- Migración:
  - `015_accounting_exports_base.sql`
- Módulo:
  - `app/Accounting/AccountingController.php`
  - `app/Accounting/AccountingService.php`
  - `app/Accounting/AccountingRepository.php`
- Endpoints:
  - `GET /api/v1/accounting/exports/sales`
  - `GET /api/v1/accounting/exports/collections`
  - `GET /api/v1/accounting/exports/purchases`
  - `PUT /api/v1/accounting/account-map`
  - `GET /api/v1/accounting/account-map`
  - `POST /api/v1/accounting/periods/close`

### Permisos implementados
- `accounting.read`
- `accounting.manage`

## W7-004 Hardware POS bridge (opcional)

### Alcance
- Cola de impresión ESC/POS con idempotencia.
- Operaciones de bridge:
  - imprimir ticket,
  - abrir gaveta,
  - ping/estado de dispositivo.
- Registro de estado por dispositivo/caja/sucursal.

### Implementado
- Migración:
  - `016_hardware_bridge_base.sql`
- Módulo:
  - `app/HardwareBridge/HardwareBridgeController.php`
  - `app/HardwareBridge/HardwareBridgeService.php`
  - `app/HardwareBridge/HardwareBridgeRepository.php`
  - `app/HardwareBridge/HardwarePrintJobService.php`
- Endpoints:
  - `POST /api/v1/hardware/devices`
  - `GET /api/v1/hardware/devices`
  - `POST /api/v1/hardware/print-jobs`
  - `GET /api/v1/hardware/print-jobs`
  - `POST /api/v1/hardware/devices/{id}/drawer/open`
  - `GET /api/v1/hardware/devices/{id}/health`

### Permisos implementados
- `hardware_bridge.read`
- `hardware_bridge.manage`

## Criterios DoD del bloque
- [x] Módulos bloquean API con `MODULE_DISABLED` al estar deshabilitados.
- [x] Endpoints con filtros por `tenant_id` y validación RBAC.
- [x] Smoke negativa incorporada para ambos módulos.
- [x] Smoke positiva mínima para export accounting y print job mock.
- [x] Documentación actualizada (`docs/api.md`, `docs/api-modules.md`, `docs/requests.http`, `docs/context.md`).

## Plan de prueba recomendado
```bash
php bin/console.php migrate
php bin/console.php migrate:status
php bin/console.php seed:dev
php tests/smoke/run.php --negative
php tests/smoke/run.php --all
```

## Riesgos y mitigación
- Riesgo: crecimiento de complejidad en POS core.
- Mitigación: mantener ambos módulos desacoplados y opcionales.

- Riesgo: endpoints sin guardas completas.
- Mitigación: checklist por ruta (Auth + TenantGuard + módulo + permiso).

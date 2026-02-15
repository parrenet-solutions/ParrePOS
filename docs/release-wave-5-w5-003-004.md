# Entrega Técnica Wave 5 (W5-003 y W5-004)

## Resumen
Se implementó inventario MVP multi-sucursal con movimientos y stock agregado, integrado de forma transaccional al flujo de venta/anulación POS.

## W5-003 Inventario MVP multi-sucursal

Implementado:
- Evolución de `inventory_movements` para soportar:
  - `branch_id`
  - `item_id`
  - `movement_type` (`IN`/`OUT`)
  - `reason_code`
  - `reference_type`, `reference_id`
  - `created_by`
- Nuevos endpoints:
  - `POST /api/v1/inventory/movements`
  - `GET /api/v1/inventory/stock`
  - `GET /api/v1/inventory/kardex`
- Capa reusable:
  - `app/Inventory/InventoryRepository.php`
  - `app/Inventory/InventoryService.php`
  - `app/Inventory/InventoryController.php`

Migración:
- `database/migrations/006_inventory_multibranch.sql`

## W5-004 POS <-> Inventario transaccional

Implementado:
- `pos_sale_items` ahora soporta `item_id` para trazabilidad de stock.
- En `POST /api/v1/pos/sales`:
  - si `inventory` está habilitado:
    - valida stock disponible antes de crear venta
    - registra movimientos `OUT` por línea
- En `POST /api/v1/pos/sales/{id}/void`:
  - revierte movimientos creando `IN` de compensación (`SALE_VOID`)
  - operación idempotente (evita doble reverso)

Errores de negocio:
- `OUT_OF_STOCK` cuando no hay disponibilidad.

## Smoke/QA actualizado

`tests/smoke/run.php` agrega cobertura positiva:
- `Inventory Manual IN and Stock`
- `POS Sale Inventory Deduct and Void Reverse`

## Archivos clave
- `app/Inventory/InventoryController.php`
- `app/Inventory/InventoryRepository.php`
- `app/Inventory/InventoryService.php`
- `app/Pos/PosSaleController.php`
- `app/Pos/PosSaleRepository.php`
- `app/Pos/PosSaleService.php`
- `app/Bootstrap/App.php`
- `database/migrations/006_inventory_multibranch.sql`
- `database/schema.sql`
- `tests/smoke/run.php`
- `docs/api.md`
- `docs/requests.http`

## Validación sugerida
```bash
php bin/console.php migrate
php bin/console.php seed:dev
php tests/smoke/run.php --positive
php tests/smoke/run.php --all
```

## Nota
- La integración inventario<->POS se activa cuando módulo `inventory` está habilitado para el tenant.

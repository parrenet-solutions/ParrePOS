ALTER TABLE pos_sale_items
    ADD COLUMN IF NOT EXISTS item_id BIGINT UNSIGNED NULL AFTER sale_id;

SET @idx_pos_item_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'pos_sale_items'
      AND INDEX_NAME = 'idx_pos_sale_items_item'
);
SET @idx_pos_item_sql := IF(
    @idx_pos_item_exists = 0,
    'ALTER TABLE pos_sale_items ADD INDEX idx_pos_sale_items_item (tenant_id, item_id)',
    'SELECT 1'
);
PREPARE idx_pos_item_stmt FROM @idx_pos_item_sql;
EXECUTE idx_pos_item_stmt;
DEALLOCATE PREPARE idx_pos_item_stmt;

SET @fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'pos_sale_items'
      AND CONSTRAINT_NAME = 'fk_pos_sale_items_item'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @fk_sql := IF(
    @fk_exists = 0,
    'ALTER TABLE pos_sale_items ADD CONSTRAINT fk_pos_sale_items_item FOREIGN KEY (item_id) REFERENCES catalog_items(id)',
    'SELECT 1'
);
PREPARE fk_stmt FROM @fk_sql;
EXECUTE fk_stmt;
DEALLOCATE PREPARE fk_stmt;

ALTER TABLE inventory_movements
    ADD COLUMN IF NOT EXISTS branch_id BIGINT UNSIGNED NULL AFTER tenant_id,
    ADD COLUMN IF NOT EXISTS item_id BIGINT UNSIGNED NULL AFTER branch_id,
    ADD COLUMN IF NOT EXISTS movement_type VARCHAR(10) NULL AFTER item_name,
    ADD COLUMN IF NOT EXISTS reason_code VARCHAR(50) NULL AFTER qty,
    ADD COLUMN IF NOT EXISTS reference_type VARCHAR(30) NULL AFTER reason_code,
    ADD COLUMN IF NOT EXISTS reference_id BIGINT UNSIGNED NULL AFTER reference_type,
    ADD COLUMN IF NOT EXISTS created_by BIGINT UNSIGNED NULL AFTER reference_id;

UPDATE inventory_movements im
INNER JOIN pos_sales s ON s.id = im.sale_id AND s.tenant_id = im.tenant_id
SET
    im.branch_id = COALESCE(im.branch_id, s.branch_id),
    im.movement_type = COALESCE(im.movement_type, 'OUT'),
    im.reason_code = COALESCE(im.reason_code, 'SALE'),
    im.reference_type = COALESCE(im.reference_type, 'SALE'),
    im.reference_id = COALESCE(im.reference_id, im.sale_id)
WHERE im.sale_id IS NOT NULL;

UPDATE inventory_movements
SET
    branch_id = COALESCE(branch_id, 0),
    item_id = COALESCE(item_id, 0),
    movement_type = COALESCE(movement_type, 'OUT')
WHERE branch_id IS NULL OR item_id IS NULL OR movement_type IS NULL;

ALTER TABLE inventory_movements
    MODIFY COLUMN branch_id BIGINT UNSIGNED NOT NULL,
    MODIFY COLUMN item_id BIGINT UNSIGNED NOT NULL,
    MODIFY COLUMN sale_id BIGINT UNSIGNED NULL,
    MODIFY COLUMN movement_type VARCHAR(10) NOT NULL;

SET @idx_inv_branch_item_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'inventory_movements'
      AND INDEX_NAME = 'idx_inventory_branch_item'
);
SET @idx_inv_branch_item_sql := IF(
    @idx_inv_branch_item_exists = 0,
    'ALTER TABLE inventory_movements ADD INDEX idx_inventory_branch_item (tenant_id, branch_id, item_id)',
    'SELECT 1'
);
PREPARE idx_inv_branch_item_stmt FROM @idx_inv_branch_item_sql;
EXECUTE idx_inv_branch_item_stmt;
DEALLOCATE PREPARE idx_inv_branch_item_stmt;

SET @idx_inv_reference_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'inventory_movements'
      AND INDEX_NAME = 'idx_inventory_reference'
);
SET @idx_inv_reference_sql := IF(
    @idx_inv_reference_exists = 0,
    'ALTER TABLE inventory_movements ADD INDEX idx_inventory_reference (tenant_id, reference_type, reference_id)',
    'SELECT 1'
);
PREPARE idx_inv_reference_stmt FROM @idx_inv_reference_sql;
EXECUTE idx_inv_reference_stmt;
DEALLOCATE PREPARE idx_inv_reference_stmt;

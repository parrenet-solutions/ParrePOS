ALTER TABLE sync_events
    ADD COLUMN IF NOT EXISTS op_id VARCHAR(64) NULL AFTER event_id,
    ADD COLUMN IF NOT EXISTS payload_hash CHAR(64) NULL AFTER payload_json,
    ADD COLUMN IF NOT EXISTS conflict_code VARCHAR(50) NULL AFTER error_message;

SET @idx_sync_op_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'sync_events'
      AND INDEX_NAME = 'idx_sync_op'
);
SET @idx_sync_op_sql := IF(
    @idx_sync_op_exists = 0,
    'ALTER TABLE sync_events ADD INDEX idx_sync_op (tenant_id, device_id, type, op_id)',
    'SELECT 1'
);
PREPARE idx_sync_op_stmt FROM @idx_sync_op_sql;
EXECUTE idx_sync_op_stmt;
DEALLOCATE PREPARE idx_sync_op_stmt;

CREATE TABLE IF NOT EXISTS sync_conflicts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    device_id VARCHAR(100) NOT NULL,
    type VARCHAR(120) NOT NULL,
    op_id VARCHAR(64) NOT NULL,
    event_id VARCHAR(36) NOT NULL,
    existing_event_id VARCHAR(36) NULL,
    reason_code VARCHAR(50) NOT NULL,
    payload_json JSON NOT NULL,
    existing_payload_json JSON NULL,
    resolved TINYINT(1) NOT NULL DEFAULT 0,
    resolved_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sync_conflicts_tenant (tenant_id),
    KEY idx_sync_conflicts_device (tenant_id, device_id),
    KEY idx_sync_conflicts_op (tenant_id, device_id, type, op_id),
    CONSTRAINT fk_sync_conflicts_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

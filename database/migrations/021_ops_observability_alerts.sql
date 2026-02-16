CREATE TABLE IF NOT EXISTS ops_alert_rules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(80) NOT NULL,
    metric_key VARCHAR(80) NOT NULL,
    comparator VARCHAR(10) NOT NULL DEFAULT 'GT',
    threshold_value DECIMAL(14,4) NOT NULL,
    severity VARCHAR(20) NOT NULL DEFAULT 'WARN',
    channel VARCHAR(20) NOT NULL DEFAULT 'INTERNAL',
    target VARCHAR(190) NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    last_triggered_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    UNIQUE KEY uniq_ops_alert_rule_code (tenant_id, code),
    KEY idx_ops_alert_rules_tenant_enabled (tenant_id, enabled),
    CONSTRAINT fk_ops_alert_rules_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ops_alert_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    rule_id BIGINT UNSIGNED NOT NULL,
    metric_value DECIMAL(14,4) NOT NULL,
    threshold_value DECIMAL(14,4) NOT NULL,
    severity VARCHAR(20) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'OPEN',
    payload_json JSON NULL,
    triggered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    acknowledged_at DATETIME NULL,
    resolved_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ops_alert_events_tenant_status (tenant_id, status),
    KEY idx_ops_alert_events_rule (tenant_id, rule_id),
    CONSTRAINT fk_ops_alert_events_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_ops_alert_events_rule FOREIGN KEY (rule_id) REFERENCES ops_alert_rules(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

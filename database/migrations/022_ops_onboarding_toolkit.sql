CREATE TABLE IF NOT EXISTS ops_onboarding_checklists (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    checklist_code VARCHAR(80) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'IN_PROGRESS',
    progress_pct DECIMAL(5,2) NOT NULL DEFAULT 0,
    checklist_json JSON NOT NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    UNIQUE KEY uniq_ops_onboarding_checklist (tenant_id, checklist_code),
    KEY idx_ops_onboarding_tenant_status (tenant_id, status),
    CONSTRAINT fk_ops_onboarding_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

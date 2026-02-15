CREATE TABLE IF NOT EXISTS fiscal_profiles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    legal_name VARCHAR(190) NOT NULL,
    rnc VARCHAR(20) NOT NULL,
    dgii_registered TINYINT(1) NOT NULL DEFAULT 0,
    environment VARCHAR(20) NOT NULL DEFAULT 'CERT',
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    UNIQUE KEY uniq_fiscal_profiles_tenant (tenant_id),
    KEY idx_fiscal_profiles_status (tenant_id, status),
    CONSTRAINT fk_fiscal_profiles_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS fiscal_sequences (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    ncf_type VARCHAR(3) NOT NULL,
    series VARCHAR(10) NOT NULL,
    current_number BIGINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    UNIQUE KEY uniq_fiscal_sequences (tenant_id, ncf_type, series),
    KEY idx_fiscal_sequences_tenant (tenant_id),
    CONSTRAINT fk_fiscal_sequences_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS fiscal_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    invoice_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    ncf_type VARCHAR(3) NOT NULL,
    series VARCHAR(10) NOT NULL,
    sequence BIGINT UNSIGNED NOT NULL,
    ncf VARCHAR(32) NOT NULL,
    track_id VARCHAR(64) NULL,
    request_payload JSON NOT NULL,
    response_payload JSON NULL,
    error_message VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    UNIQUE KEY uniq_fiscal_documents_invoice (tenant_id, invoice_id),
    UNIQUE KEY uniq_fiscal_documents_ncf (tenant_id, ncf),
    KEY idx_fiscal_documents_status (tenant_id, status),
    CONSTRAINT fk_fiscal_documents_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_fiscal_documents_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS fiscal_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    fiscal_document_id BIGINT UNSIGNED NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    payload_json JSON NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_fiscal_events_tenant (tenant_id),
    KEY idx_fiscal_events_doc (tenant_id, fiscal_document_id),
    CONSTRAINT fk_fiscal_events_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_fiscal_events_document FOREIGN KEY (fiscal_document_id) REFERENCES fiscal_documents(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS fiscal_acks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    fiscal_document_id BIGINT UNSIGNED NOT NULL,
    ack_code VARCHAR(50) NOT NULL,
    ack_message VARCHAR(255) NULL,
    raw_payload JSON NOT NULL,
    received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_fiscal_acks_doc_code (tenant_id, fiscal_document_id, ack_code),
    KEY idx_fiscal_acks_tenant (tenant_id),
    CONSTRAINT fk_fiscal_acks_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_fiscal_acks_document FOREIGN KEY (fiscal_document_id) REFERENCES fiscal_documents(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

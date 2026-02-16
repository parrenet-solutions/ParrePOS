-- ParrePos Wave 0 schema

CREATE TABLE IF NOT EXISTS tenants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    UNIQUE KEY uniq_tenants_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(150) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    UNIQUE KEY uniq_users_email (tenant_id, email),
    KEY idx_users_tenant (tenant_id),
    CONSTRAINT fk_users_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(100) NOT NULL,
    name VARCHAR(150) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_roles_code (tenant_id, code),
    KEY idx_roles_tenant (tenant_id),
    CONSTRAINT fk_roles_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(100) NOT NULL,
    name VARCHAR(150) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_permissions_code (tenant_id, code),
    KEY idx_permissions_tenant (tenant_id),
    CONSTRAINT fk_permissions_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS role_permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    UNIQUE KEY uniq_role_permissions (tenant_id, role_id, permission_id),
    KEY idx_role_permissions_tenant (tenant_id),
    CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles(id),
    CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions(id),
    CONSTRAINT fk_role_permissions_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_roles (tenant_id, user_id, role_id),
    KEY idx_user_roles_tenant (tenant_id),
    CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_user_roles_role FOREIGN KEY (role_id) REFERENCES roles(id),
    CONSTRAINT fk_user_roles_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS refresh_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    replaced_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_refresh_tokens_hash (token_hash),
    KEY idx_refresh_tokens_user (tenant_id, user_id),
    CONSTRAINT fk_refresh_tokens_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_refresh_tokens_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tenant_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    modules JSON NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    UNIQUE KEY uniq_tenant_settings (tenant_id),
    CONSTRAINT fk_tenant_settings_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS plans (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL,
    name VARCHAR(120) NOT NULL,
    modules_json JSON NOT NULL,
    limits_json JSON NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    UNIQUE KEY uniq_plans_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tenant_subscriptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    plan_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    UNIQUE KEY uniq_tenant_subscriptions_tenant (tenant_id),
    KEY idx_tenant_subscriptions_plan (plan_id),
    CONSTRAINT fk_tenant_subscriptions_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_tenant_subscriptions_plan FOREIGN KEY (plan_id) REFERENCES plans(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(120) NOT NULL,
    meta JSON NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_tenant (tenant_id),
    KEY idx_audit_user (tenant_id, user_id),
    KEY idx_audit_action (tenant_id, action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS customers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    type VARCHAR(20) NOT NULL,
    doc_number VARCHAR(50) NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(40) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    UNIQUE KEY uniq_customers_doc (tenant_id, doc_number),
    KEY idx_customers_tenant (tenant_id),
    CONSTRAINT fk_customers_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS catalog_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    type VARCHAR(20) NOT NULL,
    name VARCHAR(150) NOT NULL,
    price DECIMAL(12,2) NOT NULL,
    itbis_rate DECIMAL(4,2) NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    KEY idx_catalog_items_tenant (tenant_id),
    CONSTRAINT fk_catalog_items_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS invoices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
    series VARCHAR(10) NULL,
    sequence BIGINT UNSIGNED NULL,
    invoice_number VARCHAR(32) NULL,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    tax_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    total DECIMAL(12,2) NOT NULL DEFAULT 0,
    issued_at DATETIME NULL,
    void_reason VARCHAR(255) NULL,
    voided_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    UNIQUE KEY uniq_invoice_number (tenant_id, invoice_number),
    KEY idx_invoices_tenant (tenant_id),
    KEY idx_invoices_customer (tenant_id, customer_id),
    CONSTRAINT fk_invoices_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_invoices_customer FOREIGN KEY (customer_id) REFERENCES customers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS invoice_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    invoice_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    qty DECIMAL(12,2) NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    tax_rate DECIMAL(4,2) NOT NULL DEFAULT 0,
    discount DECIMAL(12,2) NOT NULL DEFAULT 0,
    line_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_invoice_items_tenant (tenant_id),
    KEY idx_invoice_items_invoice (tenant_id, invoice_id),
    CONSTRAINT fk_invoice_items_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_invoice_items_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS invoice_templates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    config_json JSON NOT NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    KEY idx_invoice_templates_tenant (tenant_id),
    CONSTRAINT fk_invoice_templates_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS document_files (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    doc_type VARCHAR(50) NOT NULL,
    ref_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    file_path VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    KEY idx_document_files_tenant (tenant_id),
    KEY idx_document_files_ref (tenant_id, ref_id),
    CONSTRAINT fk_document_files_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS email_outbox (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    email_to VARCHAR(190) NOT NULL,
    subject VARCHAR(190) NOT NULL,
    body TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    sent_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_email_outbox_tenant (tenant_id),
    KEY idx_email_outbox_status (status),
    CONSTRAINT fk_email_outbox_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS recurring_rules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    items_json JSON NOT NULL,
    interval_days INT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    next_run_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    KEY idx_recurring_tenant (tenant_id),
    KEY idx_recurring_next_run (next_run_at),
    CONSTRAINT fk_recurring_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_recurring_customer FOREIGN KEY (customer_id) REFERENCES customers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS invoice_sequences (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    series VARCHAR(10) NOT NULL,
    current_number BIGINT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY uniq_invoice_sequences (tenant_id, series),
    KEY idx_invoice_sequences_tenant (tenant_id),
    CONSTRAINT fk_invoice_sequences_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
    KEY idx_fiscal_documents_created (tenant_id, created_at),
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

CREATE TABLE IF NOT EXISTS branches (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    address VARCHAR(255) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    KEY idx_branches_tenant (tenant_id),
    CONSTRAINT fk_branches_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pos_registers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    device_id VARCHAR(100) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    UNIQUE KEY uniq_pos_register_device (tenant_id, device_id),
    KEY idx_pos_registers_tenant (tenant_id),
    KEY idx_pos_registers_branch (tenant_id, branch_id),
    CONSTRAINT fk_pos_registers_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_pos_registers_branch FOREIGN KEY (branch_id) REFERENCES branches(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pos_ticket_sequences (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NOT NULL,
    register_id BIGINT UNSIGNED NOT NULL,
    current_number BIGINT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY uniq_pos_ticket_seq (tenant_id, branch_id, register_id),
    CONSTRAINT fk_pos_ticket_seq_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pos_cash_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NOT NULL,
    register_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'OPEN',
    opening_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    expected_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    closing_amount DECIMAL(12,2) NULL,
    difference DECIMAL(12,2) NULL,
    opened_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    closed_at DATETIME NULL,
    KEY idx_pos_cash_sessions_tenant (tenant_id),
    KEY idx_pos_cash_sessions_register (tenant_id, register_id),
    CONSTRAINT fk_pos_cash_sessions_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_pos_cash_sessions_register FOREIGN KEY (register_id) REFERENCES pos_registers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pos_sales (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NOT NULL,
    register_id BIGINT UNSIGNED NOT NULL,
    cash_session_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NULL,
    ticket_number VARCHAR(50) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PAID',
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    tax_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    total DECIMAL(12,2) NOT NULL DEFAULT 0,
    paid_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    void_reason VARCHAR(255) NULL,
    hold_reason VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    paid_at DATETIME NULL,
    voided_at DATETIME NULL,
    UNIQUE KEY uniq_pos_ticket (tenant_id, ticket_number),
    KEY idx_pos_sales_tenant (tenant_id),
    KEY idx_pos_sales_register (tenant_id, register_id),
    KEY idx_pos_sales_customer (tenant_id, customer_id),
    CONSTRAINT fk_pos_sales_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_pos_sales_branch FOREIGN KEY (branch_id) REFERENCES branches(id),
    CONSTRAINT fk_pos_sales_register FOREIGN KEY (register_id) REFERENCES pos_registers(id),
    CONSTRAINT fk_pos_sales_session FOREIGN KEY (cash_session_id) REFERENCES pos_cash_sessions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pos_sale_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    sale_id BIGINT UNSIGNED NOT NULL,
    item_id BIGINT UNSIGNED NULL,
    name VARCHAR(150) NOT NULL,
    qty DECIMAL(12,2) NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    tax_rate DECIMAL(4,2) NOT NULL DEFAULT 0,
    discount DECIMAL(12,2) NOT NULL DEFAULT 0,
    line_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pos_sale_items_tenant (tenant_id),
    KEY idx_pos_sale_items_sale (tenant_id, sale_id),
    KEY idx_pos_sale_items_item (tenant_id, item_id),
    CONSTRAINT fk_pos_sale_items_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_pos_sale_items_sale FOREIGN KEY (sale_id) REFERENCES pos_sales(id),
    CONSTRAINT fk_pos_sale_items_item FOREIGN KEY (item_id) REFERENCES catalog_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pos_payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NOT NULL,
    register_id BIGINT UNSIGNED NOT NULL,
    cash_session_id BIGINT UNSIGNED NOT NULL,
    sale_id BIGINT UNSIGNED NOT NULL,
    method VARCHAR(30) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    reference VARCHAR(120) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pos_payments_tenant (tenant_id),
    KEY idx_pos_payments_session (tenant_id, cash_session_id),
    CONSTRAINT fk_pos_payments_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_pos_payments_sale FOREIGN KEY (sale_id) REFERENCES pos_sales(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pos_cash_movements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NOT NULL,
    register_id BIGINT UNSIGNED NOT NULL,
    cash_session_id BIGINT UNSIGNED NOT NULL,
    type VARCHAR(10) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    reason_code VARCHAR(50) NULL,
    description VARCHAR(255) NOT NULL,
    reference VARCHAR(120) NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pos_cash_movements_session (tenant_id, cash_session_id),
    KEY idx_pos_cash_movements_register_date (tenant_id, register_id, created_at),
    CONSTRAINT fk_pos_cash_movements_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_pos_cash_movements_session FOREIGN KEY (cash_session_id) REFERENCES pos_cash_sessions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sync_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    device_id VARCHAR(100) NOT NULL,
    event_id VARCHAR(36) NOT NULL,
    op_id VARCHAR(64) NULL,
    type VARCHAR(120) NOT NULL,
    idempotency_key VARCHAR(120) NOT NULL,
    payload_json JSON NOT NULL,
    payload_hash CHAR(64) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    error_message VARCHAR(255) NULL,
    conflict_code VARCHAR(50) NULL,
    applied_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_sync_event (event_id),
    UNIQUE KEY uniq_sync_idempotency (tenant_id, device_id, idempotency_key),
    KEY idx_sync_op (tenant_id, device_id, type, op_id),
    KEY idx_sync_tenant (tenant_id),
    KEY idx_sync_status_created (tenant_id, status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

CREATE TABLE IF NOT EXISTS idempotency_keys (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    device_id VARCHAR(100) NOT NULL,
    idempotency_key VARCHAR(120) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'APPLIED',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_idempotency (tenant_id, device_id, idempotency_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inventory_movements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NOT NULL,
    item_id BIGINT UNSIGNED NOT NULL,
    sale_id BIGINT UNSIGNED NULL,
    item_name VARCHAR(150) NOT NULL,
    movement_type VARCHAR(10) NOT NULL,
    qty DECIMAL(12,2) NOT NULL,
    reason_code VARCHAR(50) NULL,
    reference_type VARCHAR(30) NULL,
    reference_id BIGINT UNSIGNED NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_inventory_tenant (tenant_id),
    KEY idx_inventory_branch_item (tenant_id, branch_id, item_id),
    KEY idx_inventory_reference (tenant_id, reference_type, reference_id),
    CONSTRAINT fk_inventory_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS suppliers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    doc_number VARCHAR(50) NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(40) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    UNIQUE KEY uniq_suppliers_doc (tenant_id, doc_number),
    KEY idx_suppliers_tenant (tenant_id),
    CONSTRAINT fk_suppliers_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS purchase_orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    supplier_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    tax_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    total DECIMAL(12,2) NOT NULL DEFAULT 0,
    notes VARCHAR(255) NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    received_by BIGINT UNSIGNED NULL,
    received_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    KEY idx_purchase_orders_tenant (tenant_id),
    KEY idx_purchase_orders_supplier (tenant_id, supplier_id),
    KEY idx_purchase_orders_branch (tenant_id, branch_id),
    CONSTRAINT fk_purchase_orders_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_purchase_orders_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    CONSTRAINT fk_purchase_orders_branch FOREIGN KEY (branch_id) REFERENCES branches(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS purchase_order_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    purchase_order_id BIGINT UNSIGNED NOT NULL,
    item_id BIGINT UNSIGNED NOT NULL,
    item_name VARCHAR(150) NOT NULL,
    qty DECIMAL(12,2) NOT NULL,
    unit_cost DECIMAL(12,2) NOT NULL,
    tax_rate DECIMAL(4,2) NOT NULL DEFAULT 0,
    line_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_purchase_order_items_tenant (tenant_id),
    KEY idx_purchase_order_items_order (tenant_id, purchase_order_id),
    KEY idx_purchase_order_items_item (tenant_id, item_id),
    CONSTRAINT fk_purchase_order_items_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_purchase_order_items_order FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id),
    CONSTRAINT fk_purchase_order_items_item FOREIGN KEY (item_id) REFERENCES catalog_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS purchase_receipts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    purchase_order_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NOT NULL,
    received_by BIGINT UNSIGNED NOT NULL,
    notes VARCHAR(255) NULL,
    received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_purchase_receipts_order (tenant_id, purchase_order_id),
    KEY idx_purchase_receipts_tenant (tenant_id),
    KEY idx_purchase_receipts_branch (tenant_id, branch_id),
    CONSTRAINT fk_purchase_receipts_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_purchase_receipts_order FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id),
    CONSTRAINT fk_purchase_receipts_branch FOREIGN KEY (branch_id) REFERENCES branches(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inventory_transfers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    from_branch_id BIGINT UNSIGNED NOT NULL,
    to_branch_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
    notes VARCHAR(255) NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    dispatched_by BIGINT UNSIGNED NULL,
    dispatched_at DATETIME NULL,
    received_by BIGINT UNSIGNED NULL,
    received_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    KEY idx_inventory_transfers_tenant (tenant_id),
    KEY idx_inventory_transfers_status (tenant_id, status),
    CONSTRAINT fk_inventory_transfers_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_inventory_transfers_from_branch FOREIGN KEY (from_branch_id) REFERENCES branches(id),
    CONSTRAINT fk_inventory_transfers_to_branch FOREIGN KEY (to_branch_id) REFERENCES branches(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inventory_transfer_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    transfer_id BIGINT UNSIGNED NOT NULL,
    item_id BIGINT UNSIGNED NOT NULL,
    item_name VARCHAR(150) NOT NULL,
    qty DECIMAL(12,2) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_inventory_transfer_items_tenant (tenant_id),
    KEY idx_inventory_transfer_items_transfer (tenant_id, transfer_id),
    KEY idx_inventory_transfer_items_item (tenant_id, item_id),
    CONSTRAINT fk_inventory_transfer_items_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_inventory_transfer_items_transfer FOREIGN KEY (transfer_id) REFERENCES inventory_transfers(id),
    CONSTRAINT fk_inventory_transfer_items_item FOREIGN KEY (item_id) REFERENCES catalog_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inventory_counts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'OPEN',
    notes VARCHAR(255) NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    closed_by BIGINT UNSIGNED NULL,
    closed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    KEY idx_inventory_counts_tenant (tenant_id),
    KEY idx_inventory_counts_branch_status (tenant_id, branch_id, status),
    CONSTRAINT fk_inventory_counts_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_inventory_counts_branch FOREIGN KEY (branch_id) REFERENCES branches(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inventory_count_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    inventory_count_id BIGINT UNSIGNED NOT NULL,
    item_id BIGINT UNSIGNED NOT NULL,
    item_name VARCHAR(150) NOT NULL,
    expected_qty DECIMAL(12,2) NOT NULL DEFAULT 0,
    counted_qty DECIMAL(12,2) NOT NULL DEFAULT 0,
    variance_qty DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_inventory_count_items_tenant (tenant_id),
    KEY idx_inventory_count_items_count (tenant_id, inventory_count_id),
    KEY idx_inventory_count_items_item (tenant_id, item_id),
    CONSTRAINT fk_inventory_count_items_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_inventory_count_items_count FOREIGN KEY (inventory_count_id) REFERENCES inventory_counts(id),
    CONSTRAINT fk_inventory_count_items_item FOREIGN KEY (item_id) REFERENCES catalog_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS customer_segments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(60) NOT NULL,
    name VARCHAR(120) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    UNIQUE KEY uniq_customer_segments_code (tenant_id, code),
    KEY idx_customer_segments_tenant (tenant_id),
    CONSTRAINT fk_customer_segments_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS customer_segment_members (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    segment_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_customer_segment_members (tenant_id, segment_id, customer_id),
    KEY idx_customer_segment_members_tenant (tenant_id),
    KEY idx_customer_segment_members_customer (tenant_id, customer_id),
    CONSTRAINT fk_customer_segment_members_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_customer_segment_members_segment FOREIGN KEY (segment_id) REFERENCES customer_segments(id),
    CONSTRAINT fk_customer_segment_members_customer FOREIGN KEY (customer_id) REFERENCES customers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS price_lists (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    segment_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(60) NOT NULL,
    name VARCHAR(120) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    valid_from DATE NULL,
    valid_to DATE NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    UNIQUE KEY uniq_price_lists_code (tenant_id, code),
    KEY idx_price_lists_tenant (tenant_id),
    KEY idx_price_lists_segment (tenant_id, segment_id),
    CONSTRAINT fk_price_lists_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_price_lists_segment FOREIGN KEY (segment_id) REFERENCES customer_segments(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS price_list_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    price_list_id BIGINT UNSIGNED NOT NULL,
    item_id BIGINT UNSIGNED NOT NULL,
    price DECIMAL(12,2) NOT NULL,
    tax_rate DECIMAL(4,2) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    UNIQUE KEY uniq_price_list_items (tenant_id, price_list_id, item_id),
    KEY idx_price_list_items_tenant (tenant_id),
    KEY idx_price_list_items_item (tenant_id, item_id),
    CONSTRAINT fk_price_list_items_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_price_list_items_list FOREIGN KEY (price_list_id) REFERENCES price_lists(id),
    CONSTRAINT fk_price_list_items_item FOREIGN KEY (item_id) REFERENCES catalog_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS receivable_accounts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    origin_type VARCHAR(30) NOT NULL DEFAULT 'MANUAL',
    origin_id BIGINT UNSIGNED NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'OPEN',
    amount_total DECIMAL(12,2) NOT NULL,
    amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0,
    amount_due DECIMAL(12,2) NOT NULL,
    due_date DATE NULL,
    notes VARCHAR(255) NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    closed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    KEY idx_receivable_accounts_tenant (tenant_id),
    KEY idx_receivable_accounts_customer (tenant_id, customer_id),
    KEY idx_receivable_accounts_status (tenant_id, status),
    KEY idx_receivable_accounts_due (tenant_id, due_date),
    KEY idx_receivable_accounts_due_status (tenant_id, status, due_date),
    CONSTRAINT fk_receivable_accounts_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_receivable_accounts_customer FOREIGN KEY (customer_id) REFERENCES customers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS receivable_payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    receivable_account_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_method VARCHAR(30) NOT NULL DEFAULT 'CASH',
    reference VARCHAR(120) NULL,
    notes VARCHAR(255) NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_receivable_payments_tenant (tenant_id),
    KEY idx_receivable_payments_account (tenant_id, receivable_account_id),
    CONSTRAINT fk_receivable_payments_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_receivable_payments_account FOREIGN KEY (receivable_account_id) REFERENCES receivable_accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS integration_connectors (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(40) NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    provider VARCHAR(40) NOT NULL DEFAULT 'MOCK',
    endpoint_url VARCHAR(255) NULL,
    auth_json JSON NULL,
    settings_json JSON NULL,
    last_test_status VARCHAR(20) NULL,
    last_test_at DATETIME NULL,
    last_test_error VARCHAR(255) NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    UNIQUE KEY uniq_integration_connectors_tenant_code (tenant_id, code),
    KEY idx_integration_connectors_tenant_enabled (tenant_id, enabled),
    CONSTRAINT fk_integration_connectors_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS integration_deliveries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    connector_code VARCHAR(40) NOT NULL,
    event_type VARCHAR(120) NOT NULL,
    idempotency_key VARCHAR(120) NULL,
    payload_json JSON NOT NULL,
    response_json JSON NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    http_status INT NULL,
    last_error VARCHAR(500) NULL,
    queued_job_id BIGINT UNSIGNED NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    last_attempt_at DATETIME NULL,
    sent_at DATETIME NULL,
    UNIQUE KEY uniq_integration_delivery_idem (tenant_id, connector_code, idempotency_key),
    KEY idx_integration_deliveries_tenant_status (tenant_id, status),
    KEY idx_integration_deliveries_tenant_connector (tenant_id, connector_code),
    KEY idx_integration_deliveries_created (tenant_id, created_at),
    CONSTRAINT fk_integration_deliveries_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS jobs_queue (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    queue_name VARCHAR(100) NOT NULL,
    idempotency_key VARCHAR(191) NULL,
    payload_json JSON NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    attempts INT NOT NULL DEFAULT 0,
    max_attempts INT NOT NULL DEFAULT 5,
    last_error VARCHAR(500) NULL,
    available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reserved_at DATETIME NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    KEY idx_jobs_queue_status_available (status, available_at),
    KEY idx_jobs_queue_queue_available (queue_name, status, available_at),
    UNIQUE KEY uniq_jobs_queue_idempotency (queue_name, idempotency_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS jobs_dlq (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id BIGINT UNSIGNED NULL,
    queue_name VARCHAR(100) NOT NULL,
    payload_json JSON NOT NULL,
    attempts INT NOT NULL,
    max_attempts INT NOT NULL,
    last_error VARCHAR(500) NULL,
    requeued_job_id BIGINT UNSIGNED NULL,
    failed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    requeued_at DATETIME NULL,
    KEY idx_jobs_dlq_requeued (requeued_at),
    KEY idx_jobs_dlq_queue_failed (queue_name, failed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payment_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    sale_id BIGINT UNSIGNED NULL,
    provider VARCHAR(40) NOT NULL,
    channel VARCHAR(30) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    currency VARCHAR(10) NOT NULL DEFAULT 'DOP',
    status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    idempotency_key VARCHAR(120) NOT NULL,
    external_ref VARCHAR(120) NULL,
    response_json JSON NULL,
    error_message VARCHAR(255) NULL,
    created_by BIGINT UNSIGNED NULL,
    captured_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    UNIQUE KEY uniq_payment_tx_idem (tenant_id, provider, idempotency_key),
    KEY idx_payment_tx_tenant_status (tenant_id, status),
    KEY idx_payment_tx_tenant_sale (tenant_id, sale_id),
    KEY idx_payment_tx_tenant_created (tenant_id, created_at),
    KEY idx_payment_tx_tenant_provider_external_ref (tenant_id, provider, external_ref),
    CONSTRAINT fk_payment_tx_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_payment_tx_sale FOREIGN KEY (sale_id) REFERENCES pos_sales(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payment_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    payment_transaction_id BIGINT UNSIGNED NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    status VARCHAR(20) NOT NULL,
    payload_json JSON NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_payment_events_tenant (tenant_id),
    KEY idx_payment_events_tx (tenant_id, payment_transaction_id),
    CONSTRAINT fk_payment_events_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_payment_events_tx FOREIGN KEY (payment_transaction_id) REFERENCES payment_transactions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payment_webhook_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    provider VARCHAR(40) NOT NULL,
    webhook_event_id VARCHAR(120) NOT NULL,
    payload_json JSON NOT NULL,
    processed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_payment_webhook_event (tenant_id, provider, webhook_event_id),
    KEY idx_payment_webhook_tenant (tenant_id),
    CONSTRAINT fk_payment_webhook_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payment_reconciliations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    provider VARCHAR(40) NOT NULL,
    reconciliation_date DATE NOT NULL,
    pos_payments_count INT NOT NULL DEFAULT 0,
    pos_payments_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    provider_payments_count INT NOT NULL DEFAULT 0,
    provider_payments_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    diff_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'OPEN',
    summary_json JSON NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    UNIQUE KEY uniq_payment_reconciliation_day (tenant_id, provider, reconciliation_date),
    KEY idx_payment_reconciliation_tenant (tenant_id),
    KEY idx_payment_reconciliation_tenant_status (tenant_id, status),
    CONSTRAINT fk_payment_reconciliation_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS accounting_account_maps (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    map_key VARCHAR(80) NOT NULL,
    account_code VARCHAR(50) NOT NULL,
    description VARCHAR(190) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    UNIQUE KEY uniq_accounting_account_map (tenant_id, map_key),
    KEY idx_accounting_account_maps_tenant (tenant_id),
    KEY idx_accounting_account_maps_active (tenant_id, active),
    CONSTRAINT fk_accounting_account_maps_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS accounting_period_closures (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    period_ym CHAR(7) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'CLOSED',
    summary_json JSON NULL,
    closed_by BIGINT UNSIGNED NULL,
    closed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    UNIQUE KEY uniq_accounting_period (tenant_id, period_ym),
    KEY idx_accounting_period_tenant (tenant_id),
    KEY idx_accounting_period_status (tenant_id, status),
    CONSTRAINT fk_accounting_period_closures_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hardware_devices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NULL,
    register_id BIGINT UNSIGNED NULL,
    device_code VARCHAR(80) NOT NULL,
    name VARCHAR(150) NOT NULL,
    type VARCHAR(40) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    connection_json JSON NULL,
    last_seen_at DATETIME NULL,
    last_error VARCHAR(255) NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    UNIQUE KEY uniq_hardware_device_code (tenant_id, device_code),
    KEY idx_hardware_devices_tenant (tenant_id),
    KEY idx_hardware_devices_branch_register (tenant_id, branch_id, register_id),
    CONSTRAINT fk_hardware_devices_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_hardware_devices_branch FOREIGN KEY (branch_id) REFERENCES branches(id),
    CONSTRAINT fk_hardware_devices_register FOREIGN KEY (register_id) REFERENCES pos_registers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hardware_print_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    device_id BIGINT UNSIGNED NOT NULL,
    sale_id BIGINT UNSIGNED NULL,
    template VARCHAR(80) NOT NULL DEFAULT 'POS_TICKET',
    payload_json JSON NOT NULL,
    idempotency_key VARCHAR(120) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    queued_job_id BIGINT UNSIGNED NULL,
    attempts INT NOT NULL DEFAULT 0,
    last_error VARCHAR(255) NULL,
    printed_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    UNIQUE KEY uniq_hardware_print_job_idem (tenant_id, device_id, idempotency_key),
    KEY idx_hardware_print_jobs_tenant (tenant_id),
    KEY idx_hardware_print_jobs_status (tenant_id, status),
    CONSTRAINT fk_hardware_print_jobs_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_hardware_print_jobs_device FOREIGN KEY (device_id) REFERENCES hardware_devices(id),
    CONSTRAINT fk_hardware_print_jobs_sale FOREIGN KEY (sale_id) REFERENCES pos_sales(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hardware_device_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    device_id BIGINT UNSIGNED NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    payload_json JSON NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_hardware_device_events_tenant (tenant_id),
    KEY idx_hardware_device_events_device (tenant_id, device_id),
    CONSTRAINT fk_hardware_device_events_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_hardware_device_events_device FOREIGN KEY (device_id) REFERENCES hardware_devices(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inventory_branch_item_policies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NOT NULL,
    item_id BIGINT UNSIGNED NOT NULL,
    min_qty DECIMAL(12,2) NOT NULL DEFAULT 0,
    max_qty DECIMAL(12,2) NOT NULL DEFAULT 0,
    reorder_qty DECIMAL(12,2) NOT NULL DEFAULT 0,
    lead_time_days INT NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    UNIQUE KEY uniq_inventory_policy_branch_item (tenant_id, branch_id, item_id),
    KEY idx_inventory_policy_tenant_status (tenant_id, status),
    KEY idx_inventory_policy_branch (tenant_id, branch_id),
    KEY idx_inventory_policy_item (tenant_id, item_id),
    CONSTRAINT fk_inventory_policy_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_inventory_policy_branch FOREIGN KEY (branch_id) REFERENCES branches(id),
    CONSTRAINT fk_inventory_policy_item FOREIGN KEY (item_id) REFERENCES catalog_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ops_daily_closures (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NOT NULL,
    close_date DATE NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'CLOSED',
    opening_cash DECIMAL(12,2) NOT NULL DEFAULT 0,
    cash_in DECIMAL(12,2) NOT NULL DEFAULT 0,
    cash_out DECIMAL(12,2) NOT NULL DEFAULT 0,
    cash_sales DECIMAL(12,2) NOT NULL DEFAULT 0,
    sales_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    sales_count INT NOT NULL DEFAULT 0,
    payments_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    payments_count INT NOT NULL DEFAULT 0,
    expected_cash DECIMAL(12,2) NOT NULL DEFAULT 0,
    declared_cash DECIMAL(12,2) NOT NULL DEFAULT 0,
    difference DECIMAL(12,2) NOT NULL DEFAULT 0,
    summary_json JSON NULL,
    reason_reopen VARCHAR(255) NULL,
    closed_by BIGINT UNSIGNED NULL,
    closed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reopened_by BIGINT UNSIGNED NULL,
    reopened_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    UNIQUE KEY uniq_ops_daily_closure (tenant_id, branch_id, close_date),
    KEY idx_ops_daily_closure_tenant_status (tenant_id, status),
    KEY idx_ops_daily_closure_branch_date (tenant_id, branch_id, close_date),
    CONSTRAINT fk_ops_daily_closure_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_ops_daily_closure_branch FOREIGN KEY (branch_id) REFERENCES branches(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS backup_snapshots (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    snapshot_type VARCHAR(30) NOT NULL DEFAULT 'LOGICAL',
    status VARCHAR(20) NOT NULL DEFAULT 'DONE',
    checksum VARCHAR(80) NOT NULL,
    size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    metadata_json JSON NOT NULL,
    requested_by BIGINT UNSIGNED NULL,
    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    KEY idx_backup_snapshots_tenant (tenant_id),
    KEY idx_backup_snapshots_status (tenant_id, status),
    CONSTRAINT fk_backup_snapshots_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS backup_restore_runs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    backup_snapshot_id BIGINT UNSIGNED NOT NULL,
    mode VARCHAR(20) NOT NULL DEFAULT 'DRY_RUN',
    status VARCHAR(20) NOT NULL DEFAULT 'REQUESTED',
    summary_json JSON NULL,
    requested_by BIGINT UNSIGNED NULL,
    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    KEY idx_backup_restore_runs_tenant (tenant_id),
    KEY idx_backup_restore_runs_status (tenant_id, status),
    CONSTRAINT fk_backup_restore_runs_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_backup_restore_runs_snapshot FOREIGN KEY (backup_snapshot_id) REFERENCES backup_snapshots(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS security_mfa_methods (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    method VARCHAR(20) NOT NULL DEFAULT 'TOTP',
    secret_hash VARCHAR(255) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    verified_at DATETIME NULL,
    last_used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    UNIQUE KEY uniq_security_mfa_user_method (tenant_id, user_id, method),
    KEY idx_security_mfa_tenant (tenant_id),
    CONSTRAINT fk_security_mfa_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_security_mfa_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS security_secret_rotations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    secret_key VARCHAR(80) NOT NULL,
    old_hash VARCHAR(255) NULL,
    new_hash VARCHAR(255) NOT NULL,
    rotated_by BIGINT UNSIGNED NULL,
    rotated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_security_rotations_tenant (tenant_id),
    KEY idx_security_rotations_key (tenant_id, secret_key),
    CONSTRAINT fk_security_rotations_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

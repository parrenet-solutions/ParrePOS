-- Cola de jobs persistente con reintentos y DLQ
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
    failed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_jobs_dlq_queue_failed (queue_name, failed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

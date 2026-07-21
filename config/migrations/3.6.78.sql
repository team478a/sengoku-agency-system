ALTER TABLE external_partner_sites
    ADD COLUMN IF NOT EXISTS inbound_scopes TEXT NULL AFTER inbound_api_key,
    ADD COLUMN IF NOT EXISTS outbound_scopes TEXT NULL AFTER inbound_scopes,
    ADD COLUMN IF NOT EXISTS inbound_allowed_system_key VARCHAR(100) DEFAULT NULL AFTER outbound_scopes,
    ADD COLUMN IF NOT EXISTS api_key_expires_at DATETIME DEFAULT NULL AFTER inbound_allowed_system_key,
    ADD COLUMN IF NOT EXISTS inbound_ip_allowlist TEXT NULL AFTER api_key_expires_at,
    ADD COLUMN IF NOT EXISTS hmac_secret TEXT NULL AFTER inbound_ip_allowlist,
    ADD COLUMN IF NOT EXISTS hmac_key_id VARCHAR(100) DEFAULT NULL AFTER hmac_secret;

CREATE TABLE IF NOT EXISTS referral_aliases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    alias_type VARCHAR(50) NOT NULL,
    alias_value_hash VARCHAR(64) NOT NULL,
    alias_value_masked VARCHAR(191) DEFAULT NULL,
    canonical_token_id INT NOT NULL,
    source_system_key VARCHAR(100) DEFAULT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    metadata_json MEDIUMTEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_referral_alias (alias_type, alias_value_hash),
    INDEX idx_referral_alias_token (canonical_token_id),
    INDEX idx_referral_alias_source (source_system_key),
    INDEX idx_referral_alias_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS customer_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    common_user_id VARCHAR(64) NOT NULL,
    source_system_key VARCHAR(100) NOT NULL,
    source_user_id VARCHAR(191) DEFAULT NULL,
    order_id VARCHAR(191) NOT NULL,
    order_item_id VARCHAR(191) NOT NULL DEFAULT 'default',
    product_code VARCHAR(191) DEFAULT NULL,
    registration_referrer_agency_id VARCHAR(100) DEFAULT NULL,
    assigned_agency_id VARCHAR(100) DEFAULT NULL,
    sales_agent_id VARCHAR(100) DEFAULT NULL,
    closing_agent_id VARCHAR(100) DEFAULT NULL,
    referral_session_key VARCHAR(100) DEFAULT NULL,
    payment_status VARCHAR(50) DEFAULT NULL,
    entitlement_status VARCHAR(50) DEFAULT NULL,
    amount DECIMAL(12,2) DEFAULT NULL,
    currency VARCHAR(10) NOT NULL DEFAULT 'JPY',
    occurred_at DATETIME DEFAULT NULL,
    metadata_json MEDIUMTEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_customer_transaction (source_system_key, order_id, order_item_id),
    INDEX idx_customer_transactions_common (common_user_id),
    INDEX idx_customer_transactions_system_user (source_system_key, source_user_id),
    INDEX idx_customer_transactions_referrer (registration_referrer_agency_id),
    INDEX idx_customer_transactions_assigned (assigned_agency_id),
    INDEX idx_customer_transactions_sales (sales_agent_id),
    INDEX idx_customer_transactions_closing (closing_agent_id),
    INDEX idx_customer_transactions_occurred (occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS customer_assignment_histories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    common_user_id VARCHAR(64) NOT NULL,
    previous_assigned_agency_id VARCHAR(100) DEFAULT NULL,
    new_assigned_agency_id VARCHAR(100) DEFAULT NULL,
    reason VARCHAR(255) DEFAULT NULL,
    operated_by_type VARCHAR(50) DEFAULT NULL,
    operated_by_id INT DEFAULT NULL,
    correlation_id VARCHAR(100) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_assignment_history_common (common_user_id),
    INDEX idx_assignment_history_new (new_assigned_agency_id),
    INDEX idx_assignment_history_correlation (correlation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS integration_outbox_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id VARCHAR(100) NOT NULL UNIQUE,
    event_type VARCHAR(100) NOT NULL,
    event_version VARCHAR(20) NOT NULL DEFAULT '1.0',
    source_system_key VARCHAR(100) NOT NULL DEFAULT 'agency-system',
    target_site_key VARCHAR(100) DEFAULT NULL,
    endpoint VARCHAR(500) DEFAULT NULL,
    payload_json MEDIUMTEXT NOT NULL,
    payload_hash VARCHAR(64) DEFAULT NULL,
    hmac_key_id VARCHAR(100) DEFAULT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    attempt_count INT NOT NULL DEFAULT 0,
    max_attempts INT NOT NULL DEFAULT 8,
    next_attempt_at DATETIME DEFAULT NULL,
    last_attempt_at DATETIME DEFAULT NULL,
    last_error TEXT DEFAULT NULL,
    correlation_id VARCHAR(100) DEFAULT NULL,
    idempotency_key VARCHAR(191) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    processed_at DATETIME DEFAULT NULL,
    INDEX idx_outbox_status_next (status, next_attempt_at),
    INDEX idx_outbox_site (target_site_key),
    INDEX idx_outbox_correlation (correlation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS integration_event_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id VARCHAR(100) DEFAULT NULL,
    site_key VARCHAR(100) DEFAULT NULL,
    endpoint VARCHAR(500) DEFAULT NULL,
    http_status INT DEFAULT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    request_headers_json MEDIUMTEXT DEFAULT NULL,
    response_body MEDIUMTEXT DEFAULT NULL,
    error_message TEXT DEFAULT NULL,
    attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_event_attempts_event (event_id),
    INDEX idx_event_attempts_site (site_key),
    INDEX idx_event_attempts_success (success, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO system_settings (key_name, value) VALUES
('common_hub_verified_identity_only', '1'),
('external_partner_outbox_enabled', '1'),
('external_partner_hmac_enabled', '1')
ON DUPLICATE KEY UPDATE key_name=key_name;

INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.6.78', 'Sen no Kuni common integration contract hardening');

CREATE TABLE IF NOT EXISTS common_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    common_user_id VARCHAR(64) NOT NULL UNIQUE,
    primary_email_hash VARCHAR(64) DEFAULT NULL,
    primary_phone_hash VARCHAR(64) DEFAULT NULL,
    primary_wallet_address VARCHAR(255) DEFAULT NULL,
    status ENUM('active','merged','disabled') NOT NULL DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_common_users_status (status),
    INDEX idx_common_users_email_hash (primary_email_hash),
    INDEX idx_common_users_phone_hash (primary_phone_hash),
    INDEX idx_common_users_wallet (primary_wallet_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS service_user_mappings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    common_user_id VARCHAR(64) NOT NULL,
    service_key VARCHAR(100) NOT NULL,
    service_user_id VARCHAR(191) NOT NULL,
    agent_id INT DEFAULT NULL,
    email_hash VARCHAR(64) DEFAULT NULL,
    phone_hash VARCHAR(64) DEFAULT NULL,
    wallet_address VARCHAR(255) DEFAULT NULL,
    profile_json MEDIUMTEXT DEFAULT NULL,
    status ENUM('active','merged','disabled') NOT NULL DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_service_user (service_key, service_user_id),
    UNIQUE KEY uniq_common_service_user (common_user_id, service_key, service_user_id),
    INDEX idx_service_mappings_common (common_user_id),
    INDEX idx_service_mappings_agent (agent_id),
    INDEX idx_service_mappings_status (status),
    INDEX idx_service_mappings_email_hash (email_hash),
    INDEX idx_service_mappings_phone_hash (phone_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS agency_customer_relations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    common_user_id VARCHAR(64) NOT NULL,
    agent_id INT DEFAULT NULL,
    project_id INT NOT NULL DEFAULT 0,
    relation_type VARCHAR(50) NOT NULL DEFAULT 'referral',
    source_service_key VARCHAR(100) DEFAULT NULL,
    source_service_user_id VARCHAR(191) DEFAULT NULL,
    referral_token_id INT DEFAULT NULL,
    referral_source VARCHAR(255) DEFAULT NULL,
    locked TINYINT(1) NOT NULL DEFAULT 1,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_common_relation_project (common_user_id, relation_type, project_id),
    INDEX idx_customer_rel_common (common_user_id),
    INDEX idx_customer_rel_agent (agent_id),
    INDEX idx_customer_rel_project (project_id),
    INDEX idx_customer_rel_source (source_service_key, source_service_user_id),
    INDEX idx_customer_rel_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS integration_idempotency_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    key_hash VARCHAR(64) NOT NULL UNIQUE,
    endpoint VARCHAR(255) NOT NULL,
    method VARCHAR(12) NOT NULL,
    request_hash VARCHAR(64) DEFAULT NULL,
    response_status INT DEFAULT NULL,
    response_body MEDIUMTEXT DEFAULT NULL,
    expires_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_idempotency_expires (expires_at),
    INDEX idx_idempotency_endpoint (endpoint, method)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS integration_event_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    direction ENUM('inbound','outbound') NOT NULL,
    site_key VARCHAR(100) DEFAULT NULL,
    event_type VARCHAR(100) NOT NULL,
    endpoint VARCHAR(255) DEFAULT NULL,
    http_status INT DEFAULT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    common_user_id VARCHAR(64) DEFAULT NULL,
    agent_id INT DEFAULT NULL,
    request_body MEDIUMTEXT DEFAULT NULL,
    response_body MEDIUMTEXT DEFAULT NULL,
    error_message TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_integration_logs_created (created_at),
    INDEX idx_integration_logs_site (site_key, created_at),
    INDEX idx_integration_logs_common (common_user_id),
    INDEX idx_integration_logs_agent (agent_id),
    INDEX idx_integration_logs_success (success, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO system_settings (key_name, value) VALUES
('common_id_enabled', '0'),
('referral_v2_enabled', '0'),
('external_registration_capture_enabled', '0')
ON DUPLICATE KEY UPDATE key_name=key_name;

INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.6.59', '共通ID連携基盤');

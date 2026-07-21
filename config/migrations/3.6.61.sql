CREATE TABLE IF NOT EXISTS referral_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(80) NOT NULL UNIQUE,
    agent_id INT NOT NULL,
    project_id INT NOT NULL DEFAULT 0,
    token_type VARCHAR(50) NOT NULL DEFAULT 'lp',
    destination_service_key VARCHAR(100) NOT NULL DEFAULT '',
    destination_url VARCHAR(500) DEFAULT NULL,
    metadata_json MEDIUMTEXT DEFAULT NULL,
    click_count INT NOT NULL DEFAULT 0,
    last_used_at DATETIME DEFAULT NULL,
    expires_at DATETIME DEFAULT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_referral_token_scope (agent_id, project_id, token_type, destination_service_key),
    INDEX idx_referral_tokens_token (token),
    INDEX idx_referral_tokens_agent (agent_id),
    INDEX idx_referral_tokens_project (project_id),
    INDEX idx_referral_tokens_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS referral_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_key VARCHAR(80) NOT NULL UNIQUE,
    referral_token_id INT NOT NULL,
    token VARCHAR(80) NOT NULL,
    agent_id INT NOT NULL,
    project_id INT NOT NULL DEFAULT 0,
    service_key VARCHAR(100) DEFAULT NULL,
    service_user_id VARCHAR(191) DEFAULT NULL,
    common_user_id VARCHAR(64) DEFAULT NULL,
    landing_url VARCHAR(500) DEFAULT NULL,
    destination_url VARCHAR(500) DEFAULT NULL,
    referrer_url VARCHAR(500) DEFAULT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,
    ip_hash VARCHAR(64) DEFAULT NULL,
    event_type VARCHAR(50) NOT NULL DEFAULT 'click',
    metadata_json MEDIUMTEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_referral_sessions_token (referral_token_id, created_at),
    INDEX idx_referral_sessions_agent (agent_id, created_at),
    INDEX idx_referral_sessions_common (common_user_id),
    INDEX idx_referral_sessions_service (service_key, service_user_id),
    INDEX idx_referral_sessions_event (event_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO system_settings (key_name, value) VALUES
('referral_token_api_enabled', '0')
ON DUPLICATE KEY UPDATE key_name=key_name;

INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.6.61', '紹介トークン v2 API');

ALTER TABLE common_users
    ADD COLUMN IF NOT EXISTS acquisition_channel VARCHAR(100) DEFAULT NULL AFTER primary_wallet_address,
    ADD COLUMN IF NOT EXISTS acquisition_source VARCHAR(191) DEFAULT NULL AFTER acquisition_channel,
    ADD COLUMN IF NOT EXISTS campaign_id VARCHAR(191) DEFAULT NULL AFTER acquisition_source,
    ADD COLUMN IF NOT EXISTS registration_referrer_agent_id INT DEFAULT NULL AFTER campaign_id,
    ADD COLUMN IF NOT EXISTS assigned_agent_id INT DEFAULT NULL AFTER registration_referrer_agent_id,
    ADD COLUMN IF NOT EXISTS agent_link_status VARCHAR(50) NOT NULL DEFAULT 'none' AFTER assigned_agent_id,
    ADD COLUMN IF NOT EXISTS management_status VARCHAR(50) NOT NULL DEFAULT 'general' AFTER agent_link_status,
    ADD COLUMN IF NOT EXISTS merged_into_common_user_id VARCHAR(64) DEFAULT NULL AFTER management_status,
    ADD COLUMN IF NOT EXISTS first_touch_at DATETIME DEFAULT NULL AFTER merged_into_common_user_id,
    ADD COLUMN IF NOT EXISTS last_touch_at DATETIME DEFAULT NULL AFTER first_touch_at,
    ADD COLUMN IF NOT EXISTS metadata_json MEDIUMTEXT DEFAULT NULL AFTER last_touch_at,
    ADD INDEX IF NOT EXISTS idx_common_users_referrer (registration_referrer_agent_id),
    ADD INDEX IF NOT EXISTS idx_common_users_assigned (assigned_agent_id),
    ADD INDEX IF NOT EXISTS idx_common_users_agent_link (agent_link_status),
    ADD INDEX IF NOT EXISTS idx_common_users_management (management_status);

CREATE TABLE IF NOT EXISTS user_identities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    common_user_id VARCHAR(64) NOT NULL,
    identity_type VARCHAR(50) NOT NULL,
    provider VARCHAR(100) NOT NULL DEFAULT '',
    identity_hash VARCHAR(64) NOT NULL,
    identity_masked VARCHAR(191) DEFAULT NULL,
    verified TINYINT(1) NOT NULL DEFAULT 0,
    confidence_score TINYINT UNSIGNED NOT NULL DEFAULT 100,
    source_system_key VARCHAR(100) DEFAULT NULL,
    source_external_user_id VARCHAR(191) DEFAULT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    first_seen_at DATETIME DEFAULT NULL,
    last_seen_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_identity (identity_type, provider, identity_hash),
    INDEX idx_user_identities_common (common_user_id),
    INDEX idx_user_identities_source (source_system_key, source_external_user_id),
    INDEX idx_user_identities_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS system_account_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    common_user_id VARCHAR(64) NOT NULL,
    system_key VARCHAR(100) NOT NULL,
    external_user_id VARCHAR(191) NOT NULL,
    agent_id INT DEFAULT NULL,
    email_hash VARCHAR(64) DEFAULT NULL,
    phone_hash VARCHAR(64) DEFAULT NULL,
    wallet_address VARCHAR(255) DEFAULT NULL,
    login_email_hash VARCHAR(64) DEFAULT NULL,
    display_name VARCHAR(191) DEFAULT NULL,
    role_name VARCHAR(100) DEFAULT NULL,
    profile_json MEDIUMTEXT DEFAULT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    linked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_synced_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_system_account (system_key, external_user_id),
    UNIQUE KEY uniq_common_system_account (common_user_id, system_key, external_user_id),
    INDEX idx_system_links_common (common_user_id),
    INDEX idx_system_links_agent (agent_id),
    INDEX idx_system_links_status (status),
    INDEX idx_system_links_email_hash (email_hash),
    INDEX idx_system_links_phone_hash (phone_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS agent_touchpoints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    common_user_id VARCHAR(64) DEFAULT NULL,
    agent_id INT DEFAULT NULL,
    project_id INT NOT NULL DEFAULT 0,
    referral_token_id INT DEFAULT NULL,
    referral_session_key VARCHAR(100) DEFAULT NULL,
    touchpoint_type VARCHAR(50) NOT NULL DEFAULT 'visit',
    source_system_key VARCHAR(100) DEFAULT NULL,
    source_external_user_id VARCHAR(191) DEFAULT NULL,
    source_url VARCHAR(500) DEFAULT NULL,
    landing_url VARCHAR(500) DEFAULT NULL,
    user_agent_hash VARCHAR(64) DEFAULT NULL,
    ip_hash VARCHAR(64) DEFAULT NULL,
    locked TINYINT(1) NOT NULL DEFAULT 0,
    occurred_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    confirmed_at DATETIME DEFAULT NULL,
    metadata_json MEDIUMTEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_touchpoints_common (common_user_id),
    INDEX idx_touchpoints_agent (agent_id),
    INDEX idx_touchpoints_project (project_id),
    INDEX idx_touchpoints_token (referral_token_id),
    INDEX idx_touchpoints_session (referral_session_key),
    INDEX idx_touchpoints_type_time (touchpoint_type, occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS account_merge_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    from_common_user_id VARCHAR(64) NOT NULL,
    to_common_user_id VARCHAR(64) NOT NULL,
    merge_reason VARCHAR(255) DEFAULT NULL,
    confidence_score TINYINT UNSIGNED DEFAULT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'completed',
    operated_by_type VARCHAR(50) DEFAULT NULL,
    operated_by_id INT DEFAULT NULL,
    before_json MEDIUMTEXT DEFAULT NULL,
    after_json MEDIUMTEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_merge_from (from_common_user_id),
    INDEX idx_merge_to (to_common_user_id),
    INDEX idx_merge_status (status),
    INDEX idx_merge_operator (operated_by_type, operated_by_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO system_settings (key_name, value) VALUES
('common_hub_enabled', '0'),
('common_hub_read_enabled', '0'),
('common_hub_write_enabled', '0'),
('passport_integration_enabled', '0'),
('shopping_integration_enabled', '0'),
('wallet_integration_enabled', '0'),
('ai_art_integration_enabled', '0')
ON DUPLICATE KEY UPDATE key_name=key_name;

INSERT IGNORE INTO schema_migrations (version, description)
VALUES ('3.6.72', 'common customer hub stage 1 schema');

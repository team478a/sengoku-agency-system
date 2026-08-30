CREATE TABLE IF NOT EXISTS point_currencies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    point_code VARCHAR(50) NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    symbol VARCHAR(20) NOT NULL DEFAULT '',
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_point_currency_code (point_code),
    INDEX idx_point_currencies_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS point_campaigns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_key VARCHAR(100) NOT NULL,
    point_currency_code VARCHAR(50) NOT NULL,
    name VARCHAR(255) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'draft',
    active_version_id INT DEFAULT NULL,
    starts_at DATETIME DEFAULT NULL,
    ends_at DATETIME DEFAULT NULL,
    created_by_admin_id INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_point_campaign_key (campaign_key),
    INDEX idx_point_campaigns_point (point_currency_code),
    INDEX idx_point_campaigns_status (status, starts_at, ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS point_campaign_versions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    version_no INT NOT NULL,
    registrant_points INT NOT NULL DEFAULT 0,
    direct_referrer_points INT NOT NULL DEFAULT 0,
    upper_director_points INT NOT NULL DEFAULT 0,
    rule_json MEDIUMTEXT DEFAULT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'draft',
    activated_at DATETIME DEFAULT NULL,
    created_by_admin_id INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_point_campaign_version (campaign_id, version_no),
    INDEX idx_point_campaign_versions_status (campaign_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS point_award_events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    award_event_key VARCHAR(191) NOT NULL,
    campaign_id INT NOT NULL,
    campaign_version_id INT DEFAULT NULL,
    point_code VARCHAR(50) NOT NULL DEFAULT 'orly',
    recipient_common_user_id VARCHAR(64) DEFAULT NULL,
    recipient_agent_id INT DEFAULT NULL,
    recipient_type VARCHAR(50) NOT NULL,
    target_common_user_id VARCHAR(64) DEFAULT NULL,
    trigger_event_type VARCHAR(100) NOT NULL,
    trigger_event_id VARCHAR(191) DEFAULT NULL,
    source_system_key VARCHAR(100) DEFAULT NULL,
    project_key VARCHAR(100) DEFAULT NULL,
    direct_referrer_agent_id INT DEFAULT NULL,
    upper_director_agent_id INT DEFAULT NULL,
    points INT NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    wallet_event_id VARCHAR(191) DEFAULT NULL,
    outbox_event_id VARCHAR(100) DEFAULT NULL,
    referral_snapshot_json MEDIUMTEXT DEFAULT NULL,
    payload_hash VARCHAR(64) DEFAULT NULL,
    correlation_id VARCHAR(100) DEFAULT NULL,
    occurred_at DATETIME DEFAULT NULL,
    processed_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_point_award_event_key (award_event_key),
    INDEX idx_point_award_campaign (campaign_id, campaign_version_id),
    INDEX idx_point_award_recipient_common (recipient_common_user_id),
    INDEX idx_point_award_recipient_agent (recipient_agent_id),
    INDEX idx_point_award_target_common (target_common_user_id),
    INDEX idx_point_award_trigger (trigger_event_type, trigger_event_id),
    INDEX idx_point_award_status (status, created_at),
    INDEX idx_point_award_project (project_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS point_adjustments (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    adjustment_key VARCHAR(191) NOT NULL,
    award_event_id BIGINT DEFAULT NULL,
    point_code VARCHAR(50) NOT NULL DEFAULT 'orly',
    recipient_common_user_id VARCHAR(64) DEFAULT NULL,
    recipient_agent_id INT DEFAULT NULL,
    points_delta INT NOT NULL,
    reason VARCHAR(255) NOT NULL,
    note TEXT DEFAULT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    created_by_admin_id INT DEFAULT NULL,
    approved_by_admin_id INT DEFAULT NULL,
    approved_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_point_adjustment_key (adjustment_key),
    INDEX idx_point_adjustments_award (award_event_id),
    INDEX idx_point_adjustments_recipient_common (recipient_common_user_id),
    INDEX idx_point_adjustments_recipient_agent (recipient_agent_id),
    INDEX idx_point_adjustments_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS point_setting_audit_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(100) NOT NULL,
    target_table VARCHAR(100) DEFAULT NULL,
    target_id VARCHAR(100) DEFAULT NULL,
    before_json MEDIUMTEXT DEFAULT NULL,
    after_json MEDIUMTEXT DEFAULT NULL,
    reason VARCHAR(255) DEFAULT NULL,
    operated_by_admin_id INT DEFAULT NULL,
    request_id VARCHAR(100) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_point_setting_audit_target (target_table, target_id),
    INDEX idx_point_setting_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO point_currencies (point_code, display_name, symbol, status)
VALUES ('orly', 'オーリーポイント', 'ORLY', 'active')
ON DUPLICATE KEY UPDATE
    display_name=VALUES(display_name),
    symbol=VALUES(symbol),
    status=VALUES(status);

INSERT INTO point_campaigns (campaign_key, point_currency_code, name, status)
VALUES ('orly_referral_signup', 'orly', 'ORLY紹介登録ポイント', 'draft')
ON DUPLICATE KEY UPDATE
    point_currency_code=VALUES(point_currency_code),
    name=VALUES(name);

INSERT INTO point_campaign_versions
    (campaign_id, version_no, registrant_points, direct_referrer_points, upper_director_points, status, rule_json)
SELECT id, 1, 3000, 3000, 1000, 'draft',
       '{"trigger":"referral.confirmed","wallet_delivery":"disabled_until_feature_flag_enabled"}'
FROM point_campaigns
WHERE campaign_key = 'orly_referral_signup'
ON DUPLICATE KEY UPDATE
    registrant_points=VALUES(registrant_points),
    direct_referrer_points=VALUES(direct_referrer_points),
    upper_director_points=VALUES(upper_director_points),
    rule_json=VALUES(rule_json);

INSERT IGNORE INTO system_settings (key_name, value) VALUES
('orly_point_campaign_enabled', '0'),
('orly_point_award_enabled', '0'),
('orly_wallet_delivery_enabled', '0');

INSERT IGNORE INTO schema_migrations (version, description)
VALUES ('3.6.169', 'ORLYポイント紹介付与基盤');

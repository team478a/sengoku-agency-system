CREATE DATABASE IF NOT EXISTS sengoku_lp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sengoku_lp;

CREATE TABLE IF NOT EXISTS admins (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(100) NOT NULL UNIQUE,
    display_name VARCHAR(255) DEFAULT NULL,
    password    VARCHAR(255) NOT NULL,
    email       VARCHAR(255) NOT NULL,
    role        ENUM('super_admin','staff') DEFAULT 'super_admin',
    status      ENUM('active','inactive') DEFAULT 'active',
    reset_token VARCHAR(64) DEFAULT NULL,
    reset_token_exp DATETIME DEFAULT NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS projects (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    slug        VARCHAR(100) NOT NULL UNIQUE,
    name        VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    status      ENUM('active','inactive') DEFAULT 'active',
    sort_order  INT DEFAULT 0,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lp_templates (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    project_id      INT DEFAULT NULL,
    slug            VARCHAR(100) NOT NULL UNIQUE,
    name            VARCHAR(255) NOT NULL,
    description     TEXT,
    thumbnail_url   TEXT,
    html_file       VARCHAR(255) NOT NULL,
    status          ENUM('active','inactive') DEFAULT 'active',
    sort_order      INT DEFAULT 0,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lp_template_fields (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    field_key VARCHAR(100) NOT NULL,
    field_type ENUM('text','textarea','image') DEFAULT 'text',
    label VARCHAR(255) NOT NULL,
    value_text MEDIUMTEXT DEFAULT NULL,
    value_file TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_template_field (template_id, field_key),
    INDEX idx_template_fields_template (template_id),
    FOREIGN KEY (template_id) REFERENCES lp_templates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS agent_project_templates (
    agent_id INT NOT NULL,
    project_id INT NOT NULL,
    template_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (agent_id, project_id),
    INDEX idx_agent_project_template_project (project_id),
    INDEX idx_agent_project_template_template (template_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS agents (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    external_id          VARCHAR(191) DEFAULT NULL UNIQUE,
    agent_code           VARCHAR(50) NOT NULL UNIQUE,
    level                TINYINT(1) DEFAULT 1,
    parent_id            INT DEFAULT NULL,
    default_commission_rate DECIMAL(5,2) DEFAULT NULL,
    agent_name           VARCHAR(255) NOT NULL,
    person_name          VARCHAR(255) NOT NULL,
    email                VARCHAR(255) NOT NULL,
    login_email          VARCHAR(255) DEFAULT NULL,
    phone                VARCHAR(100),
    line_url             TEXT,
    show_form            TINYINT(1) DEFAULT 1,
    show_line_btn        TINYINT(1) DEFAULT 1,
    profile_image        TEXT,
    profile_text         TEXT,
    influencer_enabled   TINYINT(1) DEFAULT 0,
    influencer_name      VARCHAR(255) DEFAULT NULL,
    metamask_wallet_address VARCHAR(255) DEFAULT NULL,
    influencer_profile_text TEXT DEFAULT NULL,
    instagram_url        TEXT DEFAULT NULL,
    x_url                TEXT DEFAULT NULL,
    tiktok_url           TEXT DEFAULT NULL,
    youtube_url          TEXT DEFAULT NULL,
    default_template_id  INT,
    notify_email         TINYINT(1) DEFAULT 1,
    notify_line          TINYINT(1) DEFAULT 0,
    line_messaging_token TEXT,
    line_user_id         TEXT,
    notify_chatwork      TINYINT(1) DEFAULT 0,
    chatwork_webhook     TEXT,
    notify_slack         TINYINT(1) DEFAULT 0,
    slack_webhook        TEXT,
    password             VARCHAR(255) DEFAULT NULL,
    setup_token          VARCHAR(64) DEFAULT NULL,
    setup_token_exp      DATETIME DEFAULT NULL,
    apply_token          VARCHAR(64) DEFAULT NULL,
    apply_token_exp      DATETIME DEFAULT NULL,
    apply_token_director VARCHAR(64) DEFAULT NULL,
    apply_token_director_exp DATETIME DEFAULT NULL,
    apply_token_advisor  VARCHAR(64) DEFAULT NULL,
    apply_token_advisor_exp DATETIME DEFAULT NULL,
    position_type        VARCHAR(50) DEFAULT NULL,
    position_label       VARCHAR(100) DEFAULT NULL,
    status               ENUM('active','inactive') DEFAULT 'active',
    created_at           DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_parent (parent_id),
    FOREIGN KEY (default_template_id) REFERENCES lp_templates(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS applicants (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    company_name    VARCHAR(255) NOT NULL,
    person_name     VARCHAR(255) NOT NULL,
    email           VARCHAR(255) NOT NULL,
    phone           VARCHAR(100),
    line_url        TEXT,
    message         TEXT,
    status          ENUM('pending','approved','rejected') DEFAULT 'pending',
    agent_id        INT DEFAULT NULL,
    target_level    TINYINT(1) DEFAULT 3,
    position_type   VARCHAR(50) DEFAULT NULL,
    position_label  VARCHAR(100) DEFAULT NULL,
    recruitment_link_id INT DEFAULT NULL,
    recruitment_source VARCHAR(255) DEFAULT NULL,
    reviewed_at     DATETIME DEFAULT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS recruitment_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agent_id INT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    target_level TINYINT(1) NOT NULL DEFAULT 1,
    position_type VARCHAR(50) DEFAULT NULL,
    position_label VARCHAR(100) DEFAULT NULL,
    status ENUM('active','inactive') DEFAULT 'active',
    click_count INT NOT NULL DEFAULT 0,
    last_clicked_at DATETIME DEFAULT NULL,
    expires_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_recruitment_agent (agent_id),
    INDEX idx_recruitment_target (target_level),
    FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS agent_followups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_agent_id INT NOT NULL,
    target_agent_id INT NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'needs_follow',
    note TEXT,
    next_follow_at DATE DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_followups_owner (owner_agent_id),
    INDEX idx_followups_target (target_agent_id),
    INDEX idx_followups_next (next_follow_at),
    FOREIGN KEY (owner_agent_id) REFERENCES agents(id) ON DELETE CASCADE,
    FOREIGN KEY (target_agent_id) REFERENCES agents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS leads (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    project_id  INT DEFAULT NULL,
    common_user_id VARCHAR(64) DEFAULT NULL,
    referral_token_id INT DEFAULT NULL,
    referral_session_key VARCHAR(80) DEFAULT NULL,
    referral_source VARCHAR(255) DEFAULT NULL,
    agent_id    INT NOT NULL,
    name        VARCHAR(255) NOT NULL,
    email       VARCHAR(255) NOT NULL,
    phone       VARCHAR(100),
    message     TEXT,
    source_url  TEXT,
    status      ENUM('new','contacted','prospect','won','lost','closed') DEFAULT 'new',
    internal_note TEXT DEFAULT NULL,
    next_action_at DATE DEFAULT NULL,
    template_id  INT DEFAULT NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_leads_common_user (common_user_id),
    INDEX idx_leads_referral_token (referral_token_id),
    INDEX idx_leads_referral_session (referral_session_key),
    FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS access_logs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    project_id  INT DEFAULT NULL,
    referral_token_id INT DEFAULT NULL,
    referral_session_key VARCHAR(80) DEFAULT NULL,
    agent_id    INT NOT NULL,
    type        ENUM('pv','line_click','contact_click') DEFAULT 'pv',
    template_id  INT DEFAULT NULL,
    ip_hash     VARCHAR(64),
    user_agent  TEXT,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_access_referral_token (referral_token_id),
    INDEX idx_access_referral_session (referral_session_key),
    FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS schema_migrations (
    version     VARCHAR(20) NOT NULL PRIMARY KEY,
    description VARCHAR(255),
    applied_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS system_settings (
    key_name   VARCHAR(100) PRIMARY KEY,
    value      TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sso_clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_key VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    audience VARCHAR(100) NOT NULL,
    callback_url TEXT NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_sso_clients_status (status),
    INDEX idx_sso_clients_audience (audience)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS external_partner_sites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_key VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    base_url TEXT NOT NULL,
    api_key TEXT NOT NULL,
    inbound_api_key TEXT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    sort_order INT NOT NULL DEFAULT 0,
    last_test_status VARCHAR(30) NULL,
    last_test_message TEXT NULL,
    last_test_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_external_partner_sites_status (status),
    INDEX idx_external_partner_sites_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS login_logs (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_type    ENUM('admin','agent') NOT NULL,
    user_id      INT DEFAULT NULL,
    email        VARCHAR(255),
    ip_hash      VARCHAR(64),
    success      TINYINT(1) DEFAULT 0,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_logs_created (created_at),
    INDEX idx_login_logs_type_success (user_type, success, created_at),
    INDEX idx_login_logs_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS login_attempts (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    ip_hash      VARCHAR(64) NOT NULL,
    user_type    ENUM('admin','agent') NOT NULL,
    attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_attempt (ip_hash, user_type, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS common_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    common_user_id VARCHAR(64) NOT NULL UNIQUE,
    primary_email_hash VARCHAR(64) DEFAULT NULL,
    primary_phone_hash VARCHAR(64) DEFAULT NULL,
    primary_wallet_address VARCHAR(255) DEFAULT NULL,
    acquisition_channel VARCHAR(100) DEFAULT NULL,
    acquisition_source VARCHAR(191) DEFAULT NULL,
    campaign_id VARCHAR(191) DEFAULT NULL,
    registration_referrer_agent_id INT DEFAULT NULL,
    assigned_agent_id INT DEFAULT NULL,
    agent_link_status VARCHAR(50) NOT NULL DEFAULT 'none',
    management_status VARCHAR(50) NOT NULL DEFAULT 'general',
    merged_into_common_user_id VARCHAR(64) DEFAULT NULL,
    first_touch_at DATETIME DEFAULT NULL,
    last_touch_at DATETIME DEFAULT NULL,
    metadata_json MEDIUMTEXT DEFAULT NULL,
    status ENUM('active','merged','disabled') NOT NULL DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_common_users_status (status),
    INDEX idx_common_users_email_hash (primary_email_hash),
    INDEX idx_common_users_phone_hash (primary_phone_hash),
    INDEX idx_common_users_wallet (primary_wallet_address),
    INDEX idx_common_users_referrer (registration_referrer_agent_id),
    INDEX idx_common_users_assigned (assigned_agent_id),
    INDEX idx_common_users_agent_link (agent_link_status),
    INDEX idx_common_users_management (management_status)
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

CREATE TABLE IF NOT EXISTS admin_action_logs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    admin_id    INT DEFAULT NULL,
    action      VARCHAR(100) NOT NULL,
    target_type VARCHAR(50) NOT NULL,
    target_id   INT DEFAULT NULL,
    details     TEXT,
    ip_hash     VARCHAR(64),
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_action_created (admin_id, created_at),
    INDEX idx_target (target_type, target_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notices (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    title      VARCHAR(255) NOT NULL,
    body       TEXT NOT NULL,
    status     ENUM('active','inactive') DEFAULT 'active',
    is_pinned  TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS material_categories (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    sort_order INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS materials (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    project_id  INT DEFAULT NULL,
    category_id INT DEFAULT NULL,
    title       VARCHAR(255) NOT NULL,
    description TEXT,
    instagram_text MEDIUMTEXT DEFAULT NULL,
    x_text MEDIUMTEXT DEFAULT NULL,
    line_text MEDIUMTEXT DEFAULT NULL,
    file_url    TEXT,
    file_type   VARCHAR(50),
    status      ENUM('active','inactive') DEFAULT 'active',
    sort_order  INT DEFAULT 0,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES material_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS material_agent_access (
    material_id INT NOT NULL,
    agent_id    INT NOT NULL,
    PRIMARY KEY (material_id, agent_id),
    FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE CASCADE,
    FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS promotion_requests (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    applicant_id      INT NOT NULL,
    approver_id       INT NOT NULL,
    target_level      TINYINT(1) DEFAULT 2,
    message           TEXT,
    agent_comment     TEXT DEFAULT NULL,
    agent_reviewed_at DATETIME DEFAULT NULL,
    status            ENUM('pending','agent_approved','approved','rejected') DEFAULT 'pending',
    reviewed_at       DATETIME DEFAULT NULL,
    created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (applicant_id) REFERENCES agents(id) ON DELETE CASCADE,
    FOREIGN KEY (approver_id) REFERENCES agents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO schema_migrations (version, description) VALUES ('1.0.0', '初期インストール');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.1.0', '権限変更の安全化・通知・全配下アドバイザー一覧・管理者操作ログ');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.2.0', '管理者操作ログ閲覧画面');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.3.0', 'スマートフォン表示崩れ修正');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.4.0', '活動レポート画面');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.4.1', '募集URL生成時の白画面対策');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.4.2', 'マイグレーションコメント処理修正');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.4.3', '募集URL生成の旧カラム互換対応');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.4.4', '代理店LPプレビュー認証修正');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.4.5', 'アドバイザー募集区分管理');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.4.6', '専用LP QRコード共有強化');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.4.7', '戦国NFT LPスマホヒーロー画像修正');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.4.8', '募集URL詳細管理');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.4.9', '上位代理店活動レポート強化');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.5.0', '配下フォロー履歴管理');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.5.1', '問い合わせ商談管理強化');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.5.2', 'LPテンプレート成果計測');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.5.3', 'スマートフォン表示補強');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.5.4', 'CSV出力機能追加');

INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.5.5', 'アドバイザー区分名称設定');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.5.6', 'メンバー管理表示改善');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.5.7', 'メンバー管理500エラー修正');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.5.8', 'メンバー管理PHP式修正');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.5.9', 'メンバー管理安全版差し替え');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.6.0', 'メンバー管理アドバイザー種別反映');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.6.1', '既存アドバイザー種別補完');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.6.2', '申請管理アドバイザー種別反映');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.6.3', '代理店画面とメール文面整理');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.6.4', '募集URLテーブル補修');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.6.5', 'フォロー管理テーブル補修と権限別マイページ表示');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.6.6', '配下管理削除機能追加');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.6.7', '問い合わせ削除と状態ソート追加');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.6.8', '管理者問い合わせ削除機能追加');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.6.9', '紹介素材専用URL差し込み対応');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.6.10', '紹介素材投稿文コピー改善');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.6.11', '紹介素材SNS別コピー改善');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.6.12', '紹介素材SNS別投稿文とマニュアル改善');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.6.13', 'インフルエンサープロフィール設定');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.6.14', 'プロジェクト別LP・素材・問い合わせ管理');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.6.15', 'LP編集項目管理');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.6.16', 'LP表示編集のPC・スマホ画像切替対応');

INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.6.17', 'AI art LP templates');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.6.18', 'Project delete action');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.6.19', 'Project-specific LP URLs and templates');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.6.45', '代理店システムSSOハブ機能');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.6.46', '複数外部サイトSSO連携設定');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.6.48', 'SSO連携先ステータス操作改善');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.6.49', 'SSO起動時のリダイレクト出力制御修正');

INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.6.51', 'multiple external partner API destinations');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.6.56', 'ログイン記録閲覧機能');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.6.57', '代理店活動状況レポート');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.6.58', '代理店向け配下活動レポート');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.6.59', '共通ID連携基盤');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.6.60', '共通ID v2 API');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.6.61', '紹介トークン v2 API');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.6.62', 'LP・問い合わせ紹介トークン連携');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.6.63', '外部連携イベント送信強化');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.6.64', '外部連携ログ閲覧・再送機能');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.6.65', 'common id lookup page');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.6.66', 'common id correction operations and audit logs');

INSERT IGNORE INTO projects (id, slug, name, description, status, sort_order) VALUES
(1, 'sengoku-influencer', '戦国インフルエンサー', '既存の戦国インフルエンサー告知プロジェクト', 'active', 1);

INSERT IGNORE INTO projects (id, slug, name, description, status, sort_order) VALUES
(2, 'ai-art-school', 'AIアート教室', 'AIアート教室・無料体験向けLPプロジェクト', 'active', 20);

INSERT IGNORE INTO system_settings (key_name, value) VALUES
('label_level3', 'エージェント'),
('label_level2', 'ディレクター'),
('label_level1', 'アドバイザー'),
('label_position_advisor', 'アドバイザー'),
('label_position_super_advisor', 'スーパーアドバイザー'),
('label_position_influencer', 'インフルエンサー'),
('admin_email', ''),
('site_url', ''),
('external_api_token', ''),
('external_partner_sync_enabled', '0'),
('external_partner_base_url', ''),
('external_partner_api_key', ''),
('common_id_enabled', '0'),
('common_hub_enabled', '0'),
('common_hub_read_enabled', '0'),
('common_hub_write_enabled', '0'),
('referral_v2_enabled', '0'),
('external_registration_capture_enabled', '0'),
('referral_token_api_enabled', '0'),
('passport_integration_enabled', '0'),
('shopping_integration_enabled', '0'),
('wallet_integration_enabled', '0'),
('ai_art_integration_enabled', '0'),
('sso_rr_enabled', '0'),
('sso_rr_callback_url', ''),
('sso_rr_audience', 'sengoku-rr'),
('sso_issuer', ''),
('sso_key_id', ''),
('sso_private_key', ''),
('sso_public_key', ''),
('resend_api_key', ''),
('mail_from', ''),
('mail_from_name', '戦国経済圏'),
('mail_tpl_application_subject', '【戦国経済圏】新規エージェント申請が届きました'),
('mail_tpl_application_body', '新しい申請が届きました。管理画面からご確認ください。'),
('mail_tpl_approval_subject', '【戦国経済圏】承認されました'),
('mail_tpl_approval_body', '{person_name} 様  承認されました。以下のURLからパスワードを設定して、マイページにアクセスしてください。 {setup_url}  LP URL：{lp_url} マイページ：{mypage_url}'),
('mail_tpl_rejection_subject', '【戦国経済圏】申請について'),
('mail_tpl_rejection_body', '{person_name} 様  申請を確認しましたが、今回は承認を見送らせていただきました。'),
('mail_tpl_promo_request_subject', '【戦国経済圏】昇格申請が届きました'),
('mail_tpl_promo_request_body', '{person_name}（{agent_code}）から昇格申請が届きました。マイページから確認・承認してください。{mypage_url}');



-- 評議員NFTテンプレート追加（元LP index.html ベース）

-- LPテンプレート初期データ（実ファイルに対応）
INSERT IGNORE INTO lp_templates (slug, name, description, html_file, sort_order, status) VALUES
('samurai',    '戦国プレミアム',           '武将テイストのプレミアムLP',       'samurai.php',    1, 'active'),
('evaluator',  '評議員NFT',                '評議員NFT向けLP',                  'evaluator.php',  2, 'active'),
('oshi',       '推し武将LP',               '推し活テイストのLP',               'oshi.php',       3, 'active'),
('sengoku',    '戦国経済圏メイン',         '戦国経済圏メインLP',               'sengoku.php',    4, 'active'),
('influencer', '戦国インフルエンサーNFT',  'インフルエンサー向けNFT LP',       'influencer.php', 5, 'active'),
('nft',        '戦国NFT',                  '戦国経済圏 評議員NFT LP',          'nft.php',        6, 'active');

UPDATE lp_templates SET project_id = 1 WHERE project_id IS NULL;

INSERT IGNORE INTO lp_templates (project_id, slug, name, description, html_file, sort_order, status) VALUES
((SELECT id FROM projects WHERE slug='ai-art-school' LIMIT 1), 'ai-art-illustration-pop', 'AIアート教室 イラストPOP', 'イラスト・ポップ訴求のAIアートLP', 'ai-art-illustration-pop.php', 20, 'active'),
((SELECT id FROM projects WHERE slug='ai-art-school' LIMIT 1), 'ai-art-young-sns', 'AIアート教室 若年層SNS', '若年層・SNS訴求のAIアートLP', 'ai-art-young-sns.php', 21, 'active'),
((SELECT id FROM projects WHERE slug='ai-art-school' LIMIT 1), 'ai-art-senior-salon', 'AIアート教室 シニアサロン', 'シニア・サロン訴求のAIアートLP', 'ai-art-senior-salon.php', 22, 'active'),
((SELECT id FROM projects WHERE slug='ai-art-school' LIMIT 1), 'ai-art-lp-illustration', 'AIアート教室 イラスト', 'イラスト訴求のAIアートLP', 'ai-art-lp-illustration.php', 23, 'active'),
((SELECT id FROM projects WHERE slug='ai-art-school' LIMIT 1), 'ai-art-lp-older', 'AIアート教室 大人向け', '大人・シニア向けAIアートLP', 'ai-art-lp-older.php', 24, 'active'),
((SELECT id FROM projects WHERE slug='ai-art-school' LIMIT 1), 'ai-art-lp-young', 'AIアート教室 若年層向け', '若年層向けAIアートLP', 'ai-art-lp-young.php', 25, 'active');
INSERT IGNORE INTO sso_clients (client_key, name, audience, callback_url, status, sort_order) VALUES
('sengoku-rr', 'sengoku-rr.com', 'sengoku-rr', 'https://sengoku-rr.com/agency/sso', 'active', 10);
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.6.67', 'common id lookup server error rescue');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.6.68', 'external integration retry screen hardening');
INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.6.69', 'external integration partner status and batch retry');

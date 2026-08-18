CREATE TABLE IF NOT EXISTS customer_entitlements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    common_user_id VARCHAR(64) NOT NULL,
    system_key VARCHAR(100) NOT NULL,
    external_user_id VARCHAR(191) NULL,
    project_key VARCHAR(100) NULL,
    project_id INT NULL,
    product_code VARCHAR(191) NOT NULL DEFAULT '',
    order_id VARCHAR(191) NOT NULL DEFAULT '',
    order_item_id VARCHAR(191) NOT NULL DEFAULT 'default',
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    starts_at DATETIME NULL,
    expires_at DATETIME NULL,
    source_event VARCHAR(100) NULL,
    metadata_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_customer_entitlement (common_user_id, system_key, product_code, order_id, order_item_id),
    KEY idx_common_user_id (common_user_id),
    KEY idx_system_key (system_key),
    KEY idx_project_key (project_key),
    KEY idx_product_code (product_code),
    KEY idx_status (status),
    KEY idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO schema_migrations (version, description)
VALUES ('3.6.150', 'customer entitlements and customer SSO');

ALTER TABLE external_product_rules
    ADD COLUMN IF NOT EXISTS target_service VARCHAR(100) DEFAULT NULL AFTER target_service_key,
    ADD COLUMN IF NOT EXISTS description TEXT DEFAULT NULL AFTER notes;

UPDATE external_product_rules
SET target_service = target_service_key
WHERE (target_service IS NULL OR target_service = '')
  AND target_service_key IS NOT NULL
  AND target_service_key <> '';

UPDATE external_product_rules
SET description = notes
WHERE (description IS NULL OR description = '')
  AND notes IS NOT NULL
  AND notes <> '';

CREATE TABLE IF NOT EXISTS reward_import_batches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    file_name VARCHAR(255) NOT NULL,
    file_hash CHAR(64) NOT NULL,
    total_rows INT NOT NULL DEFAULT 0,
    valid_rows INT NOT NULL DEFAULT 0,
    error_rows INT NOT NULL DEFAULT 0,
    imported_by_admin_id INT DEFAULT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'imported',
    imported_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_reward_import_file_hash (file_hash),
    INDEX idx_reward_import_imported_at (imported_at),
    INDEX idx_reward_import_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS agent_reward_ledger (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    import_batch_id INT NOT NULL,
    external_reward_key VARCHAR(191) NOT NULL,
    agent_id INT NOT NULL,
    agent_code VARCHAR(100) NOT NULL,
    source_system_key VARCHAR(100) DEFAULT NULL,
    project_key VARCHAR(100) DEFAULT NULL,
    product_code VARCHAR(191) DEFAULT NULL,
    order_id VARCHAR(191) DEFAULT NULL,
    order_item_id VARCHAR(191) DEFAULT NULL,
    common_user_id VARCHAR(100) DEFAULT NULL,
    amount_minor BIGINT NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'JPY',
    status VARCHAR(50) NOT NULL DEFAULT 'confirmed',
    occurred_at DATETIME DEFAULT NULL,
    description TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_agent_reward_external_key (external_reward_key),
    INDEX idx_agent_reward_batch (import_batch_id),
    INDEX idx_agent_reward_agent (agent_id, status),
    INDEX idx_agent_reward_source (source_system_key, product_code),
    INDEX idx_agent_reward_occurred (occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO schema_migrations (version, description)
VALUES ('3.6.165', '報酬CSV検証・プレビュー・二重取込防止・履歴');

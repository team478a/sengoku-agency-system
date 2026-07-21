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

ALTER TABLE applicants ADD COLUMN IF NOT EXISTS recruitment_link_id INT DEFAULT NULL;
ALTER TABLE applicants ADD COLUMN IF NOT EXISTS recruitment_source VARCHAR(255) DEFAULT NULL;

UPDATE agents
SET position_type = 'advisor'
WHERE level = 1
  AND (position_type IS NULL OR position_type = '');

UPDATE applicants
SET position_type = 'advisor'
WHERE target_level = 1
  AND (position_type IS NULL OR position_type = '');

UPDATE recruitment_links
SET position_type = 'advisor'
WHERE target_level = 1
  AND (position_type IS NULL OR position_type = '');

UPDATE agents
SET position_label = CASE position_type
    WHEN 'super_advisor' THEN COALESCE(NULLIF((SELECT value FROM system_settings WHERE key_name = 'label_position_super_advisor' LIMIT 1), ''), 'スーパーアドバイザー')
    WHEN 'influencer' THEN COALESCE(NULLIF((SELECT value FROM system_settings WHERE key_name = 'label_position_influencer' LIMIT 1), ''), 'インフルエンサー')
    ELSE COALESCE(NULLIF((SELECT value FROM system_settings WHERE key_name = 'label_position_advisor' LIMIT 1), ''), 'アドバイザー')
END
WHERE level = 1
  AND (position_label IS NULL OR position_label = '');

UPDATE applicants
SET position_label = CASE position_type
    WHEN 'super_advisor' THEN COALESCE(NULLIF((SELECT value FROM system_settings WHERE key_name = 'label_position_super_advisor' LIMIT 1), ''), 'スーパーアドバイザー')
    WHEN 'influencer' THEN COALESCE(NULLIF((SELECT value FROM system_settings WHERE key_name = 'label_position_influencer' LIMIT 1), ''), 'インフルエンサー')
    ELSE COALESCE(NULLIF((SELECT value FROM system_settings WHERE key_name = 'label_position_advisor' LIMIT 1), ''), 'アドバイザー')
END
WHERE target_level = 1
  AND (position_label IS NULL OR position_label = '');

UPDATE recruitment_links
SET position_label = CASE position_type
    WHEN 'super_advisor' THEN COALESCE(NULLIF((SELECT value FROM system_settings WHERE key_name = 'label_position_super_advisor' LIMIT 1), ''), 'スーパーアドバイザー')
    WHEN 'influencer' THEN COALESCE(NULLIF((SELECT value FROM system_settings WHERE key_name = 'label_position_influencer' LIMIT 1), ''), 'インフルエンサー')
    ELSE COALESCE(NULLIF((SELECT value FROM system_settings WHERE key_name = 'label_position_advisor' LIMIT 1), ''), 'アドバイザー')
END
WHERE target_level = 1
  AND (position_label IS NULL OR position_label = '');

INSERT IGNORE INTO schema_migrations (version, description)
VALUES ('3.6.1', '既存アドバイザー種別補完');

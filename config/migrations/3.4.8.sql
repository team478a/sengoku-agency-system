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

INSERT IGNORE INTO schema_migrations (version, description)
VALUES ('3.4.8', '募集URL管理');

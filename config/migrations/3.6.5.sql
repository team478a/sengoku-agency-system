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

INSERT IGNORE INTO schema_migrations (version, description)
VALUES ('3.6.5', 'フォロー管理テーブル補修と権限別マイページ表示');

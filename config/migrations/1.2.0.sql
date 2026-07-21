-- v1.2.0: 代理店申請テーブル追加
CREATE TABLE IF NOT EXISTS applicants (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    company_name    VARCHAR(255) NOT NULL,
    person_name     VARCHAR(255) NOT NULL,
    email           VARCHAR(255) NOT NULL,
    phone           VARCHAR(100),
    line_url        TEXT,
    message         TEXT,
    status          ENUM('pending','approved','rejected') DEFAULT 'pending',
    agent_id        INT DEFAULT NULL,          -- 承認後に紐づくagents.id
    reviewed_at     DATETIME DEFAULT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO schema_migrations (version, description)
VALUES ('1.2.0', '代理店申請テーブル追加');

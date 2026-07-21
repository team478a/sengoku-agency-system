-- 3.1.0: safer role changes, duplicate email checks, all-advisor view, and admin action logs.

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

INSERT IGNORE INTO schema_migrations (version, description)
VALUES ('3.1.0', '権限変更の安全化・通知・全配下アドバイザー一覧・管理者操作ログ');

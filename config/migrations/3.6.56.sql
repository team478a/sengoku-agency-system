ALTER TABLE login_logs ADD INDEX IF NOT EXISTS idx_login_logs_created (created_at);
ALTER TABLE login_logs ADD INDEX IF NOT EXISTS idx_login_logs_type_success (user_type, success, created_at);
ALTER TABLE login_logs ADD INDEX IF NOT EXISTS idx_login_logs_email (email);

INSERT IGNORE INTO schema_migrations (version, description)
VALUES ('3.6.56', 'ログイン記録閲覧機能');

ALTER TABLE access_logs ADD COLUMN IF NOT EXISTS template_id INT DEFAULT NULL;
ALTER TABLE leads ADD COLUMN IF NOT EXISTS template_id INT DEFAULT NULL;

INSERT IGNORE INTO schema_migrations (version, description)
VALUES ('3.5.2', 'LPテンプレート成果計測');

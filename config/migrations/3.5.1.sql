ALTER TABLE leads MODIFY COLUMN status ENUM('new','contacted','prospect','won','lost','closed') DEFAULT 'new';
ALTER TABLE leads ADD COLUMN IF NOT EXISTS internal_note TEXT DEFAULT NULL;
ALTER TABLE leads ADD COLUMN IF NOT EXISTS next_action_at DATE DEFAULT NULL;

INSERT IGNORE INTO schema_migrations (version, description)
VALUES ('3.5.1', '問い合わせ商談管理強化');

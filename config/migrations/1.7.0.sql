-- v1.7.0: エージェント階層化
ALTER TABLE agents ADD COLUMN level     TINYINT(1) DEFAULT 1 AFTER agent_code;
ALTER TABLE agents ADD COLUMN parent_id INT DEFAULT NULL AFTER level;
ALTER TABLE agents ADD INDEX idx_parent (parent_id);

INSERT IGNORE INTO system_settings (key_name, value) VALUES
('label_level3', 'エージェント'),
('label_level2', 'ディレクター'),
('label_level1', 'アドバイザー');

INSERT IGNORE INTO schema_migrations (version, description)
VALUES ('1.7.0', 'エージェント階層化（level/parent_idカラム追加）');

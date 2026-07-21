-- v2.3.1: エージェント専用申請フォーム

-- エージェント専用申請トークン
ALTER TABLE agents ADD COLUMN apply_token VARCHAR(64) DEFAULT NULL;
ALTER TABLE agents ADD COLUMN apply_token_exp DATETIME DEFAULT NULL;

-- applicantsにagent_id（紹介元エージェント）を追加
ALTER TABLE applicants ADD COLUMN agent_id INT DEFAULT NULL;
ALTER TABLE applicants ADD COLUMN target_level TINYINT(1) DEFAULT 1;

INSERT IGNORE INTO schema_migrations (version, description)
VALUES ('2.3.1', 'エージェント専用申請フォーム対応');

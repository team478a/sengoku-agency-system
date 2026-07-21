-- 2.9.0: Align applications with headquarters -> agent entry flow.
ALTER TABLE applicants ADD COLUMN target_level TINYINT(1) DEFAULT 3;
ALTER TABLE agents ADD COLUMN apply_token_director VARCHAR(64) DEFAULT NULL;
ALTER TABLE agents ADD COLUMN apply_token_director_exp DATETIME DEFAULT NULL;
ALTER TABLE agents ADD COLUMN apply_token_advisor VARCHAR(64) DEFAULT NULL;
ALTER TABLE agents ADD COLUMN apply_token_advisor_exp DATETIME DEFAULT NULL;

-- Direct headquarters applications should be agent applications.
UPDATE applicants
SET target_level = 3
WHERE agent_id IS NULL
  AND status = 'pending'
  AND (target_level IS NULL OR target_level = 1);

INSERT IGNORE INTO schema_migrations (version, description)
VALUES ('2.9.0', '本部直轄申請をエージェント申請に整理');

-- 3.4.5 Advisor position labels

ALTER TABLE applicants ADD COLUMN IF NOT EXISTS position_type VARCHAR(50) DEFAULT NULL;
ALTER TABLE applicants ADD COLUMN IF NOT EXISTS position_label VARCHAR(100) DEFAULT NULL;

ALTER TABLE agents ADD COLUMN IF NOT EXISTS position_type VARCHAR(50) DEFAULT NULL;
ALTER TABLE agents ADD COLUMN IF NOT EXISTS position_label VARCHAR(100) DEFAULT NULL;

UPDATE applicants
SET position_type = 'advisor', position_label = '通常アドバイザー'
WHERE target_level = 1
  AND (position_type IS NULL OR position_type = '');

UPDATE agents
SET position_type = 'advisor', position_label = '通常アドバイザー'
WHERE level = 1
  AND (position_type IS NULL OR position_type = '');

INSERT IGNORE INTO schema_migrations (version, description)
VALUES ('3.4.5', 'アドバイザー募集区分管理');

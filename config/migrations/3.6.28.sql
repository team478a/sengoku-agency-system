ALTER TABLE agents ADD COLUMN IF NOT EXISTS external_id VARCHAR(191) DEFAULT NULL AFTER id;
ALTER TABLE agents ADD COLUMN IF NOT EXISTS default_commission_rate DECIMAL(5,2) DEFAULT NULL AFTER parent_id;
ALTER TABLE agents ADD UNIQUE KEY idx_agents_external_id (external_id);

INSERT IGNORE INTO schema_migrations (version, description)
VALUES ('3.6.28', 'External agency integration API');

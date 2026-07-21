ALTER TABLE external_partner_sites ADD COLUMN IF NOT EXISTS inbound_api_key TEXT NULL AFTER api_key;

UPDATE external_partner_sites
SET inbound_api_key = CONCAT('sai_', SHA2(CONCAT(UUID(), '-', id, '-', RAND()), 256))
WHERE COALESCE(inbound_api_key, '') = '';

INSERT IGNORE INTO schema_migrations (version) VALUES ('3.6.55');

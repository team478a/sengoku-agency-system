ALTER TABLE agents ADD COLUMN IF NOT EXISTS login_email VARCHAR(255) DEFAULT NULL AFTER email;

INSERT IGNORE INTO schema_migrations (version, description)
VALUES ('3.6.30', 'Separate contact email and login email for agency integration API');

INSERT INTO system_settings (key_name, value) VALUES
('integration_log_masking_enabled', '1')
ON DUPLICATE KEY UPDATE key_name=key_name;

INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.6.79', 'Harden update screen, integration log masking, and transactional common hub writes');

INSERT IGNORE INTO system_settings (key_name, value) VALUES
('external_partner_sync_enabled', '0'),
('external_partner_base_url', ''),
('external_partner_api_key', '');

INSERT IGNORE INTO schema_migrations (version, description) VALUES
('3.6.38', 'bidirectional external agency sync settings');

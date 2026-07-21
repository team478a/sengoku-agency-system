INSERT IGNORE INTO system_settings (key_name, value) VALUES
('sso_rr_enabled', '0'),
('sso_rr_callback_url', ''),
('sso_rr_audience', 'sengoku-rr'),
('sso_issuer', ''),
('sso_key_id', ''),
('sso_private_key', ''),
('sso_public_key', '');

INSERT IGNORE INTO schema_migrations (version, description)
VALUES ('3.6.45', '代理店システムSSOハブ機能');

INSERT IGNORE INTO system_settings (key_name, value)
VALUES ('external_api_token', '');

INSERT IGNORE INTO schema_migrations (version, description)
VALUES ('3.6.27', 'External hierarchy API token setting');

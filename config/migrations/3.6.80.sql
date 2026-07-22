-- v3.6.80: Outbox retry / DLQ operation settings.

INSERT INTO system_settings (key_name, value)
VALUES
    ('external_partner_outbox_retry_enabled', '1'),
    ('external_partner_outbox_default_max_attempts', '8')
ON DUPLICATE KEY UPDATE
    value = VALUES(value);

INSERT IGNORE INTO schema_migrations (version) VALUES ('3.6.80');

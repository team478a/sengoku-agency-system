-- v3.6.80: Outbox retry / DLQ operation settings.

INSERT INTO system_settings (key_name, value, description)
VALUES
    ('external_partner_outbox_retry_enabled', '1', '外部連携Outboxの自動再送を有効にする'),
    ('external_partner_outbox_default_max_attempts', '8', '外部連携Outboxの標準最大再送回数')
ON DUPLICATE KEY UPDATE
    description = VALUES(description);

INSERT IGNORE INTO schema_migrations (version) VALUES ('3.6.80');

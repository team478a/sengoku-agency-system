-- v3.6.81: External integration outbox worker claim / stale recovery.

ALTER TABLE integration_outbox_events
    ADD COLUMN IF NOT EXISTS claim_token VARCHAR(100) DEFAULT NULL AFTER status,
    ADD COLUMN IF NOT EXISTS claimed_at DATETIME DEFAULT NULL AFTER claim_token,
    ADD COLUMN IF NOT EXISTS claim_expires_at DATETIME DEFAULT NULL AFTER claimed_at,
    ADD COLUMN IF NOT EXISTS worker_id VARCHAR(100) DEFAULT NULL AFTER claim_expires_at;

CREATE INDEX IF NOT EXISTS idx_outbox_claim_status ON integration_outbox_events (status, claim_expires_at, next_attempt_at);

INSERT INTO system_settings (key_name, value, description)
VALUES
    ('external_partner_outbox_claim_timeout_seconds', '300', 'External integration outbox worker claim timeout seconds'),
    ('external_integration_retry_allow_query_token', '1', 'Allow cron token in query string for backward compatibility'),
    ('external_integration_retry_header_name', 'X-SenNoKuni-Cron-Token', 'Header name for external integration retry cron token')
ON DUPLICATE KEY UPDATE
    description = VALUES(description);

INSERT IGNORE INTO schema_migrations (version) VALUES ('3.6.81');

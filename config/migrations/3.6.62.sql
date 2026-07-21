ALTER TABLE leads
    ADD COLUMN IF NOT EXISTS common_user_id VARCHAR(64) DEFAULT NULL AFTER project_id,
    ADD COLUMN IF NOT EXISTS referral_token_id INT DEFAULT NULL AFTER common_user_id,
    ADD COLUMN IF NOT EXISTS referral_session_key VARCHAR(80) DEFAULT NULL AFTER referral_token_id,
    ADD COLUMN IF NOT EXISTS referral_source VARCHAR(255) DEFAULT NULL AFTER referral_session_key,
    ADD INDEX IF NOT EXISTS idx_leads_common_user (common_user_id),
    ADD INDEX IF NOT EXISTS idx_leads_referral_token (referral_token_id),
    ADD INDEX IF NOT EXISTS idx_leads_referral_session (referral_session_key);

ALTER TABLE access_logs
    ADD COLUMN IF NOT EXISTS referral_token_id INT DEFAULT NULL AFTER project_id,
    ADD COLUMN IF NOT EXISTS referral_session_key VARCHAR(80) DEFAULT NULL AFTER referral_token_id,
    ADD INDEX IF NOT EXISTS idx_access_referral_token (referral_token_id),
    ADD INDEX IF NOT EXISTS idx_access_referral_session (referral_session_key);

INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.6.62', 'LP・問い合わせ紹介トークン連携');

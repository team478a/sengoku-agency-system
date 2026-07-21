ALTER TABLE agents ADD COLUMN IF NOT EXISTS influencer_enabled TINYINT(1) DEFAULT 0 AFTER profile_text;
ALTER TABLE agents ADD COLUMN IF NOT EXISTS influencer_name VARCHAR(255) DEFAULT NULL AFTER influencer_enabled;
ALTER TABLE agents ADD COLUMN IF NOT EXISTS metamask_wallet_address VARCHAR(255) DEFAULT NULL AFTER influencer_name;
ALTER TABLE agents ADD COLUMN IF NOT EXISTS influencer_profile_text TEXT DEFAULT NULL AFTER metamask_wallet_address;
ALTER TABLE agents ADD COLUMN IF NOT EXISTS instagram_url TEXT DEFAULT NULL AFTER influencer_profile_text;
ALTER TABLE agents ADD COLUMN IF NOT EXISTS x_url TEXT DEFAULT NULL AFTER instagram_url;
ALTER TABLE agents ADD COLUMN IF NOT EXISTS tiktok_url TEXT DEFAULT NULL AFTER x_url;
ALTER TABLE agents ADD COLUMN IF NOT EXISTS youtube_url TEXT DEFAULT NULL AFTER tiktok_url;

INSERT IGNORE INTO schema_migrations (version, description)
VALUES ('3.6.13', 'インフルエンサープロフィール設定');

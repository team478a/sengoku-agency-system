ALTER TABLE agents ADD COLUMN IF NOT EXISTS bank_name VARCHAR(100) DEFAULT NULL AFTER influencer_profile_text;
ALTER TABLE agents ADD COLUMN IF NOT EXISTS bank_branch_name VARCHAR(100) DEFAULT NULL AFTER bank_name;
ALTER TABLE agents ADD COLUMN IF NOT EXISTS bank_branch_code VARCHAR(10) DEFAULT NULL AFTER bank_branch_name;
ALTER TABLE agents ADD COLUMN IF NOT EXISTS bank_account_type VARCHAR(20) DEFAULT NULL AFTER bank_branch_code;
ALTER TABLE agents ADD COLUMN IF NOT EXISTS bank_account_number VARCHAR(30) DEFAULT NULL AFTER bank_account_type;
ALTER TABLE agents ADD COLUMN IF NOT EXISTS bank_account_holder VARCHAR(100) DEFAULT NULL AFTER bank_account_number;
ALTER TABLE agents ADD COLUMN IF NOT EXISTS bank_account_holder_kana VARCHAR(100) DEFAULT NULL AFTER bank_account_holder;

INSERT IGNORE INTO schema_migrations (version, description)
VALUES ('3.6.123', 'プロフィール振込先銀行情報');

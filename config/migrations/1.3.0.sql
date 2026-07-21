-- v1.3.0: 代理店認証カラム追加
ALTER TABLE agents ADD COLUMN password        VARCHAR(255) DEFAULT NULL AFTER slack_webhook;
ALTER TABLE agents ADD COLUMN setup_token     VARCHAR(64)  DEFAULT NULL AFTER password;
ALTER TABLE agents ADD COLUMN setup_token_exp DATETIME     DEFAULT NULL AFTER setup_token;

INSERT IGNORE INTO schema_migrations (version, description)
VALUES ('1.3.0', '代理店認証カラム追加');

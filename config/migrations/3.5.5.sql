INSERT IGNORE INTO system_settings (key_name, value) VALUES
('label_position_advisor', 'アドバイザー'),
('label_position_super_advisor', 'スーパーアドバイザー'),
('label_position_influencer', 'インフルエンサー');

INSERT IGNORE INTO schema_migrations (version, description)
VALUES ('3.5.5', 'アドバイザー区分名称設定');

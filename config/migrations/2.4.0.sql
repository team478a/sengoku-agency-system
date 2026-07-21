-- v2.4.0: 階層名称を本部→エージェント→ディレクター→アドバイザーへ整理

INSERT INTO system_settings (key_name, value) VALUES
('label_level3', 'エージェント'),
('label_level2', 'ディレクター'),
('label_level1', 'アドバイザー')
ON DUPLICATE KEY UPDATE
value = CASE
    WHEN key_name = 'label_level1' AND (value = '' OR value = '代理店') THEN 'アドバイザー'
    WHEN key_name = 'label_level2' AND (value = '' OR value = 'エージェント') THEN 'ディレクター'
    WHEN key_name = 'label_level3' AND value = '' THEN 'エージェント'
    ELSE value
END;

INSERT IGNORE INTO schema_migrations (version, description)
VALUES ('2.4.0', '階層名称をエージェント・ディレクター・アドバイザーへ整理');

UPDATE system_settings
SET value = REPLACE(value, '新規代理店申請', '新規アドバイザー申請')
WHERE key_name IN ('mail_tpl_application_subject', 'mail_tpl_application_body');

UPDATE system_settings
SET value = REPLACE(value, '代理店として承認', 'アドバイザーとして承認')
WHERE key_name IN ('mail_tpl_approval_subject', 'mail_tpl_approval_body');

UPDATE system_settings
SET value = REPLACE(value, '代理店コード', 'アドバイザーコード')
WHERE key_name = 'mail_tpl_approval_body';

UPDATE system_settings
SET value = REPLACE(value, '代理店申請', 'アドバイザー申請')
WHERE key_name IN ('mail_tpl_rejection_subject', 'mail_tpl_rejection_body');

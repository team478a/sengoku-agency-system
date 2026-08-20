UPDATE system_settings
SET value = '千ノ国代理店システム'
WHERE key_name = 'mail_from_name'
  AND value IN ('戦国経済圏', '戦国代理店', '戦国代理店システム', '');

UPDATE system_settings
SET value = REPLACE(value, '【戦国経済圏】', '【千ノ国代理店システム】')
WHERE key_name LIKE 'mail_tpl_%'
  AND value LIKE '%【戦国経済圏】%';

UPDATE system_settings
SET value = REPLACE(value, '戦国経済圏 運営事務局', '千ノ国代理店システム 運営事務局')
WHERE key_name LIKE 'mail_tpl_%'
  AND value LIKE '%戦国経済圏 運営事務局%';

INSERT IGNORE INTO schema_migrations (version, description)
VALUES ('3.6.155', 'visible brand rename to Sen no Kuni agency system');

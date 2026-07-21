-- 3.4.2: Migration comment parser fix.

INSERT IGNORE INTO schema_migrations (version, description)
VALUES ('3.4.2', 'マイグレーションコメント処理修正');

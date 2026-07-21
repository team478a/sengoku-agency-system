-- 3.4.1: Recruitment URL generation fallback.
-- No schema changes are required here.
-- Dashboard auto-checks missing token columns.

INSERT IGNORE INTO schema_migrations (version, description)
VALUES ('3.4.1', '募集URL生成時の白画面対策');

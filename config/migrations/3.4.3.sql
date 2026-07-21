-- 3.4.3: Recruitment URL legacy column fallback.

INSERT IGNORE INTO schema_migrations (version, description)
VALUES ('3.4.3', '募集URL生成の旧カラム互換対応');

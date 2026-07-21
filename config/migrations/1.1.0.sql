-- v1.1.0: LP導線設定カラム追加
ALTER TABLE agents ADD COLUMN show_form     TINYINT(1) DEFAULT 1 AFTER line_url;
ALTER TABLE agents ADD COLUMN show_line_btn TINYINT(1) DEFAULT 1 AFTER show_form;

INSERT IGNORE INTO schema_migrations (version, description)
VALUES ('1.1.0', 'LP導線設定カラム追加');

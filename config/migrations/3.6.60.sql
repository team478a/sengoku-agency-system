ALTER TABLE agency_customer_relations MODIFY project_id INT NOT NULL DEFAULT 0;
ALTER TABLE agency_customer_relations DROP INDEX uniq_common_relation_type;
ALTER TABLE agency_customer_relations ADD UNIQUE KEY uniq_common_relation_project (common_user_id, relation_type, project_id);

INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.6.60', '共通ID v2 API');

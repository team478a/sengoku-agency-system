CREATE TABLE IF NOT EXISTS agent_project_templates (
    agent_id INT NOT NULL,
    project_id INT NOT NULL,
    template_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (agent_id, project_id),
    INDEX idx_agent_project_template_project (project_id),
    INDEX idx_agent_project_template_template (template_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO agent_project_templates (agent_id, project_id, template_id)
SELECT a.id, t.project_id, a.default_template_id
FROM agents a
INNER JOIN lp_templates t ON a.default_template_id = t.id
WHERE a.default_template_id IS NOT NULL
  AND t.project_id IS NOT NULL;

INSERT IGNORE INTO schema_migrations (version, description)
VALUES ('3.6.19', 'Project-specific LP URLs and templates');

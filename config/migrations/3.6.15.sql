CREATE TABLE IF NOT EXISTS lp_template_fields (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    field_key VARCHAR(100) NOT NULL,
    field_type ENUM('text','textarea','image') DEFAULT 'text',
    label VARCHAR(255) NOT NULL,
    value_text MEDIUMTEXT DEFAULT NULL,
    value_file TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_template_field (template_id, field_key),
    INDEX idx_template_fields_template (template_id),
    FOREIGN KEY (template_id) REFERENCES lp_templates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO schema_migrations (version, description)
VALUES ('3.6.15', 'LP編集項目管理');

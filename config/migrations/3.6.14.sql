CREATE TABLE IF NOT EXISTS projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    status ENUM('active','inactive') DEFAULT 'active',
    sort_order INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO projects (id, slug, name, description, status, sort_order)
VALUES (1, 'sengoku-influencer', '戦国インフルエンサー', '既存の戦国インフルエンサー告知プロジェクト', 'active', 1);

ALTER TABLE lp_templates ADD COLUMN IF NOT EXISTS project_id INT DEFAULT NULL AFTER id;
ALTER TABLE materials ADD COLUMN IF NOT EXISTS project_id INT DEFAULT NULL AFTER id;
ALTER TABLE leads ADD COLUMN IF NOT EXISTS project_id INT DEFAULT NULL AFTER id;
ALTER TABLE access_logs ADD COLUMN IF NOT EXISTS project_id INT DEFAULT NULL AFTER id;

UPDATE lp_templates SET project_id = 1 WHERE project_id IS NULL;
UPDATE materials SET project_id = 1 WHERE project_id IS NULL;
UPDATE leads l
LEFT JOIN lp_templates t ON l.template_id = t.id
SET l.project_id = COALESCE(t.project_id, 1)
WHERE l.project_id IS NULL;
UPDATE access_logs al
LEFT JOIN lp_templates t ON al.template_id = t.id
SET al.project_id = COALESCE(t.project_id, 1)
WHERE al.project_id IS NULL;

INSERT IGNORE INTO schema_migrations (version, description)
VALUES ('3.6.14', 'プロジェクト別LP・素材・問い合わせ管理');

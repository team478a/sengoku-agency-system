-- Product and reward eligibility rules.

CREATE TABLE IF NOT EXISTS external_product_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source_system_key VARCHAR(100) NOT NULL,
    product_code VARCHAR(191) NOT NULL,
    display_name VARCHAR(255) NOT NULL,
    project_key VARCHAR(100) DEFAULT NULL,
    project_id INT DEFAULT NULL,
    validity_days INT DEFAULT NULL,
    reward_eligibility VARCHAR(50) NOT NULL DEFAULT 'UNKNOWN',
    entitlement_type VARCHAR(100) DEFAULT NULL,
    target_service_key VARCHAR(100) DEFAULT NULL,
    refund_policy VARCHAR(50) NOT NULL DEFAULT 'manual_review',
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    notes TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_external_product_rule (source_system_key, product_code),
    INDEX idx_external_product_rules_project (project_key),
    INDEX idx_external_product_rules_status (status, reward_eligibility)
);

ALTER TABLE integration_inbox_events
    ADD COLUMN IF NOT EXISTS product_rule_id INT DEFAULT NULL AFTER product_code,
    ADD COLUMN IF NOT EXISTS eligibility_reason VARCHAR(255) DEFAULT NULL AFTER eligibility_status,
    ADD INDEX IF NOT EXISTS idx_inbox_product_rule (product_rule_id);

INSERT INTO external_product_rules
    (source_system_key, product_code, display_name, project_key, project_id, validity_days,
     reward_eligibility, entitlement_type, target_service_key, refund_policy, status, notes)
VALUES
    ('AI_ART_SCHOOL', 'ai-art-course', 'AIアート教室 受講料金', 'ai-art-school',
     (SELECT id FROM projects WHERE slug='ai-art-school' LIMIT 1), NULL,
     'NOT_ELIGIBLE', 'course_access', 'AI_ART_SCHOOL', 'manual_review', 'active',
     'AIアート教室の受講料金は代理店報酬対象外です。')
ON DUPLICATE KEY UPDATE
    display_name=VALUES(display_name),
    project_key=VALUES(project_key),
    project_id=VALUES(project_id),
    reward_eligibility=VALUES(reward_eligibility),
    entitlement_type=VALUES(entitlement_type),
    target_service_key=VALUES(target_service_key),
    refund_policy=VALUES(refund_policy),
    status=VALUES(status),
    notes=VALUES(notes);

INSERT IGNORE INTO schema_migrations (version) VALUES ('3.6.164');

-- v1.8.0: 3階層化（本部→エージェント→ディレクター→アドバイザー）

-- 昇格申請テーブル
CREATE TABLE IF NOT EXISTS promotion_requests (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    applicant_id INT NOT NULL,
    approver_id  INT NOT NULL,
    status       ENUM('pending','agent_approved','approved','rejected') DEFAULT 'pending',
    message      TEXT,
    agent_comment TEXT DEFAULT NULL,
    agent_reviewed_at DATETIME DEFAULT NULL,
    reviewed_at  DATETIME DEFAULT NULL,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (applicant_id) REFERENCES agents(id) ON DELETE CASCADE,
    FOREIGN KEY (approver_id)  REFERENCES agents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 階層名称
INSERT IGNORE INTO system_settings (key_name, value) VALUES ('label_level3', 'エージェント');
INSERT IGNORE INTO system_settings (key_name, value) VALUES ('label_level2', 'ディレクター');
INSERT IGNORE INTO system_settings (key_name, value) VALUES ('label_level1', 'アドバイザー');

-- 昇格申請通知メールテンプレート
INSERT IGNORE INTO system_settings (key_name, value) VALUES ('mail_tpl_promo_request_subject', '【戦国経済圏】昇格申請が届きました');
INSERT IGNORE INTO system_settings (key_name, value) VALUES ('mail_tpl_promo_request_body', '{person_name}（{agent_code}）から昇格申請が届きました。マイページから確認・承認してください。{mypage_url}');

INSERT IGNORE INTO schema_migrations (version, description) VALUES ('1.8.0', '3階層化・昇格申請テーブル追加');

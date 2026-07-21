-- v2.1.1: 昇格申請2段階承認対応
ALTER TABLE promotion_requests
  MODIFY COLUMN status ENUM('pending','agent_approved','approved','rejected') DEFAULT 'pending';

ALTER TABLE promotion_requests ADD COLUMN agent_comment    TEXT     DEFAULT NULL AFTER message;
ALTER TABLE promotion_requests ADD COLUMN agent_reviewed_at DATETIME DEFAULT NULL AFTER agent_comment;

INSERT IGNORE INTO schema_migrations (version, description)
VALUES ('2.1.1', '昇格申請2段階承認対応');

-- target_levelカラム追加（アドバイザー→ディレクター昇格対応）
ALTER TABLE promotion_requests ADD COLUMN target_level TINYINT(1) DEFAULT 2 AFTER approver_id;

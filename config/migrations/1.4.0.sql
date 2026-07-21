-- v1.4.0: 紹介素材テーブル追加

-- 素材カテゴリ
CREATE TABLE IF NOT EXISTS material_categories (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    sort_order INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 初期カテゴリ
INSERT IGNORE INTO material_categories (name, sort_order) VALUES
('SNS用',     1),
('チラシ用',  2),
('動画',      3),
('トーク例',  4),
('その他',    9);

-- 素材本体
CREATE TABLE IF NOT EXISTS materials (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    category_id  INT DEFAULT NULL,
    title        VARCHAR(255) NOT NULL,
    description  TEXT,
    type         ENUM('text','image','video','file') NOT NULL,
    content_text MEDIUMTEXT,           -- typeがtextの場合
    file_path    VARCHAR(500),         -- typeがimage/video/fileの場合
    file_name    VARCHAR(255),         -- 元のファイル名
    file_size    INT,
    access_type  ENUM('all','specific') DEFAULT 'all',  -- all=全代理店 specific=個別指定
    sort_order   INT DEFAULT 0,
    status       ENUM('active','inactive') DEFAULT 'active',
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES material_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 個別指定アクセス（access_type='specific'の場合に使用）
CREATE TABLE IF NOT EXISTS material_agent_access (
    material_id INT NOT NULL,
    agent_id    INT NOT NULL,
    PRIMARY KEY (material_id, agent_id),
    FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE CASCADE,
    FOREIGN KEY (agent_id)    REFERENCES agents(id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO schema_migrations (version, description)
VALUES ('1.4.0', '紹介素材テーブル追加');

CREATE TABLE IF NOT EXISTS sso_clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_key VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    audience VARCHAR(100) NOT NULL,
    callback_url TEXT NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_sso_clients_status (status),
    INDEX idx_sso_clients_audience (audience)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO system_settings (key_name, value) VALUES
('sso_issuer', ''),
('sso_key_id', ''),
('sso_private_key', ''),
('sso_public_key', '');

INSERT INTO sso_clients (client_key, name, audience, callback_url, status, sort_order)
SELECT
    'sengoku-rr',
    'sengoku-rr.com',
    COALESCE(NULLIF((SELECT value FROM system_settings WHERE key_name='sso_rr_audience' LIMIT 1), ''), 'sengoku-rr'),
    COALESCE(NULLIF((SELECT value FROM system_settings WHERE key_name='sso_rr_callback_url' LIMIT 1), ''), 'https://sengoku-rr.com/agency/sso'),
    IF(COALESCE((SELECT value FROM system_settings WHERE key_name='sso_rr_enabled' LIMIT 1), '0') = '1', 'active', 'inactive'),
    10
WHERE NOT EXISTS (SELECT 1 FROM sso_clients WHERE client_key='sengoku-rr');

INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.6.46', '複数外部サイトSSO連携設定');

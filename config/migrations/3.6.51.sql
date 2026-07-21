CREATE TABLE IF NOT EXISTS external_partner_sites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_key VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    base_url TEXT NOT NULL,
    api_key TEXT NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    sort_order INT NOT NULL DEFAULT 0,
    last_test_status VARCHAR(30) NULL,
    last_test_message TEXT NULL,
    last_test_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_external_partner_sites_status (status),
    INDEX idx_external_partner_sites_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO external_partner_sites (site_key, name, base_url, api_key, status, sort_order)
SELECT
    'sengoku-rr',
    'sengoku-rr.com',
    (SELECT value FROM system_settings WHERE key_name='external_partner_base_url' LIMIT 1),
    (SELECT value FROM system_settings WHERE key_name='external_partner_api_key' LIMIT 1),
    IF(COALESCE((SELECT value FROM system_settings WHERE key_name='external_partner_sync_enabled' LIMIT 1), '0') = '1', 'active', 'inactive'),
    10
WHERE COALESCE((SELECT value FROM system_settings WHERE key_name='external_partner_base_url' LIMIT 1), '') <> ''
  AND COALESCE((SELECT value FROM system_settings WHERE key_name='external_partner_api_key' LIMIT 1), '') <> ''
  AND NOT EXISTS (SELECT 1 FROM external_partner_sites WHERE site_key='sengoku-rr');

INSERT IGNORE INTO schema_migrations (version) VALUES ('3.6.51');

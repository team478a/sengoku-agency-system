ALTER TABLE admins ADD COLUMN IF NOT EXISTS display_name VARCHAR(255) DEFAULT NULL AFTER username;
ALTER TABLE admins ADD COLUMN IF NOT EXISTS role ENUM('super_admin','staff') DEFAULT 'super_admin' AFTER email;
ALTER TABLE admins ADD COLUMN IF NOT EXISTS status ENUM('active','inactive') DEFAULT 'active' AFTER role;
ALTER TABLE admins ADD COLUMN IF NOT EXISTS updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

UPDATE admins
SET
  display_name = COALESCE(NULLIF(display_name, ''), username),
  role = COALESCE(NULLIF(role, ''), 'super_admin'),
  status = COALESCE(NULLIF(status, ''), 'active')
WHERE id IS NOT NULL;

INSERT IGNORE INTO schema_migrations (version, description)
VALUES ('3.6.43', 'Add admin staff accounts metadata');

UPDATE sso_clients
SET status = 'active', updated_at = NOW()
WHERE client_key = 'sengoku-rr'
  AND callback_url <> ''
  AND status = 'inactive';

INSERT IGNORE INTO schema_migrations (version, description) VALUES ('3.6.48', 'SSO連携先ステータス操作改善');

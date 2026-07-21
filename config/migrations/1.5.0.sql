-- v1.5.0: システム設定テーブル追加（Resend APIキー等）
CREATE TABLE IF NOT EXISTS system_settings (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    key_name   VARCHAR(100) NOT NULL UNIQUE,
    value      TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 初期設定
INSERT IGNORE INTO system_settings (key_name, value) VALUES
('resend_api_key',   ''),
('mail_from',        ''),
('mail_from_name',   '戦国経済圏'),
('admin_email',      ''),
('site_url',         '');

INSERT IGNORE INTO schema_migrations (version, description)
VALUES ('1.5.0', 'システム設定テーブル追加');

-- メールテンプレート
INSERT IGNORE INTO system_settings (key_name, value) VALUES
('mail_tpl_application_subject', '【戦国経済圏】新規アドバイザー申請が届きました'),
('mail_tpl_application_body',    '新しいアドバイザー申請が届きました。管理画面からご確認ください。  ■ 申請者情報 会社名・屋号：{company_name} 担当者名：{person_name} メール：{email} 電話：{phone} 志望動機：{message}  ▼ 管理画面で確認する {admin_url}'),
('mail_tpl_approval_subject', '【戦国経済圏】アドバイザーとして承認されました'),
('mail_tpl_approval_body',    '{person_name} 様  この度、戦国経済圏のアドバイザーとして承認されました。 以下のURLからパスワードを設定して、マイページにアクセスしてください。  ▼ 初回パスワード設定URL（24時間有効） {setup_url}  ■ あなたの情報 アドバイザーコード：{agent_code} LP URL：{lp_url} マイページ：{mypage_url}  ご不明な点は本部担当者までお問い合わせください。 マニュアル：{manual_url}');

-- v1.6.0: ログイン履歴・お知らせ・ログイン試行制限

-- ① ログイン履歴
CREATE TABLE IF NOT EXISTS login_logs (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_type  ENUM('admin','agent') NOT NULL,
    user_id    INT DEFAULT NULL,
    email      VARCHAR(255),
    ip_hash    VARCHAR(64),
    success    TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ② ログイン試行制限
CREATE TABLE IF NOT EXISTS login_attempts (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    ip_hash    VARCHAR(64) NOT NULL,
    user_type  ENUM('admin','agent') NOT NULL,
    attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_type (ip_hash, user_type),
    INDEX idx_time (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ⑦ お知らせ
CREATE TABLE IF NOT EXISTS notices (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    title      VARCHAR(255) NOT NULL,
    body       TEXT NOT NULL,
    is_pinned  TINYINT(1) DEFAULT 0,
    status     ENUM('active','inactive') DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ⑩ メールテンプレートデフォルト値
INSERT IGNORE INTO system_settings (key_name, value) VALUES
('mail_tpl_application_subject', '【戦国経済圏】新規アドバイザー申請が届きました'),
('mail_tpl_application_body',
'新しいアドバイザー申請が届きました。管理画面からご確認ください。  ■ 申請者情報 会社名・屋号：{company_name} 担当者名：{person_name} メール：{email} 電話：{phone} 志望動機：{message}  ▼ 管理画面で確認する {admin_url}'),
('mail_tpl_approval_subject', '【戦国経済圏】アドバイザーとして承認されました'),
('mail_tpl_approval_body',
'{person_name} 様  この度、戦国経済圏のアドバイザーとして承認されました。 以下のURLからパスワードを設定して、マイページにアクセスしてください。  ▼ 初回パスワード設定URL（24時間有効） {setup_url}  ■ あなたの情報 アドバイザーコード：{agent_code} LP URL：{lp_url} マイページ：{mypage_url}  ご不明な点は本部担当者までお問い合わせください。 マニュアル：{manual_url}'),
('mail_tpl_rejection_subject', '【戦国経済圏】アドバイザー申請について'),
('mail_tpl_rejection_body',
'{person_name} 様  この度は戦国経済圏のアドバイザーにご応募いただきありがとうございました。 誠に恐れ入りますが、今回は採用を見送らせていただくこととなりました。  またの機会にぜひご応募ください。  戦国経済圏 運営事務局');

INSERT IGNORE INTO schema_migrations (version, description)
VALUES ('1.6.0', 'ログイン履歴・お知らせ・ログイン試行制限テーブル追加');

<?php
$pageTitle = 'システム設定';
require_once __DIR__ . '/header.php';

$db      = getDB();
$csrf    = getCsrfToken();
$message = '';
$msgType = 'success';

// ① 管理者パスワード変更
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_pw') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = '不正なリクエストです。'; $msgType = 'error';
    } else {
        $currentPw = $_POST['current_pw'] ?? '';
        $newPw1    = $_POST['new_pw']     ?? '';
        $newPw2    = $_POST['new_pw2']    ?? '';
        $adminId   = $_SESSION['admin_id'];

        $stmt = $db->prepare("SELECT password FROM admins WHERE id=?");
        $stmt->execute([$adminId]);
        $admin = $stmt->fetch();

        if (!$admin || !password_verify($currentPw, $admin['password'])) {
            $message = '現在のパスワードが正しくありません。'; $msgType = 'error';
        } elseif (strlen($newPw1) < 8) {
            $message = '新しいパスワードは8文字以上にしてください。'; $msgType = 'error';
        } elseif ($newPw1 !== $newPw2) {
            $message = '新しいパスワードが一致しません。'; $msgType = 'error';
        } else {
            $db->prepare("UPDATE admins SET password=? WHERE id=?")
               ->execute([password_hash($newPw1, PASSWORD_BCRYPT), $adminId]);
            $message = 'パスワードを変更しました。';
        }
    }
}

// 設定保存
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = '不正なリクエストです。'; $msgType = 'error';
    } else {
        $fields = ['resend_api_key', 'mail_from', 'mail_from_name', 'admin_email', 'site_url', 'label_level1', 'label_level2', 'label_level3',
                   'label_position_advisor', 'label_position_super_advisor', 'label_position_influencer',
                   'mail_tpl_application_subject', 'mail_tpl_application_body',
                   'mail_tpl_approval_subject', 'mail_tpl_approval_body',
                   'mail_tpl_rejection_subject', 'mail_tpl_rejection_body',
                   'mail_tpl_promotion_subject', 'mail_tpl_promotion_body',
                   'mail_tpl_promo_request_subject', 'mail_tpl_promo_request_body',
                   'external_partner_sync_enabled', 'external_partner_base_url', 'external_partner_api_key'];
        $stmt = $db->prepare("INSERT INTO system_settings (key_name, value) VALUES (?, ?)
                              ON DUPLICATE KEY UPDATE value=VALUES(value)");
        foreach ($fields as $key) {
            if (!array_key_exists($key, $_POST)) continue;
            $val = trim($_POST[$key] ?? '');
            if ($key === 'external_partner_api_key' && $val === '') continue;
            $stmt->execute([$key, $val]);
        }
        $message = '設定を保存しました。';
    }
}

// 外部連携APIトークン
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array(($_POST['action'] ?? ''), ['generate_external_api_token', 'clear_external_api_token'], true)) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = '不正なリクエストです。'; $msgType = 'error';
    } else {
        $token = ($_POST['action'] ?? '') === 'generate_external_api_token' ? bin2hex(random_bytes(32)) : '';
        $stmt = $db->prepare("INSERT INTO system_settings (key_name, value) VALUES ('external_api_token', ?)
                              ON DUPLICATE KEY UPDATE value=VALUES(value)");
        $stmt->execute([$token]);
        $message = $token !== '' ? '外部連携APIトークンを発行しました。' : '外部連携APIを無効化しました。';
        $msgType = 'success';
    }
}

// 外部送信先 接続テスト
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'test_external_partner_connection') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = '不正なリクエストです。'; $msgType = 'error';
    } else {
        $result = testExternalPartnerConnection();
        $endpoint = (string)($result['endpoint'] ?? '');
        $status = (int)($result['status'] ?? 0);
        $response = trim((string)($result['response'] ?? ''));
        $error = trim((string)($result['error'] ?? ''));
        if (!empty($result['ok'])) {
            $message = 'RR側APIへの接続テストに成功しました。送信先: ' . $endpoint . ' / HTTP ' . $status;
            $msgType = 'success';
        } else {
            $message = 'RR側APIへの接続テストに失敗しました。送信先: ' . ($endpoint !== '' ? $endpoint : '未設定') . ' / HTTP ' . $status;
            if ($error !== '') {
                $message .= ' / エラー: ' . $error;
            }
            if ($response !== '') {
                $message .= ' / 応答: ' . $response;
            }
            $msgType = 'error';
        }
    }
}

// テスト送信
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'test') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = '不正なリクエストです。'; $msgType = 'error';
    } else {
        try {
            require_once __DIR__ . '/../includes/mailer.php';
            $mailer  = new Mailer();
            $testTo  = trim($_POST['test_to'] ?? '');
            if (!$testTo) {
                $message = 'テスト送信先メールアドレスを入力してください。'; $msgType = 'error';
            } else {
                $ok = $mailer->send(
                    $testTo,
                    '【戦国経済圏】テストメール',
                    '<p>Resendメール送信のテストです。正常に受信できれば設定完了です。</p>',
                    'Resendメール送信のテストです。正常に受信できれば設定完了です。'
                );
                $message = $ok ? "テストメールを {$testTo} に送信しました。受信を確認してください。" : 'メール送信に失敗しました。APIキーと送信元アドレスを確認してください。';
                $msgType = $ok ? 'success' : 'error';
            }
        } catch (Exception $e) {
            $message = 'エラー: ' . $e->getMessage(); $msgType = 'error';
        }
    }
}

// 現在の設定取得
$rows = $db->query("SELECT key_name, value FROM system_settings")->fetchAll();

// ⑨ ログイン履歴（最新20件）
$loginLogs = $db->query("
    SELECT l.*, a.agent_name
    FROM login_logs l
    LEFT JOIN agents a ON l.user_type='agent' AND l.user_id=a.id
    ORDER BY l.created_at DESC LIMIT 20
")->fetchAll();
$s = [];
foreach ($rows as $row) $s[$row['key_name']] = $row['value'];
$externalApiEndpoint = getSiteBaseUrl() . '/api/hierarchy.php';
$agencyIntegrationEndpoint = getSiteBaseUrl() . '/api/integrations/agencies';
?>

<?php if ($message): ?>
<div class="alert alert-<?= $msgType ?>"><?= h($message) ?></div>
<?php endif; ?>

<div class="card" style="margin-bottom:1.25rem;">
    <p class="card-title">外部連携APIの通信方向</p>
    <p style="font-size:.82rem;color:var(--text-muted);line-height:1.8;margin-bottom:1rem;">
        基本方針は <strong style="color:var(--gold-lt);">sengoku-rr.com から sengoku-ai.com へ送信・取得</strong> です。
        sengoku-ai.com側で承認・昇格した代理店情報は、外部システムが階層取得APIで取得します。
    </p>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1rem;margin-bottom:1rem;">
        <div class="form-group" style="margin:0;">
            <label>階層取得API（GET）</label>
            <input type="text" readonly value="<?= h($externalApiEndpoint) ?>" onclick="this.select()">
            <p style="font-size:.72rem;color:var(--text-muted);line-height:1.7;margin-top:.35rem;">
                sengoku-rr.com側が、sengoku-ai.comの代理店階層を取得します。
            </p>
        </div>
        <div class="form-group" style="margin:0;">
            <label>代理店同期API（POST/GET）</label>
            <input type="text" readonly value="<?= h($agencyIntegrationEndpoint) ?>" onclick="this.select()">
            <p style="font-size:.72rem;color:var(--text-muted);line-height:1.7;margin-top:.35rem;">
                sengoku-rr.com側で作成・更新された代理店情報を、sengoku-ai.comへ送信します。
            </p>
        </div>
    </div>
    <div style="background:rgba(201,168,76,.06);border:1px solid rgba(201,168,76,.16);border-radius:4px;padding:.85rem 1rem;">
        <p style="font-size:.76rem;color:var(--text-muted);line-height:1.8;margin:0;">
            認証は両APIとも <code>x-api-key: {APIキー}</code> または <code>Authorization: Bearer {APIキー}</code> のどちらでも利用できます。
            使用するAPIキーはこの画面で発行した外部連携APIキーです。
            階層取得は <code>format=flat/tree</code>、<code>root_code=代理店コード</code>、<code>include_contact=1</code>、<code>include_sso=1</code> に対応します。
        </p>
    </div>
</div>

<!-- 階層名称設定 -->
<div class="card" style="margin-bottom:1.25rem;">
    <p class="card-title">🏷️ 階層名称設定</p>
    <p style="font-size:.82rem;color:var(--text-muted);margin-bottom:1.25rem;line-height:1.8;">
        管理画面・マイページで表示される上位・下位の呼び方を設定します。<br>
        変更後は「設定を保存する」を押してください。
    </p>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="save">
        <!-- 他の設定値を保持 -->
        <?php foreach (['resend_api_key','mail_from','mail_from_name','admin_email','site_url',
                        'mail_tpl_application_subject','mail_tpl_application_body',
                        'mail_tpl_approval_subject','mail_tpl_approval_body',
                        'mail_tpl_rejection_subject','mail_tpl_rejection_body'] as $k): ?>
        <input type="hidden" name="<?= $k ?>" value="<?= h($s[$k] ?? '') ?>">
        <?php endforeach; ?>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;max-width:480px;">
            <div class="form-group">
                <label>上位の名称</label>
                <input type="text" name="label_level3" value="<?= h($s['label_level3'] ?? 'エージェント') ?>" placeholder="エージェント">
                <p style="font-size:.72rem;color:var(--text-muted);margin-top:.3rem;">例：エージェント・パートナー・上位管理者</p>
            </div>
            <div class="form-group">
                <label>中位の名称</label>
                <input type="text" name="label_level2" value="<?= h($s['label_level2'] ?? 'ディレクター') ?>" placeholder="ディレクター">
                <p style="font-size:.72rem;color:var(--text-muted);margin-top:.3rem;">例：ディレクター・マネージャー</p>
            </div>
            <div class="form-group">
                <label>下位の名称</label>
                <input type="text" name="label_level1" value="<?= h($s['label_level1'] ?? 'アドバイザー') ?>" placeholder="アドバイザー">
                <p style="font-size:.72rem;color:var(--text-muted);margin-top:.3rem;">例：アドバイザー・メンバー・紹介者</p>
            </div>
        </div>
        <div style="margin-top:1rem;margin-bottom:1rem;">
            <p style="font-size:.86rem;color:var(--gold);font-weight:700;margin:0 0 .75rem;">アドバイザー区分名称</p>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;max-width:760px;">
                <div class="form-group">
                    <label>アドバイザー</label>
                    <input type="text" name="label_position_advisor" value="<?= h($s['label_position_advisor'] ?? 'アドバイザー') ?>" placeholder="アドバイザー">
                </div>
                <div class="form-group">
                    <label>スーパーアドバイザー</label>
                    <input type="text" name="label_position_super_advisor" value="<?= h($s['label_position_super_advisor'] ?? 'スーパーアドバイザー') ?>" placeholder="スーパーアドバイザー">
                </div>
                <div class="form-group">
                    <label>インフルエンサー</label>
                    <input type="text" name="label_position_influencer" value="<?= h($s['label_position_influencer'] ?? 'インフルエンサー') ?>" placeholder="インフルエンサー">
                </div>
            </div>
            <p style="font-size:.72rem;color:var(--text-muted);margin-top:.25rem;">招待URL作成、申請フォーム、配下一覧、CSV出力などの区分表示に反映されます。</p>
        </div>
        <div style="background:rgba(201,168,76,.06);border:1px solid rgba(201,168,76,.15);border-radius:3px;padding:.75rem 1rem;font-size:.82rem;color:var(--text-muted);margin-bottom:1rem;">
            現在の設定：
            <strong style="color:var(--gold-lt);"><?= h($s['label_level3'] ?? 'エージェント') ?></strong>（上位）
            →
            <strong style="color:var(--gold-lt);"><?= h($s['label_level2'] ?? 'ディレクター') ?></strong>（中位）
            →
            <strong style="color:var(--gold-lt);"><?= h($s['label_level1'] ?? 'アドバイザー') ?></strong>（下位）
        </div>
        <button type="submit" class="btn btn-gold">名称を保存する</button>
    </form>
</div>

<!-- Resend設定 -->
<div class="card">
    <p class="card-title">📧 メール設定（Resend）</p>
    <p style="font-size:.82rem;color:var(--text-muted);margin-bottom:1.25rem;line-height:1.8;">
        <a href="https://resend.com" target="_blank" style="color:var(--gold);">Resend</a> のAPIキーを設定することで、申請通知・承認メールが自動送信されます。<br>
        送信元ドメインはResendのダッシュボードで事前に認証が必要です。
    </p>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="label_level1" value="<?= h($s['label_level1'] ?? 'アドバイザー') ?>">
        <input type="hidden" name="label_level2" value="<?= h($s['label_level2'] ?? 'ディレクター') ?>">
        <input type="hidden" name="label_level3" value="<?= h($s['label_level3'] ?? 'エージェント') ?>">

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1rem;">
            <div class="form-group">
                <label>Resend APIキー *</label>
                <input type="password" name="resend_api_key"
                       value="<?= h($s['resend_api_key'] ?? '') ?>"
                       placeholder="re_xxxxxxxxxx">
                <p style="font-size:.72rem;color:var(--text-muted);margin-top:.3rem;">
                    <a href="https://resend.com/api-keys" target="_blank" style="color:var(--gold);">Resendダッシュボード</a> → API Keys で取得
                </p>
            </div>
            <div class="form-group">
                <label>送信元メールアドレス *</label>
                <input type="email" name="mail_from"
                       value="<?= h($s['mail_from'] ?? '') ?>"
                       placeholder="noreply@yourdomain.com">
                <p style="font-size:.72rem;color:var(--text-muted);margin-top:.3rem;">Resendで認証済みドメインのアドレスを使用</p>
            </div>
            <div class="form-group">
                <label>送信者名</label>
                <input type="text" name="mail_from_name"
                       value="<?= h($s['mail_from_name'] ?? '戦国経済圏') ?>"
                       placeholder="戦国経済圏">
            </div>
            <div class="form-group">
                <label>本部メールアドレス（申請通知の受信先）*</label>
                <input type="email" name="admin_email"
                       value="<?= h($s['admin_email'] ?? '') ?>"
                       placeholder="admin@yourdomain.com">
            </div>
            <div class="form-group">
                <label>サイトURL（メール内リンクに使用）</label>
                <input type="url" name="site_url"
                       value="<?= h($s['site_url'] ?? '') ?>"
                       placeholder="https://sengoku-ai.com">
            </div>
        </div>

        <button type="submit" class="btn btn-gold">設定を保存する</button>
    </form>
</div>


<!-- メールテンプレート編集 -->
<div class="card" style="margin-top:1.25rem;">
    <p class="card-title">✉️ メールテンプレート編集</p>
    <p style="font-size:.82rem;color:var(--text-muted);margin-bottom:1.5rem;line-height:1.8;">
        メール本文を自由に編集できます。<code style="background:rgba(201,168,76,.1);padding:.1rem .4rem;border-radius:2px;font-size:.8rem;">{変数名}</code> は送信時に自動的に置き換えられます。
    </p>

    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="save">
        <!-- Resend設定は維持 -->
        <input type="hidden" name="resend_api_key" value="<?= h($s['resend_api_key'] ?? '') ?>">
        <input type="hidden" name="mail_from"      value="<?= h($s['mail_from'] ?? '') ?>">
        <input type="hidden" name="mail_from_name" value="<?= h($s['mail_from_name'] ?? '戦国経済圏') ?>">
        <input type="hidden" name="admin_email"    value="<?= h($s['admin_email'] ?? '') ?>">
        <input type="hidden" name="site_url"       value="<?= h($s['site_url'] ?? '') ?>">
        <input type="hidden" name="label_level1" value="<?= h($s['label_level1'] ?? 'アドバイザー') ?>">
        <input type="hidden" name="label_level2" value="<?= h($s['label_level2'] ?? 'ディレクター') ?>">
        <input type="hidden" name="label_level3" value="<?= h($s['label_level3'] ?? 'エージェント') ?>">

        <!-- タブ切替 -->
        <div style="display:flex;gap:.5rem;margin-bottom:1.25rem;border-bottom:1px solid var(--border);padding-bottom:.75rem;">
            <button type="button" onclick="switchTpl('application')" id="tab_application"
                style="padding:.4rem 1rem;border-radius:3px 3px 0 0;font-size:.82rem;cursor:pointer;font-family:inherit;font-weight:700;
                       background:rgba(201,168,76,.18);color:var(--gold);border:1px solid rgba(201,168,76,.4);">
                ① 申請通知（本部へ）
            </button>
            <button type="button" onclick="switchTpl('approval')" id="tab_approval"
                style="padding:.4rem 1rem;border-radius:3px 3px 0 0;font-size:.82rem;cursor:pointer;font-family:inherit;
                       background:rgba(255,255,255,.04);color:var(--text-muted);border:1px solid var(--border);">
                ② 承認通知（アドバイザーへ）
            </button>
            <button type="button" onclick="switchTpl('rejection')" id="tab_rejection"
                style="padding:.4rem 1rem;border-radius:3px 3px 0 0;font-size:.82rem;cursor:pointer;font-family:inherit;
                       background:rgba(255,255,255,.04);color:var(--text-muted);border:1px solid var(--border);">
                ③ 却下通知（申請者へ）
            </button>
            <button type="button" onclick="switchTpl('promotion')" id="tab_promotion"
                style="padding:.4rem 1rem;border-radius:3px 3px 0 0;font-size:.82rem;cursor:pointer;font-family:inherit;
                       background:rgba(255,255,255,.04);color:var(--text-muted);border:1px solid var(--border);">
                ④ 昇格通知
            </button>
            <button type="button" onclick="switchTpl('promo_request')" id="tab_promo_request"
                style="padding:.4rem 1rem;border-radius:3px 3px 0 0;font-size:.82rem;cursor:pointer;font-family:inherit;
                       background:rgba(255,255,255,.04);color:var(--text-muted);border:1px solid var(--border);">
                ⑤ 昇格申請通知（エージェントへ）
            </button>
        </div>

        <!-- ① 申請通知テンプレート -->
        <div id="tpl_application">
            <div class="form-group">
                <label>件名</label>
                <input type="text" name="mail_tpl_application_subject"
                       value="<?= h($s['mail_tpl_application_subject'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>本文</label>
                <textarea name="mail_tpl_application_body" rows="10"><?= h($s['mail_tpl_application_body'] ?? '') ?></textarea>
            </div>
            <div style="background:rgba(201,168,76,.06);border:1px solid rgba(201,168,76,.15);border-radius:3px;padding:.85rem 1rem;font-size:.78rem;color:var(--text-muted);line-height:1.9;">
                <strong style="color:var(--gold);">使用可能な変数：</strong><br>
                <code>{company_name}</code> 会社名・屋号　
                <code>{person_name}</code> 担当者名　
                <code>{email}</code> メール　
                <code>{phone}</code> 電話　
                <code>{message}</code> 志望動機　
                <code>{admin_url}</code> 管理画面URL
            </div>
        </div>

        <!-- ② 承認通知テンプレート -->
        <div id="tpl_approval" style="display:none;">
            <div class="form-group">
                <label>件名</label>
                <input type="text" name="mail_tpl_approval_subject"
                       value="<?= h($s['mail_tpl_approval_subject'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>本文</label>
                <textarea name="mail_tpl_approval_body" rows="12"><?= h($s['mail_tpl_approval_body'] ?? '') ?></textarea>
            </div>
            <div style="background:rgba(201,168,76,.06);border:1px solid rgba(201,168,76,.15);border-radius:3px;padding:.85rem 1rem;font-size:.78rem;color:var(--text-muted);line-height:1.9;">
                <strong style="color:var(--gold);">使用可能な変数：</strong><br>
                <code>{person_name}</code> 担当者名　
                <code>{agent_code}</code> アドバイザーコード　
                <code>{setup_url}</code> 初回設定URL　
                <code>{lp_url}</code> LP URL　
                <code>{mypage_url}</code> マイページURL　
                <code>{manual_url}</code> マニュアルURL
            </div>
        </div>

        <!-- ③ 却下通知テンプレート -->
        <div id="tpl_rejection" style="display:none;">
            <div class="form-group">
                <label>件名</label>
                <input type="text" name="mail_tpl_rejection_subject"
                       value="<?= h($s['mail_tpl_rejection_subject'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>本文</label>
                <textarea name="mail_tpl_rejection_body" rows="8"><?= h($s['mail_tpl_rejection_body'] ?? '') ?></textarea>
            </div>
            <div style="background:rgba(201,168,76,.06);border:1px solid rgba(201,168,76,.15);border-radius:3px;padding:.85rem 1rem;font-size:.78rem;color:var(--text-muted);line-height:1.9;">
                <strong style="color:var(--gold);">使用可能な変数：</strong><br>
                <code>{person_name}</code> 担当者名
            </div>
        </div>

        <!-- ④ 昇格通知テンプレート -->
        <div id="tpl_promotion" style="display:none;">
            <div class="form-group">
                <label>件名</label>
                <input type="text" name="mail_tpl_promotion_subject"
                       value="<?= h($s['mail_tpl_promotion_subject'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>本文</label>
                <textarea name="mail_tpl_promotion_body" rows="10"><?= h($s['mail_tpl_promotion_body'] ?? '') ?></textarea>
            </div>
            <div style="background:rgba(201,168,76,.06);border:1px solid rgba(201,168,76,.15);border-radius:3px;padding:.85rem 1rem;font-size:.78rem;color:var(--text-muted);line-height:1.9;">
                <strong style="color:var(--gold);">使用可能な変数：</strong><br>
                <code>{person_name}</code> 担当者名　
                <code>{agent_code}</code> コード　
                <code>{lp_url}</code> LP URL　
                <code>{mypage_url}</code> マイページURL　
                <code>{label_level1}</code> 下位名称　
                <code>{label_level2}</code> 中位名称
            </div>
        </div>

        <!-- ⑤ 昇格申請通知 -->
        <div id="tpl_promo_request" style="display:none;">
            <div class="form-group">
                <label>件名</label>
                <input type="text" name="mail_tpl_promo_request_subject"
                       value="<?= h($s['mail_tpl_promo_request_subject'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>本文</label>
                <textarea name="mail_tpl_promo_request_body" rows="8"><?= h($s['mail_tpl_promo_request_body'] ?? '') ?></textarea>
            </div>
            <div style="background:rgba(201,168,76,.06);border:1px solid rgba(201,168,76,.15);border-radius:3px;padding:.85rem 1rem;font-size:.78rem;color:var(--text-muted);line-height:1.9;">
                <strong style="color:var(--gold);">使用可能な変数：</strong><br>
                <code>{person_name}</code> 申請者名　<code>{agent_code}</code> コード　
                <code>{message}</code> 申請メッセージ　<code>{mypage_url}</code> マイページURL　
                <code>{label_level2}</code> ディレクター名称　<code>{label_level3}</code> エージェント名称
            </div>
        </div>

        <button type="submit" class="btn btn-gold" style="margin-top:1.25rem;">テンプレートを保存する</button>
    </form>
</div>

<script>
function switchTpl(name) {
    ['application','approval','rejection','promotion','promo_request'].forEach(t => {
        document.getElementById('tpl_' + t).style.display = t === name ? '' : 'none';
        const tab = document.getElementById('tab_' + t);
        if (t === name) {
            tab.style.background = 'rgba(201,168,76,.18)';
            tab.style.color = 'var(--gold)';
            tab.style.border = '1px solid rgba(201,168,76,.4)';
            tab.style.fontWeight = '700';
        } else {
            tab.style.background = 'rgba(255,255,255,.04)';
            tab.style.color = 'var(--text-muted)';
            tab.style.border = '1px solid var(--border)';
            tab.style.fontWeight = '400';
        }
    });
}
</script>

<!-- テスト送信 -->
<div class="card" style="margin-top:1.25rem;">
    <p class="card-title">🧪 テスト送信</p>
    <p style="font-size:.82rem;color:var(--text-muted);margin-bottom:1rem;">設定を保存後、テストメールを送信して動作確認ができます。</p>
    <form method="post" style="display:flex;gap:.75rem;align-items:flex-end;flex-wrap:wrap;">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="test">
        <div class="form-group" style="margin:0;flex:1;min-width:240px;">
            <label>テスト送信先メールアドレス</label>
            <input type="email" name="test_to" placeholder="test@example.com">
        </div>
        <button type="submit" class="btn btn-outline">テストメールを送信</button>
    </form>
</div>

<!-- 送信フロー説明 -->
<div class="card" style="margin-top:1.25rem;">
    <p class="card-title">📋 メール送信フロー</p>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
        <div style="background:rgba(201,168,76,.06);border:1px solid rgba(201,168,76,.2);border-radius:4px;padding:1.25rem;">
            <p style="font-weight:700;color:var(--gold-lt);margin-bottom:.75rem;">① 申請時（本部へ）</p>
            <p style="font-size:.82rem;color:var(--text-muted);line-height:1.9;">
                アドバイザーが <code>/apply</code> から申請<br>
                → 本部メールアドレスに通知<br>
                → 申請内容と管理画面リンクが届く
            </p>
        </div>
        <div style="background:rgba(94,203,155,.06);border:1px solid rgba(94,203,155,.2);border-radius:4px;padding:1.25rem;">
            <p style="font-weight:700;color:#5ecb9b;margin-bottom:.75rem;">② 承認時（アドバイザーへ）</p>
            <p style="font-size:.82rem;color:var(--text-muted);line-height:1.9;">
                本部が管理画面で「承認」ボタン<br>
                → アドバイザーメールに初回設定URLを送信<br>
                → LP URL・マイページURLも記載
            </p>
        </div>
    </div>
</div>

<!-- 外部連携API -->
<div class="card" style="margin-top:1.25rem;">
    <p class="card-title">🔌 外部連携API</p>
    <p style="font-size:.82rem;color:var(--text-muted);margin-bottom:1rem;line-height:1.8;">
        この欄は旧バージョン互換用の共通設定です。<br>
        新しい外部サイト連携は、接続先ごとにAPIキーを発行できる
        <a href="/admin/external_partners.php" style="color:var(--gold);font-weight:700;">外部API連携</a>
        画面を使ってください。
    </p>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1rem;margin-bottom:1rem;">
        <div class="form-group" style="margin:0;">
            <label>階層取得API</label>
            <input type="text" readonly value="<?= h($externalApiEndpoint) ?>" onclick="this.select()">
        </div>
        <div class="form-group" style="margin:0;">
            <label>sengoku-ai.com 旧共通受信用APIキー</label>
            <input type="text" readonly value="<?= h($s['external_api_token'] ?? '') ?>" placeholder="未発行" onclick="this.select()">
            <p style="font-size:.72rem;color:var(--text-muted);line-height:1.7;margin-top:.35rem;">
                旧方式の共通キーです。新規連携では「外部API連携」画面の接続先ごとのAI発行キーを使ってください。
            </p>
        </div>
    </div>
    <form method="post" style="border:1px solid rgba(201,168,76,.16);border-radius:4px;padding:1rem;margin-bottom:1rem;background:rgba(201,168,76,.04);">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="external_partner_sync_enabled" value="0">
        <p style="font-weight:700;color:var(--gold);margin-bottom:.75rem;">sengoku-rr.com 受信用APIキー（AIからRRへ送信）</p>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1rem;align-items:end;">
            <div class="form-group" style="margin:0;">
                <label>双方向同期</label>
                <label style="display:flex;gap:.55rem;align-items:center;font-weight:700;">
                    <input type="checkbox" name="external_partner_sync_enabled" value="1" <?= (($s['external_partner_sync_enabled'] ?? '0') === '1') ? 'checked' : '' ?>>
                    sengoku-ai.com から sengoku-rr.com へ送信する
                </label>
            </div>
            <div class="form-group" style="margin:0;">
                <label>送信先URL（ドメインまたはAPIエンドポイント）</label>
                <input type="url" name="external_partner_base_url" value="<?= h($s['external_partner_base_url'] ?? '') ?>" placeholder="https://sengoku-rr.com">
                <p style="font-size:.72rem;color:var(--text-muted);line-height:1.6;margin:.35rem 0 0;">
                    基本は <code>https://sengoku-rr.com</code> だけでOKです。<br>
                    <code>/api/integrations/agencies</code> まで入力した場合もそのまま利用します。
                </p>
            </div>
            <div class="form-group" style="margin:0;">
                <label>sengoku-rr.com 受信用APIキー</label>
                <input type="password" name="external_partner_api_key" value="" placeholder="<?= !empty($s['external_partner_api_key']) ? '設定済み。変更する場合のみ入力' : 'RR側で発行したAPIキー' ?>">
            </div>
        </div>
        <p style="font-size:.72rem;color:var(--text-muted);line-height:1.7;margin:.75rem 0;">
            このキーは sengoku-rr.com 側で発行し、この画面へ登録します。上の sengoku-ai.com 受信用APIキーとは別物です。
        </p>
        <button type="submit" class="btn btn-gold">送信用設定を保存</button>
    </form>
    <form method="post" style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;margin:-.35rem 0 1rem;">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="test_external_partner_connection">
        <button type="submit" class="btn" onclick="return confirm('保存済みの送信先URLとAPIキーで、RR側APIへ接続テストを送信します。実行しますか？');">接続テスト</button>
        <span style="font-size:.72rem;color:var(--text-muted);line-height:1.6;">
            保存済み設定で <code>connection_test</code> を送信します。RR側は <code>dry_run=true</code> の場合、データ保存せず応答してください。
        </span>
    </form>
    <div class="form-group" style="margin-bottom:1rem;">
        <label>代理店同期API</label>
        <input type="text" readonly value="<?= h($agencyIntegrationEndpoint) ?>" onclick="this.select()">
    </div>

    <div style="background:rgba(201,168,76,.06);border:1px solid rgba(201,168,76,.16);border-radius:4px;padding:.85rem 1rem;margin-bottom:1rem;">
        <p style="font-size:.78rem;color:var(--text-muted);line-height:1.8;margin-bottom:.5rem;">階層取得APIの例</p>
        <code style="display:block;color:var(--gold-lt);font-size:.78rem;word-break:break-all;line-height:1.8;">
            <?= h($externalApiEndpoint) ?>?format=tree
        </code>
        <code style="display:block;color:var(--gold-lt);font-size:.78rem;word-break:break-all;line-height:1.8;margin-top:.35rem;">
            Authorization: Bearer <?= h($s['external_api_token'] ?? '発行したトークン') ?>
        </code>
        <p style="font-size:.78rem;color:var(--text-muted);line-height:1.8;margin:.85rem 0 .5rem;">代理店同期APIの例</p>
        <code style="display:block;color:var(--gold-lt);font-size:.78rem;word-break:break-all;line-height:1.8;">
            POST <?= h($agencyIntegrationEndpoint) ?>
        </code>
        <code style="display:block;color:var(--gold-lt);font-size:.78rem;word-break:break-all;line-height:1.8;margin-top:.35rem;">
            x-api-key: <?= h($s['external_api_token'] ?? '発行したAPIキー') ?>
        </code>
        <p style="font-size:.72rem;color:var(--text-muted);line-height:1.7;margin-top:.65rem;">
            階層取得は <code>format=flat</code> で一覧形式、<code>root_code=代理店コード</code> で特定代理店配下のみ、<code>include_contact=1</code> でメール・電話、<code>include_sso=1</code> でSSO起動URLも含めます。<br>
            代理店同期は <code>external_id</code> をキーに登録・更新されます。
        </p>
    </div>

    <div style="display:flex;gap:.65rem;flex-wrap:wrap;">
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
            <input type="hidden" name="action" value="generate_external_api_token">
            <button type="submit" class="btn btn-gold" onclick="return confirm('外部連携APIトークンを再発行しますか？既存の外部連携は新しいトークンへ差し替えが必要です。');">
                <?= empty($s['external_api_token']) ? 'APIトークンを発行' : 'APIトークンを再発行' ?>
            </button>
        </form>
        <?php if (!empty($s['external_api_token'])): ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
            <input type="hidden" name="action" value="clear_external_api_token">
            <button type="submit" class="btn btn-danger" onclick="return confirm('外部連携APIを無効化しますか？');">APIを無効化</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<!-- ① 管理者パスワード変更 -->
<div class="card" style="margin-top:1.25rem;">
    <p class="card-title">🔐 管理者パスワード変更</p>
    <form method="post" style="max-width:400px;">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="change_pw">
        <div class="form-group">
            <label>現在のパスワード</label>
            <input type="password" name="current_pw" required>
        </div>
        <div class="form-group">
            <label>新しいパスワード（8文字以上）</label>
            <input type="password" name="new_pw" required minlength="8">
        </div>
        <div class="form-group">
            <label>新しいパスワード（確認）</label>
            <input type="password" name="new_pw2" required minlength="8">
        </div>
        <button type="submit" class="btn btn-gold">パスワードを変更する</button>
    </form>
</div>

<!-- ⑨ ログイン履歴 -->
<div class="card" style="margin-top:1.25rem;padding:0;overflow:hidden;">
    <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--border);">
        <div style="display:flex;justify-content:space-between;gap:1rem;align-items:center;">
            <p class="card-title" style="margin:0;border:none;padding:0;">🕐 ログイン履歴（最新20件）</p>
            <a href="/admin/login_logs.php" class="btn btn-outline btn-sm">すべて見る</a>
        </div>
    </div>
    <table>
        <thead><tr><th>種別</th><th>ユーザー</th><th>結果</th><th>日時</th></tr></thead>
        <tbody>
        <?php if ($loginLogs): foreach ($loginLogs as $log): ?>
        <tr>
            <td style="font-size:.78rem;">
                <span style="background:<?= $log['user_type']==='admin'?'rgba(201,168,76,.15)':'rgba(94,203,155,.1)' ?>;
                      color:<?= $log['user_type']==='admin'?'var(--gold)':'#5ecb9b' ?>;
                      padding:.15rem .5rem;border-radius:2px;font-size:.72rem;font-weight:700;">
                    <?= $log['user_type']==='admin' ? '管理者' : 'アドバイザー' ?>
                </span>
            </td>
            <td style="font-size:.82rem;">
                <?= h($log['user_type']==='admin' ? ($log['email'] ?? '—') : ($log['agent_name'] ?? $log['email'] ?? '—')) ?>
            </td>
            <td>
                <span style="color:<?= $log['success']?'#5ecb9b':'#e08080' ?>;font-size:.82rem;font-weight:700;">
                    <?= $log['success'] ? '✓ 成功' : '✗ 失敗' ?>
                </span>
            </td>
            <td style="font-size:.75rem;color:var(--text-muted);"><?= date('m/d H:i', strtotime($log['created_at'])) ?></td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:2rem;">ログイン履歴がありません。</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>

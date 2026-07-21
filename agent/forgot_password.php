<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';

startSecureSession();

if (!empty($_SESSION['agent_id'])) {
    header('Location: /agent/dashboard.php');
    exit;
}

$db = getDB();
$message = '';
$msgType = 'success';
$emailValue = '';

function resetBuildSiteUrl(PDO $db): string {
    try {
        $row = $db->query("SELECT value FROM system_settings WHERE key_name='site_url'")->fetch();
        $url = trim((string)($row['value'] ?? ''));
        if ($url !== '') return rtrim($url, '/');
    } catch (Throwable $e) {
        error_log('Password reset site_url lookup failed: ' . $e->getMessage());
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? '');
}

function resetFindAgentByEmail(PDO $db, string $email): ?array {
    if (function_exists('tableHasColumn') && tableHasColumn('agents', 'login_email')) {
        $stmt = $db->prepare("
            SELECT *
            FROM agents
            WHERE (login_email = ? OR ((login_email IS NULL OR login_email = '') AND email = ?))
              AND status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$email, $email]);
    } else {
        $stmt = $db->prepare("SELECT * FROM agents WHERE email = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$email]);
    }
    $agent = $stmt->fetch();
    return $agent ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailValue = trim((string)($_POST['email'] ?? ''));
    $ipHash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '');

    if ($emailValue === '' || !filter_var($emailValue, FILTER_VALIDATE_EMAIL)) {
        $message = 'メールアドレスを正しく入力してください。';
        $msgType = 'error';
    } elseif (function_exists('checkLoginThrottle') && checkLoginThrottle($ipHash, 'agent_password_reset')) {
        $message = '再発行の試行回数が多すぎます。5分後に再度お試しください。';
        $msgType = 'error';
    } else {
        if (function_exists('recordLoginAttempt')) {
            recordLoginAttempt($ipHash, 'agent_password_reset');
        }

        $agent = resetFindAgentByEmail($db, $emailValue);
        if ($agent) {
            $token = bin2hex(random_bytes(32));
            $exp = date('Y-m-d H:i:s', time() + 72 * 3600);
            $db->prepare("UPDATE agents SET setup_token=?, setup_token_exp=? WHERE id=?")
               ->execute([$token, $exp, (int)$agent['id']]);

            $setupUrl = resetBuildSiteUrl($db) . '/agent/setup.php?token=' . $token;
            $subject = '【戦国経済圏】パスワード再設定URLのご案内';
            $text = ($agent['person_name'] ?? '') . " 様\n\n"
                  . "パスワード再設定の申請を受け付けました。\n"
                  . "以下のURLから新しいパスワードを設定してください。\n\n"
                  . $setupUrl . "\n\n"
                  . "有効期限は72時間です。心当たりがない場合は、このメールを破棄してください。";
            $html = '<p>' . h($agent['person_name'] ?? '') . ' 様</p>'
                  . '<p>パスワード再設定の申請を受け付けました。以下のURLから新しいパスワードを設定してください。</p>'
                  . '<p><a href="' . h($setupUrl) . '" style="color:#C9A84C;word-break:break-all;">' . h($setupUrl) . '</a></p>'
                  . '<p>有効期限は72時間です。心当たりがない場合は、このメールを破棄してください。</p>';

            try {
                $sent = (new Mailer())->send((string)$agent['email'], $subject, $html, $text);
                if (!$sent) {
                    error_log('Password reset mail was not sent for agent_id=' . (int)$agent['id']);
                }
            } catch (Throwable $e) {
                error_log('Password reset mail error: ' . $e->getMessage());
            }
        }

        $message = '再設定用URLの発行を受け付けました。登録済みのメールアドレス宛に案内が届きます。届かない場合は管理者へ連絡してください。';
        $msgType = 'success';
        $emailValue = '';
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>パスワード再発行 | 戦国経済圏</title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+JP:wght@400;700;900&family=Noto+Sans+JP:wght@300;400;500&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
    --black: #0A0805; --ink: #13100D; --gold: #C9A84C; --gold-lt: #E2C87A;
    --cream: #F5F0E8; --paper: #E8E0CC; --border: rgba(201,168,76,.18);
    --text-muted: rgba(245,240,232,.45);
}
body {
    font-family: 'Noto Sans JP', sans-serif;
    background: var(--black);
    color: var(--paper);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2rem 1rem;
}
.logo-wrap { text-align: center; margin-bottom: 2rem; }
.logo { font-family: 'Noto Serif JP', serif; font-size: 1.2rem; font-weight: 900; color: var(--gold-lt); letter-spacing: .1em; }
.logo-sub { font-size: .72rem; color: var(--text-muted); margin-top: .3rem; letter-spacing: .15em; }
.card {
    background: var(--ink);
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: 2.25rem 2rem;
    width: 100%;
    max-width: 420px;
    box-shadow: 0 8px 40px rgba(0,0,0,.4);
}
.card-title { font-family: 'Noto Serif JP', serif; font-size: 1.25rem; font-weight: 700; color: var(--gold-lt); margin-bottom: .4rem; }
.card-sub { font-size: .82rem; color: var(--text-muted); margin-bottom: 1.75rem; line-height: 1.8; }
.form-group { margin-bottom: 1.1rem; }
label { display: block; font-size: .75rem; letter-spacing: .08em; color: var(--gold); margin-bottom: .4rem; }
input[type="email"] {
    width: 100%;
    padding: .75rem 1rem;
    background: rgba(255,255,255,.05);
    border: 1px solid var(--border);
    border-radius: 3px;
    color: var(--cream);
    font-family: inherit;
    font-size: .9rem;
}
input:focus { outline: none; border-color: var(--gold); background: rgba(255,255,255,.07); }
.btn-submit {
    width: 100%;
    padding: .9rem;
    background: linear-gradient(135deg, var(--gold), var(--gold-lt));
    color: var(--ink);
    font-family: 'Noto Serif JP', serif;
    font-weight: 700;
    font-size: .95rem;
    border: none;
    border-radius: 3px;
    cursor: pointer;
    margin-top: .5rem;
}
.btn-submit:hover { opacity: .9; }
.alert { padding: .75rem 1rem; border-radius: 3px; font-size: .83rem; margin-bottom: 1.1rem; line-height: 1.7; }
.alert-success { background: rgba(94,203,155,.1); border: 1px solid rgba(94,203,155,.35); color: #5ecb9b; }
.alert-error { background: rgba(139,26,26,.18); border: 1px solid rgba(178,34,34,.4); color: #e08080; }
.footer-links { margin-top: 1.3rem; text-align: center; font-size: .78rem; color: var(--text-muted); }
.footer-links a { color: var(--gold); text-decoration: none; }
</style>
</head>
<body>
<div class="logo-wrap">
    <p class="logo">⚔ 戦国経済圏</p>
    <p class="logo-sub">パスワード再発行</p>
</div>

<div class="card">
    <p class="card-title">パスワード再発行</p>
    <p class="card-sub">登録済みのメールアドレスを入力してください。再設定用URLをメールで送信します。</p>

    <?php if ($message): ?>
    <div class="alert alert-<?= h($msgType) ?>"><?= h($message) ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="form-group">
            <label>メールアドレス</label>
            <input type="email" name="email" required autofocus value="<?= h($emailValue) ?>" placeholder="example@mail.com">
        </div>
        <button type="submit" class="btn-submit">再設定URLを送信する</button>
    </form>
</div>

<div class="footer-links">
    <a href="/agent/login.php">ログイン画面へ戻る</a>
</div>
</body>
</html>

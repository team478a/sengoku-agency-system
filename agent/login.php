<?php
/**
 * アドバイザーマイページ ログイン
 * header.phpを使わず独自レイアウト
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();

// すでにログイン済みならダッシュボードへ
if (!empty($_SESSION['agent_id'])) {
    header('Location: /agent/dashboard.php');
    exit;
}

$db      = getDB();
$message = '';

$ipHash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pw    = $_POST['password'] ?? '';

    if (checkLoginThrottle($ipHash, 'agent')) {
        $message = 'ログイン試行回数が上限を超えました。15分後に再試行してください。';
    } else {
        if (tableHasColumn('agents', 'login_email')) {
            $stmt = $db->prepare("
                SELECT * FROM agents
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

        if ($agent && $agent['password'] && password_verify($pw, $agent['password'])) {
            clearLoginAttempts($ipHash, 'agent');
            recordLoginLog('agent', $agent['id'], $email, true);
            session_regenerate_id(true);
            $_SESSION['agent_id'] = $agent['id'];
            header('Location: /agent/dashboard.php');
            exit;
        } elseif ($agent && !$agent['password']) {
            $message = '初回設定が完了していません。承認メールのURLからパスワードを設定してください。';
        } else {
            recordLoginAttempt($ipHash, 'agent');
            recordLoginLog('agent', null, $email, false);
            $message = 'メールアドレスまたはパスワードが正しくありません。';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ログイン | 戦国経済圏 アドバイザー</title>
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
.logo-wrap {
    text-align: center;
    margin-bottom: 2rem;
}
.logo {
    font-family: 'Noto Serif JP', serif;
    font-size: 1.2rem;
    font-weight: 900;
    color: var(--gold-lt);
    letter-spacing: .1em;
}
.logo-sub {
    font-size: .72rem;
    color: var(--text-muted);
    margin-top: .3rem;
    letter-spacing: .15em;
}
.card {
    background: var(--ink);
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: 2.25rem 2rem;
    width: 100%;
    max-width: 400px;
    box-shadow: 0 8px 40px rgba(0,0,0,.4);
}
.card-title {
    font-family: 'Noto Serif JP', serif;
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--gold-lt);
    margin-bottom: .4rem;
}
.card-sub {
    font-size: .82rem;
    color: var(--text-muted);
    margin-bottom: 1.75rem;
    line-height: 1.7;
}
.form-group { margin-bottom: 1.1rem; }
label {
    display: block;
    font-size: .75rem;
    letter-spacing: .08em;
    color: var(--gold);
    margin-bottom: .4rem;
}
input[type="email"], input[type="password"] {
    width: 100%;
    padding: .75rem 1rem;
    background: rgba(255,255,255,.05);
    border: 1px solid var(--border);
    border-radius: 3px;
    color: var(--cream);
    font-family: inherit;
    font-size: .9rem;
    transition: border-color .2s;
}
input:focus { outline: none; border-color: var(--gold); background: rgba(255,255,255,.07); }
.password-toggle-wrap { position: relative; display: block; width: 100%; }
.password-toggle-wrap input { padding-right: 3rem !important; }
.password-toggle-btn {
    position: absolute;
    right: .45rem;
    top: 50%;
    transform: translateY(-50%);
    border: 1px solid var(--border);
    background: rgba(201,168,76,.12);
    color: var(--gold);
    border-radius: 3px;
    width: 2.15rem;
    height: 2.15rem;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}
.password-toggle-btn svg { width: 17px; height: 17px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
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
    transition: opacity .2s, transform .15s;
}
.btn-submit:hover { opacity: .9; transform: translateY(-1px); }
.alert {
    padding: .75rem 1rem;
    border-radius: 3px;
    font-size: .83rem;
    margin-bottom: 1.1rem;
    background: rgba(139,26,26,.18);
    border: 1px solid rgba(178,34,34,.4);
    color: #e08080;
    line-height: 1.7;
}
.footer-links {
    margin-top: 1.5rem;
    text-align: center;
    font-size: .75rem;
    color: var(--text-muted);
}
.footer-links a { color: var(--gold); text-decoration: none; }
</style>
</head>
<body>

<div class="logo-wrap">
    <p class="logo">⚔ 戦国経済圏</p>
    <p class="logo-sub">アドバイザーマイページ</p>
</div>

<div class="card">
    <p class="card-title">ログイン</p>
    <p class="card-sub">登録のメールアドレスとパスワードでログインしてください。</p>

    <?php if (!empty($_GET['err']) && $_GET['err'] === 'inactive'): ?>
    <div class="alert">アカウントが停止されています。本部にお問い合わせください。</div>
    <?php endif; ?>
    <?php if ($message): ?>
    <div class="alert"><?= h($message) ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="form-group">
            <label>メールアドレス</label>
            <input type="email" name="email" required autofocus
                   value="<?= h($_POST['email'] ?? '') ?>"
                   placeholder="example@mail.com">
        </div>
        <div class="form-group">
            <label>パスワード</label>
            <input type="password" name="password" required placeholder="パスワード">
        </div>
        <button type="submit" class="btn-submit">ログイン</button>
    </form>
    <div class="footer-links" style="margin-top:1rem;">
        <a href="/agent/forgot_password.php">パスワードを忘れた方・再発行</a>
    </div>
</div>

<div class="footer-links">
    <a href="/manual">使い方マニュアル</a>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const eyeIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
    const eyeOffIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18"></path><path d="M10.6 10.6A2 2 0 0 0 12 14a2 2 0 0 0 1.4-.6"></path><path d="M9.9 5.1A10.8 10.8 0 0 1 12 5c6.5 0 10 7 10 7a16 16 0 0 1-3.1 4.1"></path><path d="M6.1 6.1C3.5 7.8 2 12 2 12s3.5 7 10 7c1.2 0 2.3-.2 3.3-.7"></path></svg>';
    document.querySelectorAll('input[type="password"]').forEach(function(input) {
        const wrapper = document.createElement('span');
        wrapper.className = 'password-toggle-wrap';
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'password-toggle-btn';
        button.innerHTML = eyeIcon;
        button.setAttribute('aria-label', 'パスワードを表示');
        button.addEventListener('click', function() {
            const visible = input.type === 'password';
            input.type = visible ? 'text' : 'password';
            button.innerHTML = visible ? eyeOffIcon : eyeIcon;
            button.setAttribute('aria-label', visible ? 'パスワードを非表示' : 'パスワードを表示');
        });
        wrapper.appendChild(button);
    });
});
</script>
</body>
</html>

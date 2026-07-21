<?php
/**
 * アドバイザー 初回パスワード設定
 * header.phpを使わず独自レイアウト
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();

$db      = getDB();
$token   = $_GET['token'] ?? ($_POST['token'] ?? '');
$message = '';
$msgType = 'success';
$agent   = null;

if ($token) {
    $stmt = $db->prepare("
        SELECT * FROM agents
        WHERE setup_token = ? AND setup_token_exp > NOW() AND status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $agent = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $agent) {
    $pw1 = $_POST['password']  ?? '';
    $pw2 = $_POST['password2'] ?? '';

    if (strlen($pw1) < 8) {
        $message = 'パスワードは8文字以上で設定してください。'; $msgType = 'error';
    } elseif ($pw1 !== $pw2) {
        $message = 'パスワードが一致しません。'; $msgType = 'error';
    } else {
        $hash = password_hash($pw1, PASSWORD_BCRYPT);
        $db->prepare("UPDATE agents SET password=?, setup_token=NULL, setup_token_exp=NULL WHERE id=?")
           ->execute([$hash, $agent['id']]);
        if (function_exists('recordLoginLog')) {
            recordLoginLog('agent', (int)$agent['id'], (string)(($agent['login_email'] ?? '') ?: ($agent['email'] ?? '')), true);
        }
        $_SESSION['agent_id'] = $agent['id'];
        header('Location: /agent/dashboard.php?welcome=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>パスワード設定 | 戦国経済圏 アドバイザー</title>
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
.card { background: var(--ink); border: 1px solid var(--border); border-radius: 6px; padding: 2.25rem 2rem; width: 100%; max-width: 420px; box-shadow: 0 8px 40px rgba(0,0,0,.4); }
.card-title { font-family: 'Noto Serif JP', serif; font-size: 1.25rem; font-weight: 700; color: var(--gold-lt); margin-bottom: .4rem; }
.card-sub { font-size: .82rem; color: var(--text-muted); margin-bottom: 1.75rem; line-height: 1.8; }
.form-group { margin-bottom: 1.1rem; }
label { display: block; font-size: .75rem; letter-spacing: .08em; color: var(--gold); margin-bottom: .4rem; }
input[type="email"], input[type="password"] {
    width: 100%; padding: .75rem 1rem;
    background: rgba(255,255,255,.05); border: 1px solid var(--border); border-radius: 3px;
    color: var(--cream); font-family: inherit; font-size: .9rem; transition: border-color .2s;
}
input:disabled { opacity: .5; cursor: not-allowed; }
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
    width: 100%; padding: .9rem;
    background: linear-gradient(135deg, var(--gold), var(--gold-lt));
    color: var(--ink); font-family: 'Noto Serif JP', serif; font-weight: 700; font-size: .95rem;
    border: none; border-radius: 3px; cursor: pointer; margin-top: .5rem; transition: opacity .2s;
}
.btn-submit:hover { opacity: .9; }
.alert { padding: .75rem 1rem; border-radius: 3px; font-size: .83rem; margin-bottom: 1.1rem; }
.alert-error { background: rgba(139,26,26,.18); border: 1px solid rgba(178,34,34,.4); color: #e08080; }
.invalid-wrap { text-align: center; padding: 2rem 0; }
.invalid-icon { font-size: 3rem; margin-bottom: 1rem; }
.invalid-title { font-family: 'Noto Serif JP', serif; font-size: 1.2rem; color: var(--gold-lt); margin-bottom: .75rem; }
.invalid-sub { font-size: .85rem; color: var(--text-muted); line-height: 1.8; }
</style>
</head>
<body>

<div class="logo-wrap">
    <p class="logo">⚔ 戦国経済圏</p>
    <p class="logo-sub">アドバイザーマイページ</p>
</div>

<div class="card">
<?php if (!$token || !$agent): ?>
    <div class="invalid-wrap">
        <div class="invalid-icon">⚠️</div>
        <p class="invalid-title">リンクが無効です</p>
        <p class="invalid-sub">
            URLが正しくないか、有効期限（24時間）が切れています。<br>
            本部担当者にお問い合わせください。
        </p>
    </div>
<?php else: ?>
    <p class="card-title">パスワードを設定</p>
    <p class="card-sub">
        ようこそ、<?= h($agent['person_name']) ?>さん。<br>
        マイページにログインするためのパスワードを設定してください。
    </p>

    <?php if ($message): ?>
    <div class="alert alert-error"><?= h($message) ?></div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="token" value="<?= h($token) ?>">
        <div class="form-group">
            <label>メールアドレス</label>
            <input type="email" value="<?= h(($agent['login_email'] ?? '') ?: $agent['email']) ?>" disabled>
        </div>
        <div class="form-group">
            <label>パスワード（8文字以上）</label>
            <input type="password" name="password" required minlength="8" autofocus placeholder="8文字以上">
        </div>
        <div class="form-group">
            <label>パスワード（確認）</label>
            <input type="password" name="password2" required minlength="8" placeholder="もう一度入力">
        </div>
        <button type="submit" class="btn-submit">パスワードを設定してログイン</button>
    </form>
<?php endif; ?>
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

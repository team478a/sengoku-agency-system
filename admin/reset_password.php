<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();

if (isAdminLoggedIn()) {
    header('Location: /admin/dashboard.php');
    exit;
}

$db = getDB();
$token = (string)($_GET['token'] ?? ($_POST['token'] ?? ''));
$message = '';
$admin = null;

if ($token !== '' && tableHasColumn('admins', 'reset_token') && tableHasColumn('admins', 'reset_token_exp')) {
    $stmt = $db->prepare("SELECT * FROM admins WHERE reset_token = ? AND reset_token_exp > NOW() AND status = 'active' LIMIT 1");
    $stmt->execute([$token]);
    $admin = $stmt->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $admin) {
    $csrf = (string)($_POST['csrf_token'] ?? '');
    $pw1 = (string)($_POST['password'] ?? '');
    $pw2 = (string)($_POST['password2'] ?? '');

    if (!verifyCsrfToken($csrf)) {
        $message = '不正なリクエストです。画面を再読み込みして再度お試しください。';
    } elseif (strlen($pw1) < 8) {
        $message = 'パスワードは8文字以上で設定してください。';
    } elseif ($pw1 !== $pw2) {
        $message = 'パスワードが一致しません。';
    } else {
        $db->prepare("UPDATE admins SET password=?, reset_token=NULL, reset_token_exp=NULL, updated_at=NOW() WHERE id=?")
           ->execute([password_hash($pw1, PASSWORD_BCRYPT), (int)$admin['id']]);
        header('Location: /admin/login.php?reset=1');
        exit;
    }
}

$csrfToken = getCsrfToken();
$displayName = $admin ? (($admin['display_name'] ?? '') ?: $admin['username']) : '';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>管理者パスワード再設定 | 戦国経済圏</title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+JP:wght@700&family=Noto+Sans+JP:wght@400;700&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Noto Sans JP',sans-serif;background:#1a1410;color:#f5f0e8;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1rem}.wrap{width:100%;max-width:420px}.logo{text-align:center;margin-bottom:2rem}.logo p{font-family:'Noto Serif JP',serif;font-size:1.1rem;color:#e8c96e;letter-spacing:.1em}.logo small{font-size:.72rem;color:rgba(245,240,232,.4);letter-spacing:.15em;margin-top:.25rem;display:block}.card{background:#221e1a;border:1px solid rgba(201,168,76,.2);border-radius:6px;padding:2rem;box-shadow:0 8px 40px rgba(0,0,0,.4)}.card h1{font-family:'Noto Serif JP',serif;font-size:1.15rem;color:#e8c96e;margin-bottom:.65rem}.lead{font-size:.84rem;line-height:1.8;color:rgba(245,240,232,.62);margin-bottom:1.35rem}.form-group{margin-bottom:1rem}label{display:block;font-size:.75rem;color:#c9a84c;letter-spacing:.08em;margin-bottom:.4rem}input{width:100%;padding:.75rem 1rem;background:rgba(255,255,255,.05);border:1px solid rgba(201,168,76,.2);border-radius:3px;color:#f5f0e8;font-family:inherit;font-size:.9rem}input:focus{outline:none;border-color:#c9a84c;background:rgba(255,255,255,.07)}.password-toggle-wrap{position:relative;display:block;width:100%}.password-toggle-wrap input{padding-right:3rem!important}.password-toggle-btn{position:absolute;right:.45rem;top:50%;transform:translateY(-50%);border:1px solid rgba(201,168,76,.2);background:rgba(201,168,76,.12);color:#c9a84c;border-radius:3px;width:2.15rem;height:2.15rem;padding:0;display:inline-flex;align-items:center;justify-content:center;cursor:pointer}.password-toggle-btn svg{width:17px;height:17px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}.btn{width:100%;padding:.9rem;background:linear-gradient(135deg,#c9a84c,#e8c96e);color:#1a1410;font-family:'Noto Serif JP',serif;font-weight:700;font-size:.95rem;border:none;border-radius:3px;cursor:pointer;margin-top:.5rem}.btn:hover{opacity:.9}.error{background:rgba(139,26,26,.2);border:1px solid rgba(224,128,128,.3);color:#e08080;padding:.65rem .9rem;border-radius:3px;font-size:.83rem;margin-bottom:1rem;line-height:1.6}.invalid{text-align:center;color:rgba(245,240,232,.62);line-height:1.8}.back{margin-top:1rem;text-align:center;font-size:.8rem}.back a{color:#c9a84c;text-decoration:none}.back a:hover{text-decoration:underline}
</style>
</head>
<body>
<div class="wrap">
    <div class="logo"><p>⚔ 戦国経済圏</p><small>管理パネル</small></div>
    <div class="card">
        <?php if (!$admin): ?>
            <h1>リンクが無効です</h1>
            <p class="invalid">URLが正しくないか、有効期限が切れています。<br>もう一度パスワード再発行を行ってください。</p>
            <div class="back"><a href="/admin/forgot_password.php">再発行画面へ</a></div>
        <?php else: ?>
            <h1>パスワード再設定</h1>
            <p class="lead"><?= h($displayName) ?> さんの新しい管理者パスワードを設定してください。</p>
            <?php if ($message): ?><div class="error"><?= h($message) ?></div><?php endif; ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="token" value="<?= h($token) ?>">
                <div class="form-group">
                    <label>新しいパスワード（8文字以上）</label>
                    <input type="password" name="password" required minlength="8" autofocus>
                </div>
                <div class="form-group">
                    <label>新しいパスワード（確認）</label>
                    <input type="password" name="password2" required minlength="8">
                </div>
                <button type="submit" class="btn">パスワードを変更する</button>
            </form>
            <div class="back"><a href="/admin/login.php">ログイン画面へ戻る</a></div>
        <?php endif; ?>
    </div>
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

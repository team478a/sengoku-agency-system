<?php
require_once __DIR__ . '/../includes/functions.php';
startSecureSession();

if (isAdminLoggedIn()) {
    header('Location: /admin/dashboard.php');
    exit;
}

$error  = '';
$notice = (!empty($_GET['reset']) && $_GET['reset'] === '1') ? 'パスワードを変更しました。新しいパスワードでログインしてください。' : '';
$ipHash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // ② ブルートフォース対策
    if (checkLoginThrottle($ipHash, 'admin')) {
        $error = 'ログイン試行回数が上限を超えました。15分後に再試行してください。';
    } elseif ($username && $password) {
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM admins WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        $isActive = !$admin || !array_key_exists('status', $admin) || ($admin['status'] ?? 'active') === 'active';
        if ($admin && $isActive && password_verify($password, $admin['password'])) {
            clearLoginAttempts($ipHash, 'admin');
            recordLoginLog('admin', $admin['id'], $username, true);
            session_regenerate_id(true);
            $_SESSION['admin_id']   = $admin['id'];
            $_SESSION['admin_name'] = !empty($admin['display_name'] ?? '') ? $admin['display_name'] : $admin['username'];
            $_SESSION['admin_role'] = $admin['role'] ?? 'super_admin';
            header('Location: /admin/dashboard.php');
            exit;
        }
        recordLoginAttempt($ipHash, 'admin');
        recordLoginLog('admin', null, $username, false);
        $error = 'ユーザー名またはパスワードが正しくありません。';
        sleep(1);
    } else {
        $error = 'ユーザー名とパスワードを入力してください。';
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>管理画面ログイン | 戦国経済圏</title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+JP:wght@700&family=Noto+Sans+JP:wght@400;700&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Noto Sans JP',sans-serif;background:#1a1410;color:#f5f0e8;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1rem}
.wrap{width:100%;max-width:360px}
.logo{text-align:center;margin-bottom:2rem}
.logo p{font-family:'Noto Serif JP',serif;font-size:1.1rem;color:#e8c96e;letter-spacing:.1em}
.logo small{font-size:.72rem;color:rgba(245,240,232,.4);letter-spacing:.15em;margin-top:.25rem;display:block}
.card{background:#221e1a;border:1px solid rgba(201,168,76,.2);border-radius:6px;padding:2rem;box-shadow:0 8px 40px rgba(0,0,0,.4)}
.card h1{font-family:'Noto Serif JP',serif;font-size:1.15rem;color:#e8c96e;margin-bottom:1.5rem}
.form-group{margin-bottom:1rem}
label{display:block;font-size:.75rem;color:#c9a84c;letter-spacing:.08em;margin-bottom:.4rem}
input{width:100%;padding:.75rem 1rem;background:rgba(255,255,255,.05);border:1px solid rgba(201,168,76,.2);border-radius:3px;color:#f5f0e8;font-family:inherit;font-size:.9rem}
input:focus{outline:none;border-color:#c9a84c;background:rgba(255,255,255,.07)}
.password-toggle-wrap{position:relative;display:block;width:100%}
.password-toggle-wrap input{padding-right:3rem!important}
.password-toggle-btn{position:absolute;right:.45rem;top:50%;transform:translateY(-50%);border:1px solid rgba(201,168,76,.2);background:rgba(201,168,76,.12);color:#c9a84c;border-radius:3px;width:2.15rem;height:2.15rem;padding:0;display:inline-flex;align-items:center;justify-content:center;cursor:pointer}
.password-toggle-btn svg{width:17px;height:17px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.btn{width:100%;padding:.9rem;background:linear-gradient(135deg,#c9a84c,#e8c96e);color:#1a1410;font-family:'Noto Serif JP',serif;font-weight:700;font-size:.95rem;border:none;border-radius:3px;cursor:pointer;margin-top:.5rem}
.btn:hover{opacity:.9}
.error{background:rgba(139,26,26,.2);border:1px solid rgba(224,128,128,.3);color:#e08080;padding:.65rem .9rem;border-radius:3px;font-size:.83rem;margin-bottom:1rem;line-height:1.6}
.success{background:rgba(42,120,75,.2);border:1px solid rgba(92,184,122,.3);color:#80d89b;padding:.65rem .9rem;border-radius:3px;font-size:.83rem;margin-bottom:1rem;line-height:1.6}
.forgot-link{margin-top:1rem;text-align:center;font-size:.8rem}.forgot-link a{color:#c9a84c;text-decoration:none}.forgot-link a:hover{text-decoration:underline}
</style>
</head>
<body>
<div class="wrap">
    <div class="logo">
        <p>⚔ 戦国経済圏</p>
        <small>管理パネル</small>
    </div>
    <div class="card">
        <h1>ログイン</h1>
        <?php if ($notice): ?><div class="success"><?= h($notice) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
        <form method="post">
            <div class="form-group">
                <label>ユーザー名</label>
                <input type="text" name="username" required autofocus value="<?= h($_POST['username'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>パスワード</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn">ログイン</button>
        </form>
        <div class="forgot-link"><a href="/admin/forgot_password.php">パスワードを忘れた方・再発行</a></div>
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

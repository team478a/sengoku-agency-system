<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';

startSecureSession();

if (isAdminLoggedIn()) {
    header('Location: /admin/dashboard.php');
    exit;
}

$db = getDB();
$message = '';
$msgType = 'success';
$loginValue = '';

function adminResetBuildSiteUrl(PDO $db): string {
    try {
        $row = $db->query("SELECT value FROM system_settings WHERE key_name='site_url'")->fetch();
        $url = trim((string)($row['value'] ?? ''));
        if ($url !== '') {
            return rtrim($url, '/');
        }
    } catch (Throwable $e) {
        error_log('Admin password reset site_url lookup failed: ' . $e->getMessage());
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginValue = trim((string)($_POST['login'] ?? ''));
    $csrf = (string)($_POST['csrf_token'] ?? '');
    $ipHash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '');

    if (!verifyCsrfToken($csrf)) {
        $message = '不正なリクエストです。画面を再読み込みして再度お試しください。';
        $msgType = 'error';
    } elseif ($loginValue === '') {
        $message = 'メールアドレスまたはユーザー名を入力してください。';
        $msgType = 'error';
    } elseif (!tableHasColumn('admins', 'reset_token') || !tableHasColumn('admins', 'reset_token_exp')) {
        $message = '管理者パスワード再発行のDBマイグレーションが未適用です。アップデート画面からDBマイグレーションを適用してください。';
        $msgType = 'error';
    } elseif (function_exists('checkLoginThrottle') && checkLoginThrottle($ipHash, 'admin_password_reset')) {
        $message = '再発行の試行回数が多すぎます。5分後に再度お試しください。';
        $msgType = 'error';
    } else {
        if (function_exists('recordLoginAttempt')) {
            recordLoginAttempt($ipHash, 'admin_password_reset');
        }

        $stmt = $db->prepare("SELECT * FROM admins WHERE (email = ? OR username = ?) AND status = 'active' LIMIT 1");
        $stmt->execute([$loginValue, $loginValue]);
        $admin = $stmt->fetch();

        if ($admin && !empty($admin['email'])) {
            $token = bin2hex(random_bytes(32));
            $exp = date('Y-m-d H:i:s', time() + 72 * 3600);
            $db->prepare("UPDATE admins SET reset_token=?, reset_token_exp=?, updated_at=NOW() WHERE id=?")
               ->execute([$token, $exp, (int)$admin['id']]);

            $resetUrl = adminResetBuildSiteUrl($db) . '/admin/reset_password.php?token=' . $token;
            $name = (string)(($admin['display_name'] ?? '') ?: $admin['username']);
            $subject = '【戦国経済圏】管理者パスワード再設定URLのご案内';
            $text = $name . " 様\n\n"
                  . "管理画面パスワード再設定の申請を受け付けました。\n"
                  . "以下のURLから新しいパスワードを設定してください。\n\n"
                  . $resetUrl . "\n\n"
                  . "有効期限は72時間です。心当たりがない場合は、このメールを破棄してください。";
            $html = '<p>' . h($name) . ' 様</p>'
                  . '<p>管理画面パスワード再設定の申請を受け付けました。以下のURLから新しいパスワードを設定してください。</p>'
                  . '<p><a href="' . h($resetUrl) . '" style="color:#C9A84C;word-break:break-all;">' . h($resetUrl) . '</a></p>'
                  . '<p>有効期限は72時間です。心当たりがない場合は、このメールを破棄してください。</p>';

            try {
                $sent = (new Mailer())->send((string)$admin['email'], $subject, $html, $text);
                if (!$sent) {
                    error_log('Admin password reset mail was not sent for admin_id=' . (int)$admin['id']);
                }
            } catch (Throwable $e) {
                error_log('Admin password reset mail error: ' . $e->getMessage());
            }
        }

        $message = '再設定用URLの発行を受け付けました。登録済みのメールアドレス宛に案内が届きます。届かない場合は管理者へ連絡してください。';
        $msgType = 'success';
        $loginValue = '';
    }
}

$csrfToken = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>管理者パスワード再発行 | 戦国経済圏</title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+JP:wght@700&family=Noto+Sans+JP:wght@400;700&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Noto Sans JP',sans-serif;background:#1a1410;color:#f5f0e8;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1rem}
.wrap{width:100%;max-width:420px}.logo{text-align:center;margin-bottom:2rem}.logo p{font-family:'Noto Serif JP',serif;font-size:1.1rem;color:#e8c96e;letter-spacing:.1em}.logo small{font-size:.72rem;color:rgba(245,240,232,.4);letter-spacing:.15em;margin-top:.25rem;display:block}
.card{background:#221e1a;border:1px solid rgba(201,168,76,.2);border-radius:6px;padding:2rem;box-shadow:0 8px 40px rgba(0,0,0,.4)}.card h1{font-family:'Noto Serif JP',serif;font-size:1.15rem;color:#e8c96e;margin-bottom:.65rem}.lead{font-size:.84rem;line-height:1.8;color:rgba(245,240,232,.62);margin-bottom:1.35rem}
.form-group{margin-bottom:1rem}label{display:block;font-size:.75rem;color:#c9a84c;letter-spacing:.08em;margin-bottom:.4rem}input{width:100%;padding:.75rem 1rem;background:rgba(255,255,255,.05);border:1px solid rgba(201,168,76,.2);border-radius:3px;color:#f5f0e8;font-family:inherit;font-size:.9rem}input:focus{outline:none;border-color:#c9a84c;background:rgba(255,255,255,.07)}
.btn{width:100%;padding:.9rem;background:linear-gradient(135deg,#c9a84c,#e8c96e);color:#1a1410;font-family:'Noto Serif JP',serif;font-weight:700;font-size:.95rem;border:none;border-radius:3px;cursor:pointer;margin-top:.5rem}.btn:hover{opacity:.9}
.alert{padding:.65rem .9rem;border-radius:3px;font-size:.83rem;margin-bottom:1rem;line-height:1.6}.alert-success{background:rgba(42,120,75,.2);border:1px solid rgba(92,184,122,.3);color:#80d89b}.alert-error{background:rgba(139,26,26,.2);border:1px solid rgba(224,128,128,.3);color:#e08080}.back{margin-top:1rem;text-align:center;font-size:.8rem}.back a{color:#c9a84c;text-decoration:none}.back a:hover{text-decoration:underline}
</style>
</head>
<body>
<div class="wrap">
    <div class="logo"><p>⚔ 戦国経済圏</p><small>管理パネル</small></div>
    <div class="card">
        <h1>パスワード再発行</h1>
        <p class="lead">登録済みのメールアドレス、またはユーザー名を入力してください。再設定用URLをメールで送信します。</p>
        <?php if ($message): ?><div class="alert alert-<?= h($msgType) ?>"><?= h($message) ?></div><?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
            <div class="form-group">
                <label>メールアドレスまたはユーザー名</label>
                <input type="text" name="login" required autofocus value="<?= h($loginValue) ?>">
            </div>
            <button type="submit" class="btn">再設定URLを送信する</button>
        </form>
        <div class="back"><a href="/admin/login.php">ログイン画面へ戻る</a></div>
    </div>
</div>
</body>
</html>

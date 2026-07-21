<?php
$pageTitle = 'SSO連携';
$ssoLaunchBufferLevel = ob_get_level();
ob_start();
require_once __DIR__ . '/header.php';

try {
    $client = null;
    $clientKey = normalizeSsoClientKey($_GET['client'] ?? '');
    $aud = trim($_GET['aud'] ?? '');

    if ($clientKey !== '') {
        $client = getSsoClientByKey($clientKey);
    } elseif ($aud !== '') {
        $client = getSsoClientByAudience($aud);
    }

    if (!$client) {
        $legacy = getAgencySsoSettings();
        if (!empty($legacy['enabled']) && buildSsoCallbackEndpoint($legacy['callback_url']) !== '') {
            $client = [
                'client_key' => 'sengoku-rr',
                'name' => 'sengoku-rr.com',
                'audience' => $legacy['audience'],
                'callback_url' => $legacy['callback_url'],
                'status' => 'active',
            ];
        }
    }

    if (!$client) {
        throw new RuntimeException('SSO連携先が見つかりません。管理者にSSO連携設定を確認してもらってください。');
    }
    if (($client['status'] ?? '') !== 'active') {
        throw new RuntimeException('このSSO連携先は停止中です。');
    }

    $callbackUrl = buildSsoCallbackEndpoint($client['callback_url'] ?? '');
    if ($callbackUrl === '') {
        throw new RuntimeException('SSO受信URLが未設定です。管理者にSSO連携設定を確認してもらってください。');
    }

    $returnTo = $_GET['return_to'] ?? null;
    $token = buildAgencySsoJwt($currentAgent, is_string($returnTo) ? $returnTo : null, $client);
    $separator = strpos($callbackUrl, '?') === false ? '?' : '&';
    while (ob_get_level() > $ssoLaunchBufferLevel) {
        ob_end_clean();
    }
    header('Referrer-Policy: no-referrer');
    header('Location: ' . $callbackUrl . $separator . 'token=' . rawurlencode($token));
    exit;
} catch (Throwable $e) {
    error_log('SSO launch failed: ' . $e->getMessage());
    $errorMessage = $e->getMessage();
}
?>

<div class="alert alert-error"><?= h($errorMessage ?? 'SSO連携に失敗しました。') ?></div>
<div class="card">
    <p class="card-title">外部ポータルへ移動できませんでした</p>
    <p style="font-size:.86rem;color:var(--text-muted);line-height:1.8;margin-bottom:1rem;">
        連携先サイトの設定、SSO受信URL、署名鍵のいずれかが未設定または停止中の可能性があります。
        管理者に「SSO連携設定」を確認してもらってください。
    </p>
    <a href="/agent/dashboard.php" class="btn btn-outline">ダッシュボードへ戻る</a>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>

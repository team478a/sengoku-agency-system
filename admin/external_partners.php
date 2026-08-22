<?php
$pageTitle = '外部API連携';
require_once __DIR__ . '/header.php';

$db = getDB();
$csrf = getCsrfToken();
$message = '';
$msgType = 'success';
$hasTable = !empty(tableColumns('external_partner_sites'));
$hasInboundKeyColumn = $hasTable && tableHasColumn('external_partner_sites', 'inbound_api_key');
$hasEndpointSplitColumns = $hasTable
    && tableHasColumn('external_partner_sites', 'agency_sync_endpoint')
    && tableHasColumn('external_partner_sites', 'common_event_endpoint');

function adminNormalizeExternalSiteKey(string $key): string {
    $key = strtolower(trim($key));
    $key = preg_replace('/[^a-z0-9_-]+/', '-', $key);
    return trim((string)$key, '-');
}

function adminNormalizeExternalEndpoint(string $url): string {
    return rtrim(trim($url), '/');
}

function adminExternalPartnerEndpointLabel(string $baseUrl): string {
    $endpoint = buildExternalPartnerEndpoint($baseUrl);
    return $endpoint !== '' ? $endpoint : '-';
}

function adminExternalPartnerAgencyEndpointLabel(array $site): string {
    $endpoint = buildExternalPartnerAgencyEndpoint($site);
    return $endpoint !== '' ? $endpoint : '-';
}

function adminExternalPartnerEventEndpointLabel(array $site): string {
    $endpoint = buildExternalPartnerEventEndpoint($site);
    return $endpoint !== '' ? $endpoint : '-';
}

function adminGenerateExternalInboundKey(): string {
    return 'sai_' . bin2hex(random_bytes(32));
}

function adminExternalPartnerTestLabel(string $type): string {
    $labels = [
        'purchase' => '購入イベント',
        'entitlement' => '権限付与',
        'customer_sso' => '顧客SSO',
    ];
    return $labels[$type] ?? '外部連携';
}

function adminTestExternalPartnerCommonEvent(int $siteId, string $type): array {
    $site = getExternalPartnerSiteById($siteId);
    if (!$site) {
        return ['ok' => false, 'status' => 0, 'endpoint' => '', 'error' => '連携先が見つかりません。'];
    }
    $apiKey = trim((string)($site['api_key'] ?? ''));
    if ($apiKey === '' && tableHasColumn('external_partner_sites', 'inbound_api_key')) {
        $apiKey = trim((string)($site['inbound_api_key'] ?? ''));
    }
    if ($apiKey === '') {
        return ['ok' => false, 'status' => 0, 'endpoint' => '', 'error' => '外部サービス用APIキーが未設定です。'];
    }
    $endpoint = buildExternalPartnerEventEndpoint($site);
    if ($endpoint === '') {
        return ['ok' => false, 'status' => 0, 'endpoint' => '', 'error' => '送信先エンドポイントが未設定です。'];
    }

    $eventTypes = [
        'purchase' => 'purchase.completed',
        'entitlement' => 'entitlement.granted',
        'customer_sso' => 'customer_sso.token_issued',
    ];
    $eventType = $eventTypes[$type] ?? 'connection_test';
    $siteKey = (string)($site['site_key'] ?? '');
    $now = date('c');
    $payload = [
        'event' => $eventType,
        'event_type' => $eventType,
        'dry_run' => true,
        'test' => true,
        'source' => 'sengoku-ai',
        'site_key' => $siteKey,
        'system_key' => $siteKey,
        'common_user_id' => 'cu_test_' . substr(hash('sha256', $siteKey . $type), 0, 16),
        'external_user_id' => 'test-user-001',
        'agent_code' => 'agent_test',
        'project_key' => 'sengoku-influencer',
        'product_code' => 'test-product',
        'order_id' => 'test-order-' . date('YmdHis'),
        'order_item_id' => 'default',
        'amount' => 0,
        'currency' => 'JPY',
        'entitlement_status' => 'active',
        'starts_at' => $now,
        'expires_at' => date('c', strtotime('+30 days')),
        'metadata' => [
            'test_type' => $type,
            'description' => adminExternalPartnerTestLabel($type) . 'の疎通確認です。保存せず200系を返してください。',
        ],
    ];
    if ($type === 'customer_sso') {
        $payload['sso'] = [
            'token_format' => 'JWT RS256',
            'launch_url' => getSiteBaseUrl() . '/agent/sso_launch.php?client=' . rawurlencode($siteKey),
            'jwks_url' => getSiteBaseUrl() . '/.well-known/jwks.json',
            'dry_run' => true,
        ];
    }
    $payload = normalizeExternalPartnerEventPayload($eventType, $payload, [
        'correlation_id' => 'test_' . $type . '_' . date('YmdHis'),
    ]);

    return postJsonToExternalPartnerWithResult($endpoint, $apiKey, $payload, [
        'site_key' => $siteKey,
        'event_type' => $eventType,
        'event_id' => (string)$payload['event_id'],
        'idempotency_key' => 'test-' . $type . '-' . $siteId . '-' . date('YmdHis'),
        'correlation_id' => (string)$payload['correlation_id'],
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = '不正なリクエストです。';
        $msgType = 'error';
    } else {
        $action = $_POST['action'] ?? '';
        try {
            if (in_array($action, ['generate_external_api_token', 'clear_external_api_token'], true)) {
                $token = $action === 'generate_external_api_token' ? bin2hex(random_bytes(32)) : '';
                $stmt = $db->prepare("INSERT INTO system_settings (key_name, value) VALUES ('external_api_token', ?)
                                      ON DUPLICATE KEY UPDATE value=VALUES(value)");
                $stmt->execute([$token]);
                $message = $token !== '' ? 'sengoku-ai.com 受信用APIキーを発行しました。' : 'sengoku-ai.com 受信用APIキーを無効化しました。';
                $msgType = 'success';
            } elseif (!$hasTable) {
                throw new RuntimeException('外部連携先テーブルが未適用です。アップデート画面でDBマイグレーションを適用してください。');
            } elseif ($action === 'generate_partner_inbound_key') {
                if (!$hasInboundKeyColumn) {
                    throw new RuntimeException('連携先ごとの受信用APIキー列が未適用です。アップデート画面でDBマイグレーションを適用してください。');
                }
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) {
                    throw new RuntimeException('連携先が指定されていません。');
                }
                $token = adminGenerateExternalInboundKey();
                $stmt = $db->prepare("UPDATE external_partner_sites SET inbound_api_key=?, api_key=?, updated_at=NOW() WHERE id=?");
                $stmt->execute([$token, $token, $id]);
                $message = '外部サービス用APIキーを再発行しました。この1つのキーを外部サイト側の送受信設定に差し替えてください。';
                $msgType = 'success';
            } elseif ($action === 'save_partner_site') {
                $id = (int)($_POST['id'] ?? 0);
                $siteKey = adminNormalizeExternalSiteKey((string)($_POST['site_key'] ?? ''));
                $name = trim((string)($_POST['name'] ?? ''));
                $baseUrl = rtrim(trim((string)($_POST['base_url'] ?? '')), '/');
                $agencySyncEndpoint = adminNormalizeExternalEndpoint((string)($_POST['agency_sync_endpoint'] ?? ''));
                $commonEventEndpoint = adminNormalizeExternalEndpoint((string)($_POST['common_event_endpoint'] ?? ''));
                $apiKey = trim((string)($_POST['api_key'] ?? ''));
                $status = ($_POST['status'] ?? 'active') === 'active' ? 'active' : 'inactive';
                $sortOrder = (int)($_POST['sort_order'] ?? 0);

                if ($siteKey === '' || $name === '' || $baseUrl === '') {
                    throw new RuntimeException('サイトキー、連携先名、送信先URLは必須です。');
                }
                if (!filter_var($baseUrl, FILTER_VALIDATE_URL)) {
                    throw new RuntimeException('送信先URLを正しく入力してください。');
                }
                if ($hasEndpointSplitColumns) {
                    if ($agencySyncEndpoint === '') {
                        $agencySyncEndpoint = buildExternalPartnerAgencyEndpoint(['base_url' => $baseUrl]);
                    }
                    if ($commonEventEndpoint === '') {
                        $commonEventEndpoint = buildExternalPartnerEventEndpoint(['base_url' => $baseUrl]);
                    }
                    if (!filter_var($agencySyncEndpoint, FILTER_VALIDATE_URL)) {
                        throw new RuntimeException('Agency sync URL is invalid.');
                    }
                    if (!filter_var($commonEventEndpoint, FILTER_VALIDATE_URL)) {
                        throw new RuntimeException('Common event URL is invalid.');
                    }
                }
                if ($id <= 0 && !$hasInboundKeyColumn && $apiKey === '') {
                    throw new RuntimeException('新規追加時はAPIキーが必須です。DBマイグレーション適用後は自動発行できます。');
                }

                if ($id > 0) {
                    if ($hasEndpointSplitColumns && $apiKey !== '') {
                        if ($hasInboundKeyColumn) {
                            $stmt = $db->prepare("UPDATE external_partner_sites SET site_key=?, name=?, base_url=?, agency_sync_endpoint=?, common_event_endpoint=?, api_key=?, inbound_api_key=?, status=?, sort_order=?, updated_at=NOW() WHERE id=?");
                            $stmt->execute([$siteKey, $name, $baseUrl, $agencySyncEndpoint, $commonEventEndpoint, $apiKey, $apiKey, $status, $sortOrder, $id]);
                        } else {
                            $stmt = $db->prepare("UPDATE external_partner_sites SET site_key=?, name=?, base_url=?, agency_sync_endpoint=?, common_event_endpoint=?, api_key=?, status=?, sort_order=?, updated_at=NOW() WHERE id=?");
                            $stmt->execute([$siteKey, $name, $baseUrl, $agencySyncEndpoint, $commonEventEndpoint, $apiKey, $status, $sortOrder, $id]);
                        }
                    } elseif ($hasEndpointSplitColumns) {
                        $stmt = $db->prepare("UPDATE external_partner_sites SET site_key=?, name=?, base_url=?, agency_sync_endpoint=?, common_event_endpoint=?, status=?, sort_order=?, updated_at=NOW() WHERE id=?");
                        $stmt->execute([$siteKey, $name, $baseUrl, $agencySyncEndpoint, $commonEventEndpoint, $status, $sortOrder, $id]);
                    } elseif ($apiKey !== '') {
                        if ($hasInboundKeyColumn) {
                            $stmt = $db->prepare("UPDATE external_partner_sites SET site_key=?, name=?, base_url=?, api_key=?, inbound_api_key=?, status=?, sort_order=?, updated_at=NOW() WHERE id=?");
                            $stmt->execute([$siteKey, $name, $baseUrl, $apiKey, $apiKey, $status, $sortOrder, $id]);
                        } else {
                            $stmt = $db->prepare("UPDATE external_partner_sites SET site_key=?, name=?, base_url=?, api_key=?, status=?, sort_order=?, updated_at=NOW() WHERE id=?");
                            $stmt->execute([$siteKey, $name, $baseUrl, $apiKey, $status, $sortOrder, $id]);
                        }
                    } else {
                        $stmt = $db->prepare("UPDATE external_partner_sites SET site_key=?, name=?, base_url=?, status=?, sort_order=?, updated_at=NOW() WHERE id=?");
                        $stmt->execute([$siteKey, $name, $baseUrl, $status, $sortOrder, $id]);
                    }
                    $message = '連携先サイトを更新しました。';
                } else {
                    if ($hasEndpointSplitColumns && $hasInboundKeyColumn) {
                        $serviceKey = $apiKey !== '' ? $apiKey : adminGenerateExternalInboundKey();
                        $stmt = $db->prepare("INSERT INTO external_partner_sites (site_key, name, base_url, agency_sync_endpoint, common_event_endpoint, api_key, inbound_api_key, status, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$siteKey, $name, $baseUrl, $agencySyncEndpoint, $commonEventEndpoint, $serviceKey, $serviceKey, $status, $sortOrder]);
                    } elseif ($hasEndpointSplitColumns) {
                        $stmt = $db->prepare("INSERT INTO external_partner_sites (site_key, name, base_url, agency_sync_endpoint, common_event_endpoint, api_key, status, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$siteKey, $name, $baseUrl, $agencySyncEndpoint, $commonEventEndpoint, $apiKey, $status, $sortOrder]);
                    } elseif ($hasInboundKeyColumn) {
                        $serviceKey = $apiKey !== '' ? $apiKey : adminGenerateExternalInboundKey();
                        $stmt = $db->prepare("INSERT INTO external_partner_sites (site_key, name, base_url, api_key, inbound_api_key, status, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$siteKey, $name, $baseUrl, $serviceKey, $serviceKey, $status, $sortOrder]);
                    } else {
                        $stmt = $db->prepare("INSERT INTO external_partner_sites (site_key, name, base_url, api_key, status, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$siteKey, $name, $baseUrl, $apiKey, $status, $sortOrder]);
                    }
                    $message = '連携先サイトを追加しました。';
                }
            } elseif ($action === 'toggle_partner_site') {
                $id = (int)($_POST['id'] ?? 0);
                $status = ($_POST['status'] ?? '') === 'active' ? 'active' : 'inactive';
                $stmt = $db->prepare("UPDATE external_partner_sites SET status=?, updated_at=NOW() WHERE id=?");
                $stmt->execute([$status, $id]);
                $message = $status === 'active' ? '連携先を有効化しました。' : '連携先を停止しました。';
            } elseif ($action === 'delete_partner_site') {
                $id = (int)($_POST['id'] ?? 0);
                $stmt = $db->prepare("DELETE FROM external_partner_sites WHERE id=?");
                $stmt->execute([$id]);
                $message = '連携先サイトを削除しました。';
            } elseif ($action === 'test_partner_site') {
                $id = (int)($_POST['id'] ?? 0);
                $result = testExternalPartnerSiteConnection($id);
                $endpoint = (string)($result['endpoint'] ?? '');
                $status = (int)($result['status'] ?? 0);
                $response = trim((string)($result['response'] ?? ''));
                $error = trim((string)($result['error'] ?? ''));
                if (!empty($result['ok'])) {
                    $stmt = $db->prepare("UPDATE external_partner_sites SET last_test_status='success', last_test_at=NOW(), last_test_message=? WHERE id=?");
                    $stmt->execute(['HTTP ' . $status, $id]);
                    $message = '接続テストに成功しました。送信先: ' . $endpoint . ' / HTTP ' . $status;
                    $msgType = 'success';
                } else {
                    $detail = 'HTTP ' . $status;
                    if ($error !== '') $detail .= ' / ' . $error;
                    if ($response !== '') $detail .= ' / ' . $response;
                    $stmt = $db->prepare("UPDATE external_partner_sites SET last_test_status='failed', last_test_at=NOW(), last_test_message=? WHERE id=?");
                    $stmt->execute([substr($detail, 0, 500), $id]);
                    $message = '接続テストに失敗しました。送信先: ' . ($endpoint !== '' ? $endpoint : '-') . ' / ' . $detail;
                    $msgType = 'error';
                }
            } elseif (in_array($action, ['test_partner_purchase', 'test_partner_entitlement', 'test_partner_customer_sso'], true)) {
                $id = (int)($_POST['id'] ?? 0);
                $type = [
                    'test_partner_purchase' => 'purchase',
                    'test_partner_entitlement' => 'entitlement',
                    'test_partner_customer_sso' => 'customer_sso',
                ][$action];
                $label = adminExternalPartnerTestLabel($type);
                $result = adminTestExternalPartnerCommonEvent($id, $type);
                $endpoint = (string)($result['endpoint'] ?? '');
                $status = (int)($result['status'] ?? 0);
                $response = trim((string)($result['response'] ?? ''));
                $error = trim((string)($result['error'] ?? ''));
                if (!empty($result['ok'])) {
                    $stmt = $db->prepare("UPDATE external_partner_sites SET last_test_status='success', last_test_at=NOW(), last_test_message=? WHERE id=?");
                    $stmt->execute([$label . ' / HTTP ' . $status, $id]);
                    $message = $label . 'テストに成功しました。送信先: ' . $endpoint . ' / HTTP ' . $status;
                    $msgType = 'success';
                } else {
                    $detail = 'HTTP ' . $status;
                    if ($error !== '') $detail .= ' / ' . $error;
                    if ($response !== '') $detail .= ' / ' . $response;
                    $stmt = $db->prepare("UPDATE external_partner_sites SET last_test_status='failed', last_test_at=NOW(), last_test_message=? WHERE id=?");
                    $stmt->execute([substr($label . ' / ' . $detail, 0, 500), $id]);
                    $message = $label . 'テストに失敗しました。送信先: ' . ($endpoint !== '' ? $endpoint : '-') . ' / ' . $detail;
                    $msgType = 'error';
                }
            }
        } catch (Throwable $e) {
            $message = $e->getMessage();
            $msgType = 'error';
        }
    }
}

$editSite = null;
if ($hasTable && isset($_GET['edit'])) {
    $editSite = getExternalPartnerSiteById((int)$_GET['edit']);
}
$sites = $hasTable ? getExternalPartnerSites(false) : [];
$receiverApiKey = trim(getSystemSettingValue('external_api_token', ''));
$agencyIntegrationEndpoint = getSiteBaseUrl() . '/api/integrations/agencies';
$hierarchyEndpoint = getSiteBaseUrl() . '/api/hierarchy.php';
?>

<?php if ($message): ?>
<div class="alert alert-<?= h($msgType) ?>"><?= h($message) ?></div>
<?php endif; ?>

<?php if (!$hasTable): ?>
<div class="alert alert-error">外部API連携先のDBマイグレーションが未適用です。管理画面の「アップデート」からDBマイグレーションを適用してください。</div>
<?php endif; ?>
<?php if ($hasTable && !$hasInboundKeyColumn): ?>
<div class="alert alert-error">連携先ごとの外部サービス用APIキーのDBマイグレーションが未適用です。管理画面の「アップデート」からDBマイグレーションを適用してください。</div>
<?php endif; ?>

<div class="card">
    <p class="card-title">外部連携APIの使い方</p>
    <p style="font-size:.82rem;color:var(--text-muted);line-height:1.8;margin-bottom:1rem;">
        新規連携では、連携先サイトごとに1つの「外部サービス用APIキー」を使います。
        外部サイトから sengoku-ai.com へ送る時も、sengoku-ai.com から外部サイトへ送る時も同じキーで確認できます。
    </p>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:1rem;">
        <div style="border:1px solid var(--border);border-radius:6px;padding:1rem;background:rgba(201,168,76,.04);">
            <p style="font-weight:700;color:var(--gold);margin-bottom:.65rem;">基本フロー（接続先ごと）</p>
            <ol style="font-size:.78rem;color:var(--text-muted);line-height:1.85;margin:0;padding-left:1.2rem;">
                <li>この画面で連携先サイトを追加します。</li>
                <li>AI側が、その連携先専用の外部サービス用APIキーを自動発行します。</li>
                <li>発行された1つのキーを外部サイト側に登録してもらいます。</li>
                <li>接続テストで、外部サイト側が受信できるか確認します。</li>
            </ol>
        </div>

        <div style="border:1px solid var(--border);border-radius:6px;padding:1rem;background:rgba(201,168,76,.04);">
            <p style="font-weight:700;color:var(--gold);margin-bottom:.65rem;">旧共通キー（互換用）</p>
            <p style="font-size:.78rem;color:var(--text-muted);line-height:1.7;margin-bottom:.75rem;">
                過去バージョン向けの共通キーです。新しい連携では下の「連携先サイト一覧」にある、接続先ごとの外部サービス用APIキーを使ってください。
            </p>
            <div class="form-group">
                <label>sengoku-ai.com 旧共通受信用API</label>
                <input type="text" readonly value="<?= h($agencyIntegrationEndpoint) ?>" onclick="this.select()">
            </div>
            <div class="form-group">
                <label>sengoku-ai.com 旧共通受信用APIキー</label>
                <?php if ($receiverApiKey !== ''): ?>
                    <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;">
                        <input type="password" id="receiverApiKey" value="<?= h($receiverApiKey) ?>" readonly data-no-toggle="1" style="flex:1;min-width:240px;">
                        <button type="button" class="btn btn-outline" onclick="toggleSecret('receiverApiKey')">表示</button>
                        <button type="button" class="btn btn-outline" onclick="copySecret('receiverApiKey', '旧共通受信用APIキーをコピーしました。')">コピー</button>
                    </div>
                <?php else: ?>
                    <input type="text" readonly value="" placeholder="未発行">
                <?php endif; ?>
            </div>
            <div style="display:flex;gap:.65rem;flex-wrap:wrap;">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <input type="hidden" name="action" value="generate_external_api_token">
                    <button type="submit" class="btn btn-gold" onclick="return confirm('sengoku-ai.com 旧共通受信用APIキーを<?= $receiverApiKey !== '' ? '再発行' : '発行' ?>します。よろしいですか？');">
                        <?= $receiverApiKey !== '' ? '旧共通キーを再発行' : '旧共通キーを発行' ?>
                    </button>
                </form>
                <?php if ($receiverApiKey !== ''): ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <input type="hidden" name="action" value="clear_external_api_token">
                    <button type="submit" class="btn btn-danger" onclick="return confirm('sengoku-ai.com 旧共通受信用APIキーを無効化します。よろしいですか？');">無効化</button>
                </form>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<div class="card">
    <p class="card-title">外部連携APIの追加</p>
    <p style="font-size:.82rem;color:var(--text-muted);line-height:1.8;margin-bottom:1rem;">
        この画面では、sengoku-ai.com から代理店情報を送信する外部サイトを複数登録できます。
        登録された有効な連携先すべてに、承認・登録・更新・停止・削除イベントが送信されます。
    </p>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="save_partner_site">
        <input type="hidden" name="id" value="<?= (int)($editSite['id'] ?? 0) ?>">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1rem;">
            <div class="form-group">
                <label>サイトキー *</label>
                <input type="text" name="site_key" value="<?= h($editSite['site_key'] ?? '') ?>" placeholder="sengoku-rr" required>
                <p style="font-size:.72rem;color:var(--text-muted);margin-top:.3rem;">半角英数字・ハイフン・アンダーバー。後から変更しない運用を推奨。</p>
            </div>
            <div class="form-group">
                <label>連携先名 *</label>
                <input type="text" name="name" value="<?= h($editSite['name'] ?? '') ?>" placeholder="sengoku-rr.com" required>
            </div>
            <div class="form-group">
                <label>状態</label>
                <select name="status">
                    <?php $status = $editSite['status'] ?? 'active'; ?>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>有効</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>停止</option>
                </select>
            </div>
            <div class="form-group">
                <label>表示順</label>
                <input type="number" name="sort_order" value="<?= h($editSite['sort_order'] ?? '0') ?>">
            </div>
        </div>
        <div class="form-group">
            <label>送信先URL *</label>
            <input type="url" name="base_url" value="<?= h($editSite['base_url'] ?? '') ?>" placeholder="https://example.com" required>
            <?php if ($hasEndpointSplitColumns): ?>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1rem;margin-top:1rem;">
                    <div class="form-group">
                        <label>&#20195;&#29702;&#24215;&#21516;&#26399;URL</label>
                        <input type="url" name="agency_sync_endpoint" value="<?= h($editSite['agency_sync_endpoint'] ?? '') ?>" placeholder="https://example.com/api/integrations/agencies">
                        <p style="font-size:.72rem;color:var(--text-muted);line-height:1.7;margin-top:.35rem;">
                            &#20195;&#29702;&#24215;&#12398;&#30331;&#37682;&#12539;&#26356;&#26032;&#12539;&#20572;&#27490;&#12539;&#21066;&#38500;&#12434;&#36865;&#12427;URL&#12391;&#12377;&#12290;&#31354;&#27396;&#12394;&#12425;&#19978;&#12398;URL&#12363;&#12425;&#33258;&#21205;&#29983;&#25104;&#12375;&#12414;&#12377;&#12290;
                        </p>
                    </div>
                    <div class="form-group">
                        <label>&#20849;&#36890;&#12452;&#12505;&#12531;&#12488;URL</label>
                        <input type="url" name="common_event_endpoint" value="<?= h($editSite['common_event_endpoint'] ?? '') ?>" placeholder="https://example.com/api/integrations/events">
                        <p style="font-size:.72rem;color:var(--text-muted);line-height:1.7;margin-top:.35rem;">
                            &#21839;&#12356;&#21512;&#12431;&#12379;&#12539;&#20849;&#36890;&#39015;&#23458;&#12539;&#36092;&#20837;&#12539;&#27770;&#28168;&#12394;&#12393;&#12434;&#36865;&#12427;URL&#12391;&#12377;&#12290;&#31354;&#27396;&#12394;&#12425;&#19978;&#12398;URL&#12363;&#12425;&#33258;&#21205;&#29983;&#25104;&#12375;&#12414;&#12377;&#12290;
                        </p>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-warning" style="margin-top:.75rem;">
                    Endpoint split migration is not applied. Apply DB migrations from the update page.
                </div>
            <?php endif; ?>
            <p style="font-size:.72rem;color:var(--text-muted);line-height:1.7;margin-top:.35rem;">
                ドメインだけなら自動で <code>/api/integrations/agencies</code> を付与します。
                独自エンドポイントの場合は <code>https://example.com/api/integrations/agencies</code> まで入力してください。
            </p>
        </div>
        <div class="form-group">
            <label>外部サービス用APIキー（この連携先で使う1つのキー）</label>
            <?php if (!$hasInboundKeyColumn): ?>
                <input type="text" readonly value="" placeholder="DBマイグレーション適用後に利用できます">
            <?php elseif ($editSite): ?>
                <?php $inboundKey = (string)($editSite['inbound_api_key'] ?? ''); ?>
                <?php if ($inboundKey !== ''): ?>
                    <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;">
                        <input type="password" id="editInboundApiKey" value="<?= h($inboundKey) ?>" readonly data-no-toggle="1" style="flex:1;min-width:260px;">
                        <button type="button" class="btn btn-outline" onclick="toggleSecret('editInboundApiKey')">表示</button>
                        <button type="button" class="btn btn-outline" onclick="copySecret('editInboundApiKey', '外部サービス用APIキーをコピーしました。')">コピー</button>
                    </div>
                <?php else: ?>
                    <input type="text" readonly value="" placeholder="未発行です。一覧の「キー再発行」から発行してください。">
                <?php endif; ?>
            <?php else: ?>
                <input type="text" readonly value="" placeholder="追加時に自動発行されます">
            <?php endif; ?>
            <p style="font-size:.72rem;color:var(--text-muted);line-height:1.7;margin-top:.35rem;">
                外部サイトから sengoku-ai.com へ送る時も、sengoku-ai.com から外部サイトへ通知する時も、この1つのキーを使います。
                このキーを連携先の開発者へ渡してください。
            </p>
        </div>
        <div class="form-group">
            <label>外部サービス用APIキーを手動指定する場合 <?= $editSite ? '（変更時のみ）' : '（任意）' ?></label>
            <?php if ($editSite && !empty($editSite['api_key'])): ?>
                <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;">
                    <input type="password" id="currentPartnerApiKey" value="<?= h($editSite['api_key']) ?>" readonly data-no-toggle="1" style="flex:1;min-width:260px;">
                    <button type="button" class="btn btn-outline" onclick="togglePartnerApiKey()">表示</button>
                    <button type="button" class="btn btn-outline" onclick="copyPartnerApiKey()">コピー</button>
                </div>
                <p style="font-size:.72rem;color:var(--text-muted);line-height:1.7;margin-top:.35rem;">
                    現在保存されている外部サービス用APIキーです。外部サイト側の設定確認に使えます。
                </p>
                <input type="password" name="api_key" value="" placeholder="変更する場合のみ、新しい共通APIキーを入力" style="margin-top:.55rem;">
            <?php else: ?>
                <input type="password" name="api_key" value="" placeholder="空欄なら自動発行。指定した場合はこのキーを共通キーとして保存">
            <?php endif; ?>
            <p style="font-size:.72rem;color:var(--text-muted);line-height:1.7;margin-top:.35rem;">
                通常は空欄で追加してください。特定のキーを使いたい場合だけ入力します。
            </p>
        </div>
        <button type="submit" class="btn btn-gold"><?= $editSite ? '更新する' : '追加する' ?></button>
        <?php if ($editSite): ?><a href="/admin/external_partners.php" class="btn btn-outline">キャンセル</a><?php endif; ?>
    </form>
</div>

<div class="card">
    <p class="card-title">連携先サイト一覧</p>
    <div class="table-scroll">
        <table style="min-width:1180px;">
            <thead>
                <tr>
                    <th>状態</th>
                    <th>連携先</th>
                    <th>サイトキー</th>
                    <th>外部サービス用APIキー</th>
                    <th>送信先エンドポイント</th>
                    <th>接続テスト</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($sites): foreach ($sites as $site): ?>
                <tr>
                    <td><span class="badge <?= $site['status'] === 'active' ? 'badge-active' : 'badge-inactive' ?>"><?= h($site['status'] === 'active' ? '有効' : '停止') ?></span></td>
                    <td><strong><?= h($site['name']) ?></strong></td>
                    <td><code><?= h($site['site_key']) ?></code></td>
                    <td>
                        <?php if ($hasInboundKeyColumn): ?>
                            <?php $siteInboundId = 'siteInboundKey' . (int)$site['id']; ?>
                            <?php $siteInboundKey = (string)($site['inbound_api_key'] ?? ''); ?>
                            <?php if ($siteInboundKey !== ''): ?>
                                <div style="display:flex;gap:.35rem;align-items:center;flex-wrap:wrap;min-width:220px;">
                                    <input type="password" id="<?= h($siteInboundId) ?>" value="<?= h($siteInboundKey) ?>" readonly data-no-toggle="1" style="max-width:170px;">
                                    <button type="button" class="btn btn-outline btn-sm" onclick="toggleSecret('<?= h($siteInboundId) ?>')">表示</button>
                                    <button type="button" class="btn btn-outline btn-sm" onclick="copySecret('<?= h($siteInboundId) ?>', '外部サービス用APIキーをコピーしました。')">コピー</button>
                                </div>
                            <?php else: ?>
                                <span style="font-size:.78rem;color:var(--text-muted);">未発行</span>
                            <?php endif; ?>
                            <form method="post" style="display:inline-block;margin-top:.35rem;" onsubmit="return confirm('この連携先専用の外部サービス用APIキーを再発行します。外部サイト側の差し替えが必要です。よろしいですか？');">
                                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                <input type="hidden" name="action" value="generate_partner_inbound_key">
                                <input type="hidden" name="id" value="<?= (int)$site['id'] ?>">
                                <button type="submit" class="btn btn-outline btn-sm">キー再発行</button>
                            </form>
                        <?php else: ?>
                            <span style="font-size:.78rem;color:var(--text-muted);">未対応</span>
                        <?php endif; ?>
                    </td>
                    <td style="word-break:break-all;min-width:280px;">
                        <?php if ($hasEndpointSplitColumns): ?>
                            <div><strong>&#20195;&#29702;&#24215;:</strong> <?= h(adminExternalPartnerAgencyEndpointLabel($site)) ?></div>
                            <div style="margin-top:.35rem;"><strong>&#12452;&#12505;&#12531;&#12488;:</strong> <?= h(adminExternalPartnerEventEndpointLabel($site)) ?></div>
                        <?php else: ?>
                            <?= h(adminExternalPartnerEndpointLabel($site['base_url'])) ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($site['last_test_at'])): ?>
                            <span style="font-size:.78rem;color:var(--text-muted);"><?= h($site['last_test_status'] ?? '-') ?> / <?= h(date('m/d H:i', strtotime($site['last_test_at']))) ?></span>
                        <?php else: ?>
                            <span style="font-size:.78rem;color:var(--text-muted);">未実行</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="display:flex;gap:.35rem;flex-wrap:wrap;min-width:190px;">
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                <input type="hidden" name="action" value="test_partner_site">
                                <input type="hidden" name="id" value="<?= (int)$site['id'] ?>">
                                <button type="submit" class="btn btn-outline btn-sm">基本</button>
                            </form>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                <input type="hidden" name="action" value="test_partner_purchase">
                                <input type="hidden" name="id" value="<?= (int)$site['id'] ?>">
                                <button type="submit" class="btn btn-outline btn-sm">購入</button>
                            </form>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                <input type="hidden" name="action" value="test_partner_entitlement">
                                <input type="hidden" name="id" value="<?= (int)$site['id'] ?>">
                                <button type="submit" class="btn btn-outline btn-sm">権限</button>
                            </form>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                <input type="hidden" name="action" value="test_partner_customer_sso">
                                <input type="hidden" name="id" value="<?= (int)$site['id'] ?>">
                                <button type="submit" class="btn btn-outline btn-sm">顧客SSO</button>
                            </form>
                        </div>
                    </td>
                    <td>
                        <a href="/admin/external_partners.php?edit=<?= (int)$site['id'] ?>" class="btn btn-outline btn-sm">編集</a>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                            <input type="hidden" name="action" value="toggle_partner_site">
                            <input type="hidden" name="id" value="<?= (int)$site['id'] ?>">
                            <input type="hidden" name="status" value="<?= $site['status'] === 'active' ? 'inactive' : 'active' ?>">
                            <button type="submit" class="btn btn-outline btn-sm"><?= $site['status'] === 'active' ? '停止' : '有効化' ?></button>
                        </form>
                        <form method="post" style="display:inline;" onsubmit="return confirm('この連携先を削除します。よろしいですか？');">
                            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                            <input type="hidden" name="action" value="delete_partner_site">
                            <input type="hidden" name="id" value="<?= (int)$site['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">削除</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:2rem;">連携先サイトが未登録です。</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <p class="card-title">外部開発者へ伝えるURL</p>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1rem;">
        <div class="form-group">
            <label>sengoku-ai.com 受信用API</label>
            <input type="text" readonly value="<?= h($agencyIntegrationEndpoint) ?>" onclick="this.select()">
        </div>
        <div class="form-group">
            <label>階層取得API</label>
            <input type="text" readonly value="<?= h($hierarchyEndpoint) ?>" onclick="this.select()">
        </div>
    </div>
    <p style="font-size:.78rem;color:var(--text-muted);line-height:1.8;">
        外部サイト側から sengoku-ai.com に送信する場合も、sengoku-ai.com から外部サイトへ通知する場合も、
        連携先サイト一覧の「外部サービス用APIキー」を使います。
    </p>
</div>

<div class="card">
    <p class="card-title">外部開発者向けMDダウンロード</p>
    <p style="font-size:.9rem;color:var(--text-muted);line-height:1.8;">
        外部会社へ渡す連携資料をMarkdown形式でダウンロードできます。まずは「外部開発者向けMD」を渡してください。
    </p>
    <div style="display:flex;flex-wrap:wrap;gap:.75rem;">
        <a class="btn" href="/admin/developer_docs_download.php?doc=handoff">外部開発者向けMDをダウンロード</a>
        <a class="btn btn-outline" href="/admin/developer_docs_download.php?doc=setup-flow">セットアップ手順MD</a>
        <a class="btn btn-outline" href="/admin/developer_docs_download.php?doc=full-guide">詳細仕様MD</a>
    </div>
</div>

<script>
function toggleSecret(id) {
    const input = document.getElementById(id);
    if (!input) return;
    input.type = input.type === 'password' ? 'text' : 'password';
}

async function copySecret(id, message) {
    const input = document.getElementById(id);
    if (!input) return;
    try {
        await navigator.clipboard.writeText(input.value);
        alert(message || 'コピーしました。');
    } catch (e) {
        input.type = 'text';
        input.select();
        document.execCommand('copy');
        alert(message || 'コピーしました。');
    }
}

function togglePartnerApiKey() {
    toggleSecret('currentPartnerApiKey');
}

function copyPartnerApiKey() {
    copySecret('currentPartnerApiKey', '外部サービス用APIキーをコピーしました。');
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>

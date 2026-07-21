<?php
$pageTitle = '外部API連携';
require_once __DIR__ . '/header.php';

$db = getDB();
$csrf = getCsrfToken();
$message = '';
$msgType = 'success';
$hasTable = !empty(tableColumns('external_partner_sites'));
$hasInboundKeyColumn = $hasTable && tableHasColumn('external_partner_sites', 'inbound_api_key');

function adminNormalizeExternalSiteKey(string $key): string {
    $key = strtolower(trim($key));
    $key = preg_replace('/[^a-z0-9_-]+/', '-', $key);
    return trim((string)$key, '-');
}

function adminExternalPartnerEndpointLabel(string $baseUrl): string {
    $endpoint = buildExternalPartnerEndpoint($baseUrl);
    return $endpoint !== '' ? $endpoint : '-';
}

function adminGenerateExternalInboundKey(): string {
    return 'sai_' . bin2hex(random_bytes(32));
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
                $stmt = $db->prepare("UPDATE external_partner_sites SET inbound_api_key=?, updated_at=NOW() WHERE id=?");
                $stmt->execute([$token, $id]);
                $message = 'この連携先専用のAI発行キーを再発行しました。外部サイト側の設定も新しいキーへ差し替えてください。';
                $msgType = 'success';
            } elseif ($action === 'save_partner_site') {
                $id = (int)($_POST['id'] ?? 0);
                $siteKey = adminNormalizeExternalSiteKey((string)($_POST['site_key'] ?? ''));
                $name = trim((string)($_POST['name'] ?? ''));
                $baseUrl = rtrim(trim((string)($_POST['base_url'] ?? '')), '/');
                $apiKey = trim((string)($_POST['api_key'] ?? ''));
                $status = ($_POST['status'] ?? 'active') === 'active' ? 'active' : 'inactive';
                $sortOrder = (int)($_POST['sort_order'] ?? 0);

                if ($siteKey === '' || $name === '' || $baseUrl === '') {
                    throw new RuntimeException('サイトキー、連携先名、送信先URLは必須です。');
                }
                if (!filter_var($baseUrl, FILTER_VALIDATE_URL)) {
                    throw new RuntimeException('送信先URLを正しく入力してください。');
                }
                if ($id <= 0 && $apiKey === '') {
                    throw new RuntimeException('新規追加時は、連携先サイトが発行した送信用APIキーが必須です。');
                }

                if ($id > 0) {
                    if ($apiKey !== '') {
                        $stmt = $db->prepare("UPDATE external_partner_sites SET site_key=?, name=?, base_url=?, api_key=?, status=?, sort_order=?, updated_at=NOW() WHERE id=?");
                        $stmt->execute([$siteKey, $name, $baseUrl, $apiKey, $status, $sortOrder, $id]);
                    } else {
                        $stmt = $db->prepare("UPDATE external_partner_sites SET site_key=?, name=?, base_url=?, status=?, sort_order=?, updated_at=NOW() WHERE id=?");
                        $stmt->execute([$siteKey, $name, $baseUrl, $status, $sortOrder, $id]);
                    }
                    $message = '連携先サイトを更新しました。';
                } else {
                    if ($hasInboundKeyColumn) {
                        $inboundKey = adminGenerateExternalInboundKey();
                        $stmt = $db->prepare("INSERT INTO external_partner_sites (site_key, name, base_url, api_key, inbound_api_key, status, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$siteKey, $name, $baseUrl, $apiKey, $inboundKey, $status, $sortOrder]);
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
<div class="alert alert-error">連携先ごとのAI発行キーのDBマイグレーションが未適用です。管理画面の「アップデート」からDBマイグレーションを適用してください。</div>
<?php endif; ?>

<div class="card">
    <p class="card-title">外部連携APIの使い方</p>
    <p style="font-size:.82rem;color:var(--text-muted);line-height:1.8;margin-bottom:1rem;">
        APIキーは連携先サイトごとに2つ管理します。外部サイトから sengoku-ai.com へ送ってもらう時は「AI側が発行するキー」、
        sengoku-ai.com から外部サイトへ送る時は「連携先サイトが発行するキー」を使います。
    </p>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:1rem;">
        <div style="border:1px solid var(--border);border-radius:6px;padding:1rem;background:rgba(201,168,76,.04);">
            <p style="font-weight:700;color:var(--gold);margin-bottom:.65rem;">基本フロー（接続先ごと）</p>
            <ol style="font-size:.78rem;color:var(--text-muted);line-height:1.85;margin:0;padding-left:1.2rem;">
                <li>この画面で連携先サイトを追加します。</li>
                <li>AI側が、その連携先専用の受信用APIキーを自動発行します。</li>
                <li>発行されたキーを外部サイト側に登録してもらいます。</li>
                <li>外部サイト側で発行されたキーを、この画面へ登録します。</li>
            </ol>
        </div>

        <div style="border:1px solid var(--border);border-radius:6px;padding:1rem;background:rgba(201,168,76,.04);">
            <p style="font-weight:700;color:var(--gold);margin-bottom:.65rem;">旧共通キー（互換用）</p>
            <p style="font-size:.78rem;color:var(--text-muted);line-height:1.7;margin-bottom:.75rem;">
                過去バージョン向けの共通キーです。新しい連携では下の「連携先サイト一覧」にある、接続先ごとのAI発行キーを使ってください。
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
            <p style="font-size:.72rem;color:var(--text-muted);line-height:1.7;margin-top:.35rem;">
                ドメインだけなら自動で <code>/api/integrations/agencies</code> を付与します。
                独自エンドポイントの場合は <code>https://example.com/api/integrations/agencies</code> まで入力してください。
            </p>
        </div>
        <div class="form-group">
            <label>AI側が発行する受信用APIキー（この連携先専用）</label>
            <?php if (!$hasInboundKeyColumn): ?>
                <input type="text" readonly value="" placeholder="DBマイグレーション適用後に利用できます">
            <?php elseif ($editSite): ?>
                <?php $inboundKey = (string)($editSite['inbound_api_key'] ?? ''); ?>
                <?php if ($inboundKey !== ''): ?>
                    <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;">
                        <input type="password" id="editInboundApiKey" value="<?= h($inboundKey) ?>" readonly data-no-toggle="1" style="flex:1;min-width:260px;">
                        <button type="button" class="btn btn-outline" onclick="toggleSecret('editInboundApiKey')">表示</button>
                        <button type="button" class="btn btn-outline" onclick="copySecret('editInboundApiKey', 'AI発行キーをコピーしました。')">コピー</button>
                    </div>
                <?php else: ?>
                    <input type="text" readonly value="" placeholder="未発行です。一覧の「AIキー再発行」から発行してください。">
                <?php endif; ?>
            <?php else: ?>
                <input type="text" readonly value="" placeholder="追加時に自動発行されます">
            <?php endif; ?>
            <p style="font-size:.72rem;color:var(--text-muted);line-height:1.7;margin-top:.35rem;">
                外部サイトから sengoku-ai.com へ送信する時に使うキーです。このキーを連携先の開発者へ渡してください。
            </p>
        </div>
        <div class="form-group">
            <label>連携先サイトが発行した送信用APIキー <?= $editSite ? '（確認・変更）' : '*' ?></label>
            <?php if ($editSite && !empty($editSite['api_key'])): ?>
                <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;">
                    <input type="password" id="currentPartnerApiKey" value="<?= h($editSite['api_key']) ?>" readonly data-no-toggle="1" style="flex:1;min-width:260px;">
                    <button type="button" class="btn btn-outline" onclick="togglePartnerApiKey()">表示</button>
                    <button type="button" class="btn btn-outline" onclick="copyPartnerApiKey()">コピー</button>
                </div>
                <p style="font-size:.72rem;color:var(--text-muted);line-height:1.7;margin-top:.35rem;">
                    現在保存されているAPIキーです。外部サイト側の設定確認に使えます。
                </p>
                <input type="password" name="api_key" value="" placeholder="変更する場合のみ、新しいAPIキーを入力" style="margin-top:.55rem;">
            <?php else: ?>
                <input type="password" name="api_key" value="" placeholder="連携先サイト側で発行されたAPIキー">
            <?php endif; ?>
            <p style="font-size:.72rem;color:var(--text-muted);line-height:1.7;margin-top:.35rem;">
                これは「AIから外部サイトへ送信する時」に使うキーです。上のAI発行キーとは逆方向のキーです。
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
                    <th>AI発行キー</th>
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
                                    <button type="button" class="btn btn-outline btn-sm" onclick="copySecret('<?= h($siteInboundId) ?>', 'AI発行キーをコピーしました。')">コピー</button>
                                </div>
                            <?php else: ?>
                                <span style="font-size:.78rem;color:var(--text-muted);">未発行</span>
                            <?php endif; ?>
                            <form method="post" style="display:inline-block;margin-top:.35rem;" onsubmit="return confirm('この連携先専用のAI発行キーを再発行します。外部サイト側の差し替えが必要です。よろしいですか？');">
                                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                <input type="hidden" name="action" value="generate_partner_inbound_key">
                                <input type="hidden" name="id" value="<?= (int)$site['id'] ?>">
                                <button type="submit" class="btn btn-outline btn-sm">AIキー再発行</button>
                            </form>
                        <?php else: ?>
                            <span style="font-size:.78rem;color:var(--text-muted);">未対応</span>
                        <?php endif; ?>
                    </td>
                    <td style="word-break:break-all;"><?= h(adminExternalPartnerEndpointLabel($site['base_url'])) ?></td>
                    <td>
                        <?php if (!empty($site['last_test_at'])): ?>
                            <span style="font-size:.78rem;color:var(--text-muted);"><?= h($site['last_test_status'] ?? '-') ?> / <?= h(date('m/d H:i', strtotime($site['last_test_at']))) ?></span>
                        <?php else: ?>
                            <span style="font-size:.78rem;color:var(--text-muted);">未実行</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="/admin/external_partners.php?edit=<?= (int)$site['id'] ?>" class="btn btn-outline btn-sm">編集</a>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                            <input type="hidden" name="action" value="test_partner_site">
                            <input type="hidden" name="id" value="<?= (int)$site['id'] ?>">
                            <button type="submit" class="btn btn-outline btn-sm">接続テスト</button>
                        </form>
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
        外部サイト側から sengoku-ai.com に送信する場合は、連携先サイト一覧の「AI発行キー」を使います。
        「連携先サイトが発行した送信用APIキー」は、sengoku-ai.com から各外部サイトへ送信するためのキーです。
    </p>
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
    copySecret('currentPartnerApiKey', '連携先サイトのAPIキーをコピーしました。');
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>

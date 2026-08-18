<?php
$pageTitle = '外部連携セットアップ';

require_once __DIR__ . '/../includes/functions.php';
requireAdminLogin();

$db = getDB();
$csrf = getCsrfToken();
$message = '';
$msgType = 'success';
$createdPartner = null;

if (!function_exists('adminWizardNormalizeSiteKey')) {
    function adminWizardNormalizeSiteKey(string $key): string {
        $key = strtolower(trim($key));
        $key = preg_replace('/[^a-z0-9_-]+/', '-', $key) ?? '';
        return trim($key, '-');
    }
}

if (!function_exists('adminWizardGenerateApiKey')) {
    function adminWizardGenerateApiKey(): string {
        return 'sai_' . bin2hex(random_bytes(32));
    }
}

if (!function_exists('adminWizardInsertExternalPartner')) {
    function adminWizardInsertExternalPartner(array $data): int {
        $db = getDB();
        $columns = tableColumns('external_partner_sites');
        if (!$columns) {
            throw new RuntimeException('外部連携先テーブルが未適用です。管理画面の「アップデート」からDBマイグレーションを適用してください。');
        }

        $values = [];
        foreach ($data as $column => $value) {
            if (isset($columns[$column])) {
                $values[$column] = $value;
            }
        }

        $names = array_keys($values);
        $placeholders = array_fill(0, count($names), '?');
        $sql = 'INSERT INTO external_partner_sites (' . implode(', ', $names) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $db->prepare($sql);
        $stmt->execute(array_values($values));
        return (int)$db->lastInsertId();
    }
}

if (!function_exists('adminWizardUpdateConnectionTestResult')) {
    function adminWizardUpdateConnectionTestResult(int $siteId, bool $ok, string $detail): void {
        $columns = tableColumns('external_partner_sites');
        $sets = [];
        $values = [];

        if (isset($columns['last_test_status'])) {
            $sets[] = 'last_test_status=?';
            $values[] = $ok ? 'success' : 'failed';
        }
        if (isset($columns['last_test_at'])) {
            $sets[] = 'last_test_at=NOW()';
        }
        if (isset($columns['last_test_message'])) {
            $sets[] = 'last_test_message=?';
            $values[] = safeTextSubstr($detail, 0, 500);
        }

        if (!$sets) {
            return;
        }

        $values[] = $siteId;
        $stmt = getDB()->prepare('UPDATE external_partner_sites SET ' . implode(', ', $sets) . ' WHERE id=?');
        $stmt->execute($values);
    }
}

$hasExternalPartnerTable = !empty(tableColumns('external_partner_sites'));
$baseUrl = rtrim(getSiteBaseUrl(), '/');
$agencyReceiveEndpoint = $baseUrl . '/api/integrations/agencies';
$eventReceiveEndpoint = $baseUrl . '/api/integrations/events';
$hierarchyEndpoint = $baseUrl . '/api/hierarchy.php';
$commonUserResolveEndpoint = $baseUrl . '/api/common-users/resolve';
$referralCaptureEndpoint = $baseUrl . '/api/referrals/capture';
$referralConfirmEndpoint = $baseUrl . '/api/referrals/confirm';
$ssoLaunchEndpoint = $baseUrl . '/agent/sso_launch.php?client={site_key}';
$jwksEndpoint = $baseUrl . '/api/sso/jwks.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            throw new RuntimeException('不正なリクエストです。画面を再読み込みしてから、もう一度お試しください。');
        }
        if (!$hasExternalPartnerTable) {
            throw new RuntimeException('外部連携先テーブルが未適用です。管理画面の「アップデート」からDBマイグレーションを適用してください。');
        }

        $action = (string)($_POST['action'] ?? 'create_partner_site');

        if ($action === 'test_partner_site') {
            $siteId = (int)($_POST['id'] ?? 0);
            if ($siteId <= 0) {
                throw new RuntimeException('接続テストの対象が正しくありません。');
            }

            $result = testExternalPartnerSiteConnection($siteId);
            $ok = !empty($result['ok']);
            $status = (int)($result['status'] ?? 0);
            $detail = trim('HTTP ' . $status . ' ' . (string)($result['error'] ?? $result['response'] ?? ''));
            adminWizardUpdateConnectionTestResult($siteId, $ok, $detail);

            $message = $ok ? '接続テストに成功しました。' : '接続テストに失敗しました。' . ($detail !== '' ? ' ' . $detail : '');
            $msgType = $ok ? 'success' : 'error';
        } else {
            $siteKey = adminWizardNormalizeSiteKey((string)($_POST['site_key'] ?? ''));
            $name = trim((string)($_POST['name'] ?? ''));
            $basePartnerUrl = rtrim(trim((string)($_POST['base_url'] ?? '')), '/');

            if ($siteKey === '' || $name === '' || $basePartnerUrl === '') {
                throw new RuntimeException('サイトキー、連携先名、送信先URLは必須です。');
            }
            if (!filter_var($basePartnerUrl, FILTER_VALIDATE_URL)) {
                throw new RuntimeException('送信先URLを正しく入力してください。例: https://example.com');
            }

            $apiKey = adminWizardGenerateApiKey();
            $agencyEndpoint = buildExternalPartnerAgencyEndpoint(['base_url' => $basePartnerUrl]);
            $eventEndpoint = buildExternalPartnerEventEndpoint(['base_url' => $basePartnerUrl]);

            $siteId = adminWizardInsertExternalPartner([
                'site_key' => $siteKey,
                'name' => $name,
                'base_url' => $basePartnerUrl,
                'agency_sync_endpoint' => $agencyEndpoint,
                'common_event_endpoint' => $eventEndpoint,
                'api_key' => $apiKey,
                'inbound_api_key' => $apiKey,
                'status' => 'active',
                'sort_order' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $createdPartner = [
                'id' => $siteId,
                'site_key' => $siteKey,
                'api_key' => $apiKey,
                'agency_endpoint' => $agencyEndpoint,
                'event_endpoint' => $eventEndpoint,
            ];
            $message = '外部連携先を追加しました。APIキーを外部開発者へ共有してください。';
        }
    } catch (Throwable $e) {
        $message = $e instanceof PDOException
            ? '保存に失敗しました。サイトキーの重複、またはDB項目不足の可能性があります。'
            : $e->getMessage();
        $msgType = 'error';
    }
}

$partners = $hasExternalPartnerTable ? getExternalPartnerSites(false) : [];

require_once __DIR__ . '/header.php';
?>

<?php if ($message): ?>
    <div class="alert alert-<?= h($msgType) ?>"><?= h($message) ?></div>
<?php endif; ?>

<?php if (!$hasExternalPartnerTable): ?>
    <div class="alert alert-error">
        外部連携先テーブルが未適用です。管理画面の「アップデート」からDBマイグレーションを適用してください。
    </div>
<?php endif; ?>

<div class="card">
    <p class="card-title">外部連携の進め方</p>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;">
        <div class="mini-card">
            <strong>1. 連携先を登録</strong>
            <p class="muted">サイトキー、連携先名、送信先URLを登録します。</p>
        </div>
        <div class="mini-card">
            <strong>2. APIキーを共有</strong>
            <p class="muted">連携先ごとに発行したAPIキーを外部開発者へ渡します。</p>
        </div>
        <div class="mini-card">
            <strong>3. project_keyを共有</strong>
            <p class="muted">商品やLPはAPIキーを増やさず、プロジェクト管理のスラッグで区別します。</p>
        </div>
        <div class="mini-card">
            <strong>4. 接続テスト</strong>
            <p class="muted">登録後に外部サイトへテスト送信し、疎通を確認します。</p>
        </div>
    </div>
</div>

<?php if ($createdPartner): ?>
    <div class="card">
        <p class="card-title">外部開発者へ渡す情報</p>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1rem;">
            <div class="form-group">
                <label>サイトキー</label>
                <div class="copy-row">
                    <input id="createdSiteKey" type="text" readonly value="<?= h($createdPartner['site_key']) ?>">
                    <button type="button" class="btn btn-outline" onclick="copyValue('createdSiteKey')">コピー</button>
                </div>
            </div>
            <div class="form-group">
                <label>APIキー</label>
                <div class="copy-row">
                    <input id="createdApiKey" type="password" readonly value="<?= h($createdPartner['api_key']) ?>">
                    <button type="button" class="btn btn-outline" onclick="toggleSecret('createdApiKey')">表示</button>
                    <button type="button" class="btn btn-outline" onclick="copyValue('createdApiKey')">コピー</button>
                </div>
            </div>
            <div class="form-group">
                <label>共通顧客ID解決API</label>
                <div class="copy-row">
                    <input id="commonUserResolveEndpoint" type="text" readonly value="<?= h($commonUserResolveEndpoint) ?>">
                    <button type="button" class="btn btn-outline" onclick="copyValue('commonUserResolveEndpoint')">コピー</button>
                </div>
            </div>
            <div class="form-group">
                <label>紹介流入API</label>
                <div class="copy-row">
                    <input id="referralCaptureEndpoint" type="text" readonly value="<?= h($referralCaptureEndpoint) ?>">
                    <button type="button" class="btn btn-outline" onclick="copyValue('referralCaptureEndpoint')">コピー</button>
                </div>
            </div>
            <div class="form-group">
                <label>成果確定API</label>
                <div class="copy-row">
                    <input id="referralConfirmEndpoint" type="text" readonly value="<?= h($referralConfirmEndpoint) ?>">
                    <button type="button" class="btn btn-outline" onclick="copyValue('referralConfirmEndpoint')">コピー</button>
                </div>
            </div>
            <div class="form-group">
                <label>代理店同期API</label>
                <div class="copy-row">
                    <input id="agencyReceiveEndpoint" type="text" readonly value="<?= h($agencyReceiveEndpoint) ?>">
                    <button type="button" class="btn btn-outline" onclick="copyValue('agencyReceiveEndpoint')">コピー</button>
                </div>
            </div>
            <div class="form-group">
                <label>共通イベントAPI</label>
                <div class="copy-row">
                    <input id="eventReceiveEndpoint" type="text" readonly value="<?= h($eventReceiveEndpoint) ?>">
                    <button type="button" class="btn btn-outline" onclick="copyValue('eventReceiveEndpoint')">コピー</button>
                </div>
            </div>
            <div class="form-group">
                <label>階層取得API</label>
                <div class="copy-row">
                    <input id="hierarchyEndpoint" type="text" readonly value="<?= h($hierarchyEndpoint) ?>">
                    <button type="button" class="btn btn-outline" onclick="copyValue('hierarchyEndpoint')">コピー</button>
                </div>
            </div>
            <div class="form-group">
                <label>SSO起動URL</label>
                <div class="copy-row">
                    <input id="ssoLaunchEndpoint" type="text" readonly value="<?= h(str_replace('{site_key}', $createdPartner['site_key'], $ssoLaunchEndpoint)) ?>">
                    <button type="button" class="btn btn-outline" onclick="copyValue('ssoLaunchEndpoint')">コピー</button>
                </div>
            </div>
            <div class="form-group">
                <label>JWKS URL</label>
                <div class="copy-row">
                    <input id="jwksEndpoint" type="text" readonly value="<?= h($jwksEndpoint) ?>">
                    <button type="button" class="btn btn-outline" onclick="copyValue('jwksEndpoint')">コピー</button>
                </div>
            </div>
        </div>
        <div class="notice-box">
            <strong>外部開発者へ伝えるポイント</strong>
            <ul>
                <li>認証は <code>x-api-key</code> または <code>Authorization: Bearer</code> で、上のAPIキーを使います。</li>
                <li>共通顧客IDは <code>/api/common-users/resolve</code> が正式パスです。<code>/api/v2/common-users/resolve</code> は案内しません。</li>
                <li>商品やLPが複数ある場合は、プロジェクト管理の <code>project_key</code> を必ず送信します。</li>
                <li>紹介URLから外部サイトへ移動する場合は、最初に紹介流入API、登録・購入完了後に成果確定APIを呼びます。</li>
            </ul>
        </div>
    </div>
<?php endif; ?>

<div class="card">
    <p class="card-title">外部連携先を追加</p>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="create_partner_site">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1rem;">
            <div class="form-group">
                <label>サイトキー *</label>
                <input type="text" name="site_key" placeholder="sengoku-passport" required>
                <p class="muted">半角英数字、ハイフン、アンダーバー。後から変更しない運用を推奨します。</p>
            </div>
            <div class="form-group">
                <label>連携先名 *</label>
                <input type="text" name="name" placeholder="戦国パスポート" required>
            </div>
            <div class="form-group">
                <label>送信先URL *</label>
                <input type="url" name="base_url" placeholder="https://example.com" required>
                <p class="muted">ドメインだけなら、代理店同期は <code>/api/integrations/agencies</code>、共通イベントは <code>/api/integrations/events</code> を自動で使います。</p>
            </div>
        </div>
        <button type="submit" class="btn btn-gold" <?= !$hasExternalPartnerTable ? 'disabled' : '' ?>>連携先を追加してAPIキーを発行</button>
        <a href="/admin/external_partners.php" class="btn btn-outline">詳細設定へ</a>
        <a href="/admin/projects.php" class="btn btn-outline">project_keyを確認</a>
    </form>
</div>

<div class="card">
    <p class="card-title">外部開発者へ渡す共通API一覧</p>
    <div class="table-scroll">
        <table style="min-width:920px;">
            <thead>
                <tr>
                    <th>用途</th>
                    <th>URL</th>
                    <th>いつ使うか</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>共通顧客ID解決</td>
                    <td><code><?= h($commonUserResolveEndpoint) ?></code></td>
                    <td>外部サービス側でユーザー登録・ログイン・購入者照合を行うとき</td>
                </tr>
                <tr>
                    <td>紹介流入</td>
                    <td><code><?= h($referralCaptureEndpoint) ?></code></td>
                    <td>代理店LP・紹介URLから外部サービスへ遷移したとき</td>
                </tr>
                <tr>
                    <td>成果確定</td>
                    <td><code><?= h($referralConfirmEndpoint) ?></code></td>
                    <td>登録・購入・申込が完了したとき</td>
                </tr>
                <tr>
                    <td>代理店同期</td>
                    <td><code><?= h($agencyReceiveEndpoint) ?></code></td>
                    <td>外部サービス側で代理店候補や代理店情報を登録・更新するとき</td>
                </tr>
                <tr>
                    <td>共通イベント</td>
                    <td><code><?= h($eventReceiveEndpoint) ?></code></td>
                    <td>購入、予約、会員状態変更などを代理店システムへ通知するとき</td>
                </tr>
                <tr>
                    <td>階層取得</td>
                    <td><code><?= h($hierarchyEndpoint) ?></code></td>
                    <td>外部サービス側で代理店の親子関係を参照するとき</td>
                </tr>
            </tbody>
        </table>
    </div>
    <p class="muted">同じ外部サービス内で商品が増えても、連携先設定とAPIキーは増やさず、<code>project_key</code> と <code>product_code</code> で区別します。</p>
</div>

<div class="card">
    <p class="card-title">登録済み連携先</p>
    <div class="table-scroll">
        <table style="min-width:920px;">
            <thead>
                <tr>
                    <th>状態</th>
                    <th>連携先</th>
                    <th>送信先</th>
                    <th>前回テスト</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($partners): ?>
                    <?php foreach ($partners as $site): ?>
                        <?php
                        $testLabel = '未実行';
                        if (!empty($site['last_test_at'])) {
                            $testLabel = (string)($site['last_test_status'] ?? '-') . ' ' . date('m/d H:i', strtotime((string)$site['last_test_at']));
                        }
                        ?>
                        <tr>
                            <td><?= h(($site['status'] ?? '') === 'active' ? '有効' : '停止') ?></td>
                            <td><strong><?= h($site['name'] ?? '') ?></strong><br><code><?= h($site['site_key'] ?? '') ?></code></td>
                            <td><code><?= h(buildExternalPartnerAgencyEndpoint($site)) ?></code></td>
                            <td><?= h($testLabel) ?></td>
                            <td>
                                <a class="btn btn-outline" href="/admin/external_partners.php?edit=<?= (int)$site['id'] ?>">編集</a>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                    <input type="hidden" name="action" value="test_partner_site">
                                    <input type="hidden" name="id" value="<?= (int)$site['id'] ?>">
                                    <button type="submit" class="btn btn-outline" onclick="return confirm('接続テストを実行しますか？')">接続テスト</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5">連携先はまだ登録されていません。</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.mini-card {
    border: 1px solid var(--bd);
    border-radius: 8px;
    padding: 1rem;
    background: rgba(255,255,255,.04);
}
.mini-card strong {
    display: block;
    margin-bottom: .4rem;
}
.notice-box {
    margin-top: 1rem;
    border: 1px solid var(--bd);
    border-radius: 8px;
    padding: 1rem;
    background: rgba(212, 175, 55, .08);
}
.notice-box ul {
    margin: .75rem 0 0 1.2rem;
    padding: 0;
    line-height: 1.8;
}
.muted {
    color: var(--text-sub);
    font-size: .82rem;
    line-height: 1.7;
}
.copy-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto auto;
    gap: .5rem;
}
@media (max-width: 720px) {
    .copy-row {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
function toggleSecret(id) {
    const input = document.getElementById(id);
    if (!input) return;
    input.type = input.type === 'password' ? 'text' : 'password';
}

async function copyValue(id) {
    const input = document.getElementById(id);
    if (!input) return;
    try {
        await navigator.clipboard.writeText(input.value);
    } catch (e) {
        input.type = 'text';
        input.select();
        document.execCommand('copy');
    }
    alert('コピーしました');
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>

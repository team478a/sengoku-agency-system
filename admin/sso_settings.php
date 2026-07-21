<?php
$pageTitle = 'SSO連携設定';
require_once __DIR__ . '/header.php';

$db = getDB();
$csrf = getCsrfToken();
$message = '';
$msgType = 'success';
$tableReady = true;

try {
    $db->query("SELECT 1 FROM sso_clients LIMIT 1");
} catch (Throwable $e) {
    $tableReady = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = '不正なリクエストです。';
        $msgType = 'error';
    } else {
        try {
            if ($action === 'save_sso_common') {
                saveSystemSettingValue('sso_issuer', trim($_POST['sso_issuer'] ?? getSiteBaseUrl()) ?: getSiteBaseUrl());
                $message = 'SSO共通設定を保存しました。';
            } elseif ($action === 'generate_sso_keypair') {
                $keys = generateAgencySsoKeyPair();
                saveSystemSettingValue('sso_key_id', $keys['kid']);
                saveSystemSettingValue('sso_private_key', $keys['private_key']);
                saveSystemSettingValue('sso_public_key', $keys['public_key']);
                $message = 'SSO署名鍵を発行しました。公開鍵またはJWKS URLを連携先サイトへ設定してください。';
            } elseif ($action === 'clear_sso_keypair') {
                saveSystemSettingValue('sso_key_id', '');
                saveSystemSettingValue('sso_private_key', '');
                saveSystemSettingValue('sso_public_key', '');
                $message = 'SSO署名鍵を削除しました。登録済みサイトへのSSOは利用できなくなります。';
            } elseif ($action === 'save_sso_client') {
                if (!$tableReady) {
                    throw new RuntimeException('sso_clientsテーブルがありません。アップデート画面でDBマイグレーションを適用してください。');
                }
                $id = (int)($_POST['id'] ?? 0);
                $clientKey = normalizeSsoClientKey($_POST['client_key'] ?? '');
                $name = trim($_POST['name'] ?? '');
                $audience = trim($_POST['audience'] ?? '');
                $callbackUrl = buildSsoCallbackEndpoint($_POST['callback_url'] ?? '');
                $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
                $sortOrder = (int)($_POST['sort_order'] ?? 0);

                if ($clientKey === '') {
                    throw new RuntimeException('サイトキーを入力してください。');
                }
                if ($name === '') {
                    throw new RuntimeException('連携先名を入力してください。');
                }
                if ($audience === '') {
                    $audience = $clientKey;
                }
                if ($callbackUrl === '') {
                    throw new RuntimeException('SSO受信URLを入力してください。');
                }

                if ($id > 0) {
                    $stmt = $db->prepare("UPDATE sso_clients SET client_key=?, name=?, audience=?, callback_url=?, status=?, sort_order=?, updated_at=NOW() WHERE id=?");
                    $stmt->execute([$clientKey, $name, $audience, $callbackUrl, $status, $sortOrder, $id]);
                    $message = '連携先サイトを更新しました。';
                } else {
                    $stmt = $db->prepare("INSERT INTO sso_clients (client_key, name, audience, callback_url, status, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$clientKey, $name, $audience, $callbackUrl, $status, $sortOrder]);
                    $message = '連携先サイトを追加しました。';
                }
                $tableReady = true;
            } elseif ($action === 'delete_sso_client') {
                if (!$tableReady) {
                    throw new RuntimeException('sso_clientsテーブルがありません。');
                }
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) {
                    throw new RuntimeException('削除対象が不正です。');
                }
                $stmt = $db->prepare("DELETE FROM sso_clients WHERE id=?");
                $stmt->execute([$id]);
                $message = '連携先サイトを削除しました。';
            } elseif ($action === 'toggle_sso_client_status') {
                if (!$tableReady) {
                    throw new RuntimeException('sso_clientsテーブルがありません。');
                }
                $id = (int)($_POST['id'] ?? 0);
                $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
                if ($id <= 0) {
                    throw new RuntimeException('変更対象が不正です。');
                }
                $stmt = $db->prepare("UPDATE sso_clients SET status=?, updated_at=NOW() WHERE id=?");
                $stmt->execute([$status, $id]);
                $message = $status === 'active' ? '連携先サイトを有効化しました。' : '連携先サイトを停止しました。';
            }
        } catch (Throwable $e) {
            $message = $e->getMessage();
            $msgType = 'error';
        }
    }
}

$settings = getAgencySsoSettings();
$jwksEndpoint = getSiteBaseUrl() . '/api/sso/jwks.php';
$hasKey = !empty($settings['sso_private_key'] ?? $settings['private_key']) && !empty($settings['public_key']) && !empty($settings['key_id']);
$clients = $tableReady ? getSsoClients(false) : [];
$editClient = null;
if ($tableReady && isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    foreach ($clients as $client) {
        if ((int)$client['id'] === $editId) {
            $editClient = $client;
            break;
        }
    }
}
?>

<?php if ($message): ?>
<div class="alert alert-<?= h($msgType) ?>"><?= h($message) ?></div>
<?php endif; ?>

<?php if (!$tableReady): ?>
<div class="alert alert-error">
    SSO連携先テーブルが未作成です。アップデート画面からDBマイグレーションを適用してください。
</div>
<?php endif; ?>

<div class="card">
    <p class="card-title">SSO連携の考え方</p>
    <p style="font-size:.86rem;color:var(--text-muted);line-height:1.9;margin-bottom:1rem;">
        この代理店システムをログイン元として、sengoku-rr.comや今後追加する外部システムへパスワード入力なしで移動するための設定です。
        署名鍵とJWKS URLは共通で、連携先サイトごとにサイトキー・aud・SSO受信URLを登録します。
    </p>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1rem;">
        <div class="form-group" style="margin:0;">
            <label>JWKS公開URL</label>
            <input type="text" readonly value="<?= h($jwksEndpoint) ?>" onclick="this.select()">
        </div>
        <div class="form-group" style="margin:0;">
            <label>SSO起動URLの形式</label>
            <input type="text" readonly value="<?= h(getSiteBaseUrl() . '/agent/sso_launch.php?client={サイトキー}') ?>" onclick="this.select()">
        </div>
    </div>
</div>

<div class="card">
    <p class="card-title">SSO署名鍵（全連携先共通）</p>
    <form method="post" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1rem;align-items:end;margin-bottom:1rem;">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="save_sso_common">
        <div class="form-group" style="margin:0;">
            <label>iss（発行者）</label>
            <input type="url" name="sso_issuer" value="<?= h($settings['issuer'] ?? getSiteBaseUrl()) ?>" placeholder="https://sengoku-ai.com">
        </div>
        <button type="submit" class="btn btn-gold">共通設定を保存</button>
    </form>

    <p style="font-size:.82rem;color:var(--text-muted);line-height:1.8;margin-bottom:.75rem;">
        現在の鍵ID: <strong style="color:var(--gold-lt);"><?= h($settings['key_id'] ?: '未発行') ?></strong>
    </p>
    <textarea readonly rows="7" onclick="this.select()" style="font-size:.72rem;line-height:1.5;margin-bottom:.8rem;"><?= h($settings['public_key'] ?? '') ?></textarea>
    <p style="font-size:.76rem;color:var(--text-muted);line-height:1.8;margin-bottom:1rem;">
        連携先サイトはJWKS URLから公開鍵を取得してJWTを検証します。JWKSに対応していない場合だけ、この公開鍵を手動登録してください。
        秘密鍵は外部へ渡しません。
    </p>
    <div style="display:flex;gap:.65rem;flex-wrap:wrap;">
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
            <input type="hidden" name="action" value="generate_sso_keypair">
            <button type="submit" class="btn btn-gold" onclick="return confirm('SSO署名鍵を<?= $hasKey ? '再発行' : '発行' ?>しますか？連携先サイト側の公開鍵/JWKS設定も確認してください。');">
                <?= $hasKey ? 'SSO署名鍵を再発行' : 'SSO署名鍵を発行' ?>
            </button>
        </form>
        <?php if ($hasKey): ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
            <input type="hidden" name="action" value="clear_sso_keypair">
            <button type="submit" class="btn btn-danger" onclick="return confirm('SSO署名鍵を削除しますか？登録済みサイトへのSSOは利用できなくなります。');">SSO署名鍵を削除</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <p class="card-title"><?= $editClient ? '連携先サイトを編集' : '連携先サイトを追加' ?></p>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="save_sso_client">
        <input type="hidden" name="id" value="<?= h((string)($editClient['id'] ?? 0)) ?>">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;">
            <div class="form-group">
                <label>サイトキー（URL用・変更非推奨）</label>
                <input type="text" name="client_key" value="<?= h($editClient['client_key'] ?? '') ?>" placeholder="sengoku-rr" required>
                <p style="font-size:.72rem;color:var(--text-muted);line-height:1.6;margin-top:.35rem;">半角英数字・ハイフン・アンダーバー。SSO起動URLのclientになります。</p>
            </div>
            <div class="form-group">
                <label>連携先名</label>
                <input type="text" name="name" value="<?= h($editClient['name'] ?? '') ?>" placeholder="sengoku-rr.com" required>
            </div>
            <div class="form-group">
                <label>aud（連携先識別子）</label>
                <input type="text" name="audience" value="<?= h($editClient['audience'] ?? '') ?>" placeholder="sengoku-rr">
            </div>
            <div class="form-group">
                <label>状態</label>
                <select name="status">
                    <?php $status = $editClient['status'] ?? 'active'; ?>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>有効</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>停止</option>
                </select>
            </div>
            <div class="form-group">
                <label>表示順</label>
                <input type="number" name="sort_order" value="<?= h((string)($editClient['sort_order'] ?? 0)) ?>">
            </div>
            <div class="form-group" style="grid-column:1/-1;">
                <label>SSO受信URL（ドメインまたはエンドポイント）</label>
                <input type="url" name="callback_url" value="<?= h($editClient['callback_url'] ?? '') ?>" placeholder="https://sengoku-rr.com" required>
                <p style="font-size:.72rem;color:var(--text-muted);line-height:1.6;margin-top:.35rem;">
                    ドメインだけなら自動で <code>/agency/sso</code> を付与します。独自エンドポイントを使う場合は <code>https://example.com/agency/sso</code> まで入力してください。
                </p>
            </div>
        </div>
        <div style="display:flex;gap:.65rem;flex-wrap:wrap;">
            <button type="submit" class="btn btn-gold"><?= $editClient ? '更新する' : '追加する' ?></button>
            <?php if ($editClient): ?>
            <a href="/admin/sso_settings.php" class="btn btn-outline">キャンセル</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card">
    <p class="card-title">連携先サイト一覧</p>
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>状態</th>
                    <th>連携先</th>
                    <th>サイトキー</th>
                    <th>aud</th>
                    <th>SSO受信URL</th>
                    <th>SSO起動URL</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$clients): ?>
                <tr><td colspan="7" style="color:var(--text-muted);">連携先サイトはまだ登録されていません。</td></tr>
            <?php endif; ?>
            <?php foreach ($clients as $client): ?>
                <?php $launchUrl = getSiteBaseUrl() . '/agent/sso_launch.php?client=' . rawurlencode($client['client_key']); ?>
                <tr>
                    <td><span class="badge <?= $client['status'] === 'active' ? 'badge-active' : 'badge-inactive' ?>"><?= $client['status'] === 'active' ? '有効' : '停止' ?></span></td>
                    <td><strong><?= h($client['name']) ?></strong></td>
                    <td><code><?= h($client['client_key']) ?></code></td>
                    <td><code><?= h($client['audience']) ?></code></td>
                    <td><code><?= h($client['callback_url']) ?></code></td>
                    <td><input type="text" readonly value="<?= h($launchUrl) ?>" onclick="this.select()" style="min-width:260px;"></td>
                    <td>
                        <div style="display:flex;gap:.45rem;flex-wrap:wrap;">
                            <a href="/admin/sso_settings.php?edit=<?= (int)$client['id'] ?>" class="btn btn-outline btn-sm">編集</a>
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                <input type="hidden" name="action" value="toggle_sso_client_status">
                                <input type="hidden" name="id" value="<?= (int)$client['id'] ?>">
                                <?php if ($client['status'] === 'active'): ?>
                                    <input type="hidden" name="status" value="inactive">
                                    <button type="submit" class="btn btn-outline btn-sm" onclick="return confirm('この連携先サイトを停止しますか？');">停止</button>
                                <?php else: ?>
                                    <input type="hidden" name="status" value="active">
                                    <button type="submit" class="btn btn-gold btn-sm">有効化</button>
                                <?php endif; ?>
                            </form>
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                <input type="hidden" name="action" value="delete_sso_client">
                                <input type="hidden" name="id" value="<?= (int)$client['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('この連携先サイトを削除しますか？');">削除</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>

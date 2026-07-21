<?php
$pageTitle = '共通ID連携';
require_once __DIR__ . '/header.php';

$db = getDB();
$csrf = getCsrfToken();
$message = '';
$msgType = 'success';

$requiredTables = [
    'common_users' => '共通ユーザー',
    'service_user_mappings' => 'サービス別ユーザー紐づけ',
    'agency_customer_relations' => '代理店・顧客紹介関係',
    'referral_tokens' => '紹介トークン',
    'referral_sessions' => '紹介セッション',
    'integration_idempotency_keys' => 'API冪等性キー',
    'integration_event_logs' => '外部連携ログ',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = '不正なリクエストです。';
        $msgType = 'error';
    } else {
        $action = $_POST['action'] ?? '';
        try {
            if ($action === 'save_flags') {
                saveSystemSettingValue('common_id_enabled', !empty($_POST['common_id_enabled']) ? '1' : '0');
                saveSystemSettingValue('referral_v2_enabled', !empty($_POST['referral_v2_enabled']) ? '1' : '0');
                saveSystemSettingValue('external_registration_capture_enabled', !empty($_POST['external_registration_capture_enabled']) ? '1' : '0');
                saveSystemSettingValue('referral_token_api_enabled', !empty($_POST['referral_token_api_enabled']) ? '1' : '0');
                $message = '共通ID連携の機能フラグを保存しました。';
            }
        } catch (Throwable $e) {
            $message = $e->getMessage();
            $msgType = 'error';
        }
    }
}

$tablesReady = commonIdTablesReady();
$flags = getCommonIdFeatureFlags();
$stats = getCommonIdStats();

$tableStatus = [];
foreach ($requiredTables as $table => $label) {
    $tableStatus[$table] = [
        'label' => $label,
        'ready' => !empty(tableColumns($table)),
    ];
}

$recentMappings = [];
$recentRelations = [];
$recentLogs = [];
if ($tablesReady) {
    try {
        $recentMappings = $db->query("
            SELECT m.*, a.agent_code, a.agent_name
            FROM service_user_mappings m
            LEFT JOIN agents a ON m.agent_id=a.id
            ORDER BY m.updated_at DESC, m.id DESC
            LIMIT 10
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $recentMappings = [];
    }
    try {
        $recentRelations = $db->query("
            SELECT r.*, a.agent_code, a.agent_name, p.name AS project_name
            FROM agency_customer_relations r
            LEFT JOIN agents a ON r.agent_id=a.id
            LEFT JOIN projects p ON r.project_id=p.id
            ORDER BY r.updated_at DESC, r.id DESC
            LIMIT 10
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $recentRelations = [];
    }
    try {
        $recentLogs = $db->query("
            SELECT *
            FROM integration_event_logs
            ORDER BY created_at DESC, id DESC
            LIMIT 20
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $recentLogs = [];
    }
}
?>

<?php if ($message): ?>
<div class="alert alert-<?= h($msgType) ?>"><?= h($message) ?></div>
<?php endif; ?>

<?php if (!$tablesReady): ?>
<div class="alert alert-error">
    共通ID連携のDBマイグレーションが未適用です。管理画面の「アップデート」からDBマイグレーションを適用してください。
</div>
<?php endif; ?>

<div class="card">
    <p class="card-title">共通ID連携の位置づけ</p>
    <p style="font-size:.88rem;color:var(--text-muted);line-height:1.9;margin-bottom:1rem;">
        この画面は、ショッピングカート、戦国パスポート、ウォレット、今後追加される外部サービスのユーザーを、
        代理店システム側の共通IDと紹介関係に紐づけるための基盤確認画面です。
    </p>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1rem;">
        <div style="border:1px solid var(--border);border-radius:6px;padding:1rem;background:rgba(201,168,76,.04);">
            <p style="font-weight:700;color:var(--gold);margin-bottom:.5rem;">既存代理店ID</p>
            <p style="font-size:.8rem;color:var(--text-muted);line-height:1.7;">
                LPや代理店同期で使う <code>agent_code</code> / <code>external_id</code> は維持します。
            </p>
        </div>
        <div style="border:1px solid var(--border);border-radius:6px;padding:1rem;background:rgba(201,168,76,.04);">
            <p style="font-weight:700;color:var(--gold);margin-bottom:.5rem;">共通ユーザーID</p>
            <p style="font-size:.8rem;color:var(--text-muted);line-height:1.7;">
                各サービスのユーザーを <code>common_user_id</code> に集約します。代理店IDとは分離します。
            </p>
        </div>
        <div style="border:1px solid var(--border);border-radius:6px;padding:1rem;background:rgba(201,168,76,.04);">
            <p style="font-weight:700;color:var(--gold);margin-bottom:.5rem;">外部サービス起点登録</p>
            <p style="font-size:.8rem;color:var(--text-muted);line-height:1.7;">
                カートやパスポートで先に登録されたユーザーも、後から紹介元代理店へ紐づけます。
            </p>
        </div>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card"><p class="stat-label">共通ユーザー</p><p class="stat-val"><?= number_format((int)$stats['common_users']) ?></p></div>
    <div class="stat-card"><p class="stat-label">サービス紐づけ</p><p class="stat-val"><?= number_format((int)$stats['service_mappings']) ?></p></div>
    <div class="stat-card"><p class="stat-label">紹介関係</p><p class="stat-val"><?= number_format((int)$stats['customer_relations']) ?></p></div>
    <div class="stat-card"><p class="stat-label">連携ログ</p><p class="stat-val"><?= number_format((int)$stats['integration_logs']) ?></p></div>
</div>

<div class="card">
    <p class="card-title">DB適用状態</p>
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>テーブル</th>
                    <th>用途</th>
                    <th>状態</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($tableStatus as $table => $row): ?>
                <tr>
                    <td><code><?= h($table) ?></code></td>
                    <td><?= h($row['label']) ?></td>
                    <td>
                        <span class="badge <?= $row['ready'] ? 'badge-active' : 'badge-inactive' ?>">
                            <?= $row['ready'] ? '適用済み' : '未適用' ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <p class="card-title">機能フラグ</p>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="save_flags">
        <label class="form-check">
            <input type="checkbox" name="common_id_enabled" value="1" <?= $flags['common_id_enabled'] ? 'checked' : '' ?>>
            共通ID連携を有効化する
        </label>
        <label class="form-check">
            <input type="checkbox" name="referral_v2_enabled" value="1" <?= $flags['referral_v2_enabled'] ? 'checked' : '' ?>>
            紹介関係 v2 API を有効化する
        </label>
        <label class="form-check">
            <input type="checkbox" name="external_registration_capture_enabled" value="1" <?= $flags['external_registration_capture_enabled'] ? 'checked' : '' ?>>
            外部サービス起点登録の取り込みを有効化する
        </label>
        <label class="form-check">
            <input type="checkbox" name="referral_token_api_enabled" value="1" <?= !empty($flags['referral_token_api_enabled']) ? 'checked' : '' ?>>
            紹介トークンAPIを有効化する
        </label>
        <p style="font-size:.78rem;color:var(--text-muted);line-height:1.7;margin:.75rem 0 1rem;">
            初期状態はすべてOFFです。v2 APIを実装・接続テストしてから段階的にONにしてください。
        </p>
        <button type="submit" class="btn btn-gold" <?= !$tablesReady ? 'disabled' : '' ?>>保存する</button>
    </form>
</div>

<?php if ($tablesReady): ?>
<div class="card">
    <p class="card-title">最近のサービス紐づけ</p>
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>更新日時</th>
                    <th>共通ID</th>
                    <th>サービス</th>
                    <th>サービス側ID</th>
                    <th>代理店</th>
                    <th>状態</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($recentMappings): foreach ($recentMappings as $row): ?>
                <tr>
                    <td style="white-space:nowrap;color:var(--text-muted);font-size:.78rem;"><?= h(date('Y/m/d H:i', strtotime($row['updated_at']))) ?></td>
                    <td><code><?= h($row['common_user_id']) ?></code></td>
                    <td><?= h($row['service_key']) ?></td>
                    <td style="word-break:break-all;"><?= h($row['service_user_id']) ?></td>
                    <td>
                        <?= h($row['agent_name'] ?? '—') ?>
                        <?php if (!empty($row['agent_code'])): ?><span style="display:block;font-size:.72rem;color:var(--text-muted);"><?= h($row['agent_code']) ?></span><?php endif; ?>
                    </td>
                    <td><?= h($row['status']) ?></td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:2rem;">まだ紐づけはありません。</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <p class="card-title">最近の紹介関係</p>
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>更新日時</th>
                    <th>共通ID</th>
                    <th>代理店</th>
                    <th>プロジェクト</th>
                    <th>登録元</th>
                    <th>状態</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($recentRelations): foreach ($recentRelations as $row): ?>
                <tr>
                    <td style="white-space:nowrap;color:var(--text-muted);font-size:.78rem;"><?= h(date('Y/m/d H:i', strtotime($row['updated_at']))) ?></td>
                    <td><code><?= h($row['common_user_id']) ?></code></td>
                    <td>
                        <?= h($row['agent_name'] ?? '—') ?>
                        <?php if (!empty($row['agent_code'])): ?><span style="display:block;font-size:.72rem;color:var(--text-muted);"><?= h($row['agent_code']) ?></span><?php endif; ?>
                    </td>
                    <td><?= h($row['project_name'] ?? '—') ?></td>
                    <td><?= h(trim((string)($row['source_service_key'] ?? '') . ' ' . (string)($row['source_service_user_id'] ?? ''))) ?></td>
                    <td><?= h($row['status']) ?></td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:2rem;">まだ紹介関係はありません。</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <p class="card-title">最近の外部連携ログ</p>
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>日時</th>
                    <th>方向</th>
                    <th>連携先</th>
                    <th>イベント</th>
                    <th>HTTP</th>
                    <th>結果</th>
                    <th>共通ID</th>
                    <th>エラー</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($recentLogs): foreach ($recentLogs as $row): ?>
                <tr>
                    <td style="white-space:nowrap;color:var(--text-muted);font-size:.78rem;"><?= h(date('Y/m/d H:i:s', strtotime($row['created_at']))) ?></td>
                    <td><?= h($row['direction']) ?></td>
                    <td><?= h($row['site_key'] ?? '—') ?></td>
                    <td><?= h($row['event_type']) ?></td>
                    <td><?= h($row['http_status'] ?? '—') ?></td>
                    <td>
                        <span style="color:<?= !empty($row['success']) ? '#5ecb9b' : '#e08080' ?>;font-weight:700;">
                            <?= !empty($row['success']) ? '成功' : '失敗' ?>
                        </span>
                    </td>
                    <td><code><?= h($row['common_user_id'] ?? '') ?></code></td>
                    <td style="max-width:360px;"><?= h($row['error_message'] ?? '') ?></td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:2rem;">まだ連携ログはありません。</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>

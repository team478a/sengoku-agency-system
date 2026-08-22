<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdminLogin();

function rewardCsvRequiredHeaders(): array {
    return ['external_reward_key', 'agent_code'];
}

function rewardCsvHeaderAliases(): array {
    return [
        'external_reward_key' => 'external_reward_key',
        'reward_key' => 'external_reward_key',
        '外部キー' => 'external_reward_key',
        '報酬キー' => 'external_reward_key',
        'agent_code' => 'agent_code',
        '代理店コード' => 'agent_code',
        'コード' => 'agent_code',
        'amount_minor' => 'amount_minor',
        '金額_最小単位' => 'amount_minor',
        'amount' => 'amount',
        '金額' => 'amount',
        'currency' => 'currency',
        '通貨' => 'currency',
        'occurred_at' => 'occurred_at',
        '発生日' => 'occurred_at',
        '発生日時' => 'occurred_at',
        'source_system_key' => 'source_system_key',
        '送信元' => 'source_system_key',
        '連携元' => 'source_system_key',
        'project_key' => 'project_key',
        'プロジェクト' => 'project_key',
        '案件' => 'project_key',
        'product_code' => 'product_code',
        '商品コード' => 'product_code',
        'order_id' => 'order_id',
        '注文ID' => 'order_id',
        'order_item_id' => 'order_item_id',
        '注文明細ID' => 'order_item_id',
        'common_user_id' => 'common_user_id',
        '共通顧客ID' => 'common_user_id',
        'status' => 'status',
        '状態' => 'status',
        'description' => 'description',
        '内容' => 'description',
        '説明' => 'description',
        'メモ' => 'description',
    ];
}

function rewardCsvCanonicalHeader(string $header): ?string {
    $header = trim(preg_replace('/^\xEF\xBB\xBF/', '', $header));
    $aliases = rewardCsvHeaderAliases();
    return $aliases[$header] ?? $aliases[strtolower($header)] ?? null;
}

function rewardCsvDownloadSample(): void {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="reward_import_sample.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, [
        'external_reward_key',
        'agent_code',
        'amount',
        'currency',
        'occurred_at',
        'source_system_key',
        'project_key',
        'product_code',
        'order_id',
        'order_item_id',
        'common_user_id',
        'status',
        'description',
    ]);
    fputcsv($out, [
        'passport-202608-0001',
        'agent_7_8573',
        '3000',
        'JPY',
        '2026-08-23 10:00:00',
        'SENGOKU_PASSPORT',
        'sengoku-influencer',
        'passport-basic',
        'ORDER-001',
        'ITEM-001',
        'cu_sample',
        'confirmed',
        'サンプル報酬',
    ]);
    fclose($out);
    exit;
}

function rewardCsvNormalizeContent(string $content): string {
    if (function_exists('mb_check_encoding') && function_exists('mb_convert_encoding') && !mb_check_encoding($content, 'UTF-8')) {
        $content = mb_convert_encoding($content, 'UTF-8', 'SJIS-win,CP932,EUC-JP,UTF-8');
    }
    return preg_replace('/^\xEF\xBB\xBF/', '', $content);
}

function rewardCsvReadFile(string $path): array {
    $content = rewardCsvNormalizeContent((string)file_get_contents($path));
    $stream = fopen('php://temp', 'r+');
    fwrite($stream, $content);
    rewind($stream);

    $rawHeaders = fgetcsv($stream);
    if ($rawHeaders === false || $rawHeaders === [null]) {
        fclose($stream);
        return ['headers' => [], 'rows' => [], 'errors' => ['CSVにヘッダー行がありません。']];
    }

    $headers = [];
    $headerErrors = [];
    foreach ($rawHeaders as $index => $rawHeader) {
        $canonical = rewardCsvCanonicalHeader((string)$rawHeader);
        if ($canonical === null) {
            $headerErrors[] = '未対応の列があります: ' . trim((string)$rawHeader);
            continue;
        }
        if (in_array($canonical, $headers, true)) {
            $headerErrors[] = '同じ意味の列が重複しています: ' . trim((string)$rawHeader);
            continue;
        }
        $headers[$index] = $canonical;
    }

    foreach (rewardCsvRequiredHeaders() as $required) {
        if (!in_array($required, $headers, true)) {
            $headerErrors[] = '必須列がありません: ' . $required;
        }
    }
    if (!in_array('amount', $headers, true) && !in_array('amount_minor', $headers, true)) {
        $headerErrors[] = '金額列がありません: amount または amount_minor';
    }

    $rows = [];
    $line = 1;
    while (($rawRow = fgetcsv($stream)) !== false) {
        $line++;
        if ($rawRow === [null] || count(array_filter($rawRow, fn($v) => trim((string)$v) !== '')) === 0) {
            continue;
        }
        $row = ['_line' => $line];
        foreach ($headers as $index => $name) {
            $row[$name] = isset($rawRow[$index]) ? trim((string)$rawRow[$index]) : '';
        }
        $rows[] = $row;
    }
    fclose($stream);

    return ['headers' => array_values($headers), 'rows' => $rows, 'errors' => $headerErrors];
}

function rewardCsvParseAmountMinor(?string $amountMinor, ?string $amount, string $currency): ?int {
    $currency = strtoupper(trim($currency ?: 'JPY'));
    if ($amountMinor !== null && trim($amountMinor) !== '') {
        $value = str_replace([',', ' ', '　'], '', trim($amountMinor));
        return preg_match('/^-?\d+$/', $value) ? (int)$value : null;
    }

    $value = str_replace([',', ' ', '　', '¥', '￥'], '', trim((string)$amount));
    if ($value === '' || !is_numeric($value)) {
        return null;
    }

    return $currency === 'JPY' ? (int)round((float)$value) : (int)round(((float)$value) * 100);
}

function rewardCsvNormalizeDate(?string $value): ?string {
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }
    $timestamp = strtotime(str_replace('/', '-', $value));
    return $timestamp === false ? null : date('Y-m-d H:i:s', $timestamp);
}

function rewardCsvLoadAgents(PDO $db, array $codes): array {
    $codes = array_values(array_unique(array_filter(array_map('trim', $codes))));
    if (empty($codes)) {
        return [];
    }

    $agents = [];
    foreach (array_chunk($codes, 100) as $chunk) {
        $placeholders = implode(',', array_fill(0, count($chunk), '?'));
        $stmt = $db->prepare("SELECT id, agent_code, agent_name, person_name FROM agents WHERE agent_code IN ($placeholders)");
        $stmt->execute($chunk);
        foreach ($stmt->fetchAll() as $agent) {
            $agents[$agent['agent_code']] = $agent;
        }
    }
    return $agents;
}

function rewardCsvLoadExistingRewardKeys(PDO $db, array $keys): array {
    $keys = array_values(array_unique(array_filter(array_map('trim', $keys))));
    if (empty($keys)) {
        return [];
    }

    $existing = [];
    foreach (array_chunk($keys, 100) as $chunk) {
        $placeholders = implode(',', array_fill(0, count($chunk), '?'));
        $stmt = $db->prepare("SELECT external_reward_key FROM agent_reward_ledger WHERE external_reward_key IN ($placeholders)");
        $stmt->execute($chunk);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $key) {
            $existing[$key] = true;
        }
    }
    return $existing;
}

function rewardCsvBuildPreview(PDO $db, array $file): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'message' => 'CSVファイルを選択してください。'];
    }

    $fileHash = hash_file('sha256', $file['tmp_name']);
    $stmt = $db->prepare('SELECT id FROM reward_import_batches WHERE file_hash = ? LIMIT 1');
    $stmt->execute([$fileHash]);
    if ($stmt->fetchColumn()) {
        return ['ok' => false, 'message' => '同じCSVファイルはすでに取り込み済みです。'];
    }

    $parsed = rewardCsvReadFile($file['tmp_name']);
    $errors = [];
    foreach ($parsed['errors'] as $error) {
        $errors[] = ['line' => '-', 'message' => $error];
    }
    if (!empty($parsed['errors'])) {
        return [
            'ok' => true,
            'preview' => [
                'file_name' => basename((string)$file['name']),
                'file_hash' => $fileHash,
                'total_rows' => count($parsed['rows']),
                'valid_rows' => 0,
                'error_rows' => count($errors),
                'rows' => [],
                'errors' => $errors,
                'created_at' => time(),
            ],
        ];
    }

    $agents = rewardCsvLoadAgents($db, array_column($parsed['rows'], 'agent_code'));
    $existingKeys = rewardCsvLoadExistingRewardKeys($db, array_column($parsed['rows'], 'external_reward_key'));
    $seenKeys = [];
    $validRows = [];

    foreach ($parsed['rows'] as $row) {
        $rowErrors = [];
        $line = (int)$row['_line'];
        $externalKey = trim((string)($row['external_reward_key'] ?? ''));
        $agentCode = trim((string)($row['agent_code'] ?? ''));
        $currency = strtoupper(trim((string)($row['currency'] ?? 'JPY'))) ?: 'JPY';
        $amountMinor = rewardCsvParseAmountMinor($row['amount_minor'] ?? null, $row['amount'] ?? null, $currency);
        $occurredAt = rewardCsvNormalizeDate($row['occurred_at'] ?? '');
        $status = normalizeRewardLedgerStatus($row['status'] ?? 'confirmed');

        if ($externalKey === '') {
            $rowErrors[] = 'external_reward_key が空です。';
        } elseif (isset($seenKeys[$externalKey])) {
            $rowErrors[] = 'CSV内で external_reward_key が重複しています。';
        } elseif (isset($existingKeys[$externalKey])) {
            $rowErrors[] = 'external_reward_key はすでに取り込み済みです。';
        }
        if ($agentCode === '') {
            $rowErrors[] = 'agent_code が空です。';
        } elseif (!isset($agents[$agentCode])) {
            $rowErrors[] = '代理店コードが見つかりません: ' . $agentCode;
        }
        if ($amountMinor === null) {
            $rowErrors[] = '金額が数値として読めません。';
        }
        if ($amountMinor !== null && $amountMinor === 0) {
            $rowErrors[] = '金額が0です。訂正行の場合は調整理由を確認してください。';
        }
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            $rowErrors[] = '通貨は3文字で入力してください。例: JPY';
        }
        if (trim((string)($row['occurred_at'] ?? '')) !== '' && $occurredAt === null) {
            $rowErrors[] = '発生日を日付として読めません。';
        }

        $seenKeys[$externalKey] = true;
        if (!empty($rowErrors)) {
            foreach ($rowErrors as $error) {
                $errors[] = ['line' => $line, 'message' => $error];
            }
            continue;
        }

        $agent = $agents[$agentCode];
        $validRows[] = [
            'external_reward_key' => $externalKey,
            'agent_id' => (int)$agent['id'],
            'agent_code' => $agentCode,
            'agent_label' => trim(($agent['agent_name'] ?? '') . ' / ' . ($agent['person_name'] ?? ''), ' /'),
            'source_system_key' => trim((string)($row['source_system_key'] ?? '')) ?: null,
            'project_key' => trim((string)($row['project_key'] ?? '')) ?: null,
            'product_code' => trim((string)($row['product_code'] ?? '')) ?: null,
            'order_id' => trim((string)($row['order_id'] ?? '')) ?: null,
            'order_item_id' => trim((string)($row['order_item_id'] ?? '')) ?: null,
            'common_user_id' => trim((string)($row['common_user_id'] ?? '')) ?: null,
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'status' => $status,
            'occurred_at' => $occurredAt,
            'description' => trim((string)($row['description'] ?? '')) ?: null,
        ];
    }

    return [
        'ok' => true,
        'preview' => [
            'file_name' => basename((string)$file['name']),
            'file_hash' => $fileHash,
            'total_rows' => count($parsed['rows']),
            'valid_rows' => count($validRows),
            'error_rows' => count($errors),
            'rows' => $validRows,
            'errors' => $errors,
            'created_at' => time(),
        ],
    ];
}

if (isset($_GET['sample']) && $_GET['sample'] === 'csv') {
    rewardCsvDownloadSample();
}

$pageTitle = '報酬CSV・取込履歴';
$db = getDB();
$csrf = getCsrfToken();
$ready = rewardLedgerTablesReady();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = '不正なリクエストです。';
    } elseif (!$ready) {
        $error = '報酬CSV取込のDBマイグレーションが未適用です。';
    } elseif (($_POST['action'] ?? '') === 'preview') {
        $result = rewardCsvBuildPreview($db, $_FILES['reward_csv'] ?? []);
        if (!($result['ok'] ?? false)) {
            $error = $result['message'] ?? 'CSVの確認に失敗しました。';
        } else {
            $_SESSION['reward_csv_preview'] = $result['preview'];
            $message = 'CSVを確認しました。内容を確認してから取り込んでください。';
        }
    } elseif (($_POST['action'] ?? '') === 'clear_preview') {
        unset($_SESSION['reward_csv_preview']);
        $message = 'プレビューを破棄しました。';
    } elseif (($_POST['action'] ?? '') === 'import') {
        $preview = $_SESSION['reward_csv_preview'] ?? null;
        if (!$preview || empty($preview['rows'])) {
            $error = '取り込めるプレビューがありません。';
        } elseif ((int)$preview['error_rows'] > 0) {
            $error = 'エラー行があるため取り込めません。CSVを修正してください。';
        } else {
            $stmt = $db->prepare('SELECT id FROM reward_import_batches WHERE file_hash = ? LIMIT 1');
            $stmt->execute([$preview['file_hash']]);
            if ($stmt->fetchColumn()) {
                $error = '同じCSVファイルはすでに取り込み済みです。';
            } else {
                $db->beginTransaction();
                try {
                    $stmt = $db->prepare(
                        'INSERT INTO reward_import_batches
                         (file_name, file_hash, total_rows, valid_rows, error_rows, imported_by_admin_id, status)
                         VALUES (?, ?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([
                        $preview['file_name'],
                        $preview['file_hash'],
                        (int)$preview['total_rows'],
                        (int)$preview['valid_rows'],
                        (int)$preview['error_rows'],
                        $_SESSION['admin_id'] ?? null,
                        'imported',
                    ]);
                    $batchId = (int)$db->lastInsertId();

                    $insert = $db->prepare(
                        'INSERT INTO agent_reward_ledger
                         (import_batch_id, external_reward_key, agent_id, agent_code, source_system_key, project_key,
                          product_code, order_id, order_item_id, common_user_id, amount_minor, currency, status, occurred_at, description)
                         VALUES
                         (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                    );
                    foreach ($preview['rows'] as $row) {
                        $insert->execute([
                            $batchId,
                            $row['external_reward_key'],
                            $row['agent_id'],
                            $row['agent_code'],
                            $row['source_system_key'],
                            $row['project_key'],
                            $row['product_code'],
                            $row['order_id'],
                            $row['order_item_id'],
                            $row['common_user_id'],
                            $row['amount_minor'],
                            $row['currency'],
                            $row['status'],
                            $row['occurred_at'],
                            $row['description'],
                        ]);
                    }
                    $db->commit();
                    unset($_SESSION['reward_csv_preview']);
                    $message = '報酬CSVを取り込みました。';
                } catch (Throwable $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    $error = '取込に失敗しました: ' . $e->getMessage();
                }
            }
        }
    }
}

$preview = $_SESSION['reward_csv_preview'] ?? null;
$history = [];
if ($ready) {
    $history = $db->query(
        'SELECT b.*, a.username AS admin_username
         FROM reward_import_batches b
         LEFT JOIN admins a ON a.id = b.imported_by_admin_id
         ORDER BY b.imported_at DESC, b.id DESC
         LIMIT 30'
    )->fetchAll();
}

require_once __DIR__ . '/header.php';
?>

<style>
.reward-help { color: var(--muted); line-height: 1.8; }
.reward-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
.reward-stat { border: 1px solid var(--border); border-radius: 8px; padding: 14px; background: var(--panel-soft); }
.reward-stat strong { display: block; color: var(--accent); font-size: 26px; line-height: 1.2; }
.reward-actions { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
.reward-table-scroll { overflow-x: auto; border: 1px solid var(--border); border-radius: 8px; }
.reward-table { min-width: 980px; width: 100%; border-collapse: collapse; }
.reward-table th, .reward-table td { padding: 11px 12px; border-bottom: 1px solid var(--border); text-align: left; vertical-align: top; }
.reward-table th { background: var(--panel-soft); color: var(--muted); font-weight: 700; }
.reward-badge { display: inline-block; padding: 4px 10px; border: 1px solid var(--border); border-radius: 999px; background: var(--panel-soft); }
.reward-badge-error { border-color: #d45b5b; color: #b10000; background: rgba(212, 91, 91, 0.12); }
.reward-badge-ok { border-color: #55b988; color: #167046; background: rgba(85, 185, 136, 0.12); }
@media (max-width: 900px) { .reward-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 560px) { .reward-grid { grid-template-columns: 1fr; } }
</style>

<?php if ($message): ?><div class="alert success"><?= h($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert danger"><?= h($error) ?></div><?php endif; ?>

<?php if (!$ready): ?>
    <div class="alert danger">
        報酬CSV取込のDBマイグレーションが未適用です。アップデート画面で 3.6.165 を適用してください。
    </div>
<?php endif; ?>

<section class="card">
    <h2>報酬CSVの事前チェック</h2>
    <p class="reward-help">
        支払い機能は含めず、外部で計算した報酬CSVを「取込前に確認する」ための画面です。
        エラー行がある場合は取込を止め、修正してから再アップロードします。
    </p>
    <p class="reward-help">
        必須列: <code>external_reward_key</code>, <code>agent_code</code>, <code>amount</code> または <code>amount_minor</code><br>
        同じ <code>external_reward_key</code> や同じCSVファイルは二重に取り込めません。
    </p>
    <div class="reward-actions" style="margin: 14px 0;">
        <a class="btn btn-outline" href="/admin/rewards.php?sample=csv">サンプルCSVをダウンロード</a>
    </div>
    <form method="post" enctype="multipart/form-data" class="form-grid">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="preview">
        <label>
            報酬CSVファイル
            <input type="file" name="reward_csv" accept=".csv,text/csv" <?= $ready ? '' : 'disabled' ?>>
        </label>
        <div class="reward-actions">
            <button type="submit" class="btn btn-primary" <?= $ready ? '' : 'disabled' ?>>CSVを確認する</button>
        </div>
    </form>
</section>

<?php if ($preview): ?>
<section class="card">
    <h2>取込前プレビュー</h2>
    <div class="reward-grid">
        <div class="reward-stat"><strong><?= h((string)$preview['total_rows']) ?></strong>総行数</div>
        <div class="reward-stat"><strong><?= h((string)$preview['valid_rows']) ?></strong>正常行</div>
        <div class="reward-stat"><strong><?= h((string)$preview['error_rows']) ?></strong>エラー行</div>
        <div class="reward-stat"><strong><?= h($preview['file_name']) ?></strong>ファイル</div>
    </div>

    <?php if (!empty($preview['errors'])): ?>
        <h3>修正が必要な行</h3>
        <div class="reward-table-scroll">
            <table class="reward-table">
                <thead><tr><th>行</th><th>内容</th></tr></thead>
                <tbody>
                <?php foreach ($preview['errors'] as $rowError): ?>
                    <tr>
                        <td><?= h((string)$rowError['line']) ?></td>
                        <td><span class="reward-badge reward-badge-error"><?= h($rowError['message']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p><span class="reward-badge reward-badge-ok">エラーはありません。この内容で取り込めます。</span></p>
    <?php endif; ?>

    <?php if (!empty($preview['rows'])): ?>
        <h3>正常行の確認</h3>
        <div class="reward-table-scroll">
            <table class="reward-table">
                <thead>
                    <tr>
                        <th>外部キー</th>
                        <th>代理店</th>
                        <th>金額</th>
                        <th>状態</th>
                        <th>発生日</th>
                        <th>送信元</th>
                        <th>商品</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach (array_slice($preview['rows'], 0, 50) as $row): ?>
                    <tr>
                        <td><?= h($row['external_reward_key']) ?></td>
                        <td><?= h($row['agent_code']) ?><br><small><?= h($row['agent_label']) ?></small></td>
                        <td><?= h(number_format((int)$row['amount_minor'])) ?> <?= h($row['currency']) ?></td>
                        <td><?= h($row['status']) ?></td>
                        <td><?= h($row['occurred_at'] ?? '-') ?></td>
                        <td><?= h($row['source_system_key'] ?? '-') ?></td>
                        <td><?= h($row['product_code'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (count($preview['rows']) > 50): ?>
            <p class="reward-help">表示は先頭50行までです。取込対象は正常行すべてです。</p>
        <?php endif; ?>
    <?php endif; ?>

    <div class="reward-actions" style="margin-top: 16px;">
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
            <input type="hidden" name="action" value="import">
            <button type="submit" class="btn btn-primary" <?= ((int)$preview['error_rows'] === 0 && (int)$preview['valid_rows'] > 0) ? '' : 'disabled' ?>>
                このCSVを取り込む
            </button>
        </form>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
            <input type="hidden" name="action" value="clear_preview">
            <button type="submit" class="btn btn-outline">プレビューを破棄</button>
        </form>
    </div>
</section>
<?php endif; ?>

<section class="card">
    <h2>取込履歴</h2>
    <?php if (empty($history)): ?>
        <p class="reward-help">まだ取込履歴はありません。</p>
    <?php else: ?>
        <div class="reward-table-scroll">
            <table class="reward-table">
                <thead>
                    <tr>
                        <th>取込日時</th>
                        <th>ファイル</th>
                        <th>総行数</th>
                        <th>正常</th>
                        <th>エラー</th>
                        <th>担当</th>
                        <th>ハッシュ</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($history as $batch): ?>
                    <tr>
                        <td><?= h($batch['imported_at']) ?></td>
                        <td><?= h($batch['file_name']) ?></td>
                        <td><?= h((string)$batch['total_rows']) ?></td>
                        <td><?= h((string)$batch['valid_rows']) ?></td>
                        <td><?= h((string)$batch['error_rows']) ?></td>
                        <td><?= h($batch['admin_username'] ?? '-') ?></td>
                        <td><code><?= h(substr($batch['file_hash'], 0, 12)) ?>...</code></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>

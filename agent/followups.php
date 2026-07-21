<?php
$pageTitle = 'フォロー管理';
require_once __DIR__ . '/header.php';

if (($currentAgent['level'] ?? 1) < 2) {
    echo '<div class="alert alert-error">このページはディレクター以上のみ利用できます。</div>';
    require_once __DIR__ . '/footer.php';
    exit;
}

$db = getDB();
$aid = (int)$currentAgent['id'];
$labels = getLevelLabels();
$csrf = getCsrfToken();
$message = '';
$msgType = 'success';

function followupTableExists(PDO $db): bool {
    try {
        $stmt = $db->prepare("SHOW TABLES LIKE ?");
        $stmt->execute(['agent_followups']);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('Followup table check failed: ' . $e->getMessage());
        return false;
    }
}

function ensureFollowupSchema(PDO $db): bool {
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS agent_followups (
                id INT AUTO_INCREMENT PRIMARY KEY,
                owner_agent_id INT NOT NULL,
                target_agent_id INT NOT NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'needs_follow',
                note TEXT,
                next_follow_at DATE DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_followups_owner (owner_agent_id),
                INDEX idx_followups_target (target_agent_id),
                INDEX idx_followups_next (next_follow_at),
                FOREIGN KEY (owner_agent_id) REFERENCES agents(id) ON DELETE CASCADE,
                FOREIGN KEY (target_agent_id) REFERENCES agents(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        try {
            $db->prepare("INSERT IGNORE INTO schema_migrations (version, description) VALUES (?, ?)")
               ->execute(['3.5.0', '配下フォロー履歴管理']);
        } catch (Throwable $e) {
            error_log('Followup schema migration mark failed: ' . $e->getMessage());
        }
        return true;
    } catch (Throwable $e) {
        error_log('Followup schema ensure failed: ' . $e->getMessage());
        return false;
    }
}

function followupStatusLabels(): array {
    return [
        'needs_follow' => '要フォロー',
        'contacted' => '連絡済み',
        'active' => '活動中',
        'stalled' => '停滞中',
        'completed' => '完了',
    ];
}

function followupStatusClass(string $status): string {
    if (in_array($status, ['active', 'completed'], true)) {
        return 'badge-contacted';
    }
    if ($status === 'stalled') {
        return 'badge-closed';
    }
    return 'badge-new';
}

function followupAgentLabel(array $labels, array $agent): string {
    $level = (int)($agent['level'] ?? 1);
    if ($level === 1) {
        return getAdvisorPositionLabel($agent['position_type'] ?? null, $agent['position_label'] ?? null);
    }
    return $labels[$level] ?? 'メンバー';
}

if (!followupTableExists($db) && !ensureFollowupSchema($db)) {
    echo '<div class="alert alert-error">フォロー管理のDBテーブルを作成できませんでした。管理者画面のアップデートからDBマイグレーションを適用してください。</div>';
    require_once __DIR__ . '/footer.php';
    exit;
}

$descendants = getAllDescendants($aid);
$targetMap = [];
foreach ($descendants as $agent) {
    $targetMap[(int)$agent['id']] = $agent;
}

$statusLabels = followupStatusLabels();
$selectedTargetId = isset($_GET['target']) ? (int)$_GET['target'] : 0;
if ($selectedTargetId && !isset($targetMap[$selectedTargetId])) {
    $selectedTargetId = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = '不正なリクエストです。';
        $msgType = 'error';
    } else {
        $targetId = (int)($_POST['target_agent_id'] ?? 0);
        $status = (string)($_POST['status'] ?? 'needs_follow');
        $note = trim((string)($_POST['note'] ?? ''));
        $nextFollowAt = trim((string)($_POST['next_follow_at'] ?? ''));
        if (!isset($targetMap[$targetId])) {
            $message = '管理対象の配下を選択してください。';
            $msgType = 'error';
        } elseif (!array_key_exists($status, $statusLabels)) {
            $message = '対応状況が不正です。';
            $msgType = 'error';
        } elseif ($note === '' && $nextFollowAt === '') {
            $message = 'メモまたは次回連絡日を入力してください。';
            $msgType = 'error';
        } else {
            $nextValue = $nextFollowAt !== '' ? $nextFollowAt : null;
            $stmt = $db->prepare("
                INSERT INTO agent_followups
                    (owner_agent_id, target_agent_id, status, note, next_follow_at)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$aid, $targetId, $status, $note, $nextValue]);
            $message = 'フォロー履歴を追加しました。';
            $selectedTargetId = $targetId;
        }
    }
}

$historyStmt = $db->prepare("
    SELECT f.*, a.agent_name, a.person_name, a.agent_code, a.level, a.position_type, a.position_label, a.status AS agent_status
    FROM agent_followups f
    INNER JOIN agents a ON a.id = f.target_agent_id
    WHERE f.owner_agent_id=?
    ORDER BY f.created_at DESC
    LIMIT 120
");
$historyStmt->execute([$aid]);
$historyRows = $historyStmt->fetchAll();

$latestMap = [];
foreach ($historyRows as $row) {
    $tid = (int)$row['target_agent_id'];
    if (!isset($latestMap[$tid])) {
        $latestMap[$tid] = $row;
    }
}

$targetRows = [];
foreach ($descendants as $agent) {
    $id = (int)$agent['id'];
    $latest = $latestMap[$id] ?? null;
    $targetRows[] = [
        'agent' => $agent,
        'latest' => $latest,
        'next_time' => $latest && !empty($latest['next_follow_at']) ? strtotime($latest['next_follow_at']) : null,
    ];
}
usort($targetRows, static function($a, $b) {
    $an = $a['next_time'] ?? PHP_INT_MAX;
    $bn = $b['next_time'] ?? PHP_INT_MAX;
    if ($an === $bn) {
        return strcmp((string)$a['agent']['agent_name'], (string)$b['agent']['agent_name']);
    }
    return $an <=> $bn;
});

$selectedTarget = $selectedTargetId ? ($targetMap[$selectedTargetId] ?? null) : null;
$overdueCount = 0;
$today = strtotime(date('Y-m-d'));
foreach ($latestMap as $latest) {
    if (!empty($latest['next_follow_at']) && strtotime($latest['next_follow_at']) <= $today && !in_array($latest['status'], ['completed'], true)) {
        $overdueCount++;
    }
}
?>

<?php if ($message): ?><div class="alert alert-<?= h($msgType) ?>"><?= h($message) ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:1rem;margin-bottom:1.25rem;">
    <div class="card" style="text-align:center;padding:1.1rem;margin-bottom:0;">
        <p style="font-size:.72rem;color:var(--text-muted);margin-bottom:.3rem;">管理対象</p>
        <p style="font-family:'Noto Serif JP',serif;font-size:1.8rem;font-weight:900;color:var(--gold-lt);"><?= number_format(count($descendants)) ?></p>
    </div>
    <div class="card" style="text-align:center;padding:1.1rem;margin-bottom:0;">
        <p style="font-size:.72rem;color:var(--text-muted);margin-bottom:.3rem;">履歴件数</p>
        <p style="font-family:'Noto Serif JP',serif;font-size:1.8rem;font-weight:900;color:var(--gold-lt);"><?= number_format(count($historyRows)) ?></p>
    </div>
    <div class="card" style="text-align:center;padding:1.1rem;margin-bottom:0;">
        <p style="font-size:.72rem;color:var(--text-muted);margin-bottom:.3rem;">本日までの連絡予定</p>
        <p style="font-family:'Noto Serif JP',serif;font-size:1.8rem;font-weight:900;color:<?= $overdueCount > 0 ? '#e0a040' : 'var(--gold-lt)' ?>;"><?= number_format($overdueCount) ?></p>
    </div>
</div>

<div class="card">
    <p class="card-title">フォロー履歴を追加</p>
    <?php if (!$descendants): ?>
    <p style="font-size:.85rem;color:var(--text-muted);">まだ配下メンバーがいません。</p>
    <?php else: ?>
    <form method="post" style="display:grid;grid-template-columns:1.4fr 1fr 1fr;gap:.9rem;align-items:end;">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="create">
        <div class="form-group" style="margin:0;">
            <label>対象メンバー</label>
            <select name="target_agent_id" required>
                <option value="">選択してください</option>
                <?php foreach ($descendants as $agent): ?>
                <option value="<?= (int)$agent['id'] ?>" <?= (int)$agent['id'] === $selectedTargetId ? 'selected' : '' ?>>
                    <?= h($agent['agent_name']) ?> / <?= h($agent['person_name']) ?>（<?= h(followupAgentLabel($labels, $agent)) ?>）
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label>対応状況</label>
            <select name="status">
                <?php foreach ($statusLabels as $key => $label): ?>
                <option value="<?= h($key) ?>"><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label>次回連絡日</label>
            <input type="date" name="next_follow_at">
        </div>
        <div class="form-group" style="grid-column:1 / -1;margin:0;">
            <label>メモ</label>
            <textarea name="note" placeholder="例：説明会への誘導済み。次回は資料送付後の反応を確認。"></textarea>
        </div>
        <div style="grid-column:1 / -1;">
            <button class="btn btn-gold" type="submit">履歴を追加</button>
        </div>
    </form>
    <?php endif; ?>
</div>

<div class="card" style="padding:0;overflow:hidden;margin-bottom:1.25rem;">
    <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
        <p class="card-title" style="margin:0;border:none;padding:0;">配下メンバー別フォロー状況</p>
        <span style="font-size:.75rem;color:var(--text-muted);">次回連絡日が近い順</span>
    </div>
    <div class="table-scroll">
        <table style="min-width:980px;">
            <thead>
                <tr><th>メンバー</th><th>区分</th><th>状況</th><th>次回連絡日</th><th>最新メモ</th><th>LP</th><th>操作</th></tr>
            </thead>
            <tbody>
            <?php if ($targetRows): foreach ($targetRows as $row):
                $agent = $row['agent'];
                $latest = $row['latest'];
                $status = $latest['status'] ?? 'needs_follow';
                $nextAt = $latest['next_follow_at'] ?? null;
                $isDue = $nextAt && strtotime($nextAt) <= $today && $status !== 'completed';
            ?>
                <tr>
                    <td>
                        <div style="font-weight:700;color:var(--gold-lt);"><?= h($agent['agent_name']) ?></div>
                        <div style="font-size:.72rem;color:var(--text-muted);"><?= h($agent['person_name']) ?></div>
                    </td>
                    <td style="font-size:.75rem;color:var(--text-muted);"><?= h(followupAgentLabel($labels, $agent)) ?></td>
                    <td><span class="badge <?= h(followupStatusClass($status)) ?>"><?= h($statusLabels[$status] ?? $statusLabels['needs_follow']) ?></span></td>
                    <td style="font-size:.78rem;color:<?= $isDue ? '#e0a040' : 'var(--text-muted)' ?>;font-weight:<?= $isDue ? '700' : '400' ?>;">
                        <?= $nextAt ? h(date('Y/m/d', strtotime($nextAt))) : '未設定' ?>
                    </td>
                    <td style="font-size:.78rem;max-width:360px;white-space:normal;line-height:1.7;color:var(--text-muted);">
                        <?= $latest && trim((string)$latest['note']) !== '' ? nl2br(h(mb_strimwidth((string)$latest['note'], 0, 120, '...'))) : '履歴なし' ?>
                    </td>
                    <td style="font-size:.76rem;"><a href="/a/<?= h($agent['agent_code']) ?>" target="_blank" style="color:var(--gold);text-decoration:none;">確認</a></td>
                    <td><a href="/agent/followups.php?target=<?= (int)$agent['id'] ?>" class="btn btn-outline" style="font-size:.74rem;padding:.35rem .65rem;">記録</a></td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:2rem;">配下メンバーがいません。</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card" style="padding:0;overflow:hidden;">
    <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
        <p class="card-title" style="margin:0;border:none;padding:0;">最近のフォロー履歴</p>
        <?php if ($selectedTarget): ?><span style="font-size:.75rem;color:var(--gold-lt);">選択中: <?= h($selectedTarget['agent_name']) ?></span><?php endif; ?>
    </div>
    <div class="table-scroll">
        <table style="min-width:820px;">
            <thead>
                <tr><th>日時</th><th>対象</th><th>状況</th><th>次回連絡日</th><th>メモ</th></tr>
            </thead>
            <tbody>
            <?php
            $filteredHistory = $selectedTargetId
                ? array_values(array_filter($historyRows, static fn($row) => (int)$row['target_agent_id'] === $selectedTargetId))
                : $historyRows;
            ?>
            <?php if ($filteredHistory): foreach (array_slice($filteredHistory, 0, 50) as $row): ?>
                <tr>
                    <td style="font-size:.75rem;color:var(--text-muted);"><?= h(date('Y/m/d H:i', strtotime($row['created_at']))) ?></td>
                    <td>
                        <div style="font-weight:700;color:var(--gold-lt);"><?= h($row['agent_name']) ?></div>
                        <div style="font-size:.72rem;color:var(--text-muted);"><?= h($row['person_name']) ?></div>
                    </td>
                    <td><span class="badge <?= h(followupStatusClass((string)$row['status'])) ?>"><?= h($statusLabels[$row['status']] ?? $row['status']) ?></span></td>
                    <td style="font-size:.78rem;color:var(--text-muted);"><?= $row['next_follow_at'] ? h(date('Y/m/d', strtotime($row['next_follow_at']))) : '未設定' ?></td>
                    <td style="font-size:.78rem;white-space:normal;line-height:1.7;"><?= nl2br(h($row['note'] ?? '')) ?></td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:2rem;">フォロー履歴がありません。</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>

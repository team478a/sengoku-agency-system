<?php
$pageTitle = '招待URL管理';
require_once __DIR__ . '/header.php';

if (($currentAgent['level'] ?? 1) < 2) {
    echo '<div class="alert alert-error">このページはディレクター以上のみ利用できます。</div>';
    require_once __DIR__ . '/footer.php';
    exit;
}

$db = getDB();
$aid = (int)$currentAgent['id'];
$myLv = (int)($currentAgent['level'] ?? 1);
$labels = getLevelLabels();
$positionLabels = getAdvisorPositionLabels();
$csrf = getCsrfToken();
$message = '';
$msgType = 'success';
$baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];

function recruitmentTableExists(PDO $db, string $table): bool {
    try {
        $stmt = $db->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('Recruitment table check failed: ' . $e->getMessage());
        return false;
    }
}

function ensureRecruitmentSchema(PDO $db): bool {
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS recruitment_links (
                id INT AUTO_INCREMENT PRIMARY KEY,
                agent_id INT NOT NULL,
                token VARCHAR(64) NOT NULL UNIQUE,
                name VARCHAR(255) NOT NULL,
                target_level TINYINT(1) NOT NULL DEFAULT 1,
                position_type VARCHAR(50) DEFAULT NULL,
                position_label VARCHAR(100) DEFAULT NULL,
                status ENUM('active','inactive') DEFAULT 'active',
                click_count INT NOT NULL DEFAULT 0,
                last_clicked_at DATETIME DEFAULT NULL,
                expires_at DATETIME DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_recruitment_agent (agent_id),
                INDEX idx_recruitment_target (target_level),
                FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        foreach ([
            "ALTER TABLE applicants ADD COLUMN IF NOT EXISTS recruitment_link_id INT DEFAULT NULL",
            "ALTER TABLE applicants ADD COLUMN IF NOT EXISTS recruitment_source VARCHAR(255) DEFAULT NULL",
        ] as $sql) {
            try {
                $db->exec($sql);
            } catch (Throwable $e) {
                $msg = $e->getMessage();
                if (strpos($msg, 'Duplicate column') === false && strpos($msg, '42S21') === false) {
                    throw $e;
                }
            }
        }

        try {
            $db->prepare("INSERT IGNORE INTO schema_migrations (version, description) VALUES (?, ?)")
               ->execute(['3.4.8', '招待URL管理']);
        } catch (Throwable $e) {
            error_log('Recruitment schema migration mark failed: ' . $e->getMessage());
        }

        return true;
    } catch (Throwable $e) {
        error_log('Recruitment schema ensure failed: ' . $e->getMessage());
        return false;
    }
}

function createRecruitmentToken(PDO $db): string {
    do {
        $token = bin2hex(random_bytes(24));
        $stmt = $db->prepare("SELECT id FROM recruitment_links WHERE token=? LIMIT 1");
        $stmt->execute([$token]);
    } while ($stmt->fetch());
    return $token;
}

function recruitmentTargetLabel(array $labels, int $targetLevel, ?string $positionType = null, ?string $positionLabel = null): string {
    if ($targetLevel === 1) {
        return getAdvisorPositionLabel($positionType, $positionLabel);
    }
    return $labels[$targetLevel] ?? 'ディレクター';
}

if (!recruitmentTableExists($db, 'recruitment_links') && !ensureRecruitmentSchema($db)) {
    echo '<div class="alert alert-error">招待URL管理のDBマイグレーションが未適用です。管理者にアップデート画面でDBマイグレーションを適用してもらってください。</div>';
    require_once __DIR__ . '/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = '不正なリクエストです。';
        $msgType = 'error';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'create') {
            $name = trim((string)($_POST['name'] ?? ''));
            $targetLevel = (int)($_POST['target_level'] ?? 1);
            if ($myLv < 3 || !in_array($targetLevel, [1, 2], true)) {
                $targetLevel = 1;
            }
            $positionType = $targetLevel === 1 ? normalizeAdvisorPosition((string)($_POST['position_type'] ?? 'advisor')) : null;
            $positionLabel = $targetLevel === 1 ? getAdvisorPositionLabel($positionType) : null;
            $days = max(0, min(3650, (int)($_POST['expires_days'] ?? 90)));
            $expiresAt = $days > 0 ? date('Y-m-d H:i:s', strtotime('+' . $days . ' days')) : null;

            if ($name === '') {
                $message = '招待URL名を入力してください。';
                $msgType = 'error';
            } else {
                $token = createRecruitmentToken($db);
                $stmt = $db->prepare("
                    INSERT INTO recruitment_links
                        (agent_id, token, name, target_level, position_type, position_label, expires_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$aid, $token, $name, $targetLevel, $positionType, $positionLabel, $expiresAt]);
                $message = '招待URLを作成しました。';
            }
        } elseif ($action === 'toggle') {
            $id = (int)($_POST['id'] ?? 0);
            $db->prepare("UPDATE recruitment_links SET status=IF(status='active','inactive','active') WHERE id=? AND agent_id=?")
               ->execute([$id, $aid]);
            $message = '招待URLの状態を変更しました。';
        } elseif ($action === 'regenerate') {
            $id = (int)($_POST['id'] ?? 0);
            $token = createRecruitmentToken($db);
            $db->prepare("UPDATE recruitment_links SET token=?, click_count=0, last_clicked_at=NULL WHERE id=? AND agent_id=?")
               ->execute([$token, $id, $aid]);
            $message = '招待URLを再発行しました。以前のURLは無効になります。';
        }
    }
}

$rows = $db->prepare("
    SELECT rl.*,
           COUNT(ap.id) AS applicant_count,
           SUM(CASE WHEN ap.status='pending' THEN 1 ELSE 0 END) AS pending_count,
           SUM(CASE WHEN ap.status='approved' THEN 1 ELSE 0 END) AS approved_count
    FROM recruitment_links rl
    LEFT JOIN applicants ap ON ap.recruitment_link_id = rl.id
    WHERE rl.agent_id=?
    GROUP BY rl.id
    ORDER BY rl.created_at DESC
");
$rows->execute([$aid]);
$links = $rows->fetchAll();
?>

<?php if ($message): ?><div class="alert alert-<?= h($msgType) ?>"><?= h($message) ?></div><?php endif; ?>

<div class="card">
    <p class="card-title">招待URLを作成</p>
    <p style="font-size:.78rem;color:var(--text-muted);line-height:1.7;margin:-.4rem 0 1rem;">
        アドバイザー・スーパーアドバイザー・インフルエンサーなど、招待する区分ごとにURLを発行できます。
    </p>
    <form method="post" style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr auto;gap:.85rem;align-items:end;">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="create">
        <div class="form-group" style="margin:0;">
            <label>招待URL名</label>
            <input type="text" name="name" placeholder="例：説明会用 スーパーアドバイザー招待" required>
        </div>
        <div class="form-group" style="margin:0;">
            <label>招待する階層</label>
            <select name="target_level" id="targetLevel">
                <?php if ($myLv >= 3): ?><option value="2"><?= h($labels[2] ?? 'ディレクター') ?></option><?php endif; ?>
                <option value="1"><?= h($labels[1] ?? 'アドバイザー') ?></option>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label>招待する区分</label>
            <select name="position_type" id="positionType">
                <?php foreach ($positionLabels as $key => $label): ?>
                <option value="<?= h($key) ?>"><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label>有効期限</label>
            <select name="expires_days">
                <option value="90">90日</option>
                <option value="30">30日</option>
                <option value="180">180日</option>
                <option value="365">1年</option>
                <option value="0">無期限</option>
            </select>
        </div>
        <button class="btn btn-gold" type="submit">作成</button>
    </form>
</div>

<div class="card" style="padding:0;overflow:hidden;">
    <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;gap:1rem;align-items:center;flex-wrap:wrap;">
        <p class="card-title" style="margin:0;border:none;padding:0;">招待URL一覧</p>
        <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;">
            <p style="font-size:.75rem;color:var(--text-muted);">招待URLごとのクリック数と応募数を確認できます。</p>
            <a href="/agent/export_csv.php?type=recruitment_links" class="btn btn-outline" style="font-size:.75rem;padding:.35rem .75rem;">CSV出力</a>
        </div>
    </div>
    <div class="table-scroll">
        <table style="min-width:1040px;">
            <thead>
                <tr>
                    <th>名前</th><th>招待区分</th><th>URL</th><th>クリック</th><th>応募</th><th>期限</th><th>状態</th><th>操作</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($links): foreach ($links as $link):
                $url = $baseUrl . '/join/' . $link['token'];
                $isExpired = !empty($link['expires_at']) && strtotime($link['expires_at']) < time();
                $targetLabel = recruitmentTargetLabel($labels, (int)$link['target_level'], $link['position_type'] ?? null, $link['position_label'] ?? null);
            ?>
                <tr>
                    <td style="font-weight:700;"><?= h($link['name']) ?></td>
                    <td style="font-size:.78rem;color:var(--gold-lt);"><?= h($targetLabel) ?></td>
                    <td style="min-width:260px;">
                        <input type="text" readonly value="<?= h($url) ?>" style="font-size:.78rem;padding:.45rem .55rem;margin-bottom:.35rem;">
                        <div style="display:flex;gap:.4rem;flex-wrap:wrap;">
                            <button class="btn btn-outline btn-sm" type="button" data-copy="<?= h($url) ?>">コピー</button>
                            <a class="btn btn-outline btn-sm" href="<?= h($url) ?>" target="_blank">開く</a>
                            <a class="btn btn-outline btn-sm" href="https://quickchart.io/qr?size=280&text=<?= h(rawurlencode($url)) ?>" target="_blank">QR</a>
                        </div>
                    </td>
                    <td><?= number_format((int)$link['click_count']) ?></td>
                    <td>
                        <?= number_format((int)$link['applicant_count']) ?>
                        <?php if ((int)$link['pending_count'] > 0): ?>
                        <span style="color:#e0a040;font-size:.72rem;margin-left:.25rem;">未<?= (int)$link['pending_count'] ?></span>
                        <?php endif; ?>
                        <?php if ((int)$link['approved_count'] > 0): ?>
                        <span style="color:#5ecb9b;font-size:.72rem;margin-left:.25rem;">承<?= (int)$link['approved_count'] ?></span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:.75rem;color:<?= $isExpired ? '#e08080' : 'var(--text-muted)' ?>;">
                        <?= $link['expires_at'] ? h(date('Y/m/d', strtotime($link['expires_at']))) : '無期限' ?>
                    </td>
                    <td>
                        <?php if ($isExpired): ?>
                        <span class="badge" style="background:rgba(139,26,26,.2);color:#e08080;">期限切れ</span>
                        <?php elseif ($link['status'] === 'active'): ?>
                        <span class="badge badge-contacted">有効</span>
                        <?php else: ?>
                        <span class="badge badge-closed">停止中</span>
                        <?php endif; ?>
                    </td>
                    <td style="white-space:nowrap;">
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= (int)$link['id'] ?>">
                            <button class="btn btn-outline btn-sm" type="submit"><?= $link['status'] === 'active' ? '停止' : '再開' ?></button>
                        </form>
                        <form method="post" style="display:inline;" onsubmit="return confirm('URLを再発行しますか？以前のURLは使えなくなります。')">
                            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                            <input type="hidden" name="action" value="regenerate">
                            <input type="hidden" name="id" value="<?= (int)$link['id'] ?>">
                            <button class="btn btn-outline btn-sm" type="submit">再発行</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:2rem;">まだ招待URLがありません。</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const target = document.getElementById('targetLevel');
    const position = document.getElementById('positionType');
    function syncPosition() {
        if (!target || !position) return;
        position.disabled = target.value !== '1';
        position.closest('.form-group').style.opacity = target.value === '1' ? '1' : '.45';
    }
    target?.addEventListener('change', syncPosition);
    syncPosition();

    document.querySelectorAll('[data-copy]').forEach(button => {
        button.addEventListener('click', async () => {
            const text = button.getAttribute('data-copy') || '';
            try {
                await navigator.clipboard.writeText(text);
                const old = button.textContent;
                button.textContent = 'コピー済み';
                setTimeout(() => { button.textContent = old; }, 1400);
            } catch (e) {
                window.prompt('URLをコピーしてください', text);
            }
        });
    });
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>

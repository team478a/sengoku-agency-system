<?php
$pageTitle = '昇格申請';
require_once __DIR__ . '/header.php';

$myLevel = (int)($currentAgent['level'] ?? 1);
$labels  = getLevelLabels();
$db      = getDB();
$csrf    = getCsrfToken();
$message = '';
$msgType = 'success';

// アドバイザー（level=1）→ディレクター：エージェントが承認
// ディレクター（level=2）→エージェント：本部が最終承認
$targetLevel  = $myLevel + 1;
$targetLabel  = $labels[$targetLevel] ?? 'エージェント';

if ($myLevel >= 3) {
    echo '<div class="alert alert-error">これ以上昇格できません。</div>';
    require_once __DIR__ . '/footer.php';
    exit;
}

if (empty($currentAgent['parent_id'])) {
    echo '<div class="alert alert-error">上位が設定されていません。本部にお問い合わせください。</div>';
    require_once __DIR__ . '/footer.php';
    exit;
}

// 既存の申請確認
$existing = $db->prepare("SELECT * FROM promotion_requests WHERE applicant_id=? AND status IN ('pending','agent_approved')");
$existing->execute([$currentAgent['id']]);
$existing = $existing->fetch();

// 申請送信
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$existing) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = '不正なリクエストです。'; $msgType = 'error';
    } else {
        $msg = trim($_POST['message'] ?? '');
        $db->prepare("INSERT INTO promotion_requests (applicant_id, approver_id, message, target_level) VALUES (?,?,?,?)")
           ->execute([$currentAgent['id'], $currentAgent['parent_id'], $msg, $targetLevel]);

        // 親（エージェントまたはディレクター）に通知メール
        try {
            require_once __DIR__ . '/../includes/mailer.php';
            $approver = $db->prepare("SELECT * FROM agents WHERE id=?");
            $approver->execute([$currentAgent['parent_id']]);
            $approver = $approver->fetch();
            if ($approver) {
                $mailer = new Mailer();
                $mailer->sendPromotionRequestNotice($currentAgent, $approver, $msg);
            }
        } catch (Exception $e) { error_log($e->getMessage()); }

        $message = h($targetLabel) . 'への昇格申請を送信しました。';
        $existing = $db->prepare("SELECT * FROM promotion_requests WHERE applicant_id=? AND status IN ('pending','agent_approved')");
        $existing->execute([$currentAgent['id']]);
        $existing = $existing->fetch();
    }
}

// 申請履歴
$history = $db->prepare("SELECT * FROM promotion_requests WHERE applicant_id=? ORDER BY created_at DESC LIMIT 5");
$history->execute([$currentAgent['id']]);
$history = $history->fetchAll();
?>

<?php if ($message): ?><div class="alert alert-<?= $msgType ?>"><?= h($message) ?></div><?php endif; ?>

<div class="card">
    <p class="card-title">⬆️ <?= h($targetLabel) ?>への昇格申請</p>

    <?php if ($myLevel === 1): ?>
    <div style="background:rgba(94,203,155,.06);border:1px solid rgba(94,203,155,.2);border-radius:4px;padding:.85rem 1rem;margin-bottom:1.25rem;font-size:.82rem;color:var(--text-muted);line-height:1.9;">
        <strong style="color:#5ecb9b;">承認フロー：</strong>
        あなたが申請 → 上位<?= h($labels[3] ?? 'エージェント') ?>が承認 → <?= h($targetLabel) ?>に昇格
    </div>
    <?php else: ?>
    <div style="background:rgba(201,168,76,.06);border:1px solid rgba(201,168,76,.2);border-radius:4px;padding:.85rem 1rem;margin-bottom:1.25rem;font-size:.82rem;color:var(--text-muted);line-height:1.9;">
        <strong style="color:var(--gold);">承認フロー：</strong>
        あなたが申請 → 上位<?= h($labels[3] ?? 'エージェント') ?>が推薦 → 本部が最終承認 → <?= h($targetLabel) ?>に昇格
    </div>
    <?php endif; ?>

    <?php if ($existing): ?>
    <div style="background:rgba(201,168,76,.08);border:1px solid rgba(201,168,76,.25);border-radius:4px;padding:1.25rem;text-align:center;">
        <p style="font-size:1.1rem;margin-bottom:.5rem;">⏳ 審査中</p>
        <p style="font-size:.85rem;color:var(--text-muted);line-height:1.8;">
            申請日時：<?= date('Y/m/d H:i', strtotime($existing['created_at'])) ?><br>
            <?php if ($myLevel === 2 && $existing['status'] === 'agent_approved'): ?>
            上位エージェントが推薦済み。本部の最終承認をお待ちください。
            <?php else: ?>
            上位の承認をお待ちください。
            <?php endif; ?>
        </p>
    </div>
    <?php else: ?>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <div class="form-group">
            <label>申請メッセージ（任意）</label>
            <textarea name="message" rows="5" placeholder="昇格を希望する理由や実績などをご記入ください。"></textarea>
        </div>
        <button type="submit" class="btn btn-gold" onclick="return confirm('昇格申請を送信しますか？')">
            <?= h($targetLabel) ?>への昇格を申請する
        </button>
    </form>
    <?php endif; ?>
</div>

<?php if ($history): ?>
<div class="card" style="padding:0;overflow:hidden;margin-top:1.25rem;">
    <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--border);">
        <p class="card-title" style="margin:0;border:none;padding:0;">申請履歴</p>
    </div>
    <table>
        <thead><tr><th>申請日時</th><th>申請先</th><th>状態</th></tr></thead>
        <tbody>
        <?php foreach ($history as $h_row):
            $stMap = ['pending'=>['審査中','#e0a040'],'agent_approved'=>['推薦済（本部待ち）','#88aaee'],'approved'=>['承認済','#5ecb9b'],'rejected'=>['却下','#e08080']];
            [$stLbl,$stCol] = $stMap[$h_row['status']] ?? ['—','#aaa'];
            $tgtLv = $h_row['target_level'] ?? ($myLevel + 1);
        ?>
        <tr>
            <td style="font-size:.82rem;"><?= date('Y/m/d H:i', strtotime($h_row['created_at'])) ?></td>
            <td style="font-size:.82rem;"><?= h($labels[$tgtLv] ?? 'Lv.'.$tgtLv) ?></td>
            <td><span style="color:<?= $stCol ?>;font-weight:700;font-size:.82rem;"><?= $stLbl ?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>

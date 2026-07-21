<?php
$pageTitle = '昇格申請管理';
require_once __DIR__ . '/header.php';

$myLevel = (int)($currentAgent['level'] ?? 1);
if ($myLevel < 3) {
    echo '<div class="alert alert-error">このページはエージェントのみ利用できます。</div>';
    require_once __DIR__ . '/footer.php';
    exit;
}

$db      = getDB();
$aid     = $currentAgent['id'];
$csrf    = getCsrfToken();
$message = '';
$msgType = 'success';
$labels  = getLevelLabels();

// 推薦・却下処理（エージェントは「推薦」のみ、最終承認は本部）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['recommend','reject'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = '不正なリクエストです。'; $msgType = 'error';
    } else {
        $rid    = (int)$_POST['request_id'];
        $action = $_POST['action'];
        $comment = trim($_POST['agent_comment'] ?? '');

        $req = $db->prepare("SELECT pr.*, a.person_name, a.agent_name FROM promotion_requests pr JOIN agents a ON pr.applicant_id=a.id WHERE pr.id=? AND pr.approver_id=? AND pr.status='pending'");
        $req->execute([$rid, $aid]);
        $req = $req->fetch();

        if (!$req) {
            $message = '申請が見つかりません。'; $msgType = 'error';
        } elseif ($action === 'recommend') {
            $targetLevel = (int)($req['target_level'] ?? 2);

            if ($targetLevel <= 2) {
                // アドバイザー→ディレクター：エージェントが即承認
                $db->beginTransaction();
                try {
                    $db->prepare("UPDATE promotion_requests SET status='approved', agent_comment=?, agent_reviewed_at=NOW(), reviewed_at=NOW() WHERE id=?")->execute([$comment, $rid]);
                    $db->prepare("UPDATE agents SET level=? WHERE id=?")->execute([$targetLevel, $req['applicant_id']]);
                    $db->commit();
                    try {
                        require_once __DIR__ . '/../includes/mailer.php';
                        $promoted = $db->prepare("SELECT * FROM agents WHERE id=?");
                        $promoted->execute([$req['applicant_id']]);
                        $promoted = $promoted->fetch();
                        if ($promoted) { (new Mailer())->sendPromotionNotice($promoted); }
                    } catch (Exception $e) { error_log($e->getMessage()); }
                    $message = h($req['person_name']) . ' さんを承認しました。昇格通知メールを送信しました。';
                } catch (Exception $e) {
                    $db->rollBack();
                    $message = 'エラー: ' . $e->getMessage(); $msgType = 'error';
                }
            } else {
                // ディレクター→エージェント：本部へ推薦
                $db->prepare("UPDATE promotion_requests SET status='agent_approved', agent_comment=?, agent_reviewed_at=NOW() WHERE id=?")->execute([$comment, $rid]);
                try {
                    require_once __DIR__ . '/../includes/mailer.php';
                    $applicantData = $db->prepare("SELECT * FROM agents WHERE id=?");
                    $applicantData->execute([$req['applicant_id']]);
                    $applicantData = $applicantData->fetch();
                    if ($applicantData) { (new Mailer())->sendPromotionRecommendNotice($applicantData, $currentAgent, $comment); }
                } catch (Exception $e) { error_log($e->getMessage()); }
                $message = h($req['person_name']) . ' さんを本部へ推薦しました。本部の最終承認をお待ちください。';
            }
        } else {
            $db->prepare("UPDATE promotion_requests SET status='rejected', agent_comment=?, agent_reviewed_at=NOW() WHERE id=?")
               ->execute([$comment, $rid]);
            $message = '申請を却下しました。';
        }
    }
}

$allowedStatuses = ['all','pending','agent_approved','approved','rejected'];
$filterStatus = $_GET['status'] ?? 'pending';
if (!in_array($filterStatus, $allowedStatuses, true)) $filterStatus = 'pending';
$whereStatus = $filterStatus !== 'all' ? "AND pr.status = '$filterStatus'" : '';
// ※ $filterStatus はホワイトリスト検証済みのため安全
$where = $whereStatus;
$requests     = $db->prepare("
    SELECT pr.*, a.person_name, a.agent_name, a.agent_code, a.email, a.level
    FROM promotion_requests pr
    JOIN agents a ON pr.applicant_id = a.id
    WHERE pr.approver_id = ? $where
    ORDER BY pr.created_at DESC
");
$requests->execute([$aid]);
$requests = $requests->fetchAll();

$counts = $db->prepare("SELECT status, COUNT(*) FROM promotion_requests WHERE approver_id=? GROUP BY status");
$counts->execute([$aid]);
$counts = $counts->fetchAll(PDO::FETCH_KEY_PAIR);
$stLabels = ['pending'=>'未確認','agent_approved'=>'本部審査中','approved'=>'承認済','rejected'=>'却下'];
?>

<?php if ($message): ?><div class="alert alert-<?= $msgType ?>"><?= h($message) ?></div><?php endif; ?>

<!-- フロー説明 -->
<div style="background:rgba(201,168,76,.06);border:1px solid rgba(201,168,76,.15);border-radius:4px;padding:1rem 1.25rem;margin-bottom:1.25rem;font-size:.82rem;line-height:2;color:var(--text-muted);">
    <strong style="color:var(--gold);">2段階承認フロー：</strong>
    ディレクターが申請 → <strong style="color:var(--paper);">あなたが推薦</strong> → 本部が最終承認 → 昇格完了
</div>

<!-- タブ -->
<div style="display:flex;gap:.5rem;margin-bottom:1rem;flex-wrap:wrap;">
    <?php foreach (['pending'=>'未確認','agent_approved'=>'本部審査中','approved'=>'承認済','rejected'=>'却下','all'=>'すべて'] as $s=>$l): ?>
    <a href="?status=<?= $s ?>" style="padding:.38rem .9rem;border-radius:3px;font-size:.78rem;text-decoration:none;
       background:<?= $filterStatus===$s?'rgba(201,168,76,.18)':'rgba(255,255,255,.04)' ?>;
       border:1px solid <?= $filterStatus===$s?'rgba(201,168,76,.5)':'rgba(255,255,255,.08)' ?>;
       color:<?= $filterStatus===$s?'var(--gold-lt)':'rgba(245,240,232,.6)' ?>;">
        <?= $l ?><?= isset($counts[$s]) ? " ({$counts[$s]})" : ($s==='all'?' ('.array_sum($counts).')':'') ?>
    </a>
    <?php endforeach; ?>
</div>

<div class="card" style="padding:0;overflow:hidden;">
    <table>
        <thead>
            <tr><th>申請者</th><th>申請日時</th><th>申請メッセージ</th><th>状態</th><th>操作</th></tr>
        </thead>
        <tbody>
        <?php if ($requests): foreach ($requests as $req):
            $stMap = ['pending'=>['未確認','#e0a040'],'agent_approved'=>['本部審査中','#88aaee'],'approved'=>['承認済','#5ecb9b'],'rejected'=>['却下','#e08080']];
            [$stLbl,$stCol] = $stMap[$req['status']] ?? ['—','#aaa'];
        ?>
        <tr>
            <td>
                <p style="font-weight:700;font-size:.88rem;"><?= h($req['person_name']) ?></p>
                <p style="font-size:.75rem;color:var(--text-muted);"><?= h($req['agent_name']) ?></p>
            </td>
            <td style="font-size:.78rem;color:var(--text-muted);"><?= date('Y/m/d H:i', strtotime($req['created_at'])) ?></td>
            <td style="font-size:.82rem;max-width:180px;"><?= $req['message'] ? h(mb_strimwidth($req['message'],0,50,'…')) : '<span style="color:var(--text-muted);">—</span>' ?></td>
            <td><span style="color:<?= $stCol ?>;font-weight:700;font-size:.82rem;"><?= $stLbl ?></span></td>
            <td style="white-space:nowrap;">
                <?php if ($req['status'] === 'pending'): ?>
                <?php
$tgtLv2 = (int)($req['target_level'] ?? 2);
$btnLabel = $tgtLv2 <= 2 ? '承認する' : '推薦する';
?>
<button class="btn btn-gold btn-sm" onclick="openModal(<?= $req['id'] ?>, '<?= h($req['person_name']) ?>', 'recommend', <?= $tgtLv2 ?>)"><?= $btnLabel ?></button>
                <button class="btn btn-danger btn-sm" onclick="openModal(<?= $req['id'] ?>, '<?= h($req['person_name']) ?>', 'reject')">却下</button>
                <?php elseif ($req['status'] === 'agent_approved'): ?>
                <span style="font-size:.75rem;color:#88aaee;">本部審査待ち</span>
                <?php else: ?>
                <span style="font-size:.75rem;color:var(--text-muted);"><?= $req['reviewed_at'] ? date('m/d', strtotime($req['reviewed_at'])) : '—' ?></span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:2.5rem;">該当する申請はありません。</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- 推薦/却下モーダル -->
<div id="actionModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:var(--ink);border:1px solid var(--border);border-radius:6px;padding:2rem;max-width:480px;width:90%;">
        <p id="modalTitle" style="font-family:'Noto Serif JP',serif;font-size:1.05rem;font-weight:700;color:var(--gold-lt);margin-bottom:1.25rem;"></p>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
            <input type="hidden" name="action" id="modalAction">
            <input type="hidden" name="request_id" id="modalRequestId">
            <div class="form-group">
                <label>コメント（任意・本部に伝わります）</label>
                <textarea name="agent_comment" rows="4" placeholder="推薦理由・実績など"></textarea>
            </div>
            <div style="display:flex;gap:.75rem;justify-content:flex-end;margin-top:1rem;">
                <button type="button" onclick="closeModal()" class="btn btn-outline">キャンセル</button>
                <button type="submit" id="modalSubmitBtn" class="btn btn-gold">確定する</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id, name, action, targetLevel) {
    document.getElementById('modalRequestId').value = id;
    document.getElementById('modalAction').value    = action;
    document.getElementById('modalTitle').textContent = action === 'recommend'
        ? name + ' さんを本部へ推薦します'
        : name + ' さんの申請を却下します';
    document.getElementById('modalSubmitBtn').textContent = isDirectApprove ? '承認する' : (action === 'recommend' ? '本部へ推薦する' : '却下する');
    document.getElementById('modalSubmitBtn').style.background = action === 'recommend'
        ? 'linear-gradient(135deg,#C9A84C,#E2C87A)' : '#8b1a1a';
    document.getElementById('actionModal').style.display = 'flex';
}
function closeModal() {
    document.getElementById('actionModal').style.display = 'none';
}
document.getElementById('actionModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>

<?php
$pageTitle = '昇格申請（最終承認）';
require_once __DIR__ . '/header.php';

$db      = getDB();
$csrf    = getCsrfToken();
$message = '';
$msgType = 'success';
$labels  = getLevelLabels();

// 最終承認・却下
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['approve','reject'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = '不正なリクエストです。'; $msgType = 'error';
    } else {
        $rid    = (int)$_POST['request_id'];
        $action = $_POST['action'];

        $req = $db->prepare("
            SELECT pr.*, a.person_name, a.agent_name, a.agent_code, a.email
            FROM promotion_requests pr
            JOIN agents a ON pr.applicant_id=a.id
            WHERE pr.id=? AND pr.status = 'agent_approved'
        ");
        $req->execute([$rid]);
        $req = $req->fetch();

        if (!$req) {
            $message = '申請が見つかりません。'; $msgType = 'error';
        } elseif ($action === 'approve') {
            $db->beginTransaction();
            try {
                $db->prepare("UPDATE promotion_requests SET status='approved', reviewed_at=NOW() WHERE id=?")->execute([$rid]);
                $db->prepare("UPDATE agents SET level=3 WHERE id=?")->execute([$req['applicant_id']]);
                $db->commit();

                // 昇格通知メール
                try {
                    require_once __DIR__ . '/../includes/mailer.php';
                    $promoted = $db->prepare("SELECT * FROM agents WHERE id=?");
                    $promoted->execute([$req['applicant_id']]);
                    $promoted = $promoted->fetch();
                    if ($promoted) {
                        $mailer = new Mailer();
                        $mailer->sendPromotionNotice($promoted);
                    }
                } catch (Exception $e) { error_log($e->getMessage()); }
                syncAgentToExternalPartner((int)$req['applicant_id'], 'promoted');

                $message = h($req['person_name']) . ' さんを ' . h($labels[3] ?? 'エージェント') . ' に昇格しました。通知メールを送信しました。';
            } catch (Exception $e) {
                $db->rollBack();
                $message = 'エラー: ' . $e->getMessage(); $msgType = 'error';
            }
        } else {
            $db->prepare("UPDATE promotion_requests SET status='rejected', reviewed_at=NOW() WHERE id=?")->execute([$rid]);
            $message = '申請を却下しました。';
        }
    }
}

$allowedStatuses = ['all','pending','agent_approved','approved','rejected'];
$filterStatus = $_GET['status'] ?? 'agent_approved';
if (!in_array($filterStatus, $allowedStatuses, true)) $filterStatus = 'agent_approved';
$whereStatus = $filterStatus !== 'all' ? "AND pr.status = '$filterStatus'" : '';
// ※ $filterStatus はホワイトリスト検証済みのため安全
$where = $whereStatus;
$requests = $db->query("
    SELECT pr.*, 
           ap.person_name, ap.agent_name, ap.agent_code, ap.email, ap.level,
           ag.person_name AS approver_name, ag.agent_name AS approver_agent
    FROM promotion_requests pr
    JOIN agents ap ON pr.applicant_id = ap.id
    JOIN agents ag ON pr.approver_id  = ag.id
    $where
    ORDER BY pr.created_at DESC
")->fetchAll();

$counts = $db->query("SELECT status, COUNT(*) FROM promotion_requests GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$stLabels = ['pending'=>'未確認','agent_approved'=>'推薦済（承認待ち）','approved'=>'承認済','rejected'=>'却下'];
$stColors = ['pending'=>'#e0a040','agent_approved'=>'#88aaee','approved'=>'#5ecb9b','rejected'=>'#e08080'];
?>

<?php if ($message): ?><div class="alert alert-<?= $msgType ?>"><?= h($message) ?></div><?php endif; ?>

<!-- フロー説明 -->
<div style="background:rgba(201,168,76,.06);border:1px solid rgba(201,168,76,.15);border-radius:4px;padding:1rem 1.25rem;margin-bottom:1.25rem;font-size:.82rem;line-height:2;color:var(--text-muted);">
    <strong style="color:var(--gold);">2段階承認フロー：</strong>
    ディレクターが申請 → エージェントが推薦 → <strong style="color:var(--paper);">本部が最終承認</strong> → 昇格完了
</div>

<!-- タブ -->
<div style="display:flex;gap:.5rem;margin-bottom:1rem;flex-wrap:wrap;">
    <?php foreach (['agent_approved'=>'推薦済（承認待ち）','pending'=>'未確認','approved'=>'承認済','rejected'=>'却下','all'=>'すべて'] as $s=>$l): ?>
    <a href="?status=<?= $s ?>" style="padding:.38rem .9rem;border-radius:3px;font-size:.78rem;text-decoration:none;
       background:<?= $filterStatus===$s?'rgba(201,168,76,.18)':'rgba(255,255,255,.04)' ?>;
       border:1px solid <?= $filterStatus===$s?'rgba(201,168,76,.5)':'rgba(255,255,255,.08)' ?>;
       color:<?= $filterStatus===$s?'var(--gold-lt)':'rgba(245,240,232,.6)' ?>;">
        <?= $l ?><?= isset($counts[$s])?" ({$counts[$s]})":($s==='all'?' ('.array_sum($counts).')':'') ?>
    </a>
    <?php endforeach; ?>
</div>

<div class="card" style="padding:0;overflow:hidden;">
    <table>
        <thead>
            <tr><th>申請者</th><th>推薦エージェント</th><th>エージェントコメント</th><th>申請日</th><th>状態</th><th>操作</th></tr>
        </thead>
        <tbody>
        <?php if ($requests): foreach ($requests as $req):
            $stCol = $stColors[$req['status']] ?? '#aaa';
            $stLbl = $stLabels[$req['status']] ?? '—';
        ?>
        <tr>
            <td>
                <p style="font-weight:700;font-size:.88rem;"><?= h($req['person_name']) ?></p>
                <p style="font-size:.75rem;color:var(--text-muted);"><?= h($req['agent_name']) ?> / <a href="/a/<?= h($req['agent_code']) ?>" target="_blank" style="color:var(--gold);text-decoration:none;">/a/<?= h($req['agent_code']) ?></a></p>
                <?php if ($req['message']): ?>
                <p style="font-size:.75rem;color:var(--text-muted);margin-top:.2rem;">申請：<?= h(mb_strimwidth($req['message'],0,40,'…')) ?></p>
                <?php endif; ?>
            </td>
            <td style="font-size:.82rem;">
                <p><?= h($req['approver_name']) ?></p>
                <p style="font-size:.75rem;color:var(--text-muted);"><?= h($req['approver_agent']) ?></p>
            </td>
            <td style="font-size:.82rem;max-width:160px;color:rgba(245,240,232,.75);"><?= $req['agent_comment'] ? h(mb_strimwidth($req['agent_comment'],0,50,'…')) : '<span style="color:var(--text-muted);">—</span>' ?></td>
            <td style="font-size:.78rem;color:var(--text-muted);"><?= date('Y/m/d', strtotime($req['created_at'])) ?></td>
            <td><span style="color:<?= $stCol ?>;font-weight:700;font-size:.82rem;"><?= $stLbl ?></span></td>
            <td style="white-space:nowrap;">
                <?php if ($req['status'] === 'agent_approved'): ?>
                <form method="post" style="display:inline;" onsubmit="return confirm('「<?= h($req['person_name']) ?>」さんをエージェントに昇格しますか？')">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                    <button class="btn btn-gold btn-sm">最終承認</button>
                </form>
                <form method="post" style="display:inline;" onsubmit="return confirm('却下しますか？')">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                    <button class="btn btn-danger btn-sm">却下</button>
                </form>
                <?php else: ?>
                <span style="font-size:.75rem;color:var(--text-muted);"><?= $req['reviewed_at'] ? date('m/d', strtotime($req['reviewed_at'])) : '—' ?></span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:2.5rem;">該当する申請はありません。</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>

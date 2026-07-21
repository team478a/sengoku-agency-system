<?php
/**
 * 申請管理
 * 本部直下の申請承認と、配下招待URLから届いた申請の確認を行う。
 */
$pageTitle = '申請管理';
require_once __DIR__ . '/header.php';

$db = getDB();
$message = '';
$msgType = 'success';
$labels = getLevelLabels();
$positionLabels = getAdvisorPositionLabels();

function adminApplicantColumns(PDO $db, string $table): array {
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];

    $columns = [];
    try {
        $rows = $db->query("SHOW COLUMNS FROM {$table}")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $columns[$row['Field']] = true;
        }
    } catch (Throwable $e) {
        error_log('Admin applicants column check failed: ' . $e->getMessage());
    }

    return $cache[$table] = $columns;
}

function adminApplicantTargetLabel(array $labels, array $app): string {
    $level = (int)($app['target_level'] ?? 3);
    if ($level === 1) {
        return getAdvisorPositionLabel($app['position_type'] ?? null, $app['position_label'] ?? null);
    }
    return $labels[$level] ?? 'エージェント';
}

$agentColumns = adminApplicantColumns($db, 'agents');
$applicantColumns = adminApplicantColumns($db, 'applicants');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'approve') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = '不正なリクエストです。';
        $msgType = 'error';
    } else {
        $appId = (int)($_POST['applicant_id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM applicants WHERE id = ? AND status = 'pending'");
        $stmt->execute([$appId]);
        $app = $stmt->fetch();

        if (!$app) {
            $message = '申請が見つからないか、すでに処理済みです。';
            $msgType = 'error';
        } elseif (!empty($app['agent_id'])) {
            $message = '招待URL経由の申請は、紹介元の管理画面から承認してください。';
            $msgType = 'error';
        } else {
            $emailCheck = $db->prepare("SELECT id FROM agents WHERE email=? LIMIT 1");
            $emailCheck->execute([$app['email']]);
            if ($emailCheck->fetch()) {
                $message = 'このメールアドレスはすでに登録されています。既存ユーザーの権限変更を行ってください。';
                $msgType = 'error';
            } else {
                try {
                    $db->beginTransaction();

                    $targetLevel = (int)($app['target_level'] ?? 3);
                    if (!in_array($targetLevel, [1, 2, 3], true)) {
                        $targetLevel = 3;
                    }
                    $positionType = $targetLevel === 1 ? normalizeAdvisorPosition((string)($app['position_type'] ?? 'advisor')) : null;
                    $positionLabel = $targetLevel === 1 ? getAdvisorPositionLabel($positionType, $app['position_label'] ?? null) : null;
                    $targetLabel = $targetLevel === 1 ? $positionLabel : ($labels[$targetLevel] ?? 'エージェント');
                    $prefix = $targetLevel === 3 ? 'agt' : ($targetLevel === 2 ? 'dir' : 'adv');
                    $agentCode = $prefix . '_' . $appId . '_' . strtolower(substr(bin2hex(random_bytes(2)), 0, 4));

                    $tplStmt = $db->query("SELECT id FROM lp_templates WHERE status='active' ORDER BY sort_order LIMIT 1");
                    $defaultTpl = $tplStmt ? $tplStmt->fetchColumn() : null;

                    $setupToken = bin2hex(random_bytes(32));
                    $setupTokenExp = date('Y-m-d H:i:s', strtotime('+24 hours'));

                    $insertColumns = [
                        'agent_code', 'agent_name', 'person_name', 'email', 'phone', 'line_url',
                        'level', 'parent_id', 'show_form', 'show_line_btn', 'notify_email',
                        'default_template_id', 'setup_token', 'setup_token_exp'
                    ];
                    $insertValues = [
                        $agentCode, $app['company_name'], $app['person_name'], $app['email'],
                        $app['phone'] ?? '', $app['line_url'] ?? '', $targetLevel, null,
                        1, 1, 1, $defaultTpl ?: null, $setupToken, $setupTokenExp
                    ];

                    if ($targetLevel === 1 && !empty($agentColumns['position_type']) && !empty($agentColumns['position_label'])) {
                        $insertColumns[] = 'position_type';
                        $insertColumns[] = 'position_label';
                        $insertValues[] = $positionType;
                        $insertValues[] = $positionLabel;
                    }

                    $placeholders = implode(',', array_fill(0, count($insertColumns), '?'));
                    $ins = $db->prepare("
                        INSERT INTO agents (" . implode(',', $insertColumns) . ")
                        VALUES ({$placeholders})
                    ");
                    $ins->execute($insertValues);
                    $agentId = (int)$db->lastInsertId();

                    $db->prepare("UPDATE applicants SET status='approved', agent_id=?, reviewed_at=NOW() WHERE id=?")
                       ->execute([$agentId, $appId]);

                    $db->commit();

                    $baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
                    $setupUrl = $baseUrl . '/agent/setup.php?token=' . $setupToken;

                    $mailSent = false;
                    try {
                        require_once __DIR__ . '/../includes/mailer.php';
                        $newAgent = $db->prepare("SELECT * FROM agents WHERE id=?");
                        $newAgent->execute([$agentId]);
                        $newAgent = $newAgent->fetch();
                        if ($newAgent) {
                            $mailSent = (new Mailer())->sendApprovalNotice($newAgent, $setupUrl);
                        }
                    } catch (Exception $e) {
                        error_log('Approval mail failed: ' . $e->getMessage());
                    }
                    syncAgentToExternalPartner($agentId, 'approved');

                    $mailStatus = $mailSent ? '承認メール送信済み' : 'メール未送信。設定を確認してください。';
                    $message = '「' . $app['company_name'] . '」を' . $targetLabel . 'として承認しました。LP: /a/' . $agentCode . ' / 初回設定URL: ' . $setupUrl . ' / ' . $mailStatus;
                } catch (Exception $e) {
                    if ($db->inTransaction()) $db->rollBack();
                    $message = 'エラーが発生しました: ' . $e->getMessage();
                    $msgType = 'error';
                    error_log($e->getMessage());
                }
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reject') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = '不正なリクエストです。';
        $msgType = 'error';
    } else {
        $appId = (int)($_POST['applicant_id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM applicants WHERE id=? AND status='pending'");
        $stmt->execute([$appId]);
        $app = $stmt->fetch();

        $db->prepare("UPDATE applicants SET status='rejected', reviewed_at=NOW() WHERE id=?")->execute([$appId]);

        if ($app) {
            try {
                require_once __DIR__ . '/../includes/mailer.php';
                (new Mailer())->sendRejectionNotice($app);
            } catch (Exception $e) {
                error_log('Rejection mail failed: ' . $e->getMessage());
            }
        }
        $message = '申請を却下しました。' . ($app ? '通知メールを送信しました。' : '');
    }
}

$filterStatus = $_GET['status'] ?? 'pending';
$validStatuses = ['pending', 'approved', 'rejected', 'all'];
if (!in_array($filterStatus, $validStatuses, true)) {
    $filterStatus = 'pending';
}

$where = $filterStatus !== 'all' ? "WHERE a.status = " . $db->quote($filterStatus) : '';
$counts = $db->query("SELECT status, COUNT(*) as cnt FROM applicants GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);

$applicants = $db->query("
    SELECT a.*, ag.agent_code, src.agent_name AS source_agent_name, src.person_name AS source_person_name, src.level AS source_level
    FROM applicants a
    LEFT JOIN agents ag ON a.agent_id = ag.id AND a.status = 'approved'
    LEFT JOIN agents src ON a.agent_id = src.id AND a.status = 'pending'
    $where
    ORDER BY a.created_at DESC
    LIMIT 100
")->fetchAll();

$csrf = getCsrfToken();
$statusLabel = ['pending' => '審査中', 'approved' => '承認済み', 'rejected' => '却下'];
$statusColor = ['pending' => '#e8c96e', 'approved' => '#5ecb9b', 'rejected' => 'rgba(245,240,232,.45)'];
?>

<?php if ($message): ?>
<div class="alert alert-<?= h($msgType) ?>"><?= h($message) ?></div>
<?php endif; ?>

<div style="display:flex;gap:.5rem;margin-bottom:1.5rem;flex-wrap:wrap;">
  <?php
  $tabs = ['pending' => '審査中', 'approved' => '承認済み', 'rejected' => '却下', 'all' => 'すべて'];
  foreach ($tabs as $s => $label):
      $cnt = $counts[$s] ?? ($s === 'all' ? array_sum($counts) : 0);
      $active = $filterStatus === $s;
  ?>
  <a href="?status=<?= h($s) ?>"
     style="padding:.45rem 1.1rem;border-radius:3px;font-size:.82rem;text-decoration:none;font-weight:<?= $active ? '700' : '400' ?>;
            background:<?= $active ? 'rgba(201,168,76,.18)' : 'rgba(255,255,255,.04)' ?>;
            border:1px solid <?= $active ? 'rgba(201,168,76,.5)' : 'rgba(255,255,255,.08)' ?>;
            color:<?= $active ? 'var(--gold-lt)' : 'rgba(245,240,232,.6)' ?>;">
    <?= h($label) ?><?= $cnt ? ' (' . (int)$cnt . ')' : '' ?>
  </a>
  <?php endforeach; ?>
</div>

<div class="card" style="padding:0;overflow:hidden;">
  <div class="table-scroll">
    <table style="min-width:1120px;">
      <thead>
        <tr>
          <th>#</th>
          <th>会社名・屋号</th>
          <th>担当者</th>
          <th>メール</th>
          <th>電話</th>
          <th>申請区分</th>
          <th>募集元</th>
          <th>申請日時</th>
          <th>状態</th>
          <th>操作</th>
        </tr>
      </thead>
      <tbody>
      <?php if ($applicants): ?>
      <?php foreach ($applicants as $app):
          $targetLabel = adminApplicantTargetLabel($labels, $app);
          $sourceLabel = !empty($app['source_agent_name'])
              ? $app['source_agent_name'] . (!empty($app['source_person_name']) ? '（' . $app['source_person_name'] . '）' : '')
              : '本部直属';
          $detail = $app;
          $detail['_target_label'] = $targetLabel;
          $detail['_source_label'] = $sourceLabel;
      ?>
      <tr>
        <td style="color:rgba(245,240,232,.45);font-size:.8rem;"><?= (int)$app['id'] ?></td>
        <td style="font-weight:700;"><?= h($app['company_name']) ?></td>
        <td><?= h($app['person_name']) ?></td>
        <td style="font-size:.82rem;">
          <a href="mailto:<?= h($app['email']) ?>" style="color:var(--gold);text-decoration:none;"><?= h($app['email']) ?></a>
        </td>
        <td style="font-size:.82rem;"><?= h(($app['phone'] ?? '') ?: '-') ?></td>
        <td style="font-size:.82rem;color:var(--gold-lt);"><?= h($targetLabel) ?></td>
        <td style="font-size:.78rem;">
          <?php if (!empty($app['source_agent_name'])): ?>
            <?= h($app['source_agent_name']) ?><br>
            <span style="color:var(--text-muted);"><?= h($labels[(int)($app['source_level'] ?? 1)] ?? '') ?></span>
          <?php else: ?>
            <span style="color:var(--text-muted);">本部直属</span>
          <?php endif; ?>
        </td>
        <td style="font-size:.78rem;color:rgba(245,240,232,.55);"><?= h(date('Y/m/d H:i', strtotime($app['created_at']))) ?></td>
        <td>
          <span style="font-size:.78rem;font-weight:700;color:<?= h($statusColor[$app['status']] ?? 'var(--text-muted)') ?>;">
            <?= h($statusLabel[$app['status']] ?? $app['status']) ?>
          </span>
          <?php if ($app['status'] === 'approved' && !empty($app['agent_code'])): ?>
          <br><a href="/a/<?= h($app['agent_code']) ?>" target="_blank" style="font-size:.72rem;color:var(--gold);text-decoration:none;">/a/<?= h($app['agent_code']) ?> ↗</a>
          <?php endif; ?>
        </td>
        <td style="white-space:nowrap;">
          <button class="btn btn-outline btn-sm" type="button" onclick="showDetail(<?= (int)$app['id'] ?>, <?= htmlspecialchars(json_encode($detail, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>)">詳細</button>

          <?php if ($app['status'] === 'pending'): ?>
            <?php if (empty($app['agent_id'])): ?>
            <form method="post" style="display:inline;" onsubmit="return confirm('承認して<?= h($targetLabel) ?>を開設しますか？')">
              <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
              <input type="hidden" name="action" value="approve">
              <input type="hidden" name="applicant_id" value="<?= (int)$app['id'] ?>">
              <button type="submit" class="btn btn-gold btn-sm">承認</button>
            </form>
            <?php else: ?>
            <span style="font-size:.72rem;color:var(--text-muted);">募集元で承認</span>
            <?php endif; ?>
            <form method="post" style="display:inline;" onsubmit="return confirm('この申請を却下しますか？')">
              <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
              <input type="hidden" name="action" value="reject">
              <input type="hidden" name="applicant_id" value="<?= (int)$app['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm">却下</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php else: ?>
      <tr><td colspan="10" style="text-align:center;color:var(--text-muted);padding:3rem;">
        <?= $filterStatus === 'pending' ? '審査待ちの申請はありません。' : '該当する申請はありません。' ?>
      </td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div id="detailModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:1000;align-items:center;justify-content:center;padding:1rem;">
  <div style="background:var(--ink);border:1px solid rgba(201,168,76,.25);border-radius:6px;padding:2rem;max-width:620px;width:100%;max-height:80vh;overflow-y:auto;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;gap:1rem;">
      <p style="font-family:'Noto Serif JP',serif;font-size:1.05rem;font-weight:700;color:var(--gold-lt);margin:0;">申請詳細</p>
      <button onclick="closeDetail()" type="button" style="background:none;border:none;color:rgba(245,240,232,.65);font-size:1.4rem;cursor:pointer;line-height:1;">×</button>
    </div>
    <dl id="detailContent" style="font-size:.88rem;line-height:2;"></dl>
  </div>
</div>

<script>
function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, function(char) {
    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char];
  });
}

function showDetail(id, data) {
  const fields = [
    ['#', id],
    ['会社名・屋号', data.company_name],
    ['担当者名', data.person_name],
    ['メール', data.email],
    ['電話', data.phone || '-'],
    ['LINE URL', data.line_url || '-'],
    ['申請区分', data._target_label || '-'],
    ['募集元', data._source_label || '本部直属'],
    ['メッセージ', data.message || '-'],
    ['申請日時', data.created_at],
    ['状態', data.status],
  ];
  const dl = document.getElementById('detailContent');
  dl.innerHTML = fields.map(function(pair) {
    return '<div style="display:flex;gap:1rem;border-bottom:1px solid rgba(201,168,76,.1);padding:.4rem 0;">'
      + '<dt style="min-width:110px;color:var(--gold);font-size:.78rem;">' + escapeHtml(pair[0]) + '</dt>'
      + '<dd style="color:rgba(232,224,204,.85);white-space:pre-wrap;margin:0;overflow-wrap:anywhere;">' + escapeHtml(pair[1]) + '</dd>'
      + '</div>';
  }).join('');
  document.getElementById('detailModal').style.display = 'flex';
}

function closeDetail() {
  document.getElementById('detailModal').style.display = 'none';
}

document.getElementById('detailModal').addEventListener('click', function(e) {
  if (e.target === this) closeDetail();
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>

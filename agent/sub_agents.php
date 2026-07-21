<?php
$pageTitle = '配下管理';
require_once __DIR__ . '/header.php';

if (($currentAgent['level'] ?? 1) < 2) {
    echo '<div class="alert alert-error">このページはディレクター以上のみアクセスできます。</div>';
    require_once __DIR__ . '/footer.php';
    exit;
}

$db = getDB();
$aid = (int)$currentAgent['id'];
$myLv = (int)($currentAgent['level'] ?? 1);
$labels = getLevelLabels();
$mode = $_GET['mode'] ?? ($myLv >= 3 ? 'directors' : 'advisors');
if ($myLv < 3 || !in_array($mode, ['directors', 'advisors', 'all_advisors'], true)) {
    $mode = 'advisors';
}
$managedLevel = ($myLv >= 3 && $mode === 'directors') ? 2 : 1;
$managedLabel = $labels[$managedLevel] ?? ($managedLevel === 2 ? 'ディレクター' : 'アドバイザー');
$modeParam = '?mode=' . rawurlencode($mode);
$csrf = getCsrfToken();
$message = '';
$msgType = 'success';
$allAdvisorMode = ($myLv >= 3 && $mode === 'all_advisors');
$advisorPositionLabels = getAdvisorPositionLabels();

function agentManagedIds(PDO $db, int $ownerId, int $managedLevel, bool $allAdvisorMode): array {
    if (!$allAdvisorMode) {
        $stmt = $db->prepare("SELECT id FROM agents WHERE parent_id=? AND level=?");
        $stmt->execute([$ownerId, $managedLevel]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
    $descendantRows = getAllDescendants($ownerId);
    $descendants = array_map(static fn($row) => (int)$row['id'], $descendantRows);
    if (!$descendants) return [];
    $ph = implode(',', array_fill(0, count($descendants), '?'));
    $stmt = $db->prepare("SELECT id FROM agents WHERE id IN ($ph) AND level=1");
    $stmt->execute($descendants);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function agentCanManageSub(PDO $db, int $ownerId, int $subId, int $managedLevel, bool $allAdvisorMode): bool {
    return in_array($subId, agentManagedIds($db, $ownerId, $managedLevel, $allAdvisorMode), true);
}

function agentTableColumns(PDO $db, string $table): array {
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];
    $columns = [];
    try {
        $rows = $db->query("SHOW COLUMNS FROM {$table}")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) $columns[$row['Field']] = true;
    } catch (Throwable $e) {
        error_log('Agent table column check failed: ' . $e->getMessage());
    }
    return $cache[$table] = $columns;
}

function agentDefaultTemplateId(PDO $db): ?int {
    $tpl = $db->query("SELECT id FROM lp_templates WHERE status='active' ORDER BY sort_order LIMIT 1")->fetchColumn();
    return $tpl ? (int)$tpl : null;
}

$agentColumns = agentTableColumns($db, 'agents');
$applicantColumns = agentTableColumns($db, 'applicants');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'approve_applicant') {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $appId = (int)($_POST['applicant_id'] ?? 0);
        $appStmt = $db->prepare("SELECT * FROM applicants WHERE id=? AND agent_id=? AND status='pending'");
        $appStmt->execute([$appId, $aid]);
        $app = $appStmt->fetch();

        if ($app) {
            $targetLevel = (int)($app['target_level'] ?? $managedLevel);
            $allowedTargetLevels = $myLv >= 3 ? [1, 2] : [1];
            if (!in_array($targetLevel, $allowedTargetLevels, true)) {
                $message = 'この申請は現在の権限では承認できません。';
                $msgType = 'error';
            } else {
                $emailCheck = $db->prepare("SELECT id FROM agents WHERE email=? LIMIT 1");
                $emailCheck->execute([$app['email']]);
                if ($emailCheck->fetch()) {
                    $message = 'このメールアドレスはすでに登録されています。';
                    $msgType = 'error';
                } else {
                    $targetLabel = $labels[$targetLevel] ?? $managedLabel;
                    $positionType = $targetLevel === 1 ? normalizeAdvisorPosition((string)($app['position_type'] ?? 'advisor')) : '';
                    $positionLabel = $targetLevel === 1 ? getAdvisorPositionLabel($positionType, $app['position_label'] ?? null) : '';
                    $approvalLabel = $positionLabel ?: $targetLabel;
                    $codePrefix = $targetLevel === 2 ? 'dir' : 'adv';
                    $agentCode = $codePrefix . strtolower(bin2hex(random_bytes(4)));
                    $token = bin2hex(random_bytes(32));
                    $exp = date('Y-m-d H:i:s', strtotime('+72 hours'));

                    $db->beginTransaction();
                    try {
                        $insertColumns = ['agent_code','agent_name','person_name','email','phone','line_url','level','parent_id','default_template_id','show_form','show_line_btn','notify_email','setup_token','setup_token_exp'];
                        $insertValues = [$agentCode,$app['company_name'],$app['person_name'],$app['email'],$app['phone'] ?? '',$app['line_url'] ?? '',$targetLevel,$aid,agentDefaultTemplateId($db),1,1,1,$token,$exp];
                        if ($targetLevel === 1 && !empty($agentColumns['position_type']) && !empty($agentColumns['position_label'])) {
                            $insertColumns[] = 'position_type';
                            $insertColumns[] = 'position_label';
                            $insertValues[] = $positionType;
                            $insertValues[] = $positionLabel;
                        }
                        $placeholders = implode(',', array_fill(0, count($insertColumns), '?'));
                        $db->prepare("INSERT INTO agents (" . implode(',', $insertColumns) . ") VALUES ({$placeholders})")->execute($insertValues);
                        $newId = (int)$db->lastInsertId();
                        $db->prepare("UPDATE applicants SET status='approved', reviewed_at=NOW() WHERE id=?")->execute([$appId]);
                        $db->commit();

                        $baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
                        $setupUrl = $baseUrl . '/agent/setup.php?token=' . $token;
                        try {
                            require_once __DIR__ . '/../includes/mailer.php';
                            $newAgent = $db->prepare("SELECT * FROM agents WHERE id=?");
                            $newAgent->execute([$newId]);
                            $newAgent = $newAgent->fetch();
                            if ($newAgent) (new Mailer())->sendApprovalNotice($newAgent, $setupUrl);
                        } catch (Exception $e) {
                            error_log($e->getMessage());
                        }
                        syncAgentToExternalPartner($newId, 'approved_by_parent');

                        $message = '「' . $app['person_name'] . '」さんを' . $approvalLabel . 'として承認しました。初回設定URL: ' . $setupUrl;
                    } catch (Exception $e) {
                        if ($db->inTransaction()) $db->rollBack();
                        $message = 'エラー: ' . $e->getMessage();
                        $msgType = 'error';
                    }
                }
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reject_applicant') {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $appId = (int)($_POST['applicant_id'] ?? 0);
        $db->prepare("UPDATE applicants SET status='rejected', reviewed_at=NOW() WHERE id=? AND agent_id=?")->execute([$appId, $aid]);
        $message = '申請を却下しました。';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset_password') {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $sid = (int)($_POST['id'] ?? 0);
        if (agentCanManageSub($db, $aid, $sid, $managedLevel, $allAdvisorMode)) {
            $token = bin2hex(random_bytes(32));
            $exp = date('Y-m-d H:i:s', strtotime('+24 hours'));
            $db->prepare("UPDATE agents SET setup_token=?, setup_token_exp=?, password=NULL WHERE id=?")->execute([$token, $exp, $sid]);
            $baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
            $message = '初回設定URLを再発行しました（24時間有効）: ' . $baseUrl . '/agent/setup.php?token=' . $token;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $sid = (int)($_POST['id'] ?? 0);
        if (agentCanManageSub($db, $aid, $sid, $managedLevel, $allAdvisorMode)) {
            $db->prepare("UPDATE agents SET status=IF(status='active','inactive','active') WHERE id=?")->execute([$sid]);
            syncAgentToExternalPartner($sid, 'status_changed_by_parent');
            $message = 'ステータスを変更しました。';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = '不正なリクエストです。';
        $msgType = 'error';
    } else {
        $sid = (int)($_POST['id'] ?? 0);
        if ($sid === $aid) {
            $message = '自分自身は削除できません。';
            $msgType = 'error';
        } elseif (agentCanManageSub($db, $aid, $sid, $managedLevel, $allAdvisorMode)) {
            try {
                $targetStmt = $db->prepare("SELECT agent_name, level FROM agents WHERE id=?");
                $targetStmt->execute([$sid]);
                $deleteTarget = $targetStmt->fetch();

                $deleteIds = [$sid];
                foreach (getAllDescendants($sid) as $descendant) {
                    $deleteIds[] = (int)$descendant['id'];
                }
                $deleteIds = array_values(array_unique(array_filter($deleteIds)));
                rsort($deleteIds);
                $deleteRows = [];
                if ($deleteIds) {
                    $ph = implode(',', array_fill(0, count($deleteIds), '?'));
                    $rowsStmt = $db->prepare("SELECT * FROM agents WHERE id IN ($ph)");
                    $rowsStmt->execute($deleteIds);
                    $deleteRows = $rowsStmt->fetchAll();
                }
                foreach ($deleteRows as $deleteRow) {
                    syncAgentArrayToExternalPartnerSites($deleteRow, 'deleted_by_parent');
                }

                $db->beginTransaction();
                foreach ($deleteIds as $deleteId) {
                    $db->prepare("DELETE FROM agents WHERE id=?")->execute([$deleteId]);
                }
                $db->commit();

                $deletedName = $deleteTarget['agent_name'] ?? '配下メンバー';
                $message = '「' . $deletedName . '」を削除しました。配下がある場合は配下メンバーも削除されています。';
            } catch (Throwable $e) {
                if ($db->inTransaction()) $db->rollBack();
                $message = '削除に失敗しました: ' . $e->getMessage();
                $msgType = 'error';
            }
        } else {
            $message = '削除権限がありません。';
            $msgType = 'error';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = '不正なリクエストです。';
        $msgType = 'error';
    } else {
        $agentCode = sanitizeInput($_POST['agent_code'] ?? '');
        $agentName = sanitizeInput($_POST['agent_name'] ?? '');
        $personName = sanitizeInput($_POST['person_name'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $phone = sanitizeInput($_POST['phone'] ?? '');
        $lineUrl = sanitizeInput($_POST['line_url'] ?? '');
        $positionType = $managedLevel === 1 ? normalizeAdvisorPosition((string)($_POST['position_type'] ?? 'advisor')) : '';
        $positionLabel = $managedLevel === 1 ? getAdvisorPositionLabel($positionType) : '';

        $errors = [];
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $agentCode)) $errors[] = 'コードは英数字、ハイフン、アンダースコアのみ使用できます。';
        if (!$agentName) $errors[] = $managedLabel . '名は必須です。';
        if (!$personName) $errors[] = '担当者名は必須です。';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = '有効なメールアドレスを入力してください。';
        $emailCheck = $db->prepare("SELECT id FROM agents WHERE email=? LIMIT 1");
        $emailCheck->execute([$email]);
        if ($emailCheck->fetch()) $errors[] = 'このメールアドレスはすでに登録されています。';

        if (!$errors) {
            try {
                $token = bin2hex(random_bytes(32));
                $exp = date('Y-m-d H:i:s', strtotime('+72 hours'));
                $subLevel = $managedLevel;
                $insertColumns = ['agent_code','agent_name','person_name','email','phone','line_url','level','parent_id','default_template_id','show_form','show_line_btn','notify_email','setup_token','setup_token_exp'];
                $insertValues = [$agentCode,$agentName,$personName,$email,$phone,$lineUrl,$subLevel,$aid,agentDefaultTemplateId($db),1,1,1,$token,$exp];
                if ($subLevel === 1 && !empty($agentColumns['position_type']) && !empty($agentColumns['position_label'])) {
                    $insertColumns[] = 'position_type';
                    $insertColumns[] = 'position_label';
                    $insertValues[] = $positionType;
                    $insertValues[] = $positionLabel;
                }
                $placeholders = implode(',', array_fill(0, count($insertColumns), '?'));
                $db->prepare("INSERT INTO agents (" . implode(',', $insertColumns) . ") VALUES ({$placeholders})")->execute($insertValues);
                $newId = (int)$db->lastInsertId();
                $baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
                $setupUrl = $baseUrl . '/agent/setup.php?token=' . $token;

                try {
                    require_once __DIR__ . '/../includes/mailer.php';
                    $newAgent = $db->prepare("SELECT * FROM agents WHERE id=?");
                    $newAgent->execute([$newId]);
                    $newAgent = $newAgent->fetch();
                    if ($newAgent) (new Mailer())->sendApprovalNotice($newAgent, $setupUrl);
                } catch (Exception $e) {
                    error_log($e->getMessage());
                }
                syncAgentToExternalPartner($newId, 'created_by_parent');

                $registeredLabel = $positionLabel ?: $managedLabel;
                $message = '「' . $agentName . '」を' . $registeredLabel . 'として登録しました。初回設定URL（72時間有効）: ' . $setupUrl;
            } catch (PDOException $e) {
                $message = 'エラー: コードが重複している可能性があります。';
                $msgType = 'error';
            }
        } else {
            $message = implode(' ', $errors);
            $msgType = 'error';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = '不正なリクエストです。';
        $msgType = 'error';
    } else {
        $sid = (int)($_POST['id'] ?? 0);
        if (agentCanManageSub($db, $aid, $sid, $managedLevel, $allAdvisorMode)) {
            $email = sanitizeInput($_POST['email'] ?? '');
            $emailCheck = $db->prepare("SELECT id FROM agents WHERE email=? AND id<>? LIMIT 1");
            $emailCheck->execute([$email, $sid]);
            if ($emailCheck->fetch()) {
                $message = 'このメールアドレスはすでに登録されています。';
                $msgType = 'error';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $message = '有効なメールアドレスを入力してください。';
                $msgType = 'error';
            } else {
                $sets = ['agent_name=?','person_name=?','email=?','phone=?','line_url=?'];
                $params = [
                    sanitizeInput($_POST['agent_name'] ?? ''),
                    sanitizeInput($_POST['person_name'] ?? ''),
                    $email,
                    sanitizeInput($_POST['phone'] ?? ''),
                    sanitizeInput($_POST['line_url'] ?? ''),
                ];
                if ($managedLevel === 1 && !empty($agentColumns['position_type']) && !empty($agentColumns['position_label'])) {
                    $positionType = normalizeAdvisorPosition((string)($_POST['position_type'] ?? 'advisor'));
                    $sets[] = 'position_type=?';
                    $sets[] = 'position_label=?';
                    $params[] = $positionType;
                    $params[] = getAdvisorPositionLabel($positionType);
                }
                $params[] = $sid;
                $db->prepare("UPDATE agents SET " . implode(',', $sets) . " WHERE id=?")->execute($params);
                syncAgentToExternalPartner($sid, 'updated_by_parent');
                $message = '情報を更新しました。';
            }
        }
    }
}

$editSub = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    if (agentCanManageSub($db, $aid, $editId, $managedLevel, $allAdvisorMode)) {
        $s = $db->prepare("SELECT * FROM agents WHERE id=?");
        $s->execute([$editId]);
        $editSub = $s->fetch() ?: null;
    }
}

if ($allAdvisorMode) {
    $ids = agentManagedIds($db, $aid, 1, true);
    if ($ids) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare("
            SELECT a.*, p.agent_name AS parent_name
            FROM agents a
            LEFT JOIN agents p ON a.parent_id=p.id
            WHERE a.id IN ($ph)
            ORDER BY p.agent_name, a.created_at DESC
        ");
        $stmt->execute($ids);
        $subAgents = $stmt->fetchAll();
    } else {
        $subAgents = [];
    }
} else {
    $stmt = $db->prepare("SELECT * FROM agents WHERE parent_id=? AND level=? ORDER BY created_at DESC");
    $stmt->execute([$aid, $managedLevel]);
    $subAgents = $stmt->fetchAll();
}

$totalPv = 0;
$totalLeads = 0;
$newLeads = 0;
foreach ($subAgents as $sa) {
    $r = $db->prepare("SELECT COUNT(*) FROM access_logs WHERE agent_id=? AND type='pv'");
    $r->execute([$sa['id']]);
    $totalPv += (int)$r->fetchColumn();
    $r = $db->prepare("SELECT COUNT(*) FROM leads WHERE agent_id=?");
    $r->execute([$sa['id']]);
    $totalLeads += (int)$r->fetchColumn();
    $r = $db->prepare("SELECT COUNT(*) FROM leads WHERE agent_id=? AND status='new'");
    $r->execute([$sa['id']]);
    $newLeads += (int)$r->fetchColumn();
}
?>

<?php if ($message): ?><div class="alert alert-<?= h($msgType) ?>"><?= h($message) ?></div><?php endif; ?>

<?php if ($myLv >= 3): ?>
<div style="display:flex;gap:.5rem;margin-bottom:1rem;flex-wrap:wrap;">
    <a href="/agent/sub_agents.php?mode=directors" class="btn <?= $mode === 'directors' ? 'btn-gold' : 'btn-outline' ?>"><?= h($labels[2] ?? 'ディレクター') ?>管理</a>
    <a href="/agent/sub_agents.php?mode=advisors" class="btn <?= $mode === 'advisors' ? 'btn-gold' : 'btn-outline' ?>">直下<?= h($labels[1] ?? 'アドバイザー') ?>管理</a>
    <a href="/agent/sub_agents.php?mode=all_advisors" class="btn <?= $mode === 'all_advisors' ? 'btn-gold' : 'btn-outline' ?>">全配下<?= h($labels[1] ?? 'アドバイザー') ?>一覧</a>
    <a href="/agent/export_csv.php?type=sub_agents&mode=<?= h($mode) ?>" class="btn btn-outline">CSV出力</a>
</div>
<?php else: ?>
<div style="display:flex;gap:.5rem;margin-bottom:1rem;flex-wrap:wrap;">
    <a href="/agent/export_csv.php?type=sub_agents&mode=<?= h($mode) ?>" class="btn btn-outline">CSV出力</a>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1.25rem;">
    <div class="card" style="margin:0;text-align:center;padding:1.1rem;">
        <p style="font-size:.72rem;color:var(--text-muted);margin-bottom:.3rem;">配下<?= h($managedLabel) ?>数</p>
        <p style="font-family:'Noto Serif JP',serif;font-size:1.8rem;font-weight:900;color:var(--gold-lt);"><?= count($subAgents) ?></p>
    </div>
    <div class="card" style="margin:0;text-align:center;padding:1.1rem;">
        <p style="font-size:.72rem;color:var(--text-muted);margin-bottom:.3rem;">配下合計PV</p>
        <p style="font-family:'Noto Serif JP',serif;font-size:1.8rem;font-weight:900;color:var(--gold-lt);"><?= number_format($totalPv) ?></p>
    </div>
    <div class="card" style="margin:0;text-align:center;padding:1.1rem;">
        <p style="font-size:.72rem;color:var(--text-muted);margin-bottom:.3rem;">配下合計問い合わせ</p>
        <p style="font-family:'Noto Serif JP',serif;font-size:1.8rem;font-weight:900;color:<?= $newLeads > 0 ? '#e0a040' : 'var(--gold-lt)' ?>;"><?= number_format($totalLeads) ?></p>
    </div>
</div>

<?php if (!$allAdvisorMode): ?>
<div class="card">
    <p class="card-title"><?= $editSub ? h($managedLabel) . '情報を編集' : '新規' . h($managedLabel) . 'を追加' ?></p>
    <form method="post" action="/agent/sub_agents.php<?= h($modeParam) ?>">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="<?= $editSub ? 'update' : 'create' ?>">
        <?php if ($editSub): ?><input type="hidden" name="id" value="<?= (int)$editSub['id'] ?>"><?php endif; ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;">
            <?php if (!$editSub): ?>
            <div class="form-group">
                <label><?= h($managedLabel) ?>コード（URL用）</label>
                <input type="text" name="agent_code" placeholder="agent001" required>
            </div>
            <div class="form-group">
                <label>区分</label>
                <select name="sub_level" disabled>
                    <option value="<?= (int)$managedLevel ?>"><?= h($managedLabel) ?></option>
                </select>
            </div>
            <?php endif; ?>
            <?php if ($managedLevel === 1): ?>
            <div class="form-group">
                <label>アドバイザー種別</label>
                <?php $selectedPosition = normalizeAdvisorPosition((string)($editSub['position_type'] ?? 'advisor')); ?>
                <select name="position_type">
                    <?php foreach ($advisorPositionLabels as $posKey => $posLabel): ?>
                    <option value="<?= h($posKey) ?>" <?= $selectedPosition === $posKey ? 'selected' : '' ?>><?= h($posLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="form-group">
                <label><?= h($managedLabel) ?>名 *</label>
                <input type="text" name="agent_name" value="<?= h($editSub['agent_name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>担当者名 *</label>
                <input type="text" name="person_name" value="<?= h($editSub['person_name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>メールアドレス *</label>
                <input type="email" name="email" value="<?= h($editSub['email'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>電話番号</label>
                <input type="tel" name="phone" value="<?= h($editSub['phone'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>LINE URL</label>
                <input type="url" name="line_url" value="<?= h($editSub['line_url'] ?? '') ?>" placeholder="https://lin.ee/...">
            </div>
        </div>
        <div style="display:flex;gap:.75rem;">
            <button type="submit" class="btn btn-gold"><?= $editSub ? '更新する' : '登録して初回設定URLを発行' ?></button>
            <?php if ($editSub): ?><a href="/agent/sub_agents.php<?= h($modeParam) ?>" class="btn btn-outline">キャンセル</a><?php endif; ?>
        </div>
        <?php if (!$editSub): ?>
        <p style="font-size:.75rem;color:var(--text-muted);margin-top:.75rem;">登録後、初回設定URL（72時間有効）が表示されます。対象者に共有してください。</p>
        <?php endif; ?>
    </form>
</div>
<?php endif; ?>

<?php
$pendingApps = $db->prepare("SELECT * FROM applicants WHERE agent_id=? AND target_level=? AND status='pending' ORDER BY created_at DESC");
$pendingApps->execute([$aid, $managedLevel]);
$pendingApps = $pendingApps->fetchAll();
if ($pendingApps):
?>
<div class="card" style="padding:0;overflow:hidden;margin-bottom:1.25rem;">
    <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
        <p class="card-title" style="margin:0;border:none;padding:0;">参加申請 <span style="background:#8b1a1a;color:#fff;font-size:.7rem;padding:.1rem .45rem;border-radius:9px;margin-left:.5rem;"><?= count($pendingApps) ?></span></p>
    </div>
    <div class="table-scroll">
        <table style="min-width:860px;">
            <thead><tr><th>会社名・屋号</th><?php if ($managedLevel === 1): ?><th>募集区分</th><?php endif; ?><?php if (!empty($applicantColumns['recruitment_source'])): ?><th>募集元</th><?php endif; ?><th>担当者</th><th>メール</th><th>申請日</th><th>操作</th></tr></thead>
            <tbody>
            <?php foreach ($pendingApps as $app): ?>
            <tr>
                <td style="font-weight:700;"><?= h($app['company_name']) ?></td>
                <?php if ($managedLevel === 1): ?><td style="font-size:.78rem;color:var(--gold-lt);"><?= h(getAdvisorPositionLabel($app['position_type'] ?? null, $app['position_label'] ?? null)) ?></td><?php endif; ?>
                <?php if (!empty($applicantColumns['recruitment_source'])): ?><td style="font-size:.75rem;color:var(--text-muted);"><?= h(($app['recruitment_source'] ?? '') ?: '通常URL') ?></td><?php endif; ?>
                <td><?= h($app['person_name']) ?></td>
                <td style="font-size:.78rem;"><a href="mailto:<?= h($app['email']) ?>" style="color:var(--gold);text-decoration:none;"><?= h($app['email']) ?></a></td>
                <td style="font-size:.75rem;color:var(--text-muted);"><?= h(date('m/d H:i', strtotime($app['created_at']))) ?></td>
                <td style="white-space:nowrap;">
                    <form method="post" action="/agent/sub_agents.php<?= h($modeParam) ?>" style="display:inline;" onsubmit="return confirm('「<?= h($app['person_name']) ?>」さんを<?= h($labels[(int)($app['target_level'] ?? $managedLevel)] ?? $managedLabel) ?>として承認しますか？')">
                        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                        <input type="hidden" name="action" value="approve_applicant">
                        <input type="hidden" name="applicant_id" value="<?= (int)$app['id'] ?>">
                        <button class="btn btn-gold btn-sm">承認</button>
                    </form>
                    <form method="post" action="/agent/sub_agents.php<?= h($modeParam) ?>" style="display:inline;" onsubmit="return confirm('却下しますか？')">
                        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                        <input type="hidden" name="action" value="reject_applicant">
                        <input type="hidden" name="applicant_id" value="<?= (int)$app['id'] ?>">
                        <button class="btn btn-danger btn-sm">却下</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="card" style="padding:0;overflow:hidden;">
    <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--border);">
        <p class="card-title" style="margin:0;border:none;padding:0;"><?= $allAdvisorMode ? '全配下アドバイザー一覧' : '配下一覧' ?></p>
    </div>
    <div class="table-scroll">
        <table style="min-width:1180px;">
            <thead>
                <tr><th>区分</th><?php if ($managedLevel === 1 || $allAdvisorMode): ?><th>アドバイザー種別</th><?php endif; ?><th>コード</th><th>名称</th><th>担当者</th><?php if ($allAdvisorMode): ?><th>上位</th><?php endif; ?><th>PV</th><th>問い合わせ</th><th>LP</th><th>状態</th><th>操作</th></tr>
            </thead>
            <tbody>
            <?php if ($subAgents): foreach ($subAgents as $sa):
                $pv = $db->prepare("SELECT COUNT(*) FROM access_logs WHERE agent_id=? AND type='pv'");
                $pv->execute([$sa['id']]); $pv = (int)$pv->fetchColumn();
                $lc = $db->prepare("SELECT COUNT(*) FROM leads WHERE agent_id=?");
                $lc->execute([$sa['id']]); $lc = (int)$lc->fetchColumn();
                $nl = $db->prepare("SELECT COUNT(*) FROM leads WHERE agent_id=? AND status='new'");
                $nl->execute([$sa['id']]); $nl = (int)$nl->fetchColumn();
            ?>
            <tr>
                <td style="font-size:.75rem;color:var(--text-muted);"><?= h($labels[(int)($sa['level'] ?? 1)] ?? '') ?></td>
                <?php if ($managedLevel === 1 || $allAdvisorMode): ?><td style="font-size:.75rem;color:var(--gold-lt);"><?= h(getAdvisorPositionLabel($sa['position_type'] ?? null, $sa['position_label'] ?? null)) ?></td><?php endif; ?>
                <td style="font-family:monospace;font-size:.8rem;color:var(--gold)"><?= h($sa['agent_code']) ?></td>
                <td style="font-weight:700;"><?= h($sa['agent_name']) ?></td>
                <td><?= h($sa['person_name']) ?></td>
                <?php if ($allAdvisorMode): ?><td style="font-size:.75rem;color:var(--text-muted);"><?= h($sa['parent_name'] ?? '-') ?></td><?php endif; ?>
                <td style="font-size:.82rem;"><?= number_format($pv) ?></td>
                <td style="font-size:.82rem;"><?= number_format($lc) ?><?php if ($nl > 0): ?><span style="color:#e0a040;font-size:.72rem;margin-left:.3rem;">未<?= $nl ?></span><?php endif; ?></td>
                <td style="font-size:.75rem;"><a href="/a/<?= h($sa['agent_code']) ?>" target="_blank" style="color:var(--gold);text-decoration:none;">/a/<?= h($sa['agent_code']) ?> ↗</a></td>
                <td><span class="badge badge-<?= $sa['status'] === 'active' ? 'active' : 'inactive' ?>"><?= $sa['status'] === 'active' ? '公開中' : '停止中' ?></span></td>
                <td style="white-space:nowrap;">
                    <?php if (!$allAdvisorMode): ?><a href="/agent/sub_agents.php<?= h($modeParam) ?>&edit=<?= (int)$sa['id'] ?>" class="btn btn-outline btn-sm">編集</a><?php endif; ?>
                    <form method="post" action="/agent/sub_agents.php<?= h($modeParam) ?>" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?= (int)$sa['id'] ?>">
                        <button class="btn btn-outline btn-sm"><?= $sa['status'] === 'active' ? '停止' : '再開' ?></button>
                    </form>
                    <form method="post" action="/agent/sub_agents.php<?= h($modeParam) ?>" style="display:inline;" onsubmit="return confirm('「<?= h($sa['agent_name']) ?>」のパスワードをリセットしますか？')">
                        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                        <input type="hidden" name="action" value="reset_password">
                        <input type="hidden" name="id" value="<?= (int)$sa['id'] ?>">
                        <button class="btn btn-outline btn-sm" style="color:var(--gold);">PW再発行</button>
                    </form>
                    <form method="post" action="/agent/sub_agents.php<?= h($modeParam) ?>" style="display:inline;" onsubmit="return confirm('「<?= h($sa['agent_name']) ?>」を削除しますか？配下メンバーがいる場合は、その配下も削除されます。この操作は元に戻せません。')">
                        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$sa['id'] ?>">
                        <button class="btn btn-danger btn-sm" type="submit">削除</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="<?= ($allAdvisorMode ? 11 : 10) + (($managedLevel === 1 || $allAdvisorMode) ? 1 : 0) ?>" style="text-align:center;color:var(--text-muted);padding:2.5rem;">配下の<?= h($managedLabel) ?>はまだいません。上のフォームまたは招待URLから追加してください。</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>

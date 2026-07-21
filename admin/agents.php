<?php
$pageTitle = 'メンバー管理';
require_once __DIR__ . '/header.php';

$db = getDB();
$message = '';
$msgType = 'success';
$labels = getLevelLabels();
$positionLabels = getAdvisorPositionLabels();
$editAgent = null;
$csrf = getCsrfToken();

function adminAgentLog(PDO $db, string $action, int $agentId, array $details = []): void {
    try {
        $stmt = $db->prepare("INSERT INTO admin_action_logs (admin_id, action, target_type, target_id, details, ip_hash) VALUES (?, ?, 'agent', ?, ?, ?)");
        $stmt->execute([
            (int)($_SESSION['admin_id'] ?? 0) ?: null,
            $action,
            $agentId,
            json_encode($details, JSON_UNESCAPED_UNICODE),
            hash('sha256', $_SERVER['REMOTE_ADDR'] ?? ''),
        ]);
    } catch (Throwable $e) {
        error_log('admin agent log failed: ' . $e->getMessage());
    }
}

function adminBuildSetupUrl(string $token): string {
    $scheme = !empty($_SERVER['HTTPS']) ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? '') . '/agent/setup.php?token=' . $token;
}

function adminDescendantIds(PDO $db, int $agentId): array {
    $ids = [];
    $queue = [$agentId];
    while ($queue) {
        $parentId = array_shift($queue);
        $stmt = $db->prepare('SELECT id FROM agents WHERE parent_id=?');
        $stmt->execute([$parentId]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $childId) {
            $childId = (int)$childId;
            if (!in_array($childId, $ids, true)) {
                $ids[] = $childId;
                $queue[] = $childId;
            }
        }
    }
    return $ids;
}

function adminValidatePlacement(PDO $db, int $agentId, int $level, ?int $parentId, array $labels): array {
    $errors = [];
    if (!in_array($level, [1, 2, 3], true)) {
        return ['区分が不正です。'];
    }
    if ($level === 3) {
        return [];
    }
    if (!$parentId) {
        return ['上位を選択してください。'];
    }
    if ($agentId > 0 && $parentId === $agentId) {
        $errors[] = '自分自身を上位に設定できません。';
    }
    if ($agentId > 0 && in_array($parentId, adminDescendantIds($db, $agentId), true)) {
        $errors[] = '配下メンバーを上位に設定できません。';
    }
    $stmt = $db->prepare("SELECT level FROM agents WHERE id=? AND status='active'");
    $stmt->execute([$parentId]);
    $parentLevel = (int)($stmt->fetchColumn() ?: 0);
    $allowed = $level === 1 ? [2, 3] : [$level + 1];
    if (!in_array($parentLevel, $allowed, true)) {
        $allowedLabels = array_map(fn($lv) => $labels[$lv] ?? ('Lv.' . $lv), $allowed);
        $errors[] = ($labels[$level] ?? '対象') . 'の上位には' . implode('または', $allowedLabels) . 'を選択してください。';
    }
    return $errors;
}

function lpModeLabel(array $ag): string {
    $f = !empty($ag['show_form']);
    $l = !empty($ag['show_line_btn']);
    if ($f && $l) return '両方';
    if ($f) return 'フォームのみ';
    if ($l) return 'LINEのみ';
    return '非表示';
}

function lpModeColor(array $ag): string {
    $f = !empty($ag['show_form']);
    $l = !empty($ag['show_line_btn']);
    if ($f && $l) return 'color:#5ecb9b';
    if ($f) return 'color:#88aaee';
    if ($l) return 'color:#06c755';
    return 'color:rgba(245,240,232,.45)';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = '不正なリクエストです。';
        $msgType = 'error';
    } else {
        $action = $_POST['action'] ?? '';
        try {
            if ($action === 'reset_password') {
                $id = (int)($_POST['id'] ?? 0);
                $token = bin2hex(random_bytes(32));
                $exp = date('Y-m-d H:i:s', strtotime('+24 hours'));
                $db->prepare('UPDATE agents SET setup_token=?, setup_token_exp=?, password=NULL WHERE id=?')->execute([$token, $exp, $id]);
                adminAgentLog($db, 'reset_password', $id);
                $message = '初回設定URLを再発行しました: ' . adminBuildSetupUrl($token);
            } elseif ($action === 'toggle') {
                $id = (int)($_POST['id'] ?? 0);
                $db->prepare("UPDATE agents SET status=IF(status='active','inactive','active') WHERE id=?")->execute([$id]);
                adminAgentLog($db, 'toggle_status', $id);
                syncAgentToExternalPartner($id, 'status_changed');
                $message = '状態を変更しました。';
            } elseif ($action === 'delete') {
                $id = (int)($_POST['id'] ?? 0);
                $deleteAgent = getAgentById($id);
                $stmt = $db->prepare('SELECT COUNT(*) FROM agents WHERE parent_id=?');
                $stmt->execute([$id]);
                $childCount = (int)$stmt->fetchColumn();
                $stmt = $db->prepare('SELECT COUNT(*) FROM leads WHERE agent_id=?');
                $stmt->execute([$id]);
                $leadCount = (int)$stmt->fetchColumn();
                if ($childCount > 0 || $leadCount > 0) {
                    $db->prepare("UPDATE agents SET status='inactive' WHERE id=?")->execute([$id]);
                    adminAgentLog($db, 'safe_delete_to_inactive', $id, ['child_count' => $childCount, 'lead_count' => $leadCount]);
                    syncAgentToExternalPartner($id, 'deactivated');
                    $message = '配下または問い合わせ履歴があるため、削除せず停止しました。';
                } else {
                    if ($deleteAgent) syncAgentArrayToExternalPartnerSites($deleteAgent, 'deleted');
                    $db->prepare('DELETE FROM agents WHERE id=?')->execute([$id]);
                    adminAgentLog($db, 'delete', $id);
                    $message = 'メンバーを削除しました。';
                }
            } elseif ($action === 'role_update') {
                $id = (int)($_POST['id'] ?? 0);
                $level = (int)($_POST['level'] ?? 1);
                $parentId = $level === 3 ? null : ((int)($_POST['parent_id'] ?? 0) ?: null);
                $errors = adminValidatePlacement($db, $id, $level, $parentId, $labels);
                if ($errors) {
                    $message = implode(' ', $errors);
                    $msgType = 'error';
                } else {
                    $setupUrl = '';
                    if (!empty($_POST['reset_setup'])) {
                        $token = bin2hex(random_bytes(32));
                        $exp = date('Y-m-d H:i:s', strtotime('+24 hours'));
                        $db->prepare('UPDATE agents SET setup_token=?, setup_token_exp=?, password=NULL WHERE id=?')->execute([$token, $exp, $id]);
                        $setupUrl = adminBuildSetupUrl($token);
                    }
                    $rolePositionType = $level === 1 ? 'advisor' : null;
                    $rolePositionLabel = $level === 1 ? getAdvisorPositionLabel($rolePositionType) : null;
                    $db->prepare('UPDATE agents SET level=?, parent_id=?, position_type=?, position_label=? WHERE id=?')
                       ->execute([$level, $parentId, $rolePositionType, $rolePositionLabel, $id]);
                    adminAgentLog($db, 'role_update', $id, ['level' => $level, 'parent_id' => $parentId]);
                    syncAgentToExternalPartner($id, 'role_updated');
                    $message = '権限を更新しました。' . ($setupUrl ? ' 初回設定URL: ' . $setupUrl : '');
                }
            } elseif (in_array($action, ['create', 'update'], true)) {
                $id = (int)($_POST['id'] ?? 0);
                $level = (int)($_POST['level'] ?? 1);
                $parentId = $level === 3 ? null : ((int)($_POST['parent_id'] ?? 0) ?: null);
                $data = [
                    'agent_code' => sanitizeInput($_POST['agent_code'] ?? ''),
                    'agent_name' => sanitizeInput($_POST['agent_name'] ?? ''),
                    'person_name' => sanitizeInput($_POST['person_name'] ?? ''),
                    'email' => sanitizeInput($_POST['email'] ?? ''),
                    'phone' => sanitizeInput($_POST['phone'] ?? ''),
                    'line_url' => sanitizeInput($_POST['line_url'] ?? ''),
                    'profile_text' => sanitizeInput($_POST['profile_text'] ?? ''),
                    'default_template_id' => (int)($_POST['default_template_id'] ?? 0) ?: null,
                    'level' => $level,
                    'parent_id' => $parentId,
                    'show_form' => isset($_POST['show_form']) ? 1 : 0,
                    'show_line_btn' => isset($_POST['show_line_btn']) && trim($_POST['line_url'] ?? '') !== '' ? 1 : 0,
                    'notify_email' => isset($_POST['notify_email']) ? 1 : 0,
                    'notify_line' => isset($_POST['notify_line']) ? 1 : 0,
                    'line_messaging_token' => sanitizeInput($_POST['line_messaging_token'] ?? ''),
                    'line_user_id' => sanitizeInput($_POST['line_user_id'] ?? ''),
                    'notify_chatwork' => isset($_POST['notify_chatwork']) ? 1 : 0,
                    'chatwork_webhook' => sanitizeInput($_POST['chatwork_webhook'] ?? ''),
                    'notify_slack' => isset($_POST['notify_slack']) ? 1 : 0,
                    'slack_webhook' => sanitizeInput($_POST['slack_webhook'] ?? ''),
                    'profile_image' => sanitizeInput($_POST['current_profile_image'] ?? ''),
                    'position_type' => $level === 1 ? normalizeAdvisorPosition((string)($_POST['position_type'] ?? ($_POST['current_position_type'] ?? 'advisor'))) : null,
                    'position_label' => $level === 1 ? getAdvisorPositionLabel(normalizeAdvisorPosition((string)($_POST['position_type'] ?? ($_POST['current_position_type'] ?? 'advisor')))) : null,
                ];
                if (!empty($_FILES['profile_image']['tmp_name'])) {
                    $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg','jpeg','png','webp','gif'], true) && $_FILES['profile_image']['size'] < 2 * 1024 * 1024) {
                        $fname = uniqid('prof_', true) . '.' . $ext;
                        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], __DIR__ . '/../uploads/profile/' . $fname)) {
                            $data['profile_image'] = '/uploads/profile/' . $fname;
                        }
                    }
                }
                $errors = [];
                if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $data['agent_code'])) $errors[] = 'コードは英数字、ハイフン、アンダースコアのみ使えます。';
                if ($data['agent_name'] === '') $errors[] = '名称は必須です。';
                if ($data['person_name'] === '') $errors[] = '担当者名は必須です。';
                if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'メールアドレスが不正です。';
                $emailSql = 'SELECT id FROM agents WHERE email=?';
                $emailParams = [$data['email']];
                if ($action === 'update') {
                    $emailSql .= ' AND id<>?';
                    $emailParams[] = $id;
                }
                $emailStmt = $db->prepare($emailSql);
                $emailStmt->execute($emailParams);
                if ($emailStmt->fetch()) $errors[] = 'このメールアドレスはすでに登録されています。';
                if ($action === 'update') {
                    $errors = array_merge($errors, adminValidatePlacement($db, $id, $level, $parentId, $labels));
                } else {
                    $errors = array_merge($errors, adminValidatePlacement($db, 0, $level, $parentId, $labels));
                }
                if ($errors) {
                    $message = implode(' ', $errors);
                    $msgType = 'error';
                } elseif ($action === 'create') {
                    $sql = 'INSERT INTO agents (agent_code, agent_name, person_name, email, phone, line_url, profile_image, profile_text, default_template_id, level, parent_id, position_type, position_label, show_form, show_line_btn, notify_email, notify_line, line_messaging_token, line_user_id, notify_chatwork, chatwork_webhook, notify_slack, slack_webhook) VALUES (:agent_code, :agent_name, :person_name, :email, :phone, :line_url, :profile_image, :profile_text, :default_template_id, :level, :parent_id, :position_type, :position_label, :show_form, :show_line_btn, :notify_email, :notify_line, :line_messaging_token, :line_user_id, :notify_chatwork, :chatwork_webhook, :notify_slack, :slack_webhook)';
                    $db->prepare($sql)->execute($data);
                    syncAgentToExternalPartner((int)$db->lastInsertId(), 'admin_created');
                    $message = 'メンバーを登録しました。';
                } else {
                    $data['id'] = $id;
                    $sql = 'UPDATE agents SET agent_code=:agent_code, agent_name=:agent_name, person_name=:person_name, email=:email, phone=:phone, line_url=:line_url, profile_image=:profile_image, profile_text=:profile_text, default_template_id=:default_template_id, level=:level, parent_id=:parent_id, position_type=:position_type, position_label=:position_label, show_form=:show_form, show_line_btn=:show_line_btn, notify_email=:notify_email, notify_line=:notify_line, line_messaging_token=:line_messaging_token, line_user_id=:line_user_id, notify_chatwork=:notify_chatwork, chatwork_webhook=:chatwork_webhook, notify_slack=:notify_slack, slack_webhook=:slack_webhook WHERE id=:id';
                    $db->prepare($sql)->execute($data);
                    syncAgentToExternalPartner($id, 'admin_updated');
                    $message = 'メンバー情報を更新しました。';
                }
            }
        } catch (Throwable $e) {
            $message = 'エラーが発生しました: ' . $e->getMessage();
            $msgType = 'error';
            error_log($e->getMessage());
        }
    }
}

if (isset($_GET['edit'])) {
    $editAgent = getAgentById((int)$_GET['edit']);
}

$search = sanitizeInput($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$where = $search ? 'WHERE a.agent_name LIKE ? OR a.person_name LIKE ? OR a.agent_code LIKE ?' : '';
$params = $search ? ["%$search%", "%$search%", "%$search%"] : [];
$total = $db->prepare("SELECT COUNT(*) FROM agents a $where");
$total->execute($params);
$pag = paginate((int)$total->fetchColumn(), $perPage, $page);
$stmt = $db->prepare("SELECT a.*, t.name AS template_name, p.agent_name AS parent_name FROM agents a LEFT JOIN lp_templates t ON a.default_template_id=t.id LEFT JOIN agents p ON a.parent_id=p.id $where ORDER BY a.created_at DESC LIMIT $perPage OFFSET {$pag['offset']}");
$stmt->execute($params);
$agents = $stmt->fetchAll();
$templates = getActiveTemplates();
$agentsList = $db->query("SELECT id, agent_name, person_name, level FROM agents WHERE level IN (2,3) AND status='active' ORDER BY level DESC, agent_name")->fetchAll();
?>

<?php if ($message): ?>
<div class="alert alert-<?= h($msgType) ?>"><?= h($message) ?></div>
<?php endif; ?>

<div class="card">
    <p class="card-title"><?= $editAgent ? 'メンバー情報を編集' : '新規メンバー登録' ?></p>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="<?= $editAgent ? 'update' : 'create' ?>">
        <?php if ($editAgent): ?>
        <input type="hidden" name="id" value="<?= (int)$editAgent['id'] ?>">
        <input type="hidden" name="current_profile_image" value="<?= h($editAgent['profile_image'] ?? '') ?>">
        <input type="hidden" name="current_position_type" value="<?= h($editAgent['position_type'] ?? 'advisor') ?>">
        <?php endif; ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;">
            <div class="form-group"><label>コード（URL用）*</label><input type="text" name="agent_code" value="<?= h($editAgent['agent_code'] ?? '') ?>" placeholder="agent001" required></div>
            <div class="form-group"><label>名称 *</label><input type="text" name="agent_name" value="<?= h($editAgent['agent_name'] ?? '') ?>" required></div>
            <div class="form-group"><label>担当者名 *</label><input type="text" name="person_name" value="<?= h($editAgent['person_name'] ?? '') ?>" required></div>
            <div class="form-group"><label>メールアドレス *</label><input type="email" name="email" value="<?= h($editAgent['email'] ?? '') ?>" required></div>
            <div class="form-group"><label>電話番号</label><input type="tel" name="phone" value="<?= h($editAgent['phone'] ?? '') ?>"></div>
            <div class="form-group"><label>LINE URL</label><input type="url" name="line_url" id="lineUrlInput" value="<?= h($editAgent['line_url'] ?? '') ?>" placeholder="https://lin.ee/..."></div>
            <div class="form-group">
                <label>区分</label>
                <select name="level">
                    <option value="1" <?= ((int)($editAgent['level'] ?? 1) === 1) ? 'selected' : '' ?>><?= h($labels[1] ?? 'アドバイザー') ?></option>
                    <option value="2" <?= ((int)($editAgent['level'] ?? 1) === 2) ? 'selected' : '' ?>><?= h($labels[2] ?? 'ディレクター') ?></option>
                    <option value="3" <?= ((int)($editAgent['level'] ?? 1) === 3) ? 'selected' : '' ?>><?= h($labels[3] ?? 'エージェント') ?></option>
                </select>
            </div>
            <?php $selectedPosition = normalizeAdvisorPosition((string)($editAgent['position_type'] ?? 'advisor')); ?>
            <div class="form-group">
                <label>アドバイザー種別</label>
                <select name="position_type">
                    <?php foreach ($positionLabels as $key => $name): ?>
                    <option value="<?= h($key) ?>" <?= $selectedPosition === $key ? 'selected' : '' ?>><?= h($name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>上位</label>
                <select name="parent_id">
                    <option value="">本部直属</option>
                    <?php foreach ($agentsList as $parent): ?>
                    <option value="<?= (int)$parent['id'] ?>" <?= ((int)($editAgent['parent_id'] ?? 0) === (int)$parent['id']) ? 'selected' : '' ?>>[<?= h($labels[(int)$parent['level']] ?? 'Lv.'.$parent['level']) ?>] <?= h($parent['agent_name']) ?>（<?= h($parent['person_name']) ?>）</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>使用テンプレート</label>
                <select name="default_template_id">
                    <option value="">未設定</option>
                    <?php foreach ($templates as $tpl): ?>
                    <option value="<?= (int)$tpl['id'] ?>" <?= ((int)($editAgent['default_template_id'] ?? 0) === (int)$tpl['id']) ? 'selected' : '' ?>><?= h($tpl['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>プロフィール画像（2MB以内）</label><input type="file" name="profile_image" accept="image/*"></div>
        </div>
        <div class="form-group"><label>プロフィール文</label><textarea name="profile_text"><?= h($editAgent['profile_text'] ?? '') ?></textarea></div>
        <p style="font-size:.8rem;color:var(--gold);letter-spacing:.1em;margin-bottom:.75rem;font-weight:700;">LP問い合わせ導線</p>
        <div style="background:rgba(201,168,76,.06);border:1px solid rgba(201,168,76,.2);border-radius:4px;padding:1rem 1.25rem;margin-bottom:1.25rem;">
            <label class="form-check"><input type="checkbox" name="show_form" id="chkShowForm" <?= ($editAgent['show_form'] ?? 1) ? 'checked' : '' ?>> 問い合わせフォーム</label>
            <label class="form-check"><input type="checkbox" name="show_line_btn" id="chkShowLine" <?= ($editAgent['show_line_btn'] ?? 1) ? 'checked' : '' ?>> LINEボタン</label>
            <div id="lpModePreview" style="margin-top:.85rem;font-size:.82rem;color:var(--gold);"></div>
        </div>
        <p style="font-size:.8rem;color:var(--gold);letter-spacing:.1em;margin-bottom:.75rem;font-weight:700;">通知設定</p>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1rem;">
            <label class="form-check"><input type="checkbox" name="notify_email" <?= ($editAgent['notify_email'] ?? 1) ? 'checked' : '' ?>> メール通知</label>
            <div><label class="form-check"><input type="checkbox" name="notify_line" id="chkLine" <?= ($editAgent['notify_line'] ?? 0) ? 'checked' : '' ?>> LINE通知</label><div id="lineFields" style="<?= ($editAgent['notify_line'] ?? 0) ? '' : 'display:none' ?>;margin-top:.5rem;"><div class="form-group"><label>チャネルアクセストークン</label><input type="text" name="line_messaging_token" value="<?= h($editAgent['line_messaging_token'] ?? '') ?>"></div><div class="form-group"><label>送信先User ID</label><input type="text" name="line_user_id" value="<?= h($editAgent['line_user_id'] ?? '') ?>"></div></div></div>
            <div><label class="form-check"><input type="checkbox" name="notify_chatwork" id="chkCw" <?= ($editAgent['notify_chatwork'] ?? 0) ? 'checked' : '' ?>> Chatwork通知</label><div id="cwFields" style="<?= ($editAgent['notify_chatwork'] ?? 0) ? '' : 'display:none' ?>;margin-top:.5rem;"><div class="form-group"><label>Chatwork Webhook URL</label><input type="url" name="chatwork_webhook" value="<?= h($editAgent['chatwork_webhook'] ?? '') ?>"></div></div></div>
            <div><label class="form-check"><input type="checkbox" name="notify_slack" id="chkSlack" <?= ($editAgent['notify_slack'] ?? 0) ? 'checked' : '' ?>> Slack通知</label><div id="slackFields" style="<?= ($editAgent['notify_slack'] ?? 0) ? '' : 'display:none' ?>;margin-top:.5rem;"><div class="form-group"><label>Slack Webhook URL</label><input type="url" name="slack_webhook" value="<?= h($editAgent['slack_webhook'] ?? '') ?>"></div></div></div>
        </div>
        <div style="display:flex;gap:.75rem;margin-top:1rem;"><button type="submit" class="btn btn-gold"><?= $editAgent ? '更新する' : '登録する' ?></button><?php if ($editAgent): ?><a href="/admin/agents.php" class="btn btn-outline">キャンセル</a><?php endif; ?></div>
    </form>
</div>

<div style="display:flex;gap:.5rem;align-items:center;margin-bottom:1rem;">
    <form method="get" style="display:flex;gap:.5rem;flex:1;"><input type="text" name="q" value="<?= h($search) ?>" placeholder="名称・担当者名・コードで検索" style="flex:1;padding:.5rem .8rem;background:rgba(255,255,255,.06);border:1px solid var(--border);border-radius:4px;color:var(--paper);font-family:inherit;"><button type="submit" class="btn btn-outline">検索</button><?php if ($search): ?><a href="/admin/agents.php" class="btn btn-outline">クリア</a><?php endif; ?></form>
</div>

<div class="card" style="padding:0;">
    <div class="table-scroll">
    <table class="admin-agents-table">
        <thead><tr><th>コード</th><th>区分</th><th>アドバイザー種別</th><th>名称</th><th>担当者</th><th>上位</th><th>テンプレート</th><th>LP導線</th><th>LP URL</th><th>状態</th><th>操作</th></tr></thead>
        <tbody>
        <?php if ($agents): foreach ($agents as $ag): ?>
            <tr>
                <td style="font-family:monospace;font-size:.82rem;color:var(--gold)"><?= h($ag['agent_code']) ?></td>
                <td><?= h($labels[(int)($ag['level'] ?? 1)] ?? 'Lv.'.($ag['level'] ?? 1)) ?></td>
                <td><?= ((int)($ag['level'] ?? 1) === 1) ? h(getAdvisorPositionLabel($ag['position_type'] ?? null, $ag['position_label'] ?? null)) : '-' ?></td>
                <td><?= h($ag['agent_name']) ?></td>
                <td><?= h($ag['person_name']) ?></td>
                <td><?= h($ag['parent_name'] ?? '-') ?></td>
                <td><?= h($ag['template_name'] ?? '-') ?></td>
                <td style="font-weight:700;<?= lpModeColor($ag) ?>"><?= h(lpModeLabel($ag)) ?></td>
                <td><a href="/a/<?= h($ag['agent_code']) ?>" target="_blank" style="color:var(--gold);text-decoration:none;">/a/<?= h($ag['agent_code']) ?></a></td>
                <td><span class="badge badge-<?= $ag['status'] === 'active' ? 'active' : 'inactive' ?>"><?= $ag['status'] === 'active' ? '公開中' : '停止中' ?></span></td>
                <td class="agent-actions" style="white-space:nowrap;">
                    <form method="post" style="display:inline-flex;gap:.25rem;align-items:center;margin-bottom:.25rem;"><input type="hidden" name="csrf_token" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="role_update"><input type="hidden" name="id" value="<?= (int)$ag['id'] ?>"><select name="level" style="width:auto;padding:.25rem .45rem;font-size:.75rem;"><option value="1" <?= ((int)($ag['level'] ?? 1) === 1) ? 'selected' : '' ?>><?= h($labels[1] ?? 'アドバイザー') ?></option><option value="2" <?= ((int)($ag['level'] ?? 1) === 2) ? 'selected' : '' ?>><?= h($labels[2] ?? 'ディレクター') ?></option><option value="3" <?= ((int)($ag['level'] ?? 1) === 3) ? 'selected' : '' ?>><?= h($labels[3] ?? 'エージェント') ?></option></select><select name="parent_id" style="width:auto;max-width:180px;padding:.25rem .45rem;font-size:.75rem;"><option value="">本部直属</option><?php foreach ($agentsList as $parent): if ((int)$parent['id'] === (int)$ag['id']) continue; ?><option value="<?= (int)$parent['id'] ?>" <?= ((int)($ag['parent_id'] ?? 0) === (int)$parent['id']) ? 'selected' : '' ?>>[<?= h($labels[(int)$parent['level']] ?? 'Lv.'.$parent['level']) ?>] <?= h($parent['agent_name']) ?></option><?php endforeach; ?></select><label style="font-size:.72rem;"><input type="checkbox" name="reset_setup" value="1"> 初回設定URL</label><button type="submit" class="btn btn-gold btn-sm">権限保存</button></form><br>
                    <a href="/admin/agents.php?edit=<?= (int)$ag['id'] ?>" class="btn btn-outline btn-sm">編集</a>
                    <form method="post" style="display:inline;" onsubmit="return confirm('初回設定URLを再発行します。よろしいですか？')"><input type="hidden" name="csrf_token" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="reset_password"><input type="hidden" name="id" value="<?= (int)$ag['id'] ?>"><button type="submit" class="btn btn-outline btn-sm" style="color:var(--gold);">PW再発行</button></form>
                    <form method="post" style="display:inline;"><input type="hidden" name="csrf_token" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$ag['id'] ?>"><button type="submit" class="btn btn-outline btn-sm"><?= $ag['status'] === 'active' ? '停止' : '再開' ?></button></form>
                    <form method="post" style="display:inline;" onsubmit="return confirm('このメンバーを削除または停止します。よろしいですか？')"><input type="hidden" name="csrf_token" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$ag['id'] ?>"><button type="submit" class="btn btn-danger btn-sm">削除</button></form>
                </td>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="11" style="text-align:center;color:var(--text-muted);padding:3rem;">メンバーがまだ登録されていません。</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<?php if ($pag['total_pages'] > 1): ?>
<div class="pagination"><?php for ($i = 1; $i <= $pag['total_pages']; $i++): ?><?= $i === $page ? '<span class="current">'.$i.'</span>' : '<a href="?page='.$i.'&q='.urlencode($search).'">'.$i.'</a>' ?><?php endfor; ?></div>
<?php endif; ?>

<script>
function toggleFields(chkId, fieldsId) {
    const chk = document.getElementById(chkId);
    const fields = document.getElementById(fieldsId);
    if (!chk || !fields) return;
    chk.addEventListener('change', function() { fields.style.display = this.checked ? '' : 'none'; });
}
toggleFields('chkLine', 'lineFields');
toggleFields('chkCw', 'cwFields');
toggleFields('chkSlack', 'slackFields');
function updateLpPreview() {
    const f = document.getElementById('chkShowForm')?.checked;
    const l = document.getElementById('chkShowLine')?.checked;
    const url = document.getElementById('lineUrlInput')?.value.trim();
    const el = document.getElementById('lpModePreview');
    if (!el) return;
    const parts = [];
    if (f) parts.push('問い合わせフォーム');
    if (l) parts.push('LINEボタン' + (url ? '' : '（LINE URL未入力なら保存時にOFF）'));
    el.textContent = 'LP表示: ' + (parts.length ? parts.join(' + ') : '非表示');
}
['chkShowForm','chkShowLine','lineUrlInput'].forEach(id => document.getElementById(id)?.addEventListener('input', updateLpPreview));
['chkShowForm','chkShowLine'].forEach(id => document.getElementById(id)?.addEventListener('change', updateLpPreview));
updateLpPreview();
</script>
<?php require_once __DIR__ . '/footer.php'; ?>

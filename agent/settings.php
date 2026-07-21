<?php
$pageTitle = '設定';
require_once __DIR__ . '/header.php';

$db      = getDB();
$message = '';
$msgType = 'success';

if (!function_exists('agentSettingsTableHasColumn')) {
    function agentSettingsTableHasColumn(string $table, string $column): bool {
        try {
            $db = getDB();
            $stmt = $db->query("SHOW COLUMNS FROM `$table`");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
                if (($col['Field'] ?? '') === $column) {
                    return true;
                }
            }
        } catch (Throwable $e) {
        }
        return false;
    }
}

if (!function_exists('getProjects')) {
    function getProjects(bool $activeOnly = false): array {
        try {
            $db = getDB();
            $where = $activeOnly ? "WHERE status='active'" : '';
            return $db->query("SELECT * FROM projects $where ORDER BY sort_order ASC, id ASC")->fetchAll();
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('getActiveTemplatesByProject')) {
    function getActiveTemplatesByProject(): array {
        $grouped = [];
        try {
            foreach (getActiveTemplates() as $template) {
                $projectId = (int)($template['project_id'] ?? 0);
                $grouped[$projectId][] = $template;
            }
        } catch (Throwable $e) {
        }
        return $grouped;
    }
}

if (!function_exists('getAgentProjectTemplateMap')) {
    function getAgentProjectTemplateMap(int $agentId): array {
        if ($agentId <= 0) return [];
        try {
            $db = getDB();
            $db->query("SELECT 1 FROM agent_project_templates LIMIT 1");
            $stmt = $db->prepare("SELECT project_id, template_id FROM agent_project_templates WHERE agent_id=?");
            $stmt->execute([$agentId]);
            $map = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $map[(int)$row['project_id']] = (int)$row['template_id'];
            }
            return $map;
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('getSiteBaseUrl')) {
    function getSiteBaseUrl(): string {
        $baseUrl = '';
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT value FROM system_settings WHERE key_name='site_url' LIMIT 1");
            $stmt->execute();
            $baseUrl = trim((string)$stmt->fetchColumn());
        } catch (Throwable $e) {
            $baseUrl = '';
        }
        if ($baseUrl === '') {
            $baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
        } else {
            $parts = parse_url($baseUrl);
            if (!empty($parts['host'])) {
                $scheme = $parts['scheme'] ?? (isset($_SERVER['HTTPS']) ? 'https' : 'http');
                $port = !empty($parts['port']) ? ':' . $parts['port'] : '';
                $baseUrl = $scheme . '://' . $parts['host'] . $port;
            }
        }
        return rtrim($baseUrl, '/');
    }
}

if (!function_exists('buildAgentProjectLpUrl')) {
    function buildAgentProjectLpUrl(string $agentCode, ?array $project = null): string {
        $url = getSiteBaseUrl() . '/a/' . rawurlencode($agentCode);
        if (!empty($project['slug'])) {
            $url .= '?project=' . rawurlencode((string)$project['slug']);
        }
        return $url;
    }
}

if (!function_exists('getAgentProjectLpUrls')) {
    function getAgentProjectLpUrls(array $agent): array {
        $projects = getProjects(true);
        $urls = [];
        foreach ($projects as $project) {
            $urls[] = [
                'project' => $project,
                'url' => buildAgentProjectLpUrl((string)($agent['agent_code'] ?? ''), $project),
            ];
        }
        if (!$urls && !empty($agent['agent_code'])) {
            $urls[] = [
                'project' => ['name' => 'Default', 'slug' => ''],
                'url' => buildAgentProjectLpUrl((string)$agent['agent_code'], null),
            ];
        }
        return $urls;
    }
}

// 設定保存
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── プロフィール・通知設定 ──
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = '不正なリクエストです。'; $msgType = 'error';
    } elseif ($action === 'profile') {
        $templateId = (int)($_POST['default_template_id'] ?? 0);
        $templateId = $templateId > 0 ? $templateId : null;
        if ($templateId) {
            $tplCheck = $db->prepare("SELECT id FROM lp_templates WHERE id=? AND status='active'");
            $tplCheck->execute([$templateId]);
            if (!$tplCheck->fetchColumn()) {
                $templateId = null;
            }
        }

        $f = [
            'person_name'         => trim($_POST['person_name']  ?? ''),
            'phone'               => trim($_POST['phone']        ?? ''),
            'line_url'            => trim($_POST['line_url']      ?? ''),
            'profile_text'        => trim($_POST['profile_text'] ?? ''),
            'default_template_id'  => $templateId,
            'notify_email'        => isset($_POST['notify_email'])    ? 1 : 0,
            'show_form'           => isset($_POST['show_form'])     ? 1 : 0,
            // 通知設定
            'notify_email'        => isset($_POST['notify_email'])    ? 1 : 0,
            'notify_chatwork'     => isset($_POST['notify_chatwork']) ? 1 : 0,
            'chatwork_webhook'    => trim($_POST['chatwork_webhook']  ?? ''),
            'notify_slack'        => isset($_POST['notify_slack'])    ? 1 : 0,
            'slack_webhook'       => trim($_POST['slack_webhook']     ?? ''),
            'notify_line'         => isset($_POST['notify_line'])     ? 1 : 0,
            'line_messaging_token'=> trim($_POST['line_messaging_token'] ?? ''),
            'line_user_id'        => trim($_POST['line_user_id']      ?? ''),
            // LP導線
            'show_form'           => isset($_POST['show_form'])     ? 1 : 0,
            'show_line_btn'       => isset($_POST['show_line_btn']) ? 1 : 0,
            'id'                  => $currentAgent['id'],
        ];

        if (empty($f['person_name'])) {
            $message = '担当者名は必須です。'; $msgType = 'error';
        } else {
            $profileImage = $currentAgent['profile_image'] ?? '';
            // プロフィール画像
            $profileImage = $currentAgent['profile_image'] ?? '';
            if (!empty($_FILES['profile_image']['tmp_name'])) {
                $ext     = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg','jpeg','png','webp','gif'];
                if (in_array($ext, $allowed) && $_FILES['profile_image']['size'] < 2 * 1024 * 1024) {
                    $fname = uniqid('prof_', true) . '.' . $ext;
                    if (move_uploaded_file($_FILES['profile_image']['tmp_name'],
                            __DIR__ . '/../uploads/profile/' . $fname)) {
                        $profileImage = '/uploads/profile/' . $fname;
                    }
                }
            }
            $f['profile_image'] = $profileImage;

            $db->prepare("
                UPDATE agents SET
                  person_name=:person_name, phone=:phone, line_url=:line_url,
                  profile_text=:profile_text, profile_image=:profile_image,
                  default_template_id=:default_template_id,
                  show_form=:show_form, show_line_btn=:show_line_btn,
                  notify_email=:notify_email,
                  notify_chatwork=:notify_chatwork, chatwork_webhook=:chatwork_webhook,
                  notify_slack=:notify_slack, slack_webhook=:slack_webhook,
                  notify_line=:notify_line, line_messaging_token=:line_messaging_token,
                  line_user_id=:line_user_id
                WHERE id=:id
            ")->execute($f);

            // 最新情報を再取得
            $stmt = $db->prepare("SELECT * FROM agents WHERE id=?");
            $stmt = $db->prepare("SELECT * FROM agents WHERE id=?");
            $stmt->execute([$currentAgent['id']]);
            $currentAgent = $stmt->fetch();
            $message = '設定を保存しました。';
        }
    }

    // ── パスワード変更 ──
    if ($action === 'project_templates') {
        try {
            $db->query("SELECT 1 FROM agent_project_templates LIMIT 1");
            $projectsForSave = getProjects(true);
            $templatesByProjectForSave = getActiveTemplatesByProject();
            $db->prepare("DELETE FROM agent_project_templates WHERE agent_id=?")->execute([(int)$currentAgent['id']]);
            $insertProjectTemplate = $db->prepare("INSERT INTO agent_project_templates (agent_id, project_id, template_id) VALUES (?, ?, ?)");
            foreach ($projectsForSave as $projectForSave) {
                $projectId = (int)$projectForSave['id'];
                $templateId = (int)($_POST['project_template'][$projectId] ?? 0);
                if ($templateId <= 0 || empty($templatesByProjectForSave[$projectId])) {
                    continue;
                }
                foreach ($templatesByProjectForSave[$projectId] as $tplForSave) {
                    if ((int)$tplForSave['id'] === $templateId) {
                        $insertProjectTemplate->execute([(int)$currentAgent['id'], $projectId, $templateId]);
                        break;
                    }
                }
            }
            $message = 'Project LP settings saved.';
        } catch (Throwable $e) {
            $message = 'Project LP settings table is not ready. Please apply DB migration.';
            $msgType = 'error';
        }
    }

    if ($action === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new1    = $_POST['new_password']     ?? '';
        $new2    = $_POST['new_password2']    ?? '';

        if (!password_verify($current, $currentAgent['password'])) {
            $message = '現在のパスワードが正しくありません。'; $msgType = 'error';
        } elseif (strlen($new1) < 8) {
            $message = '新しいパスワードは8文字以上にしてください。'; $msgType = 'error';
        } elseif ($new1 !== $new2) {
            $message = '新しいパスワードが一致しません。'; $msgType = 'error';
        } else {
            $db->prepare("UPDATE agents SET password=? WHERE id=?")
               ->execute([password_hash($new1, PASSWORD_BCRYPT), $currentAgent['id']]);
            $message = 'パスワードを変更しました。';
        }
    }
}

$ag = $currentAgent; // 短縮エイリアス
$templates = getActiveTemplates();
$projects = getProjects(true);
$templatesByProject = getActiveTemplatesByProject();
$agentProjectTemplateMap = getAgentProjectTemplateMap((int)$ag['id']);
$projectLpUrls = getAgentProjectLpUrls($ag);
?>

<?php if ($message): ?><div class="alert alert-<?= $msgType ?>"><?= h($message) ?></div><?php endif; ?>

<!-- プロフィール・LP・通知設定 -->
<form method="post" enctype="multipart/form-data">
  <input type="hidden" name="csrf_token" value="<?= h(getCsrfToken()) ?>">
  <input type="hidden" name="action" value="profile">

  <div class="card">
    <p class="card-title">プロフィール</p>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;">
      <div class="form-group">
        <label>担当者名 *</label>
        <input type="text" name="person_name" value="<?= h($ag['person_name']) ?>" required>
      </div>
      <div class="form-group">
        <label>電話番号</label>
        <input type="tel" name="phone" value="<?= h($ag['phone'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>LINE URL <span style="font-size:.72rem;color:var(--text-muted);">（LINEボタン用）</span></label>
        <input type="url" name="line_url" value="<?= h($ag['line_url'] ?? '') ?>" placeholder="https://lin.ee/...">
      </div>
      <div class="form-group">
        <label>プロフィール画像（2MB以内）</label>
        <input type="file" name="profile_image" accept="image/*">
        <?php if (!empty($ag['profile_image'])): ?>
        <img src="<?= h($ag['profile_image']) ?>" style="width:60px;height:60px;border-radius:50%;margin-top:.5rem;object-fit:cover;">
        <?php endif; ?>
      </div>
    </div>
    <div class="form-group">
      <label>プロフィール文</label>
      <textarea name="profile_text"><?= h($ag['profile_text'] ?? '') ?></textarea>
    </div>
  </div>

  <!-- LPテンプレート -->
  <div class="card">
    <p class="card-title">告知LP</p>
    <p style="font-size:.8rem;color:var(--text-muted);margin-bottom:.85rem;">告知に使うLPテンプレートを選択してください。</p>
    <?php if ($templates): ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;">
      <?php foreach ($templates as $tpl): ?>
      <?php
        $checked = (int)($ag['default_template_id'] ?? 0) === (int)$tpl['id'];
        $thumb = $tpl['thumbnail_url'] ?? '';
      ?>
      <label style="display:block;border:1px solid <?= $checked ? 'rgba(201,168,76,.75)' : 'var(--border)' ?>;background:<?= $checked ? 'rgba(201,168,76,.1)' : 'rgba(255,255,255,.03)' ?>;border-radius:4px;padding:.85rem;cursor:pointer;">
        <div style="display:flex;gap:.75rem;align-items:center;">
          <input type="radio" name="default_template_id" value="<?= (int)$tpl['id'] ?>" <?= $checked ? 'checked' : '' ?>>
          <?php if ($thumb): ?>
          <img src="<?= h($thumb) ?>" alt="" style="width:64px;height:44px;object-fit:cover;border-radius:3px;border:1px solid var(--border);">
          <?php else: ?>
          <div style="width:64px;height:44px;border-radius:3px;border:1px solid var(--border);display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.04);">🎨</div>
          <?php endif; ?>
          <div style="min-width:0;">
            <div style="font-weight:700;color:var(--cream);"><?= h($tpl['name']) ?></div>
            <div style="font-size:.74rem;color:var(--gold);"><?= h($tpl['slug']) ?></div>
            <?php if (!empty($tpl['project_name'])): ?>
            <div style="font-size:.7rem;color:var(--text-muted);margin-top:.15rem;"><?= h($tpl['project_name']) ?></div>
            <?php endif; ?>
          </div>
        </div>
        <div style="margin-top:.65rem;display:flex;gap:.5rem;align-items:center;">
          <a href="/lp.php?preview=1&template_id=<?= (int)$tpl['id'] ?>" target="_blank" onclick="event.stopPropagation();" class="btn btn-outline" style="font-size:.75rem;padding:.35rem .75rem;">プレビュー</a>
          <?php if ($checked): ?><span style="font-size:.72rem;color:var(--gold-lt);">選択中</span><?php endif; ?>
        </div>
      </label>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p style="font-size:.82rem;color:var(--text-muted);">利用できるLPテンプレートがありません。</p>
    <?php endif; ?>
  </div>

  <!-- LP導線 -->
  <div class="card">
    <p class="card-title">LP 問い合わせ導線</p>
    <p style="font-size:.8rem;color:var(--text-muted);margin-bottom:.85rem;">LPに表示する問い合わせ方法を選択してください。</p>
    <div style="display:flex;gap:2rem;flex-wrap:wrap;">
      <label class="form-check">
        <input type="checkbox" name="show_form" <?= $ag['show_form'] ? 'checked' : '' ?>>
        <span style="font-weight:700;">📋 問い合わせフォーム</span>
      </label>
      <label class="form-check">
        <input type="checkbox" name="show_line_btn" <?= $ag['show_line_btn'] ? 'checked' : '' ?>>
        <span style="font-weight:700;color:#06c755;">💬 LINEボタン</span>
        <span style="font-size:.75rem;color:var(--text-muted);margin-left:.5rem;">（LINE URL必須）</span>
      </label>
    </div>
  </div>

  <!-- 通知設定 -->
  <div class="card">
    <p class="card-title">通知設定（問い合わせ受信時）</p>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1rem;">

      <!-- メール通知 -->
      <div>
        <label class="form-check">
          <input type="checkbox" name="notify_email" <?= $ag['notify_email'] ? 'checked' : '' ?>>
          メール通知（推奨）
        </label>
        <p style="font-size:.75rem;color:var(--text-muted);margin:.35rem 0 0 1.6rem;">送信先：<?= h($ag['email']) ?></p>
      </div>

      <!-- Chatwork -->
      <div>
        <label class="form-check">
          <input type="checkbox" name="notify_chatwork" id="chkCw" <?= $ag['notify_chatwork'] ? 'checked' : '' ?>>
          Chatwork通知
        </label>
        <div id="cwFields" style="<?= $ag['notify_chatwork'] ? '' : 'display:none' ?>;margin-top:.5rem;">
          <div class="form-group">
            <label>Chatwork Webhook URL</label>
            <input type="url" name="chatwork_webhook" value="<?= h($ag['chatwork_webhook'] ?? '') ?>">
          </div>
        </div>
      </div>

      <!-- Slack -->
      <div>
        <label class="form-check">
          <input type="checkbox" name="notify_slack" id="chkSlack" <?= $ag['notify_slack'] ? 'checked' : '' ?>>
          Slack通知
        </label>
        <div id="slackFields" style="<?= $ag['notify_slack'] ? '' : 'display:none' ?>;margin-top:.5rem;">
          <div class="form-group">
            <label>Slack Webhook URL</label>
            <input type="url" name="slack_webhook" value="<?= h($ag['slack_webhook'] ?? '') ?>">
          </div>
        </div>
      </div>

      <!-- LINE Messaging API -->
      <div>
        <label class="form-check">
          <input type="checkbox" name="notify_line" id="chkLine" <?= $ag['notify_line'] ? 'checked' : '' ?>>
          LINE通知（Messaging API）
        </label>
        <div id="lineFields" style="<?= $ag['notify_line'] ? '' : 'display:none' ?>;margin-top:.5rem;">
          <div class="form-group">
            <label>チャンネルアクセストークン</label>
            <input type="text" name="line_messaging_token" value="<?= h($ag['line_messaging_token'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>送信先 User ID</label>
            <input type="text" name="line_user_id" value="<?= h($ag['line_user_id'] ?? '') ?>">
          </div>
        </div>
      </div>
    </div>
  </div>

  <button type="submit" class="btn btn-gold">保存する</button>
</form>

<!-- パスワード変更 -->
<div class="card" style="margin-top:1.5rem;">
  <p class="card-title">Project LP URLs</p>
  <p style="font-size:.8rem;color:var(--text-muted);margin-bottom:.85rem;">Each project has its own share URL.</p>
  <?php if ($projectLpUrls): ?>
    <div style="display:grid;gap:.75rem;">
      <?php foreach ($projectLpUrls as $row): ?>
      <?php $project = $row['project']; $url = $row['url']; ?>
      <div style="display:grid;grid-template-columns:minmax(120px,220px) 1fr auto auto;gap:.6rem;align-items:center;border:1px solid var(--border);border-radius:4px;padding:.75rem;background:rgba(255,255,255,.03);">
        <strong style="color:var(--cream);"><?= h($project['name']) ?></strong>
        <code style="color:var(--gold-lt);word-break:break-all;font-size:.82rem;"><?= h($url) ?></code>
        <a href="<?= h($url) ?>" target="_blank" class="btn btn-outline btn-sm">Open</a>
        <button type="button" class="btn btn-outline btn-sm" onclick="navigator.clipboard.writeText(<?= h(json_encode($url, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>).then(()=>{this.textContent='Copied';setTimeout(()=>this.textContent='Copy',1600)})">Copy</button>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<div class="card" style="margin-top:1.5rem;">
  <p class="card-title">Project LP Template</p>
  <p style="font-size:.8rem;color:var(--text-muted);margin-bottom:.85rem;">Select which LP template is used for each project URL.</p>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= h(getCsrfToken()) ?>">
    <input type="hidden" name="action" value="project_templates">
    <div style="display:grid;gap:1rem;">
      <?php foreach ($projects as $project): ?>
      <?php
        $projectId = (int)$project['id'];
        $projectTemplates = $templatesByProject[$projectId] ?? [];
        $selectedTemplateId = (int)($agentProjectTemplateMap[$projectId] ?? 0);
        if ($selectedTemplateId <= 0 && $projectTemplates) {
            $selectedTemplateId = (int)$projectTemplates[0]['id'];
        }
      ?>
      <div style="border:1px solid var(--border);border-radius:4px;padding:.9rem;background:rgba(255,255,255,.03);">
        <div style="display:flex;justify-content:space-between;gap:1rem;align-items:center;margin-bottom:.65rem;">
          <strong style="color:var(--gold-lt);"><?= h($project['name']) ?></strong>
          <a href="<?= h(buildAgentProjectLpUrl((string)$ag['agent_code'], $project)) ?>" target="_blank" class="btn btn-outline btn-sm">Open URL</a>
        </div>
        <?php if ($projectTemplates): ?>
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:.7rem;">
            <?php foreach ($projectTemplates as $tpl): ?>
            <?php $checked = $selectedTemplateId === (int)$tpl['id']; ?>
            <label style="display:flex;gap:.55rem;align-items:center;border:1px solid <?= $checked ? 'rgba(201,168,76,.75)' : 'var(--border)' ?>;border-radius:4px;padding:.65rem;background:<?= $checked ? 'rgba(201,168,76,.1)' : 'rgba(255,255,255,.03)' ?>;cursor:pointer;">
              <input type="radio" name="project_template[<?= $projectId ?>]" value="<?= (int)$tpl['id'] ?>" <?= $checked ? 'checked' : '' ?>>
              <span>
                <span style="display:block;font-weight:700;color:var(--cream);"><?= h($tpl['name']) ?></span>
                <span style="display:block;font-size:.72rem;color:var(--gold);"><?= h($tpl['slug']) ?></span>
              </span>
            </label>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p style="font-size:.82rem;color:var(--text-muted);">No active templates.</p>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <button type="submit" class="btn btn-gold" style="margin-top:1rem;">Save project LP settings</button>
  </form>
</div>

<div class="card" style="margin-top:1.5rem;">
  <p class="card-title">パスワード変更</p>
  <form method="post" style="max-width:400px;">
    <input type="hidden" name="csrf_token" value="<?= h(getCsrfToken()) ?>">
    <input type="hidden" name="action" value="password">
    <div class="form-group">
      <label>現在のパスワード</label>
      <input type="password" name="current_password" required>
    </div>
    <div class="form-group">
      <label>新しいパスワード（8文字以上）</label>
      <input type="password" name="new_password" required minlength="8">
    </div>
    <div class="form-group">
      <label>新しいパスワード（確認）</label>
      <input type="password" name="new_password2" required minlength="8">
    </div>
    <button type="submit" class="btn btn-outline">パスワードを変更</button>
  </form>
</div>

<script>
function toggle(chkId, fieldsId) {
  document.getElementById(chkId).addEventListener('change', function() {
    document.getElementById(fieldsId).style.display = this.checked ? '' : 'none';
  });
}
toggle('chkCw',    'cwFields');
toggle('chkSlack', 'slackFields');
toggle('chkLine',  'lineFields');
</script>

<?php require_once __DIR__ . '/footer.php'; ?>

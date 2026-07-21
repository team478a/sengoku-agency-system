<?php
$pageTitle = 'プロジェクト管理';
require_once __DIR__ . '/header.php';

$db = getDB();
$csrf = getCsrfToken();
$msg = '';
$msgType = 'success';

function projectUsageCounts(PDO $db, int $projectId): array {
    return [
        'templates' => tableHasColumn('lp_templates', 'project_id') ? (int)$db->query("SELECT COUNT(*) FROM lp_templates WHERE project_id={$projectId}")->fetchColumn() : 0,
        'materials' => tableHasColumn('materials', 'project_id') ? (int)$db->query("SELECT COUNT(*) FROM materials WHERE project_id={$projectId}")->fetchColumn() : 0,
        'leads' => tableHasColumn('leads', 'project_id') ? (int)$db->query("SELECT COUNT(*) FROM leads WHERE project_id={$projectId}")->fetchColumn() : 0,
        'logs' => tableHasColumn('access_logs', 'project_id') ? (int)$db->query("SELECT COUNT(*) FROM access_logs WHERE project_id={$projectId}")->fetchColumn() : 0,
    ];
}

try {
    $db->query("SELECT 1 FROM projects LIMIT 1");
    $projectsReady = true;
} catch (Throwable $e) {
    $projectsReady = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $projectsReady) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $msg = '不正なリクエストです。';
        $msgType = 'error';
    } else {
        $action = $_POST['action'] ?? '';
        if (in_array($action, ['create', 'update'], true)) {
            $slug = preg_replace('/[^a-z0-9_\-]/', '', strtolower(trim((string)($_POST['slug'] ?? ''))));
            $name = trim((string)($_POST['name'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $sortOrder = (int)($_POST['sort_order'] ?? 0);
            $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

            if ($slug === '' || $name === '') {
                $msg = 'スラッグとプロジェクト名は必須です。';
                $msgType = 'error';
            } else {
                try {
                    if ($action === 'create') {
                        $db->prepare("INSERT INTO projects (slug, name, description, status, sort_order) VALUES (?, ?, ?, ?, ?)")
                           ->execute([$slug, $name, $description, $status, $sortOrder]);
                        $msg = 'プロジェクトを追加しました。';
                    } else {
                        $id = (int)($_POST['id'] ?? 0);
                        $db->prepare("UPDATE projects SET slug=?, name=?, description=?, status=?, sort_order=? WHERE id=?")
                           ->execute([$slug, $name, $description, $status, $sortOrder, $id]);
                        $msg = 'プロジェクトを更新しました。';
                    }
                } catch (PDOException $e) {
                    $msg = '保存に失敗しました。スラッグが重複している可能性があります。';
                    $msgType = 'error';
                }
            }
        } elseif ($action === 'toggle') {
            $id = (int)($_POST['id'] ?? 0);
            $db->prepare("UPDATE projects SET status=IF(status='active','inactive','active') WHERE id=?")
               ->execute([$id]);
            $msg = '状態を変更しました。';
        }
        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $counts = projectUsageCounts($db, $id);
            if ($id <= 0) {
                $msg = 'Delete target is invalid.';
                $msgType = 'error';
            } elseif (array_sum($counts) > 0) {
                $msg = 'This project cannot be deleted because LP templates, materials, leads, or access logs still exist.';
                $msgType = 'error';
            } else {
                $db->prepare("DELETE FROM projects WHERE id=?")->execute([$id]);
                $msg = 'Project deleted.';
            }
        }
    }
}

$editProject = null;
if ($projectsReady && isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM projects WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $editProject = $stmt->fetch() ?: null;
}

$projects = $projectsReady ? getProjects(false) : [];
?>

<?php if ($msg): ?><div class="alert alert-<?= h($msgType) ?>"><?= h($msg) ?></div><?php endif; ?>

<?php if (!$projectsReady): ?>
  <div class="alert alert-error">
    プロジェクト管理のDBマイグレーションが未適用です。管理者画面の「アップデート」からDBマイグレーションを適用してください。
  </div>
<?php else: ?>
  <div class="card">
    <p class="card-title"><?= $editProject ? 'プロジェクトを編集' : '新規プロジェクト追加' ?></p>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
      <input type="hidden" name="action" value="<?= $editProject ? 'update' : 'create' ?>">
      <?php if ($editProject): ?><input type="hidden" name="id" value="<?= (int)$editProject['id'] ?>"><?php endif; ?>

      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;">
        <div class="form-group">
          <label>スラッグ *</label>
          <input type="text" name="slug" value="<?= h($editProject['slug'] ?? '') ?>" placeholder="new-project" required>
        </div>
        <div class="form-group">
          <label>プロジェクト名 *</label>
          <input type="text" name="name" value="<?= h($editProject['name'] ?? '') ?>" placeholder="新プロジェクト名" required>
        </div>
        <div class="form-group">
          <label>状態</label>
          <select name="status">
            <?php $status = $editProject['status'] ?? 'active'; ?>
            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>公開中</option>
            <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>停止中</option>
          </select>
        </div>
        <div class="form-group">
          <label>表示順</label>
          <input type="number" name="sort_order" value="<?= h($editProject['sort_order'] ?? 0) ?>">
        </div>
      </div>

      <div class="form-group">
        <label>説明</label>
        <textarea name="description" placeholder="プロジェクトの用途や告知内容"><?= h($editProject['description'] ?? '') ?></textarea>
      </div>

      <button class="btn btn-gold"><?= $editProject ? '更新する' : '追加する' ?></button>
      <?php if ($editProject): ?><a href="/admin/projects.php" class="btn btn-outline">キャンセル</a><?php endif; ?>
    </form>
  </div>

  <div class="card table-scroll" style="padding:0;">
    <table>
      <thead>
        <tr><th>順</th><th>プロジェクト</th><th>スラッグ</th><th>状態</th><th>LP数</th><th>素材数</th><th>問い合わせ</th><th>操作</th></tr>
      </thead>
      <tbody>
      <?php foreach ($projects as $project): ?>
        <?php
          $pid = (int)$project['id'];
          $counts = projectUsageCounts($db, $pid);
          $tplCount = $counts['templates'];
          $matCount = $counts['materials'];
          $leadCount = $counts['leads'];
          $canDelete = array_sum($counts) === 0;
        ?>
        <tr>
          <td style="color:var(--text-muted);"><?= h($project['sort_order']) ?></td>
          <td>
            <strong><?= h($project['name']) ?></strong>
            <?php if (!empty($project['description'])): ?>
              <p style="font-size:.72rem;color:var(--text-muted);margin-top:.2rem;"><?= h(mb_strimwidth($project['description'], 0, 70, '…')) ?></p>
            <?php endif; ?>
          </td>
          <td style="font-family:monospace;color:var(--gold);"><?= h($project['slug']) ?></td>
          <td><span class="badge badge-<?= $project['status'] === 'active' ? 'active' : 'inactive' ?>"><?= $project['status'] === 'active' ? '公開中' : '停止中' ?></span></td>
          <td><?= number_format($tplCount) ?></td>
          <td><?= number_format($matCount) ?></td>
          <td><?= number_format($leadCount) ?></td>
          <td style="white-space:nowrap;">
            <a href="/admin/projects.php?edit=<?= $pid ?>" class="btn btn-outline btn-sm">編集</a>
            <form method="post" style="display:inline;">
              <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= $pid ?>">
              <button class="btn btn-outline btn-sm"><?= $project['status'] === 'active' ? '停止' : '公開' ?></button>
            </form>
            <?php if ($canDelete): ?>
              <form method="post" style="display:inline;" onsubmit="return confirm('Delete this project?');">
                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $pid ?>">
                <button class="btn btn-danger btn-sm">Delete</button>
              </form>
            <?php else: ?>
              <span class="btn btn-outline btn-sm" style="opacity:.45;cursor:not-allowed;" title="Cannot delete while related data exists">Delete</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>

<?php
$pageTitle = '紹介素材管理';
require_once __DIR__ . '/header.php';

$db      = getDB();
$csrf    = getCsrfToken();
$msg     = '';
$msgType = 'success';

function adminMaterialColumns(PDO $db): array {
    static $columns = null;
    if ($columns !== null) return $columns;
    $columns = [];
    try {
        $rows = $db->query("SHOW COLUMNS FROM materials")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $columns[$row['Field']] = true;
        }
    } catch (Throwable $e) {
        error_log('Material column check failed: ' . $e->getMessage());
    }
    return $columns;
}

$materialColumns = adminMaterialColumns($db);
$materialHasProject = !empty($materialColumns['project_id']);
$projects = getProjects(true);
$defaultProjectId = getDefaultProjectId();

// ── カテゴリ追加 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_category') {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $name = trim($_POST['cat_name'] ?? '');
        if ($name) {
            $db->prepare("INSERT INTO material_categories (name, sort_order) VALUES (?, ?)")
               ->execute([$name, (int)($_POST['cat_order'] ?? 0)]);
            $msg = 'カテゴリを追加しました。';
        }
    }
}

// ── カテゴリ削除 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'del_category') {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $db->prepare("DELETE FROM material_categories WHERE id=?")->execute([(int)$_POST['cat_id']]);
        $msg = 'カテゴリを削除しました。';
    }
}

// ── 素材削除 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_material') {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $mid  = (int)$_POST['material_id'];
        $mrow = $db->prepare("SELECT file_path FROM materials WHERE id=?");
        $mrow->execute([$mid]);
        $mrow = $mrow->fetch();
        if ($mrow && $mrow['file_path']) {
            $fpath = __DIR__ . '/..' . $mrow['file_path'];
            if (file_exists($fpath)) @unlink($fpath);
        }
        $db->prepare("DELETE FROM materials WHERE id=?")->execute([$mid]);
        $msg = '素材を削除しました。';
    }
}

// ── 公開/非公開切替 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_material') {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $db->prepare("UPDATE materials SET status=IF(status='active','inactive','active') WHERE id=?")
           ->execute([(int)$_POST['material_id']]);
        $msg = 'ステータスを変更しました。';
    }
}

// ── 素材登録 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_material') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $msg = '不正なリクエストです。'; $msgType = 'error';
    } else {
        $type       = $_POST['type']        ?? 'text';
        $title      = trim($_POST['title']  ?? '');
        $desc       = trim($_POST['description'] ?? '');
        $catId      = (int)($_POST['category_id'] ?? 0) ?: null;
        $projectId  = (int)($_POST['project_id'] ?? 0) ?: $defaultProjectId;
        $accessType = $_POST['access_type'] ?? 'all';
        $order      = (int)($_POST['sort_order'] ?? 0);
        $agentIds   = $_POST['agent_ids']   ?? [];
        $instagramText = trim((string)($_POST['instagram_text'] ?? ''));
        $xText         = trim((string)($_POST['x_text'] ?? ''));
        $lineText      = trim((string)($_POST['line_text'] ?? ''));

        if (!$title) { $msg = 'タイトルは必須です。'; $msgType = 'error'; }
        else {
            $contentText = null;
            $filePath    = null;
            $fileName    = null;
            $fileSize    = null;

            if ($type === 'text') {
                $contentText = $_POST['content_text'] ?? '';
            } else {
                // ファイルアップロード
                if (!empty($_FILES['material_file']['tmp_name'])) {
                    $origName = $_FILES['material_file']['name'];
                    $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                    $size     = $_FILES['material_file']['size'];

                    $allowedImg   = ['jpg','jpeg','png','webp','gif'];
                    $allowedVideo = ['mp4','mov','webm','avi'];
                    $allowedFile  = ['pdf','zip','pptx','docx','xlsx'];
                    $allowed = array_merge($allowedImg, $allowedVideo, $allowedFile);

                    // MIMEタイプ検証（③）
                    $finfo    = new finfo(FILEINFO_MIME_TYPE);
                    $mimeType = $finfo->file($_FILES['material_file']['tmp_name']);
                    $allowedMimes = [
                        'jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png',
                        'webp'=>'image/webp','gif'=>'image/gif','svg'=>'image/svg+xml',
                        'mp4'=>'video/mp4','mov'=>'video/quicktime','webm'=>'video/webm',
                        'avi'=>'video/x-msvideo','pdf'=>'application/pdf',
                        'zip'=>'application/zip','pptx'=>'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                        'docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ];
                    $expectedMime = $allowedMimes[$ext] ?? '';

                    if (!in_array($ext, $allowed)) {
                        $msg = '対応していないファイル形式です。'; $msgType = 'error';
                    } elseif ($expectedMime && $mimeType !== $expectedMime && !str_starts_with($mimeType, 'video/')) {
                        $msg = 'ファイルの内容が拡張子と一致しません。正しいファイルをアップロードしてください。'; $msgType = 'error';
                    } elseif ($size > 100 * 1024 * 1024) {
                        $msg = 'ファイルは100MB以内にしてください。'; $msgType = 'error';
                    } else {
                        $subDir   = in_array($ext, $allowedImg) ? 'images' : (in_array($ext, $allowedVideo) ? 'videos' : 'files');
                        $dir      = __DIR__ . '/../uploads/materials/' . $subDir;
                        if (!is_dir($dir)) mkdir($dir, 0755, true);
                        $fname    = uniqid('mat_', true) . '.' . $ext;
                        if (move_uploaded_file($_FILES['material_file']['tmp_name'], $dir . '/' . $fname)) {
                            $filePath = '/uploads/materials/' . $subDir . '/' . $fname;
                            $fileName = $origName;
                            $fileSize = $size;
                            // typeを自動判定
                            if (in_array($ext, $allowedImg))   $type = 'image';
                            elseif (in_array($ext, $allowedVideo)) $type = 'video';
                            else $type = 'file';
                        } else {
                            $msg = 'ファイルの保存に失敗しました。'; $msgType = 'error';
                        }
                    }
                } else {
                    $msg = 'ファイルを選択してください。'; $msgType = 'error';
                }
            }

            if (!$msg) {
                $insertColumns = ['category_id','title','description','type','content_text','file_path','file_name','file_size','access_type','sort_order'];
                $insertValues  = [$catId,$title,$desc,$type,$contentText,$filePath,$fileName,$fileSize,$accessType,$order];
                if ($materialHasProject) {
                    array_unshift($insertColumns, 'project_id');
                    array_unshift($insertValues, $projectId);
                }
                if (!empty($materialColumns['instagram_text'])) {
                    $insertColumns[] = 'instagram_text';
                    $insertValues[] = $instagramText !== '' ? $instagramText : null;
                }
                if (!empty($materialColumns['x_text'])) {
                    $insertColumns[] = 'x_text';
                    $insertValues[] = $xText !== '' ? $xText : null;
                }
                if (!empty($materialColumns['line_text'])) {
                    $insertColumns[] = 'line_text';
                    $insertValues[] = $lineText !== '' ? $lineText : null;
                }
                $placeholders = implode(',', array_fill(0, count($insertColumns), '?'));
                $db->prepare("INSERT INTO materials (" . implode(',', $insertColumns) . ") VALUES ($placeholders)")
                   ->execute($insertValues);
                $mid = $db->lastInsertId();

                // 個別指定の場合はアクセステーブルに挿入
                if ($accessType === 'specific' && $agentIds) {
                    $ins = $db->prepare("INSERT IGNORE INTO material_agent_access (material_id,agent_id) VALUES (?,?)");
                    foreach ($agentIds as $aid) $ins->execute([$mid, (int)$aid]);
                }
                $msg = '素材を登録しました。';
            }
        }
    }
}

// データ取得
$categories = $db->query("SELECT * FROM material_categories ORDER BY sort_order, id")->fetchAll();
$agents     = $db->query("SELECT id, agent_name, person_name FROM agents WHERE status='active' ORDER BY agent_name")->fetchAll();

$filterCat  = $_GET['cat'] ?? 'all';
$filterProject = $_GET['project_id'] ?? 'all';
$whereParts = ['1'];
if ($filterCat !== 'all') $whereParts[] = "m.category_id=" . (int)$filterCat;
if ($materialHasProject && $filterProject !== 'all') $whereParts[] = "m.project_id=" . (int)$filterProject;
$where = "WHERE " . implode(' AND ', $whereParts);
$materials  = $db->query("
    SELECT m.*, c.name AS cat_name" . ($materialHasProject ? ", p.name AS project_name" : "") . "
    FROM materials m
    LEFT JOIN material_categories c ON m.category_id = c.id
    " . ($materialHasProject ? "LEFT JOIN projects p ON m.project_id = p.id" : "") . "
    $where
    ORDER BY " . ($materialHasProject ? "COALESCE(p.sort_order,9999) ASC," : "") . " m.sort_order ASC, m.created_at DESC
")->fetchAll();
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?>"><?= h($msg) ?></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 280px;gap:1.25rem;align-items:start;">

<!-- 左：素材登録フォーム -->
<div>
<div class="card">
  <p class="card-title">素材を追加</p>
  <form method="post" enctype="multipart/form-data" id="materialForm">
    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
    <input type="hidden" name="action" value="create_material">

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
      <div class="form-group">
        <label>タイトル *</label>
        <input type="text" name="title" required placeholder="SNS投稿テキスト①">
      </div>
      <div class="form-group">
        <label>カテゴリ</label>
        <select name="category_id">
          <option value="">— 未分類 —</option>
          <?php foreach ($categories as $c): ?>
          <option value="<?= $c['id'] ?>"><?= h($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if ($materialHasProject): ?>
      <div class="form-group">
        <label>プロジェクト</label>
        <select name="project_id">
          <?php foreach ($projects as $project): ?>
          <option value="<?= (int)$project['id'] ?>" <?= (int)$defaultProjectId === (int)$project['id'] ? 'selected' : '' ?>><?= h($project['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <div class="form-group">
        <label>素材タイプ</label>
        <select name="type" id="typeSelect" onchange="updateTypeUI()">
          <option value="text">テキスト</option>
          <option value="image">画像</option>
          <option value="video">動画</option>
          <option value="file">ファイル（PDF等）</option>
        </select>
      </div>
      <div class="form-group">
        <label>表示順</label>
        <input type="number" name="sort_order" value="0">
      </div>
    </div>

    <div class="form-group">
      <label>説明・使い方メモ</label>
      <textarea name="description" rows="2" placeholder="このテキストはInstagramに使用してください。ハッシュタグ付きで投稿推奨。"></textarea>
    </div>

    <div class="form-group" style="background:rgba(201,168,76,.06);border:1px solid rgba(201,168,76,.2);border-radius:4px;padding:1rem;">
      <label>SNS別 投稿文</label>
      <p style="font-size:.75rem;color:var(--text-muted);line-height:1.7;margin-bottom:.75rem;">
        未入力の場合は「テキスト内容」または「説明・使い方メモ」から自動生成します。各欄で <code>{lp_url}</code> を使うと代理店ごとの専用URLに置き換わります。
      </p>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;">
        <div>
          <label>Instagram用</label>
          <textarea name="instagram_text" rows="5" placeholder="Instagram投稿用の本文。ハッシュタグ多めでもOK。&#10;&#10;詳しくはこちら&#10;{lp_url}"></textarea>
        </div>
        <div>
          <label>X用</label>
          <textarea name="x_text" rows="5" placeholder="X投稿用の短め本文。&#10;{lp_url}"></textarea>
        </div>
        <div>
          <label>LINE用</label>
          <textarea name="line_text" rows="5" placeholder="LINE・DMで送る紹介文。親しい人に送る自然な文章がおすすめ。&#10;{lp_url}"></textarea>
        </div>
      </div>
    </div>

    <!-- テキスト入力エリア -->
    <div id="textArea" class="form-group">
      <label>テキスト内容</label>
      <textarea name="content_text" rows="6" placeholder="コピペ用のテキストを入力…&#10;&#10;例：詳しくはこちら&#10;{lp_url}"></textarea>
      <p style="font-size:.75rem;color:var(--text-muted);line-height:1.7;margin-top:.45rem;">
        差し込みタグ：<code>{lp_url}</code> 専用LP URL / <code>{agent_name}</code> 名称 / <code>{person_name}</code> 担当者名 / <code>{agent_code}</code> コード。<br>
        <code>{lp_url}</code> を入れない場合も、代理店側ではコピー時に専用LP URLが末尾へ自動追加されます。
      </p>
    </div>

    <!-- ファイルアップロード -->
    <div id="fileArea" class="form-group" style="display:none;">
      <label>ファイル（画像/動画/PDF等、100MB以内）</label>
      <input type="file" name="material_file" id="materialFile"
             accept="image/*,video/*,.pdf,.zip,.pptx,.docx,.xlsx">
      <div id="filePreview" style="margin-top:.75rem;display:none;">
        <img id="imgPreview" style="max-width:200px;max-height:120px;border-radius:3px;border:1px solid var(--border);display:none;">
        <p id="fileNamePreview" style="font-size:.8rem;color:var(--gold-lt);margin-top:.4rem;"></p>
      </div>
    </div>

    <!-- 公開範囲 -->
    <div class="form-group">
      <label>公開範囲</label>
      <div style="display:flex;gap:1.5rem;flex-wrap:wrap;margin-bottom:.75rem;">
        <label class="form-check">
          <input type="radio" name="access_type" value="all" checked onchange="toggleAgentSelect()">
          全アドバイザーに公開
        </label>
        <label class="form-check">
          <input type="radio" name="access_type" value="specific" onchange="toggleAgentSelect()">
          特定のアドバイザーのみ
        </label>
      </div>
      <div id="agentSelect" style="display:none;">
        <div style="max-height:160px;overflow-y:auto;border:1px solid var(--border);border-radius:3px;padding:.75rem;background:rgba(255,255,255,.03);">
          <?php foreach ($agents as $ag): ?>
          <label class="form-check" style="margin-bottom:.4rem;">
            <input type="checkbox" name="agent_ids[]" value="<?= $ag['id'] ?>">
            <?= h($ag['person_name']) ?>（<?= h($ag['agent_name']) ?>）
          </label>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <button type="submit" class="btn btn-gold">追加する</button>
  </form>
</div>

<!-- 素材一覧 -->
<div style="display:flex;gap:.5rem;margin-bottom:.75rem;flex-wrap:wrap;align-items:center;">
  <?php if ($materialHasProject): ?>
  <a href="?project_id=all&cat=<?= h($filterCat) ?>" style="padding:.35rem .85rem;border-radius:3px;font-size:.78rem;text-decoration:none;
     background:<?= $filterProject==='all'?'rgba(201,168,76,.18)':'rgba(255,255,255,.04)' ?>;
     border:1px solid <?= $filterProject==='all'?'rgba(201,168,76,.5)':'rgba(255,255,255,.08)' ?>;
     color:<?= $filterProject==='all'?'var(--gold-lt)':'rgba(245,240,232,.6)' ?>;">全プロジェクト</a>
  <?php foreach ($projects as $project): ?>
  <a href="?project_id=<?= (int)$project['id'] ?>&cat=<?= h($filterCat) ?>" style="padding:.35rem .85rem;border-radius:3px;font-size:.78rem;text-decoration:none;
     background:<?= (string)$filterProject===(string)$project['id']?'rgba(201,168,76,.18)':'rgba(255,255,255,.04)' ?>;
     border:1px solid <?= (string)$filterProject===(string)$project['id']?'rgba(201,168,76,.5)':'rgba(255,255,255,.08)' ?>;
     color:<?= (string)$filterProject===(string)$project['id']?'var(--gold-lt)':'rgba(245,240,232,.6)' ?>;"><?= h($project['name']) ?></a>
  <?php endforeach; ?>
  <span style="flex-basis:100%;height:0;"></span>
  <?php endif; ?>
  <a href="?cat=all" style="padding:.35rem .85rem;border-radius:3px;font-size:.78rem;text-decoration:none;
     background:<?= $filterCat==='all'?'rgba(201,168,76,.18)':'rgba(255,255,255,.04)' ?>;
     border:1px solid <?= $filterCat==='all'?'rgba(201,168,76,.5)':'rgba(255,255,255,.08)' ?>;
     color:<?= $filterCat==='all'?'var(--gold-lt)':'rgba(245,240,232,.6)' ?>;">すべて</a>
  <?php foreach ($categories as $c): ?>
  <a href="?cat=<?= $c['id'] ?>&project_id=<?= h($filterProject) ?>" style="padding:.35rem .85rem;border-radius:3px;font-size:.78rem;text-decoration:none;
     background:<?= $filterCat==$c['id']?'rgba(201,168,76,.18)':'rgba(255,255,255,.04)' ?>;
     border:1px solid <?= $filterCat==$c['id']?'rgba(201,168,76,.5)':'rgba(255,255,255,.08)' ?>;
     color:<?= $filterCat==$c['id']?'var(--gold-lt)':'rgba(245,240,232,.6)' ?>;"><?= h($c['name']) ?></a>
  <?php endforeach; ?>
</div>

<div class="card" style="padding:0;overflow:hidden;">
  <table>
    <thead><tr><th>順</th><th>種別</th><th>タイトル</th><?php if ($materialHasProject): ?><th>プロジェクト</th><?php endif; ?><th>カテゴリ</th><th>公開範囲</th><th>状態</th><th>操作</th></tr></thead>
    <tbody>
    <?php if ($materials): foreach ($materials as $m):
      $typeIcon = ['text'=>'📝','image'=>'🖼','video'=>'🎬','file'=>'📎'][$m['type']] ?? '📄';
    ?>
    <tr>
      <td style="color:var(--text-muted);font-size:.78rem;"><?= $m['sort_order'] ?></td>
      <td style="font-size:1.1rem;text-align:center;"><?= $typeIcon ?></td>
      <td>
        <p style="font-weight:700;font-size:.85rem;"><?= h($m['title']) ?></p>
        <?php if ($m['description']): ?>
        <p style="font-size:.72rem;color:var(--text-muted);margin-top:.15rem;"><?= h(mb_strimwidth($m['description'],0,50,'…')) ?></p>
        <?php endif; ?>
      </td>
      <?php if ($materialHasProject): ?><td style="font-size:.78rem;color:var(--gold);"><?= h($m['project_name'] ?? '未設定') ?></td><?php endif; ?>
      <td style="font-size:.78rem;color:var(--gold);"><?= h($m['cat_name'] ?? '—') ?></td>
      <td style="font-size:.78rem;">
        <?php if ($m['access_type'] === 'all'): ?>
        <span style="color:rgba(245,240,232,.5);">全アドバイザー</span>
        <?php else: ?>
        <?php
          $cnt = $db->prepare("SELECT COUNT(*) FROM material_agent_access WHERE material_id=?");
          $cnt->execute([$m['id']]); $cnt = $cnt->fetchColumn();
        ?>
        <span style="color:var(--gold);"><?= $cnt ?>アドバイザー</span>
        <?php endif; ?>
      </td>
      <td>
        <span class="badge badge-<?= $m['status']==='active'?'active':'inactive' ?>">
          <?= $m['status']==='active'?'公開中':'非公開' ?>
        </span>
      </td>
      <td style="white-space:nowrap;">
        <form method="post" style="display:inline;">
          <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
          <input type="hidden" name="action" value="toggle_material">
          <input type="hidden" name="material_id" value="<?= $m['id'] ?>">
          <button class="btn btn-outline btn-sm"><?= $m['status']==='active'?'非公開':'公開' ?></button>
        </form>
        <form method="post" style="display:inline;" onsubmit="return confirm('削除しますか？')">
          <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
          <input type="hidden" name="action" value="delete_material">
          <input type="hidden" name="material_id" value="<?= $m['id'] ?>">
          <button class="btn btn-danger btn-sm">削除</button>
        </form>
      </td>
    </tr>
    <?php endforeach; else: ?>
    <tr><td colspan="<?= $materialHasProject ? 8 : 7 ?>" style="text-align:center;color:var(--text-muted);padding:2.5rem;">素材がまだありません。</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>
</div><!-- /左 -->

<!-- 右：カテゴリ管理 -->
<div>
<div class="card">
  <p class="card-title">カテゴリ管理</p>
  <form method="post" style="display:flex;gap:.5rem;margin-bottom:1rem;">
    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
    <input type="hidden" name="action" value="add_category">
    <input type="text" name="cat_name" placeholder="カテゴリ名" required style="flex:1;padding:.55rem .75rem;font-size:.82rem;">
    <input type="number" name="cat_order" placeholder="順" value="0" style="width:52px;padding:.55rem .5rem;font-size:.82rem;text-align:center;">
    <button class="btn btn-outline" style="white-space:nowrap;">追加</button>
  </form>
  <ul style="list-style:none;">
    <?php foreach ($categories as $c): ?>
    <li style="display:flex;justify-content:space-between;align-items:center;padding:.5rem 0;border-bottom:1px solid var(--border);font-size:.82rem;">
      <span><?= h($c['name']) ?> <span style="color:var(--text-muted);font-size:.72rem;">順<?= $c['sort_order'] ?></span></span>
      <form method="post" onsubmit="return confirm('削除しますか？')">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="del_category">
        <input type="hidden" name="cat_id" value="<?= $c['id'] ?>">
        <button class="btn btn-danger btn-sm">削除</button>
      </form>
    </li>
    <?php endforeach; ?>
  </ul>
</div>
</div><!-- /右 -->

</div><!-- /grid -->

<script>
function updateTypeUI() {
    const type = document.getElementById('typeSelect').value;
    document.getElementById('textArea').style.display = type === 'text' ? '' : 'none';
    document.getElementById('fileArea').style.display = type !== 'text' ? '' : 'none';
}

function toggleAgentSelect() {
    const specific = document.querySelector('input[name="access_type"][value="specific"]').checked;
    document.getElementById('agentSelect').style.display = specific ? '' : 'none';
}

document.getElementById('materialFile').addEventListener('change', function() {
    const preview = document.getElementById('filePreview');
    const img     = document.getElementById('imgPreview');
    const name    = document.getElementById('fileNamePreview');
    if (!this.files.length) { preview.style.display = 'none'; return; }
    const f = this.files[0];
    name.textContent = f.name + '（' + (f.size/1024).toFixed(0) + ' KB）';
    if (f.type.startsWith('image/')) {
        img.src = URL.createObjectURL(f);
        img.style.display = '';
    } else {
        img.style.display = 'none';
    }
    preview.style.display = '';
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>

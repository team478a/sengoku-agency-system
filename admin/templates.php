<?php
$pageTitle = 'テンプレート管理';
require_once __DIR__ . '/header.php';

$db      = getDB();
$csrf    = getCsrfToken();
$msg     = '';
$msgType = 'success';
$templateColumns = tableColumns('lp_templates');
$templateHasProject = !empty($templateColumns['project_id']);
$projects = getProjects(true);
$defaultProjectId = getDefaultProjectId();

// ─────────────────────────────────────────
// HTMLファイル → PHP変換ヘルパー
// bodyタグ直前に $agent 変数インジェクション用のPHPコードを埋め込み
// さらに </body> 直前に問い合わせフォームと通知JS を差し込む
// ─────────────────────────────────────────
function convertHtmlToTemplatePHP(string $html, string $slug): string {

    // 1) CSSFontのpreconnect等、外部依存はそのまま維持

    // 2) <?php ヘッダー + $agent/$csrfToken の受け取り宣言
    $phpHeader = <<<'PHP'
<?php
/**
 * LPテンプレート（自動変換済み）
 * $agent, $csrfToken はlp.phpからインジェクト済み
 */
$showForm   = !empty($agent['show_form']);
$showLinBtn = !empty($agent['show_line_btn']) && !empty($agent['line_url']);
$csrfToken  = $csrfToken ?? getCsrfToken();
?>
PHP;

    // 3) <title> を動的に
    $html = preg_replace(
        '/<title>[^<]*<\/title>/i',
        '<title><?= h($agent[\'person_name\']) ?> | <?= h($agent[\'agent_name\']) ?></title>',
        $html,
        1
    );

    // 4) </body> 直前に問い合わせセクション + フォームJS を挿入
    $contactBlock = <<<'HTML'

<!-- ===== アドバイザー問い合わせ導線（自動挿入） ===== -->
<section id="contact" style="background:#13100D;padding:4rem 1.5rem;border-top:1px solid rgba(201,168,76,.15);">
  <div style="max-width:640px;margin:0 auto;">
    <p style="font-size:.7rem;letter-spacing:.35em;color:#C9A84C;text-transform:uppercase;margin-bottom:.75rem;">Contact</p>
    <h2 style="font-family:'Noto Serif JP',serif;font-size:clamp(1.4rem,3.5vw,2rem);font-weight:900;margin-bottom:1rem;">無料相談・お問い合わせ</h2>
    <p style="font-size:.9rem;color:rgba(232,224,204,.7);line-height:2;margin-bottom:2rem;">
      <?= h($agent['person_name']) ?>（<?= h($agent['agent_name']) ?>）が担当いたします。<br>
      内容確認後、担当者より順次ご連絡いたします。
    </p>

    <?php if ($showLinBtn): ?>
    <div style="margin-bottom:<?= $showForm ? '2rem' : '0' ?>;">
      <a href="/line_click.php?agent_id=<?= $agent['id'] ?>" target="_blank" rel="noopener"
         style="display:inline-flex;align-items:center;gap:.6rem;padding:1rem 2.5rem;background:#06c755;color:#fff;font-weight:700;font-size:1rem;border-radius:3px;text-decoration:none;width:<?= $showForm ? 'auto' : '100%' ?>;justify-content:center;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.03 2 11c0 3.12 1.67 5.88 4.23 7.55L5 22l4.18-2.09C10.04 20.29 11 20.5 12 20.5c5.52 0 10-4.03 10-9S17.52 2 12 2z"/></svg>
        LINEで相談する（無料）
      </a>
      <?php if ($showForm): ?>
      <p style="margin-top:.85rem;font-size:.8rem;color:rgba(232,224,204,.35);text-align:center;">— または下のフォームから —</p>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($showForm): ?>
    <form id="__contactForm" novalidate style="margin-top:0;">
      <input type="hidden" name="agent_id" value="<?= $agent['id'] ?>">
      <input type="hidden" name="template_id" value="<?= (int)($agent['default_template_id'] ?? 0) ?>">
      <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
      <div style="margin-bottom:1.1rem;">
        <label style="display:block;font-size:.75rem;letter-spacing:.1em;color:#C9A84C;margin-bottom:.4rem;">お名前<span style="color:#e05555;margin-left:.3rem;">*</span></label>
        <input type="text" name="name" required placeholder="山田 太郎"
          style="width:100%;padding:.8rem 1rem;background:rgba(255,255,255,.05);border:1px solid rgba(201,168,76,.22);border-radius:3px;color:#E8E0CC;font-family:inherit;font-size:.9rem;">
      </div>
      <div style="margin-bottom:1.1rem;">
        <label style="display:block;font-size:.75rem;letter-spacing:.1em;color:#C9A84C;margin-bottom:.4rem;">メールアドレス<span style="color:#e05555;margin-left:.3rem;">*</span></label>
        <input type="email" name="email" required placeholder="example@mail.com"
          style="width:100%;padding:.8rem 1rem;background:rgba(255,255,255,.05);border:1px solid rgba(201,168,76,.22);border-radius:3px;color:#E8E0CC;font-family:inherit;font-size:.9rem;">
      </div>
      <div style="margin-bottom:1.1rem;">
        <label style="display:block;font-size:.75rem;letter-spacing:.1em;color:#C9A84C;margin-bottom:.4rem;">電話番号</label>
        <input type="tel" name="phone" placeholder="090-0000-0000"
          style="width:100%;padding:.8rem 1rem;background:rgba(255,255,255,.05);border:1px solid rgba(201,168,76,.22);border-radius:3px;color:#E8E0CC;font-family:inherit;font-size:.9rem;">
      </div>
      <div style="margin-bottom:1.1rem;">
        <label style="display:block;font-size:.75rem;letter-spacing:.1em;color:#C9A84C;margin-bottom:.4rem;">お問い合わせ内容<span style="color:#e05555;margin-left:.3rem;">*</span></label>
        <textarea name="message" required placeholder="ご質問・ご相談内容をご記入ください。"
          style="width:100%;padding:.8rem 1rem;background:rgba(255,255,255,.05);border:1px solid rgba(201,168,76,.22);border-radius:3px;color:#E8E0CC;font-family:inherit;font-size:.9rem;min-height:120px;resize:vertical;"></textarea>
      </div>
      <div id="__formMsg" style="display:none;padding:.8rem 1rem;border-radius:3px;font-size:.85rem;margin-bottom:.75rem;"></div>
      <button type="submit" id="__submitBtn"
        style="width:100%;padding:1rem;background:linear-gradient(135deg,#C9A84C,#E2C87A);color:#13100D;font-family:'Noto Serif JP',serif;font-weight:700;font-size:1rem;border:none;border-radius:3px;cursor:pointer;">
        送信する
      </button>
    </form>
    <script>
    document.getElementById('__contactForm').addEventListener('submit', async function(e) {
      e.preventDefault();
      const btn = document.getElementById('__submitBtn');
      const msg = document.getElementById('__formMsg');
      btn.disabled = true; btn.textContent = '送信中...';
      msg.style.display = 'none';
      const data = {};
      new FormData(this).forEach((v,k) => data[k]=v);
      try {
        const res  = await fetch('/contact.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});
        const json = await res.json();
        msg.style.display = 'block';
        if (json.success) {
          msg.style.background = 'rgba(6,199,85,.1)'; msg.style.border = '1px solid rgba(6,199,85,.4)'; msg.style.color = '#06c755';
          msg.textContent = json.message; this.reset();
        } else {
          msg.style.background = 'rgba(139,26,26,.15)'; msg.style.border = '1px solid rgba(178,34,34,.4)'; msg.style.color = '#e08080';
          msg.textContent = (json.errors||[]).join(' / ') || json.message || '送信に失敗しました。';
        }
      } catch { msg.style.display='block'; msg.textContent='通信エラーが発生しました。'; }
      finally { btn.disabled=false; btn.textContent='送信する'; msg.scrollIntoView({behavior:'smooth',block:'nearest'}); }
    });
    </script>
    <?php endif; ?>
  </div>
</section>
HTML;

    // </body> の直前に挿入
    if (stripos($html, '</body>') !== false) {
        $html = str_ireplace('</body>', $contactBlock . "\n</body>", $html);
    } else {
        $html .= $contactBlock;
    }

    return $phpHeader . $html;
}

// ─────────────────────────────────────────
// 削除
// ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $msg = '不正なリクエストです。'; $msgType = 'error';
    } else {
        $id = (int)$_POST['id'];
        $inUse = $db->prepare("SELECT COUNT(*) FROM agents WHERE default_template_id=?");
        $inUse->execute([$id]);
        if ($inUse->fetchColumn() > 0) {
            $msg = 'このテンプレートを使用しているアドバイザーがいるため削除できません。'; $msgType = 'error';
        } else {
            $tpl = $db->prepare("SELECT slug, html_file, thumbnail_url FROM lp_templates WHERE id=?");
            $tpl->execute([$id]);
            $tpl = $tpl->fetch();
            if ($tpl) {
                $tplFile = __DIR__ . '/../templates/' . $tpl['slug'] . '/' . $tpl['html_file'];
                $tplDir  = __DIR__ . '/../templates/' . $tpl['slug'];
                if (file_exists($tplFile)) @unlink($tplFile);
                if (is_dir($tplDir) && count(scandir($tplDir)) <= 2) @rmdir($tplDir);
                if (!empty($tpl['thumbnail_url'])) @unlink(__DIR__ . '/..' . $tpl['thumbnail_url']);
            }
            $db->prepare("DELETE FROM lp_templates WHERE id=?")->execute([$id]);
            $msg = 'テンプレートを削除しました。';
        }
    }
}

// ─────────────────────────────────────────
// 公開/非公開切替
// ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $db->prepare("UPDATE lp_templates SET status=IF(status='active','inactive','active') WHERE id=?")
           ->execute([(int)$_POST['id']]);
        $msg = 'ステータスを変更しました。';
    }
}

// ─────────────────────────────────────────
// 登録・更新
// ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['create','update'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $msg = '不正なリクエストです。'; $msgType = 'error';
    } else {
        $slug  = preg_replace('/[^a-z0-9_\-]/', '', strtolower(sanitizeInput($_POST['slug'] ?? '')));
        $name  = sanitizeInput($_POST['name'] ?? '');
        $desc  = sanitizeInput($_POST['description'] ?? '');
        $order = (int)($_POST['sort_order'] ?? 0);
        $projectId = (int)($_POST['project_id'] ?? 0) ?: $defaultProjectId;

        // サムネイル
        $thumbnail = sanitizeInput($_POST['current_thumbnail'] ?? '');
        if (!empty($_FILES['thumbnail']['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp','gif']) && $_FILES['thumbnail']['size'] < 2*1024*1024) {
                $fname = uniqid('tpl_', true) . '.' . $ext;
                if (move_uploaded_file($_FILES['thumbnail']['tmp_name'], __DIR__.'/../uploads/lp/'.$fname)) {
                    $thumbnail = '/uploads/lp/'.$fname;
                }
            }
        }

        // ★ LPファイルアップロード処理
        $htmlFile = sanitizeInput($_POST['html_file'] ?? ''); // 既存ファイル名（手動入力 or 前回）
        $uploadMsg = '';

        if (!empty($_FILES['lp_file']['tmp_name'])) {
            $origName = $_FILES['lp_file']['name'];
            $origExt  = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            $fileSize = $_FILES['lp_file']['size'];

            if (!in_array($origExt, ['html','htm','php'])) {
                $msg = 'アップロードできるのはHTML・PHPファイルのみです。'; $msgType = 'error';
            } elseif ($fileSize > 5 * 1024 * 1024) {
                $msg = 'ファイルサイズは5MB以内にしてください。'; $msgType = 'error';
            } elseif (empty($slug)) {
                $msg = 'スラッグを先に入力してください（フォルダ名に使用されます）。'; $msgType = 'error';
            } else {
                $fileContent = file_get_contents($_FILES['lp_file']['tmp_name']);

                // HTMLの場合はPHPテンプレートに自動変換
                if (in_array($origExt, ['html','htm'])) {
                    $fileContent = convertHtmlToTemplatePHP($fileContent, $slug);
                    $phpFileName = $slug . '.php';
                } else {
                    $phpFileName = $slug . '.php';
                }

                // ディレクトリ作成
                $tplDir = __DIR__ . '/../templates/' . $slug;
                if (!is_dir($tplDir)) {
                    mkdir($tplDir, 0755, true);
                }

                $destPath = $tplDir . '/' . $phpFileName;
                if (file_put_contents($destPath, $fileContent) !== false) {
                    $htmlFile  = $phpFileName;
                    $uploadMsg = "ファイルを保存しました：templates/{$slug}/{$phpFileName}";
                    if (in_array($origExt, ['html','htm'])) {
                        $uploadMsg .= '（HTMLをPHPテンプレートに自動変換済み）';
                    }
                } else {
                    $msg = 'ファイルの書き込みに失敗しました。パーミッションを確認してください。'; $msgType = 'error';
                }
            }
        }

        // DBに保存
        if (!$msg || $msgType === 'success') {
            if (empty($slug) || empty($name) || empty($htmlFile)) {
                $msg = 'スラッグ・名前・LPファイルは必須です。'; $msgType = 'error';
            } else {
                try {
                    if (($_POST['action'] ?? '') === 'create') {
                        if ($templateHasProject) {
                            $db->prepare("INSERT INTO lp_templates (project_id,slug,name,description,html_file,thumbnail_url,sort_order) VALUES (?,?,?,?,?,?,?)")
                               ->execute([$projectId,$slug,$name,$desc,$htmlFile,$thumbnail,$order]);
                        } else {
                            $db->prepare("INSERT INTO lp_templates (slug,name,description,html_file,thumbnail_url,sort_order) VALUES (?,?,?,?,?,?)")
                               ->execute([$slug,$name,$desc,$htmlFile,$thumbnail,$order]);
                        }
                        $msg = 'テンプレートを登録しました。' . ($uploadMsg ? '　'.$uploadMsg : '');
                    } else {
                        $id = (int)$_POST['id'];
                        if ($templateHasProject) {
                            $db->prepare("UPDATE lp_templates SET project_id=?,slug=?,name=?,description=?,html_file=?,thumbnail_url=?,sort_order=? WHERE id=?")
                               ->execute([$projectId,$slug,$name,$desc,$htmlFile,$thumbnail,$order,$id]);
                        } else {
                            $db->prepare("UPDATE lp_templates SET slug=?,name=?,description=?,html_file=?,thumbnail_url=?,sort_order=? WHERE id=?")
                               ->execute([$slug,$name,$desc,$htmlFile,$thumbnail,$order,$id]);
                        }
                        $msg = 'テンプレートを更新しました。' . ($uploadMsg ? '　'.$uploadMsg : '');
                    }
                } catch (PDOException $e) {
                    $msg = 'エラーが発生しました（スラッグが重複している可能性があります）。'; $msgType = 'error';
                }
            }
        }
    }
}

// 編集対象
$editTpl = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM lp_templates WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $editTpl = $stmt->fetch() ?: null;
}

$templates = $templateHasProject
    ? $db->query("SELECT t.*, p.name AS project_name FROM lp_templates t LEFT JOIN projects p ON t.project_id=p.id ORDER BY COALESCE(p.sort_order,9999) ASC, t.sort_order ASC, t.id ASC")->fetchAll()
    : $db->query("SELECT * FROM lp_templates ORDER BY sort_order ASC, id ASC")->fetchAll();
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?>"><?= h($msg) ?></div>
<?php endif; ?>

<div class="card">
    <p class="card-title"><?= $editTpl ? 'テンプレートを編集' : '新規テンプレート登録' ?></p>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="action"     value="<?= $editTpl ? 'update' : 'create' ?>">
        <?php if ($editTpl): ?>
        <input type="hidden" name="id"                value="<?= $editTpl['id'] ?>">
        <input type="hidden" name="current_thumbnail" value="<?= h($editTpl['thumbnail_url'] ?? '') ?>">
        <input type="hidden" name="html_file"         value="<?= h($editTpl['html_file'] ?? '') ?>">
        <?php endif; ?>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;">
            <div class="form-group">
                <label>スラッグ（英数字・小文字）*</label>
                <input type="text" name="slug" id="slugInput"
                       value="<?= h($editTpl['slug'] ?? '') ?>"
                       placeholder="my-lp" required
                       <?= $editTpl ? 'readonly style="opacity:.6;cursor:not-allowed;"' : '' ?>>
                <p style="font-size:.72rem;color:var(--text-muted);margin-top:.3rem;">
                    フォルダ名に使われます（例: <code>my-lp</code> → templates/my-lp/my-lp.php）
                </p>
            </div>
            <div class="form-group">
                <label>テンプレート名 *</label>
                <input type="text" name="name" value="<?= h($editTpl['name'] ?? '') ?>" required>
            </div>
            <?php if ($templateHasProject): ?>
            <div class="form-group">
                <label>プロジェクト</label>
                <select name="project_id">
                    <?php foreach ($projects as $project): ?>
                    <option value="<?= (int)$project['id'] ?>" <?= (int)($editTpl['project_id'] ?? $defaultProjectId) === (int)$project['id'] ? 'selected' : '' ?>><?= h($project['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="form-group">
                <label>表示順</label>
                <input type="number" name="sort_order" value="<?= h($editTpl['sort_order'] ?? 0) ?>">
            </div>
        </div>

        <div class="form-group">
            <label>説明</label>
            <textarea name="description"><?= h($editTpl['description'] ?? '') ?></textarea>
        </div>

        <!-- ★ LPファイルアップロード -->
        <div class="form-group">
            <label>
                LPファイル（HTML / PHP）<?= $editTpl ? '' : '<span style="color:#e05555;margin-left:.3rem;">*</span>' ?>
            </label>
            <input type="file" name="lp_file" id="lpFile" accept=".html,.htm,.php">
            <?php if ($editTpl && !empty($editTpl['html_file'])): ?>
            <p style="font-size:.78rem;color:var(--text-muted);margin-top:.4rem;">
                現在のファイル：<code style="color:var(--gold);"><?= h($editTpl['html_file']) ?></code>
                （再アップロードで上書き）
            </p>
            <?php endif; ?>
            <div id="fileInfo" style="display:none;margin-top:.5rem;padding:.6rem .9rem;background:rgba(201,168,76,.08);border:1px solid rgba(201,168,76,.2);border-radius:3px;font-size:.8rem;color:var(--gold-lt);"></div>
            <p style="font-size:.75rem;color:rgba(245,240,232,.4);margin-top:.4rem;">
                HTMLの場合：問い合わせフォーム・LINEボタンが自動挿入されPHPに変換されます。<br>
                PHPの場合：そのままアップロード（$agent変数は使用可能）。最大5MB。
            </p>
        </div>

        <div class="form-group">
            <label>サムネイル（2MB以内）</label>
            <input type="file" name="thumbnail" accept="image/*">
            <?php if (!empty($editTpl['thumbnail_url'])): ?>
            <img src="<?= h($editTpl['thumbnail_url']) ?>"
                 style="width:120px;height:80px;object-fit:cover;margin-top:.5rem;border-radius:4px;border:1px solid var(--border);">
            <?php endif; ?>
        </div>

        <div style="display:flex;gap:.75rem;">
            <button type="submit" class="btn btn-gold"><?= $editTpl ? '更新する' : '登録する' ?></button>
            <?php if ($editTpl): ?>
            <a href="/admin/template_customizer.php?id=<?= (int)$editTpl['id'] ?>" class="btn btn-outline">表示編集へ</a>
            <a href="/admin/templates.php" class="btn btn-outline">キャンセル</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- テンプレート一覧 -->
<div class="card" style="padding:0;overflow:hidden;">
    <table>
        <thead>
            <tr><th>順序</th><?php if ($templateHasProject): ?><th>プロジェクト</th><?php endif; ?><th>サムネイル</th><th>名前</th><th>スラッグ</th><th>ファイル</th><th>使用アドバイザー</th><th>状態</th><th>操作</th></tr>
        </thead>
        <tbody>
        <?php foreach ($templates as $tpl): ?>
            <?php
            $useCount = $db->prepare("SELECT COUNT(*) FROM agents WHERE default_template_id=?");
            $useCount->execute([$tpl['id']]);
            $cnt = $useCount->fetchColumn();
            // 実ファイル存在確認
            $tplDir   = __DIR__ . '/../templates/' . $tpl['slug'] . '/' . $tpl['html_file'];
            $fileExists = file_exists($tplDir);
            ?>
            <tr>
                <td style="text-align:center;color:var(--text-muted)"><?= $tpl['sort_order'] ?></td>
                <?php if ($templateHasProject): ?><td style="font-size:.78rem;color:var(--gold);"><?= h($tpl['project_name'] ?? '未設定') ?></td><?php endif; ?>
                <td>
                    <?php if ($tpl['thumbnail_url']): ?>
                    <img src="<?= h($tpl['thumbnail_url']) ?>" style="width:72px;height:48px;object-fit:cover;border-radius:3px;">
                    <?php else: ?>
                    <div style="width:72px;height:48px;background:rgba(255,255,255,.05);border-radius:3px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;">🎨</div>
                    <?php endif; ?>
                </td>
                <td style="font-weight:700;"><?= h($tpl['name']) ?></td>
                <td style="font-family:monospace;font-size:.82rem;color:var(--gold)"><?= h($tpl['slug']) ?></td>
                <td style="font-family:monospace;font-size:.78rem;">
                    <span style="color:<?= $fileExists ? 'rgba(245,240,232,.5)' : '#e08080' ?>">
                        <?= h($tpl['html_file']) ?>
                        <?= $fileExists ? '' : ' ⚠ファイルなし' ?>
                    </span>
                </td>
                <td style="text-align:center;"><?= $cnt ?>アドバイザー</td>
                <td>
                    <span class="badge badge-<?= $tpl['status'] === 'active' ? 'active' : 'inactive' ?>">
                        <?= $tpl['status'] === 'active' ? '公開中' : '非公開' ?>
                    </span>
                </td>
                <td style="white-space:nowrap;">
                    <a href="/admin/preview.php?id=<?= $tpl['id'] ?>" target="_blank" class="btn btn-outline btn-sm" style="color:var(--gold);">プレビュー</a>
                    <a href="/admin/template_customizer.php?id=<?= $tpl['id'] ?>" class="btn btn-outline btn-sm">表示編集</a>
                    <a href="/admin/templates.php?edit=<?= $tpl['id'] ?>" class="btn btn-outline btn-sm">編集</a>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?= $tpl['id'] ?>">
                        <button type="submit" class="btn btn-outline btn-sm"><?= $tpl['status'] === 'active' ? '非公開' : '公開' ?></button>
                    </form>
                    <?php if ($cnt == 0): ?>
                    <form method="post" style="display:inline;" onsubmit="return confirm('「<?= h($tpl['name']) ?>」を削除します。テンプレートファイルも削除されます。よろしいですか？')">
                        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $tpl['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm">削除</button>
                    </form>
                    <?php else: ?>
                    <span style="font-size:.72rem;color:var(--text-muted);">使用中</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
// ファイル選択時のプレビュー表示
document.getElementById('lpFile').addEventListener('change', function() {
    const info = document.getElementById('fileInfo');
    if (!this.files.length) { info.style.display = 'none'; return; }
    const f    = this.files[0];
    const ext  = f.name.split('.').pop().toLowerCase();
    const size = (f.size / 1024).toFixed(1);
    let note   = '';
    if (['html','htm'].includes(ext)) {
        note = '📄 HTML → PHPへ自動変換・問い合わせフォームを自動挿入します';
    } else if (ext === 'php') {
        note = '⚙️ PHPファイルをそのままアップロードします';
    }
    info.textContent = `${f.name}（${size} KB）　${note}`;
    info.style.display = 'block';

    // スラッグが空ならファイル名から自動補完
    const slugInput = document.getElementById('slugInput');
    if (!slugInput.readOnly && !slugInput.value) {
        const baseName = f.name.replace(/\.[^.]+$/, '').toLowerCase().replace(/[^a-z0-9_\-]/g, '-');
        slugInput.value = baseName;
    }
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>

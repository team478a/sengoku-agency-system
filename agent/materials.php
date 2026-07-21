<?php
$pageTitle = '紹介素材';
require_once __DIR__ . '/header.php';

$db  = getDB();
$aid = $currentAgent['id'];
$myLpUrl = $selectedAgentProjectLpUrl ?: buildAgentProjectLpUrl((string)($currentAgent['agent_code'] ?? ''), null);
$materialHasProject = tableHasColumn('materials', 'project_id');

function personalizeMaterialText(string $text, array $agent, string $lpUrl): string {
    $replacements = [
        '{lp_url}' => $lpUrl,
        '{url}' => $lpUrl,
        '{agent_code}' => (string)($agent['agent_code'] ?? ''),
        '{agent_name}' => (string)($agent['agent_name'] ?? ''),
        '{person_name}' => (string)($agent['person_name'] ?? ''),
    ];
    $personalized = strtr($text, $replacements);
    $hadUrlToken = $personalized !== $text && (str_contains($text, '{lp_url}') || str_contains($text, '{url}'));
    if (!$hadUrlToken && $lpUrl !== '' && !str_contains($personalized, $lpUrl)) {
        $personalized = rtrim($personalized) . "\n\n▼ 詳細はこちら\n" . $lpUrl;
    }
    return $personalized;
}

function buildMaterialCopyVariants(string $postText, string $lpUrl): array {
    $base = trim($postText);
    if ($base === '') {
        $base = $lpUrl;
    }
    $lineText = preg_replace('/\s*#\S+/u', '', $base);
    $lineText = trim((string)$lineText);
    if ($lineText === '') {
        $lineText = $base;
    }
    if ($lpUrl !== '' && !str_contains($lineText, $lpUrl)) {
        $lineText .= "\n" . $lpUrl;
    }
    $xText = mb_strimwidth($base, 0, 230, '…', 'UTF-8');
    if ($lpUrl !== '' && !str_contains($xText, $lpUrl)) {
        $xText = rtrim(mb_strimwidth($base, 0, 200, '…', 'UTF-8')) . "\n" . $lpUrl;
    }
    return [
        'post' => $base,
        'instagram' => $base,
        'x' => $xText,
        'line' => $lineText,
    ];
}

// このエージェントが閲覧できる素材
// all公開 OR 個別指定でこのエージェントが含まれる
$filterCat = $_GET['cat'] ?? 'all';
$filterProject = $_GET['project_id'] ?? (!empty($selectedAgentProjectId) ? (string)(int)$selectedAgentProjectId : 'all');
$catWhere  = $filterCat !== 'all' ? "AND m.category_id=" . (int)$filterCat : '';
$projectWhere = ($materialHasProject && $filterProject !== 'all') ? "AND m.project_id=" . (int)$filterProject : '';

$materials = $db->prepare("
    SELECT m.*, c.name AS cat_name" . ($materialHasProject ? ", p.name AS project_name, p.slug AS project_slug" : "") . "
    FROM materials m
    LEFT JOIN material_categories c ON m.category_id = c.id
    " . ($materialHasProject ? "LEFT JOIN projects p ON m.project_id = p.id" : "") . "
    WHERE m.status = 'active'
      AND (
        m.access_type = 'all'
        OR EXISTS (
          SELECT 1 FROM material_agent_access ma
          WHERE ma.material_id = m.id AND ma.agent_id = ?
        )
      )
      $catWhere
      $projectWhere
    ORDER BY " . ($materialHasProject ? "COALESCE(p.sort_order,9999) ASC," : "") . " m.sort_order ASC, m.created_at DESC
");
$materials->execute([$aid]);
$materials = $materials->fetchAll();

$visibleProjects = [];
if ($materialHasProject) {
    try {
        $visibleProjects = $db->query("
            SELECT DISTINCT p.id, p.name, p.sort_order
            FROM projects p
            INNER JOIN materials m ON m.project_id = p.id
            WHERE p.status='active' AND m.status='active'
            ORDER BY p.sort_order, p.id
        ")->fetchAll();
    } catch (Throwable $e) {
        $visibleProjects = [];
    }
}

$categories = $db->query("
    SELECT DISTINCT c.id, c.name, c.sort_order
    FROM material_categories c
    INNER JOIN materials m ON m.category_id = c.id
    WHERE m.status = 'active'
    " . (($materialHasProject && $filterProject !== 'all') ? "AND m.project_id=" . (int)$filterProject : "") . "
    ORDER BY c.sort_order, c.id
")->fetchAll();

// タイプ別に分類
$grouped = [];
foreach ($materials as $m) {
    $grouped[$m['cat_name'] ?? '未分類'][] = $m;
}
?>

<div class="card" style="margin-bottom:1.25rem;">
  <p class="card-title">あなたの専用URL</p>
  <div style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;">
    <code style="background:rgba(201,168,76,.08);border:1px solid var(--border);padding:.55rem .85rem;border-radius:3px;font-size:.85rem;color:var(--gold-lt);flex:1;min-width:0;word-break:break-all;"><?= h($myLpUrl) ?></code>
    <a href="<?= h($myLpUrl) ?>" target="_blank" class="btn btn-outline" style="white-space:nowrap;font-size:.82rem;padding:.55rem .9rem;">確認 ↗</a>
    <button type="button" onclick="copyRawText(<?= h(json_encode($myLpUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>,this)" class="btn btn-outline" style="white-space:nowrap;font-size:.82rem;padding:.55rem .9rem;">URLコピー</button>
  </div>
</div>

<!-- フィルタ -->
<div style="display:flex;gap:.5rem;margin-bottom:1.25rem;flex-wrap:wrap;">
  <?php if ($materialHasProject && $visibleProjects): ?>
  <a href="?project_id=all&cat=<?= h($filterCat) ?>" style="padding:.38rem .9rem;border-radius:3px;font-size:.78rem;text-decoration:none;
     background:<?= $filterProject==='all'?'rgba(201,168,76,.18)':'rgba(255,255,255,.04)' ?>;
     border:1px solid <?= $filterProject==='all'?'rgba(201,168,76,.5)':'rgba(255,255,255,.08)' ?>;
     color:<?= $filterProject==='all'?'var(--gold-lt)':'rgba(245,240,232,.6)' ?>;">全プロジェクト</a>
  <?php foreach ($visibleProjects as $project): ?>
  <a href="?project_id=<?= (int)$project['id'] ?>&cat=<?= h($filterCat) ?>" style="padding:.38rem .9rem;border-radius:3px;font-size:.78rem;text-decoration:none;
     background:<?= (string)$filterProject===(string)$project['id']?'rgba(201,168,76,.18)':'rgba(255,255,255,.04)' ?>;
     border:1px solid <?= (string)$filterProject===(string)$project['id']?'rgba(201,168,76,.5)':'rgba(255,255,255,.08)' ?>;
     color:<?= (string)$filterProject===(string)$project['id']?'var(--gold-lt)':'rgba(245,240,232,.6)' ?>;"><?= h($project['name']) ?></a>
  <?php endforeach; ?>
  <span style="flex-basis:100%;height:0;"></span>
  <?php endif; ?>
  <a href="?cat=all&project_id=<?= h($filterProject) ?>" style="padding:.38rem .9rem;border-radius:3px;font-size:.78rem;text-decoration:none;
     background:<?= $filterCat==='all'?'rgba(201,168,76,.18)':'rgba(255,255,255,.04)' ?>;
     border:1px solid <?= $filterCat==='all'?'rgba(201,168,76,.5)':'rgba(255,255,255,.08)' ?>;
     color:<?= $filterCat==='all'?'var(--gold-lt)':'rgba(245,240,232,.6)' ?>;">すべて</a>
  <?php foreach ($categories as $c): ?>
  <a href="?cat=<?= $c['id'] ?>&project_id=<?= h($filterProject) ?>" style="padding:.38rem .9rem;border-radius:3px;font-size:.78rem;text-decoration:none;
     background:<?= $filterCat==$c['id']?'rgba(201,168,76,.18)':'rgba(255,255,255,.04)' ?>;
     border:1px solid <?= $filterCat==$c['id']?'rgba(201,168,76,.5)':'rgba(255,255,255,.08)' ?>;
     color:<?= $filterCat==$c['id']?'var(--gold-lt)':'rgba(245,240,232,.6)' ?>;"><?= h($c['name']) ?></a>
  <?php endforeach; ?>
</div>

<?php if (!$materials): ?>
<div class="card" style="text-align:center;padding:3rem;">
  <p style="font-size:2rem;margin-bottom:.75rem;">📭</p>
  <p style="color:var(--text-muted);">紹介素材はまだ登録されていません。</p>
</div>
<?php else: ?>

<?php foreach ($grouped as $catName => $items): ?>
<div style="margin-bottom:2rem;">
  <p style="font-size:.72rem;letter-spacing:.2em;color:var(--gold);text-transform:uppercase;margin-bottom:.85rem;font-weight:700;"><?= h($catName) ?></p>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1rem;">
  <?php foreach ($items as $m): ?>
  <?php
    $materialBody = (string)($m['type'] === 'text' ? ($m['content_text'] ?? '') : ($m['description'] ?? ''));
    if (trim($materialBody) === '') {
        $materialBody = (string)($m['title'] ?? '');
    }
    $materialLpUrl = $myLpUrl;
    if ($materialHasProject && !empty($m['project_slug'])) {
        $materialLpUrl = buildAgentProjectLpUrl((string)($currentAgent['agent_code'] ?? ''), [
            'slug' => (string)$m['project_slug'],
        ]);
    }
    $materialPostText = personalizeMaterialText($materialBody, $currentAgent, $materialLpUrl);
    $copyVariants = buildMaterialCopyVariants($materialPostText, $materialLpUrl);
    foreach (['instagram' => 'instagram_text', 'x' => 'x_text', 'line' => 'line_text'] as $variantKey => $columnName) {
        $customText = trim((string)($m[$columnName] ?? ''));
        if ($customText !== '') {
            $copyVariants[$variantKey] = personalizeMaterialText($customText, $currentAgent, $materialLpUrl);
        }
    }
    $copyVariants['post'] = $copyVariants['instagram'] ?: $materialPostText;
  ?>

  <div class="card" style="margin-bottom:0;position:relative;">
    <!-- バッジ -->
    <div style="position:absolute;top:.75rem;right:.75rem;">
      <?php $typeLabel = ['text'=>'テキスト','image'=>'画像','video'=>'動画','file'=>'ファイル'][$m['type']] ?? ''; ?>
      <span style="font-size:.68rem;padding:.2rem .55rem;border-radius:2px;background:rgba(201,168,76,.12);color:var(--gold);font-weight:700;"><?= $typeLabel ?></span>
    </div>

    <p style="font-weight:700;font-size:.9rem;margin-bottom:.3rem;padding-right:4rem;"><?= h($m['title']) ?></p>
    <?php if ($materialHasProject && !empty($m['project_name'])): ?>
    <p style="font-size:.72rem;color:var(--gold);margin-bottom:.35rem;"><?= h($m['project_name']) ?></p>
    <?php endif; ?>
    <?php if ($m['description']): ?>
    <p style="font-size:.78rem;color:var(--text-muted);line-height:1.7;margin-bottom:.85rem;"><?= h($m['description']) ?></p>
    <?php endif; ?>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.35rem;margin:.75rem 0 .85rem;">
      <div style="background:rgba(201,168,76,.07);border:1px solid var(--border);border-radius:3px;padding:.45rem .35rem;text-align:center;font-size:.68rem;color:var(--text-muted);"><strong style="display:block;color:var(--gold-lt);font-size:.75rem;">1</strong><?= $m['type'] === 'text' ? '文を確認' : '素材を保存' ?></div>
      <div style="background:rgba(201,168,76,.07);border:1px solid var(--border);border-radius:3px;padding:.45rem .35rem;text-align:center;font-size:.68rem;color:var(--text-muted);"><strong style="display:block;color:var(--gold-lt);font-size:.75rem;">2</strong>投稿文コピー</div>
      <div style="background:rgba(201,168,76,.07);border:1px solid var(--border);border-radius:3px;padding:.45rem .35rem;text-align:center;font-size:.68rem;color:var(--text-muted);"><strong style="display:block;color:var(--gold-lt);font-size:.75rem;">3</strong>SNSに貼付</div>
    </div>

    <?php if ($m['type'] === 'text'): ?>
    <!-- テキスト素材 -->
    <div style="position:relative;">
      <pre id="text_<?= $m['id'] ?>" style="background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:3px;padding:.85rem;font-family:'Noto Sans JP',sans-serif;font-size:.82rem;line-height:1.9;white-space:pre-wrap;word-break:break-all;color:var(--paper);max-height:220px;overflow-y:auto;"><?= h($materialPostText) ?></pre>
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.45rem;margin-top:.6rem;">
        <button type="button" onclick="copyRawText(<?= h(json_encode($copyVariants['instagram'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>,this)" class="btn btn-gold" style="font-size:.76rem;padding:.55rem .35rem;">Instagram</button>
        <button type="button" onclick="copyRawText(<?= h(json_encode($copyVariants['x'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>,this)" class="btn btn-outline" style="font-size:.76rem;padding:.55rem .35rem;">X</button>
        <button type="button" onclick="copyRawText(<?= h(json_encode($copyVariants['line'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>,this)" class="btn btn-outline" style="font-size:.76rem;padding:.55rem .35rem;">LINE</button>
      </div>
      <button type="button" onclick="shareMaterial(<?= h(json_encode($copyVariants['post'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>,this)" class="btn btn-outline" style="width:100%;font-size:.78rem;padding:.55rem;margin-top:.45rem;">スマホで共有</button>
      <p style="font-size:.72rem;color:var(--text-muted);margin-top:.45rem;line-height:1.6;">この文章にはあなたの専用URLが入っています。</p>
    </div>

    <?php elseif ($m['type'] === 'image'): ?>
    <!-- 画像素材 -->
    <img src="<?= h($m['file_path']) ?>" alt="<?= h($m['title']) ?>"
         style="width:100%;border-radius:3px;border:1px solid var(--border);margin-bottom:.75rem;cursor:pointer;"
         onclick="openLightbox('<?= h($m['file_path']) ?>','<?= h($m['title']) ?>')">
    <div style="display:flex;gap:.5rem;">
      <a href="<?= h($m['file_path']) ?>" download="<?= h($m['file_name'] ?? $m['title']) ?>"
         class="btn btn-gold" style="flex:1;justify-content:center;font-size:.82rem;padding:.6rem;">
        ⬇ ダウンロード
      </a>
      <button onclick="copyImageUrl('<?= h($m['file_path']) ?>',this)"
         class="btn btn-outline" style="flex:1;font-size:.82rem;padding:.6rem;">
        🔗 画像URLコピー
      </button>
    </div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.45rem;margin-top:.5rem;">
      <button type="button" onclick="copyRawText(<?= h(json_encode($copyVariants['instagram'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>,this)" class="btn btn-gold" style="font-size:.76rem;padding:.55rem .35rem;">Instagram</button>
      <button type="button" onclick="copyRawText(<?= h(json_encode($copyVariants['x'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>,this)" class="btn btn-outline" style="font-size:.76rem;padding:.55rem .35rem;">X</button>
      <button type="button" onclick="copyRawText(<?= h(json_encode($copyVariants['line'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>,this)" class="btn btn-outline" style="font-size:.76rem;padding:.55rem .35rem;">LINE</button>
    </div>
    <button type="button" onclick="shareMaterial(<?= h(json_encode($copyVariants['post'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>,this)" class="btn btn-outline" style="width:100%;font-size:.78rem;padding:.55rem;margin-top:.45rem;">スマホで共有</button>

    <?php elseif ($m['type'] === 'video'): ?>
    <!-- 動画素材 -->
    <video controls style="width:100%;border-radius:3px;border:1px solid var(--border);margin-bottom:.75rem;max-height:240px;">
      <source src="<?= h($m['file_path']) ?>">
      お使いのブラウザは動画再生に対応していません。
    </video>
    <a href="<?= h($m['file_path']) ?>" download="<?= h($m['file_name'] ?? $m['title']) ?>"
       class="btn btn-gold" style="display:flex;justify-content:center;font-size:.82rem;padding:.6rem;">
      ⬇ 動画をダウンロード
    </a>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.45rem;margin-top:.5rem;">
      <button type="button" onclick="copyRawText(<?= h(json_encode($copyVariants['instagram'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>,this)" class="btn btn-gold" style="font-size:.76rem;padding:.55rem .35rem;">Instagram</button>
      <button type="button" onclick="copyRawText(<?= h(json_encode($copyVariants['x'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>,this)" class="btn btn-outline" style="font-size:.76rem;padding:.55rem .35rem;">X</button>
      <button type="button" onclick="copyRawText(<?= h(json_encode($copyVariants['line'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>,this)" class="btn btn-outline" style="font-size:.76rem;padding:.55rem .35rem;">LINE</button>
    </div>
    <button type="button" onclick="shareMaterial(<?= h(json_encode($copyVariants['post'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>,this)" class="btn btn-outline" style="width:100%;font-size:.78rem;padding:.55rem;margin-top:.45rem;">スマホで共有</button>

    <?php else: ?>
    <!-- ファイル素材（PDF等） -->
    <div style="display:flex;align-items:center;gap:1rem;background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:3px;padding:1rem;margin-bottom:.75rem;">
      <span style="font-size:2rem;">📎</span>
      <div style="flex:1;min-width:0;">
        <p style="font-size:.82rem;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= h($m['file_name'] ?? $m['title']) ?></p>
        <?php if ($m['file_size']): ?>
        <p style="font-size:.72rem;color:var(--text-muted);margin-top:.15rem;"><?= number_format($m['file_size']/1024) ?> KB</p>
        <?php endif; ?>
      </div>
    </div>
    <a href="<?= h($m['file_path']) ?>" download="<?= h($m['file_name'] ?? $m['title']) ?>"
       class="btn btn-gold" style="display:flex;justify-content:center;font-size:.82rem;padding:.6rem;">
      ⬇ ダウンロード
    </a>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.45rem;margin-top:.5rem;">
      <button type="button" onclick="copyRawText(<?= h(json_encode($copyVariants['instagram'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>,this)" class="btn btn-gold" style="font-size:.76rem;padding:.55rem .35rem;">Instagram</button>
      <button type="button" onclick="copyRawText(<?= h(json_encode($copyVariants['x'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>,this)" class="btn btn-outline" style="font-size:.76rem;padding:.55rem .35rem;">X</button>
      <button type="button" onclick="copyRawText(<?= h(json_encode($copyVariants['line'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>,this)" class="btn btn-outline" style="font-size:.76rem;padding:.55rem .35rem;">LINE</button>
    </div>
    <button type="button" onclick="shareMaterial(<?= h(json_encode($copyVariants['post'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>,this)" class="btn btn-outline" style="width:100%;font-size:.78rem;padding:.55rem;margin-top:.45rem;">スマホで共有</button>
    <?php endif; ?>
  </div>

  <?php endforeach; ?>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<!-- 画像ライトボックス -->
<div id="lightbox" onclick="closeLightbox()"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.88);z-index:9999;align-items:center;justify-content:center;flex-direction:column;gap:1rem;">
  <img id="lightboxImg" style="max-width:90vw;max-height:80vh;border-radius:4px;box-shadow:0 0 60px rgba(0,0,0,.8);">
  <div style="display:flex;gap:.75rem;">
    <a id="lightboxDl" download class="btn btn-gold" style="font-size:.85rem;" onclick="event.stopPropagation()">⬇ ダウンロード</a>
    <button onclick="closeLightbox()" class="btn btn-outline" style="font-size:.85rem;">閉じる</button>
  </div>
</div>

<script>
function copyText(id, btn) {
    const el   = document.getElementById(id);
    const text = el.textContent || el.innerText;
    copyRawText(text, btn);
}

function copyRawText(text, btn) {
    navigator.clipboard.writeText(text).then(() => {
        const orig = btn.textContent;
        btn.textContent = '✓ コピーしました！';
        btn.style.background = '#06c755';
        setTimeout(() => { btn.textContent = orig; btn.style.background = ''; }, 2000);
    });
}

function copyImageUrl(path, btn) {
    const url = location.origin + path;
    navigator.clipboard.writeText(url).then(() => {
        const orig = btn.textContent;
        btn.textContent = '✓ コピー済';
        setTimeout(() => { btn.textContent = orig; }, 2000);
    });
}

function shareMaterial(text, btn) {
    if (navigator.share) {
        navigator.share({ text }).catch(() => {});
        return;
    }
    copyRawText(text, btn);
}

function openLightbox(src, title) {
    const lb = document.getElementById('lightbox');
    document.getElementById('lightboxImg').src = src;
    document.getElementById('lightboxDl').href = src;
    document.getElementById('lightboxDl').download = title;
    lb.style.display = 'flex';
}

function closeLightbox() {
    document.getElementById('lightbox').style.display = 'none';
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>

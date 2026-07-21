<?php
$pageTitle = 'LP表示編集';
require_once __DIR__ . '/header.php';

$db = getDB();
$csrf = getCsrfToken();
$msg = '';
$msgType = 'success';
$templateId = (int)($_GET['id'] ?? $_POST['template_id'] ?? 0);

$presetFields = [
    'hero_title' => [
        'label' => 'ヒーロー見出し',
        'type' => 'text',
        'hint' => '{{hero_title}}',
        'note' => 'LPの一番目立つタイトルです。短く、1行から2行に収まる文言が適しています。',
    ],
    'hero_subtitle' => [
        'label' => 'ヒーローサブ見出し',
        'type' => 'text',
        'hint' => '{{hero_subtitle}}',
        'note' => '見出しの補足文です。誰向けのLPか、何を案内するLPかを簡潔に入れます。',
    ],
    'hero_body' => [
        'label' => 'ヒーロー説明文',
        'type' => 'textarea',
        'hint' => '{{hero_body}}',
        'note' => 'ヒーロー内の説明文です。長すぎるとスマホで読みにくくなるため、2から4行程度を推奨します。',
    ],
    'cta_text' => [
        'label' => 'CTAボタン文言',
        'type' => 'text',
        'hint' => '{{cta_text}}',
        'note' => 'ボタンに表示する文言です。例: 詳細を見る、今すぐ参加、無料で相談する。',
    ],
    'hero_image' => [
        'label' => 'ヒーロー画像（共通・互換用）',
        'type' => 'image',
        'hint' => '{{hero_image}}',
        'note' => 'PC/スマホを分けない場合の画像です。既存LPとの互換用です。推奨: 横1920px × 縦1080px前後、4MB以内。',
    ],
    'hero_image_pc' => [
        'label' => 'ヒーロー画像（PC用）',
        'type' => 'image',
        'hint' => '{{hero_image_pc}}',
        'note' => 'PC表示用の横長画像です。推奨: 横1920px × 縦1080px、または横1600px × 縦900px。文字入り画像は左右に余白を残してください。',
    ],
    'hero_image_sp' => [
        'label' => 'ヒーロー画像（スマホ用）',
        'type' => 'image',
        'hint' => '{{hero_image_sp}}',
        'note' => 'スマートフォン表示用の縦長画像です。推奨: 横1080px × 縦1350px、または横1080px × 縦1920px。重要な文字や人物は中央に寄せてください。',
    ],
    'background_image' => [
        'label' => '背景画像',
        'type' => 'image',
        'hint' => '{{background_image}}',
        'note' => '背景に敷く画像です。推奨: 横1920px以上。文字の後ろに使う場合は暗め・シンプルな画像が適しています。',
    ],
];

try {
    $db->query("SELECT 1 FROM lp_template_fields LIMIT 1");
    $fieldsReady = true;
} catch (Throwable $e) {
    $fieldsReady = false;
}

$template = null;
if ($templateId > 0) {
    $stmt = $db->prepare("SELECT * FROM lp_templates WHERE id=?");
    $stmt->execute([$templateId]);
    $template = $stmt->fetch() ?: null;
}

if (!$template) {
    $msg = '対象のLPテンプレートが見つかりません。';
    $msgType = 'error';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $template && $fieldsReady) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $msg = '不正なリクエストです。';
        $msgType = 'error';
    } else {
        $uploadDir = __DIR__ . '/../uploads/lp_custom';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $upsert = $db->prepare("
            INSERT INTO lp_template_fields (template_id, field_key, field_type, label, value_text, value_file)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                field_type=VALUES(field_type),
                label=VALUES(label),
                value_text=VALUES(value_text),
                value_file=VALUES(value_file)
        ");

        foreach ($presetFields as $key => $meta) {
            $valueText = trim((string)($_POST[$key] ?? ''));
            $valueFile = trim((string)($_POST[$key . '_current'] ?? ''));

            if ($meta['type'] === 'image' && !empty($_FILES[$key]['tmp_name'])) {
                $ext = strtolower(pathinfo($_FILES[$key]['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg','jpeg','png','webp','gif'], true) && $_FILES[$key]['size'] <= 4 * 1024 * 1024) {
                    $fileName = 'lp_' . $templateId . '_' . $key . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                    if (move_uploaded_file($_FILES[$key]['tmp_name'], $uploadDir . '/' . $fileName)) {
                        $valueFile = '/uploads/lp_custom/' . $fileName;
                    }
                } else {
                    $msg = '画像は jpg / png / webp / gif、4MB以内でアップロードしてください。';
                    $msgType = 'error';
                    break;
                }
            }

            $upsert->execute([
                $templateId,
                $key,
                $meta['type'],
                $meta['label'],
                $meta['type'] === 'image' ? null : $valueText,
                $meta['type'] === 'image' ? ($valueFile ?: null) : null,
            ]);
        }

        if ($msgType !== 'error') {
            $msg = 'LP表示編集を保存しました。';
        }
    }
}

$savedFields = $template ? getLpTemplateFields($templateId) : [];
?>

<?php if ($msg): ?><div class="alert alert-<?= h($msgType) ?>"><?= h($msg) ?></div><?php endif; ?>

<?php if (!$fieldsReady): ?>
  <div class="alert alert-error">
    LP表示編集のDBマイグレーションが未適用です。管理者画面の「アップデート」からDBマイグレーションを適用してください。
  </div>
<?php elseif ($template): ?>
  <div class="card">
    <p class="card-title"><?= h($template['name']) ?> の表示編集</p>
    <p style="font-size:.82rem;color:var(--text-muted);line-height:1.8;margin-bottom:1rem;">
      LP内に差し込みタグが入っている箇所だけ反映されます。例: <code>{{hero_title}}</code>、<code>{{hero_image_pc}}</code>、<code>{{hero_image_sp}}</code>
      <br>PC/スマホで画像を切り替える場合は、LP側で <code>{{hero_picture}}</code> を使うか、PHPテンプレートでは <code>lpResponsiveImage()</code> を使います。
    </p>

    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
      <input type="hidden" name="template_id" value="<?= (int)$templateId ?>">

      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1rem;">
        <?php foreach ($presetFields as $key => $meta): ?>
          <?php
            $saved = $savedFields[$key] ?? [];
            $valueText = (string)($saved['value_text'] ?? '');
            $valueFile = (string)($saved['value_file'] ?? '');
          ?>
          <div class="form-group" style="<?= $meta['type'] === 'textarea' ? 'grid-column:1/-1;' : '' ?>">
            <label><?= h($meta['label']) ?> <span style="font-size:.7rem;color:var(--text-muted);"><?= h($meta['hint']) ?></span></label>
            <?php if (!empty($meta['note'])): ?>
              <p style="font-size:.72rem;color:var(--text-muted);line-height:1.7;margin:-.15rem 0 .45rem;"><?= h($meta['note']) ?></p>
            <?php endif; ?>
            <?php if ($meta['type'] === 'textarea'): ?>
              <textarea name="<?= h($key) ?>" rows="4"><?= h($valueText) ?></textarea>
            <?php elseif ($meta['type'] === 'image'): ?>
              <input type="hidden" name="<?= h($key) ?>_current" value="<?= h($valueFile) ?>">
              <input type="file" name="<?= h($key) ?>" accept="image/*">
              <?php if ($valueFile): ?>
                <div style="margin-top:.6rem;">
                  <img src="<?= h($valueFile) ?>" style="max-width:220px;max-height:130px;object-fit:cover;border:1px solid var(--border);border-radius:4px;">
                  <p style="font-size:.72rem;color:var(--text-muted);margin-top:.25rem;word-break:break-all;"><?= h($valueFile) ?></p>
                </div>
              <?php endif; ?>
            <?php else: ?>
              <input type="text" name="<?= h($key) ?>" value="<?= h($valueText) ?>">
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <div style="display:flex;gap:.6rem;flex-wrap:wrap;margin-top:1rem;">
        <button class="btn btn-gold">保存する</button>
        <a href="/lp.php?preview=1&template_id=<?= (int)$templateId ?>" target="_blank" class="btn btn-outline">プレビュー</a>
        <a href="/admin/templates.php" class="btn btn-outline">テンプレート管理に戻る</a>
      </div>
    </form>
  </div>

  <div class="card">
    <p class="card-title">LPファイル側で使うタグ</p>
    <div class="table-scroll">
      <table>
        <thead><tr><th>用途</th><th>タグ</th><th>PHPテンプレート用</th></tr></thead>
        <tbody>
          <tr>
            <td>PC/スマホ自動切替画像</td>
            <td><code>{{hero_picture}}</code></td>
            <td><code><?= h("<?= lpResponsiveImage(\$agent, 'hero_image_pc', 'hero_image_sp', '画像説明') ?>") ?></code></td>
          </tr>
          <?php foreach ($presetFields as $key => $meta): ?>
          <tr>
            <td><?= h($meta['label']) ?></td>
            <td><code><?= h($meta['hint']) ?></code></td>
            <td><code><?= $meta['type'] === 'image' ? "<?= lpImage(\$agent, '$key', 'デフォルト画像URL') ?>" : "<?= lpText(\$agent, '$key', 'デフォルト文言') ?>" ?></code></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>

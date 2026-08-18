<?php
$pageTitle = 'プロフィール設定';
require_once __DIR__ . '/header.php';

$db = getDB();
$message = '';
$msgType = 'success';

function influencerProfileColumns(PDO $db): array
{
    $cols = [];
    try {
        foreach ($db->query("SHOW COLUMNS FROM agents")->fetchAll(PDO::FETCH_ASSOC) as $col) {
            $cols[$col['Field']] = true;
        }
    } catch (Throwable $e) {
        return [];
    }
    return $cols;
}

function normalizeInfluencerUrl(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    if (!preg_match('~^https?://~i', $url)) {
        $url = 'https://' . $url;
    }
    return $url;
}

function shortenWalletAddress(string $wallet): string
{
    $wallet = trim($wallet);
    if (strlen($wallet) <= 14) {
        return $wallet;
    }
    return substr($wallet, 0, 6) . '...' . substr($wallet, -4);
}

$requiredColumns = [
    'influencer_enabled',
    'influencer_name',
    'metamask_wallet_address',
    'influencer_profile_text',
    'bank_name',
    'bank_branch_name',
    'bank_branch_code',
    'bank_account_type',
    'bank_account_number',
    'bank_account_holder',
    'bank_account_holder_kana',
    'instagram_url',
    'x_url',
    'tiktok_url',
    'youtube_url',
];
$columns = influencerProfileColumns($db);
$missingColumns = array_values(array_filter($requiredColumns, fn($col) => empty($columns[$col])));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$missingColumns) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = '不正なリクエストです。';
        $msgType = 'error';
    } else {
        $data = [
            'influencer_enabled' => isset($_POST['influencer_enabled']) ? 1 : 0,
            'influencer_name' => trim($_POST['influencer_name'] ?? ''),
            'metamask_wallet_address' => trim($_POST['metamask_wallet_address'] ?? ''),
            'influencer_profile_text' => trim($_POST['influencer_profile_text'] ?? ''),
            'bank_name' => trim($_POST['bank_name'] ?? ''),
            'bank_branch_name' => trim($_POST['bank_branch_name'] ?? ''),
            'bank_branch_code' => trim($_POST['bank_branch_code'] ?? ''),
            'bank_account_type' => trim($_POST['bank_account_type'] ?? ''),
            'bank_account_number' => trim($_POST['bank_account_number'] ?? ''),
            'bank_account_holder' => trim($_POST['bank_account_holder'] ?? ''),
            'bank_account_holder_kana' => trim($_POST['bank_account_holder_kana'] ?? ''),
            'instagram_url' => normalizeInfluencerUrl($_POST['instagram_url'] ?? ''),
            'x_url' => normalizeInfluencerUrl($_POST['x_url'] ?? ''),
            'tiktok_url' => normalizeInfluencerUrl($_POST['tiktok_url'] ?? ''),
            'youtube_url' => normalizeInfluencerUrl($_POST['youtube_url'] ?? ''),
            'id' => (int)$currentAgent['id'],
        ];

        $errors = [];
        if ($data['influencer_enabled'] && $data['influencer_name'] === '') {
            $errors[] = 'インフルエンサーとして活動する場合は、表示名を入力してください。';
        }
        if ($data['metamask_wallet_address'] !== '' && !preg_match('/^0x[a-fA-F0-9]{40}$/', $data['metamask_wallet_address'])) {
            $errors[] = 'MetaMaskのウォレットアドレスは、0xから始まる42文字で入力してください。';
        }
        if ($data['bank_account_type'] !== '' && !in_array($data['bank_account_type'], ['普通', '当座', '貯蓄'], true)) {
            $errors[] = '口座種別を正しく選択してください。';
        }
        if ($data['bank_branch_code'] !== '' && !preg_match('/^[0-9]{3}$/', $data['bank_branch_code'])) {
            $errors[] = '支店コードは3桁の数字で入力してください。';
        }
        if ($data['bank_account_number'] !== '' && !preg_match('/^[0-9]{1,8}$/', $data['bank_account_number'])) {
            $errors[] = '口座番号は数字8桁以内で入力してください。';
        }

        $urlLabels = [
            'instagram_url' => 'Instagram',
            'x_url' => 'X',
            'tiktok_url' => 'TikTok',
            'youtube_url' => 'YouTube',
        ];
        foreach ($urlLabels as $field => $label) {
            if ($data[$field] !== '' && !filter_var($data[$field], FILTER_VALIDATE_URL)) {
                $errors[] = $label . 'のURLを正しく入力してください。';
            }
        }

        if ($errors) {
            $message = implode(' ', $errors);
            $msgType = 'error';
        } else {
            $db->prepare("
                UPDATE agents SET
                    influencer_enabled=:influencer_enabled,
                    influencer_name=:influencer_name,
                    metamask_wallet_address=:metamask_wallet_address,
                    influencer_profile_text=:influencer_profile_text,
                    bank_name=:bank_name,
                    bank_branch_name=:bank_branch_name,
                    bank_branch_code=:bank_branch_code,
                    bank_account_type=:bank_account_type,
                    bank_account_number=:bank_account_number,
                    bank_account_holder=:bank_account_holder,
                    bank_account_holder_kana=:bank_account_holder_kana,
                    instagram_url=:instagram_url,
                    x_url=:x_url,
                    tiktok_url=:tiktok_url,
                    youtube_url=:youtube_url
                WHERE id=:id
            ")->execute($data);

            $stmt = $db->prepare("SELECT * FROM agents WHERE id=?");
            $stmt->execute([(int)$currentAgent['id']]);
            $currentAgent = $stmt->fetch();
            $message = 'プロフィール設定を保存しました。';
        }
    }
}

$ag = $currentAgent;
$displayName = trim((string)($ag['influencer_name'] ?? ''));
if ($displayName === '') {
    $displayName = (string)($ag['person_name'] ?? '');
}
$wallet = trim((string)($ag['metamask_wallet_address'] ?? ''));
$profileText = trim((string)($ag['influencer_profile_text'] ?? ''));
?>

<?php if ($message): ?><div class="alert alert-<?= h($msgType) ?>"><?= h($message) ?></div><?php endif; ?>

<?php if ($missingColumns): ?>
  <div class="alert alert-error">
    プロフィール設定のDBマイグレーションが未適用です。管理者画面の「アップデート」からDBマイグレーションを適用してください。
  </div>
<?php else: ?>
  <div class="card">
    <p class="card-title">インフルエンサープロフィール</p>
    <p style="font-size:.82rem;color:var(--text-muted);line-height:1.8;margin-bottom:1rem;">
      エージェント、ディレクター、アドバイザー、スーパーアドバイザーが、インフルエンサーとしても活動する場合の公開プロフィールです。
    </p>

    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= h(getCsrfToken()) ?>">

      <label class="form-check" style="margin-bottom:1rem;">
        <input type="checkbox" name="influencer_enabled" value="1" <?= !empty($ag['influencer_enabled']) ? 'checked' : '' ?>>
        <span style="font-weight:700;color:var(--paper);">インフルエンサーとしても活動する</span>
      </label>

      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1rem;">
        <div class="form-group">
          <label>表示名</label>
          <input type="text" name="influencer_name" value="<?= h($displayName) ?>" placeholder="SNSや紹介素材で使う名前">
        </div>
        <div class="form-group">
          <label>MetaMask ウォレットアドレス</label>
          <input type="text" name="metamask_wallet_address" value="<?= h($wallet) ?>" placeholder="0x...">
        </div>
      </div>

      <div class="form-group">
        <label>プロフィール文</label>
        <textarea name="influencer_profile_text" placeholder="活動内容、得意分野、告知時に見せたい説明など"><?= h($profileText) ?></textarea>
      </div>

      <div style="border-top:1px solid var(--border);margin:1.4rem 0 1rem;padding-top:1.1rem;">
        <p class="card-title" style="margin-bottom:.35rem;">振込先銀行</p>
        <p style="font-size:.82rem;color:var(--text-muted);line-height:1.8;margin-bottom:1rem;">
          報酬や精算の振込先として利用する口座情報です。口座名義カナは通帳・銀行アプリの表記に合わせて入力してください。
        </p>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;">
          <div class="form-group">
            <label>銀行名</label>
            <input type="text" name="bank_name" value="<?= h($ag['bank_name'] ?? '') ?>" placeholder="例：三菱UFJ銀行">
          </div>
          <div class="form-group">
            <label>支店名</label>
            <input type="text" name="bank_branch_name" value="<?= h($ag['bank_branch_name'] ?? '') ?>" placeholder="例：渋谷支店">
          </div>
          <div class="form-group">
            <label>支店コード</label>
            <input type="text" name="bank_branch_code" value="<?= h($ag['bank_branch_code'] ?? '') ?>" placeholder="例：135" inputmode="numeric" maxlength="3">
          </div>
          <div class="form-group">
            <label>口座種別</label>
            <?php $bankAccountType = (string)($ag['bank_account_type'] ?? ''); ?>
            <select name="bank_account_type">
              <option value="">選択してください</option>
              <?php foreach (['普通', '当座', '貯蓄'] as $type): ?>
                <option value="<?= h($type) ?>" <?= $bankAccountType === $type ? 'selected' : '' ?>><?= h($type) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>口座番号</label>
            <input type="text" name="bank_account_number" value="<?= h($ag['bank_account_number'] ?? '') ?>" placeholder="例：1234567" inputmode="numeric" maxlength="8">
          </div>
          <div class="form-group">
            <label>口座名義</label>
            <input type="text" name="bank_account_holder" value="<?= h($ag['bank_account_holder'] ?? '') ?>" placeholder="例：山田 太郎">
          </div>
          <div class="form-group">
            <label>口座名義（カナ）</label>
            <input type="text" name="bank_account_holder_kana" value="<?= h($ag['bank_account_holder_kana'] ?? '') ?>" placeholder="例：ヤマダ タロウ">
          </div>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;">
        <div class="form-group">
          <label>Instagram URL</label>
          <input type="text" name="instagram_url" value="<?= h($ag['instagram_url'] ?? '') ?>" placeholder="https://www.instagram.com/...">
        </div>
        <div class="form-group">
          <label>X URL</label>
          <input type="text" name="x_url" value="<?= h($ag['x_url'] ?? '') ?>" placeholder="https://x.com/...">
        </div>
        <div class="form-group">
          <label>TikTok URL</label>
          <input type="text" name="tiktok_url" value="<?= h($ag['tiktok_url'] ?? '') ?>" placeholder="https://www.tiktok.com/@...">
        </div>
        <div class="form-group">
          <label>YouTube URL</label>
          <input type="text" name="youtube_url" value="<?= h($ag['youtube_url'] ?? '') ?>" placeholder="https://www.youtube.com/@...">
        </div>
      </div>

      <button type="submit" class="btn btn-gold">保存する</button>
    </form>
  </div>

  <div class="card">
    <p class="card-title">表示プレビュー</p>
    <div style="display:grid;grid-template-columns:minmax(0,1fr) auto;gap:1rem;align-items:start;">
      <div>
        <div style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;margin-bottom:.6rem;">
          <span class="badge badge-new"><?= !empty($ag['influencer_enabled']) ? 'インフルエンサー活動中' : '未公開' ?></span>
          <strong style="font-size:1.05rem;color:var(--cream);"><?= h($displayName) ?></strong>
        </div>
        <?php if ($profileText !== ''): ?>
          <p style="line-height:1.8;color:var(--paper);white-space:pre-wrap;"><?= h($profileText) ?></p>
        <?php else: ?>
          <p style="line-height:1.8;color:var(--text-muted);">プロフィール文は未設定です。</p>
        <?php endif; ?>
        <?php if ($wallet !== ''): ?>
          <p style="margin-top:.85rem;color:var(--text-muted);font-size:.82rem;">
            Wallet: <code id="walletPreview" style="color:var(--gold);"><?= h(shortenWalletAddress($wallet)) ?></code>
          </p>
        <?php endif; ?>
      </div>
      <div style="display:flex;gap:.5rem;flex-wrap:wrap;justify-content:flex-end;">
        <?php foreach (['instagram_url' => 'Instagram', 'x_url' => 'X', 'tiktok_url' => 'TikTok', 'youtube_url' => 'YouTube'] as $field => $label): ?>
          <?php if (!empty($ag[$field])): ?>
            <a class="btn btn-outline" href="<?= h($ag[$field]) ?>" target="_blank" rel="noopener"><?= h($label) ?></a>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>

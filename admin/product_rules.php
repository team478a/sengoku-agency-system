<?php
$pageTitle = '商品・報酬ルール';
require_once __DIR__ . '/header.php';

$db = getDB();
$csrf = getCsrfToken();
$msg = '';
$msgType = 'success';
$rulesReady = externalProductRulesTableReady();
$projects = getProjects(false);

function productRuleProjectLabel(array $rule): string {
    if (!empty($rule['project_name'])) {
        return (string)$rule['project_name'];
    }
    return (string)($rule['project_key'] ?? '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $rulesReady) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $msg = '不正なリクエストです。';
        $msgType = 'error';
    } else {
        $action = (string)($_POST['action'] ?? '');
        if (in_array($action, ['create', 'update'], true)) {
            $sourceSystemKey = preg_replace('/[^A-Za-z0-9_\-]/', '', trim((string)($_POST['source_system_key'] ?? '')));
            $productCode = trim((string)($_POST['product_code'] ?? ''));
            $displayName = trim((string)($_POST['display_name'] ?? ''));
            $projectId = (int)($_POST['project_id'] ?? 0);
            $projectKey = preg_replace('/[^A-Za-z0-9_\-]/', '', trim((string)($_POST['project_key'] ?? '')));
            foreach ($projects as $project) {
                if ((int)$project['id'] === $projectId) {
                    $projectKey = (string)$project['slug'];
                    break;
                }
            }
            $validityDays = trim((string)($_POST['validity_days'] ?? ''));
            $validityDays = $validityDays === '' ? null : max(0, (int)$validityDays);
            $rewardEligibility = normalizeRewardEligibilityStatus($_POST['reward_eligibility'] ?? 'UNKNOWN');
            $entitlementType = trim((string)($_POST['entitlement_type'] ?? ''));
            $targetService = trim((string)($_POST['target_service'] ?? ''));
            $refundPolicy = normalizeRefundPolicy($_POST['refund_policy'] ?? 'manual_review');
            $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
            $description = trim((string)($_POST['description'] ?? ''));

            if ($sourceSystemKey === '' || $productCode === '' || $displayName === '') {
                $msg = '送信元、商品コード、表示名は必須です。';
                $msgType = 'error';
            } else {
                try {
                    if ($action === 'create') {
                        $stmt = $db->prepare("
                            INSERT INTO external_product_rules
                                (source_system_key, product_code, display_name, project_id, project_key, validity_days,
                                 reward_eligibility, entitlement_type, target_service, refund_policy, status, description)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $sourceSystemKey,
                            $productCode,
                            $displayName,
                            $projectId > 0 ? $projectId : null,
                            $projectKey ?: null,
                            $validityDays,
                            $rewardEligibility,
                            $entitlementType ?: null,
                            $targetService ?: null,
                            $refundPolicy,
                            $status,
                            $description ?: null,
                        ]);
                        $msg = '商品ルールを追加しました。';
                    } else {
                        $id = (int)($_POST['id'] ?? 0);
                        $stmt = $db->prepare("
                            UPDATE external_product_rules
                            SET source_system_key=?, product_code=?, display_name=?, project_id=?, project_key=?,
                                validity_days=?, reward_eligibility=?, entitlement_type=?, target_service=?,
                                refund_policy=?, status=?, description=?
                            WHERE id=?
                        ");
                        $stmt->execute([
                            $sourceSystemKey,
                            $productCode,
                            $displayName,
                            $projectId > 0 ? $projectId : null,
                            $projectKey ?: null,
                            $validityDays,
                            $rewardEligibility,
                            $entitlementType ?: null,
                            $targetService ?: null,
                            $refundPolicy,
                            $status,
                            $description ?: null,
                            $id,
                        ]);
                        $msg = '商品ルールを更新しました。';
                    }
                } catch (PDOException $e) {
                    $msg = '保存に失敗しました。同じ送信元と商品コードが登録済みの可能性があります。';
                    $msgType = 'error';
                }
            }
        } elseif ($action === 'toggle') {
            $id = (int)($_POST['id'] ?? 0);
            $db->prepare("UPDATE external_product_rules SET status=IF(status='active','inactive','active') WHERE id=?")->execute([$id]);
            $msg = '状態を変更しました。';
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $count = tableHasColumn('integration_inbox_events', 'product_rule_id')
                ? (int)$db->query("SELECT COUNT(*) FROM integration_inbox_events WHERE product_rule_id={$id}")->fetchColumn()
                : 0;
            if ($count > 0) {
                $msg = '受信済みイベントで使われているため削除できません。停止にしてください。';
                $msgType = 'error';
            } else {
                $db->prepare("DELETE FROM external_product_rules WHERE id=?")->execute([$id]);
                $msg = '商品ルールを削除しました。';
            }
        }
    }
}

$editId = (int)($_GET['edit'] ?? 0);
$editRule = null;
if ($rulesReady && $editId > 0) {
    $stmt = $db->prepare("SELECT * FROM external_product_rules WHERE id=? LIMIT 1");
    $stmt->execute([$editId]);
    $editRule = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$rules = [];
if ($rulesReady) {
    $rules = $db->query("
        SELECT epr.*, p.name AS project_name
        FROM external_product_rules epr
        LEFT JOIN projects p ON epr.project_id = p.id
        ORDER BY epr.status ASC, epr.source_system_key ASC, epr.product_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
}
?>

<?php if ($msg): ?>
  <div class="alert alert-<?= h($msgType) ?>"><?= h($msg) ?></div>
<?php endif; ?>

<?php if (!$rulesReady): ?>
  <div class="alert alert-error">商品・報酬ルールのDBマイグレーションが未適用です。アップデート画面でDBマイグレーションを適用してください。</div>
<?php else: ?>
  <div class="card">
    <h3><?= $editRule ? '商品ルールを編集' : '商品ルールを追加' ?></h3>
    <p class="muted">外部サービスから届く商品コードごとに、報酬対象・利用権・返金時の扱いを管理します。未登録の商品は自動で報酬対象にせず停止します。</p>
    <form method="post" class="form-grid">
      <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
      <input type="hidden" name="action" value="<?= $editRule ? 'update' : 'create' ?>">
      <?php if ($editRule): ?><input type="hidden" name="id" value="<?= (int)$editRule['id'] ?>"><?php endif; ?>

      <label>送信元システムキー *
        <input type="text" name="source_system_key" value="<?= h($editRule['source_system_key'] ?? '') ?>" placeholder="SENGOKU_SHOPPING" required>
      </label>
      <label>商品コード *
        <input type="text" name="product_code" value="<?= h($editRule['product_code'] ?? '') ?>" placeholder="product-basic" required>
      </label>
      <label>表示名 *
        <input type="text" name="display_name" value="<?= h($editRule['display_name'] ?? '') ?>" placeholder="商品名" required>
      </label>
      <label>プロジェクト
        <select name="project_id">
          <option value="0">未指定</option>
          <?php foreach ($projects as $project): ?>
            <option value="<?= (int)$project['id'] ?>" <?= (int)($editRule['project_id'] ?? 0) === (int)$project['id'] ? 'selected' : '' ?>>
              <?= h($project['name']) ?> / <?= h($project['slug']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>project_key
        <input type="text" name="project_key" value="<?= h($editRule['project_key'] ?? '') ?>" placeholder="プロジェクト未指定時のみ">
      </label>
      <label>有効期間（日）
        <input type="number" name="validity_days" min="0" value="<?= h((string)($editRule['validity_days'] ?? '')) ?>" placeholder="空欄なら期限なし">
      </label>
      <label>報酬対象区分
        <select name="reward_eligibility">
          <?php foreach (['UNKNOWN' => '未判定', 'ELIGIBLE' => '報酬対象', 'NOT_ELIGIBLE' => '報酬対象外'] as $value => $label): ?>
            <option value="<?= h($value) ?>" <?= ($editRule['reward_eligibility'] ?? 'UNKNOWN') === $value ? 'selected' : '' ?>><?= h($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>利用権種別
        <input type="text" name="entitlement_type" value="<?= h($editRule['entitlement_type'] ?? '') ?>" placeholder="course_access / membership など">
      </label>
      <label>対象サービス
        <input type="text" name="target_service" value="<?= h($editRule['target_service'] ?? '') ?>" placeholder="AI_ART_SCHOOL など">
      </label>
      <label>返金時の扱い
        <select name="refund_policy">
          <?php foreach (['manual_review' => '手動確認', 'revoke_entitlement' => '利用権取消', 'keep_entitlement' => '利用権維持', 'none' => '処理なし'] as $value => $label): ?>
            <option value="<?= h($value) ?>" <?= ($editRule['refund_policy'] ?? 'manual_review') === $value ? 'selected' : '' ?>><?= h($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>状態
        <select name="status">
          <option value="active" <?= ($editRule['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>有効</option>
          <option value="inactive" <?= ($editRule['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>停止</option>
        </select>
      </label>
      <label class="span-all">説明
        <textarea name="description" rows="3" placeholder="報酬対象外の理由、運用メモなど"><?= h($editRule['description'] ?? '') ?></textarea>
      </label>
      <div class="span-all" style="display:flex;gap:.5rem;flex-wrap:wrap;">
        <button class="btn btn-primary"><?= $editRule ? '更新する' : '追加する' ?></button>
        <?php if ($editRule): ?><a class="btn btn-outline" href="/admin/product_rules.php">キャンセル</a><?php endif; ?>
      </div>
    </form>
  </div>

  <div class="card">
    <h3>商品ルール一覧</h3>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>状態</th>
            <th>送信元</th>
            <th>商品コード</th>
            <th>表示名</th>
            <th>プロジェクト</th>
            <th>報酬</th>
            <th>利用権</th>
            <th>返金</th>
            <th>操作</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rules as $rule): ?>
            <tr>
              <td><span class="badge badge-<?= $rule['status'] === 'active' ? 'active' : 'inactive' ?>"><?= $rule['status'] === 'active' ? '有効' : '停止' ?></span></td>
              <td><code><?= h($rule['source_system_key']) ?></code></td>
              <td><code><?= h($rule['product_code']) ?></code></td>
              <td><strong><?= h($rule['display_name']) ?></strong></td>
              <td><?= h(productRuleProjectLabel($rule)) ?: '-' ?></td>
              <td><?= h($rule['reward_eligibility']) ?></td>
              <td><?= h($rule['entitlement_type'] ?: '-') ?></td>
              <td><?= h($rule['refund_policy']) ?></td>
              <td style="white-space:nowrap;">
                <a class="btn btn-outline btn-sm" href="/admin/product_rules.php?edit=<?= (int)$rule['id'] ?>">編集</a>
                <form method="post" style="display:inline;">
                  <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?= (int)$rule['id'] ?>">
                  <button class="btn btn-outline btn-sm"><?= $rule['status'] === 'active' ? '停止' : '有効化' ?></button>
                </form>
                <form method="post" style="display:inline;" onsubmit="return confirm('この商品ルールを削除しますか？');">
                  <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$rule['id'] ?>">
                  <button class="btn btn-danger btn-sm">削除</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($rules)): ?>
            <tr><td colspan="9" class="muted">商品ルールはまだ登録されていません。</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<style>
.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 1rem;
}
.span-all {
  grid-column: 1 / -1;
}
.muted {
  color: var(--text-muted, #777);
}
</style>

<?php require_once __DIR__ . '/footer.php'; ?>

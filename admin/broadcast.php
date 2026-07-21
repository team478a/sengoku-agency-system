<?php
$pageTitle = '一斉メール送信';
require_once __DIR__ . '/header.php';

$db      = getDB();
$csrf    = getCsrfToken();
$message = '';
$msgType = 'success';
$sentCount = 0;

// 送信処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = '不正なリクエストです。'; $msgType = 'error';
    } else {
        $subject   = trim($_POST['subject'] ?? '');
        $body      = trim($_POST['body']    ?? '');
        $targetIds = $_POST['agent_ids']    ?? [];
        $sendAll   = isset($_POST['send_all']);

        if (!$subject || !$body) {
            $message = '件名と本文は必須です。'; $msgType = 'error';
        } else {
            require_once __DIR__ . '/../includes/mailer.php';
            $mailer = new Mailer();

            // 送信対象取得
            if ($sendAll) {
                $stmt = $db->query("SELECT id, agent_name, person_name, email FROM agents WHERE status='active'");
            } else {
                if (empty($targetIds)) {
                    $message = '送信先を選択してください。'; $msgType = 'error';
                    goto skip_send;
                }
                $ph   = implode(',', array_fill(0, count($targetIds), '?'));
                $stmt = $db->prepare("SELECT id, agent_name, person_name, email FROM agents WHERE id IN ($ph) AND status='active'");
                $stmt->execute(array_map('intval', $targetIds));
            }
            $agents = $stmt->fetchAll();

            $errors = [];
            foreach ($agents as $ag) {
                // {person_name}等の変数置換
                $subj = str_replace(['{person_name}','{agent_name}'], [$ag['person_name'],$ag['agent_name']], $subject);
                $bd   = str_replace(['{person_name}','{agent_name}'], [$ag['person_name'],$ag['agent_name']], $body);

                $html = '<!DOCTYPE html><html lang="ja"><head><meta charset="UTF-8"></head><body style="font-family:sans-serif;color:#1a1410;padding:2rem;max-width:560px;margin:0 auto;">
                    <div style="background:linear-gradient(135deg,#13100D,#1a1510);padding:1.25rem 2rem;border-radius:6px 6px 0 0;text-align:center;">
                        <p style="font-family:serif;font-size:1rem;font-weight:700;color:#E2C87A;letter-spacing:.1em;margin:0;">⚔ 戦国経済圏</p>
                    </div>
                    <div style="background:#fff;border:1px solid #e5e7eb;border-top:none;border-radius:0 0 6px 6px;padding:2rem;">
                        <h2 style="font-size:1rem;color:#1a1410;margin:0 0 1.5rem;padding-bottom:.75rem;border-bottom:2px solid #C9A84C;">' . htmlspecialchars($subj) . '</h2>
                        <p style="white-space:pre-line;line-height:1.9;font-size:.9rem;">' . nl2br(htmlspecialchars($bd)) . '</p>
                    </div>
                    <p style="text-align:center;font-size:.72rem;color:#9ca3af;margin-top:1rem;">© 戦国経済圏</p>
                </body></html>';

                if ($mailer->send($ag['email'], $subj, $html)) {
                    $sentCount++;
                } else {
                    $errors[] = $ag['email'];
                }
            }

            $message = $sentCount . '件送信完了。';
            if ($errors) $message .= '　失敗：' . implode(', ', $errors);
            $msgType = $errors ? 'error' : 'success';
        }
    }
    skip_send:;
}

// アドバイザー一覧
$agents = $db->query("SELECT id, agent_name, person_name, email FROM agents WHERE status='active' ORDER BY agent_name")->fetchAll();
?>

<?php if ($message): ?><div class="alert alert-<?= $msgType ?>"><?= h($message) ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 300px;gap:1.25rem;align-items:start;">

<div class="card">
    <p class="card-title">✉️ 一斉メール送信</p>
    <p style="font-size:.82rem;color:var(--text-muted);margin-bottom:1.25rem;line-height:1.8;">
        <code style="background:rgba(201,168,76,.1);padding:.1rem .4rem;border-radius:2px;">{person_name}</code>
        <code style="background:rgba(201,168,76,.1);padding:.1rem .4rem;border-radius:2px;margin-left:.3rem;">{agent_name}</code>
        は各アドバイザーの情報に自動置換されます。
    </p>
    <form method="post" id="broadcastForm">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="send">

        <!-- 送信先 -->
        <div style="background:rgba(201,168,76,.06);border:1px solid rgba(201,168,76,.15);border-radius:4px;padding:1rem 1.25rem;margin-bottom:1.25rem;">
            <p style="font-size:.78rem;color:var(--gold);font-weight:700;margin-bottom:.75rem;">送信先</p>
            <label class="form-check" style="margin-bottom:.75rem;">
                <input type="checkbox" name="send_all" id="sendAll" onchange="toggleAgentSelect()">
                <span style="font-weight:700;">全アドバイザーに送信（<?= count($agents) ?>名）</span>
            </label>
            <div id="agentSelect" style="max-height:180px;overflow-y:auto;border:1px solid var(--border);border-radius:3px;padding:.75rem;background:rgba(255,255,255,.03);margin-top:.5rem;">
                <?php foreach ($agents as $ag): ?>
                <label class="form-check" style="margin-bottom:.4rem;">
                    <input type="checkbox" name="agent_ids[]" value="<?= $ag['id'] ?>" class="agent-cb">
                    <?= h($ag['person_name']) ?>（<?= h($ag['agent_name']) ?>）
                    <span style="font-size:.72rem;color:var(--text-muted);">— <?= h($ag['email']) ?></span>
                </label>
                <?php endforeach; ?>
            </div>
            <div style="display:flex;gap:.5rem;margin-top:.5rem;">
                <button type="button" onclick="selectAll(true)" class="btn btn-outline" style="font-size:.75rem;padding:.3rem .7rem;">全選択</button>
                <button type="button" onclick="selectAll(false)" class="btn btn-outline" style="font-size:.75rem;padding:.3rem .7rem;">全解除</button>
                <span id="selectedCount" style="font-size:.75rem;color:var(--text-muted);line-height:2;">0名選択中</span>
            </div>
        </div>

        <div class="form-group">
            <label>件名 *</label>
            <input type="text" name="subject" required placeholder="【戦国経済圏】新しいテンプレートが追加されました">
        </div>
        <div class="form-group">
            <label>本文 *</label>
            <textarea name="body" rows="10" required placeholder="{person_name} 様&#10;&#10;いつもお世話になっております。&#10;戦国経済圏 運営事務局です。&#10;&#10;..."></textarea>
        </div>

        <button type="submit" class="btn btn-gold"
            onclick="return confirm(document.getElementById('sendAll').checked ? '全アドバイザー（<?= count($agents) ?>名）にメールを送信します。よろしいですか？' : '選択したアドバイザーにメールを送信します。よろしいですか？')">
            送信する
        </button>
    </form>
</div>

<!-- 送信ガイド -->
<div>
    <div class="card">
        <p class="card-title">📋 使い方</p>
        <ul style="list-style:none;font-size:.82rem;line-height:2;color:var(--text-muted);">
            <li>✓ 全アドバイザー or 個別選択で送信</li>
            <li>✓ 変数で名前を自動差し込み</li>
            <li>✓ HTMLメールで送信される</li>
            <li>✓ 送信はResend経由</li>
        </ul>
    </div>
    <div class="card" style="margin-top:.75rem;">
        <p class="card-title">🔤 使える変数</p>
        <table style="font-size:.78rem;">
            <tr><td style="padding:.35rem .5rem;color:var(--gold);"><code>{person_name}</code></td><td style="padding:.35rem .5rem;color:var(--text-muted);">担当者名</td></tr>
            <tr><td style="padding:.35rem .5rem;color:var(--gold);"><code>{agent_name}</code></td><td style="padding:.35rem .5rem;color:var(--text-muted);">アドバイザー名</td></tr>
        </table>
    </div>
</div>

</div>

<script>
function toggleAgentSelect() {
    const all = document.getElementById('sendAll').checked;
    document.getElementById('agentSelect').style.opacity = all ? '.4' : '1';
    document.getElementById('agentSelect').style.pointerEvents = all ? 'none' : '';
    updateCount();
}
function selectAll(v) {
    document.querySelectorAll('.agent-cb').forEach(cb => cb.checked = v);
    updateCount();
}
function updateCount() {
    const n = document.querySelectorAll('.agent-cb:checked').length;
    document.getElementById('selectedCount').textContent = n + '名選択中';
}
document.querySelectorAll('.agent-cb').forEach(cb => cb.addEventListener('change', updateCount));
updateCount();
</script>

<?php require_once __DIR__ . '/footer.php'; ?>

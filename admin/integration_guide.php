<?php
$pageTitle = '外部連携ガイド';
require_once __DIR__ . '/header.php';
?>

<style>
.guide-hero {
    border: 1px solid var(--border);
    background: var(--card);
    border-radius: 6px;
    padding: 1.4rem;
    margin-bottom: 1.25rem;
}
.guide-hero h2 {
    margin: 0 0 .6rem;
    font-size: 1.35rem;
}
.guide-lead {
    color: var(--text-muted);
    line-height: 1.8;
    margin: 0;
}
.guide-actions {
    display: flex;
    flex-wrap: wrap;
    gap: .65rem;
    margin-top: 1rem;
}
.guide-section {
    border: 1px solid var(--border);
    background: var(--card);
    border-radius: 6px;
    padding: 1.25rem;
    margin-bottom: 1.25rem;
}
.guide-section h3 {
    margin: 0 0 .9rem;
    font-size: 1.1rem;
}
.guide-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: .9rem;
}
.guide-card {
    border: 1px solid var(--border);
    background: var(--bg-soft);
    border-radius: 6px;
    padding: 1rem;
}
.guide-card h4 {
    margin: 0 0 .55rem;
    font-size: 1rem;
}
.guide-card p,
.guide-card li {
    color: var(--text-muted);
    line-height: 1.75;
}
.guide-card p {
    margin: 0;
}
.guide-card ul {
    margin: .35rem 0 0;
    padding-left: 1.2rem;
}
.guide-flow {
    display: grid;
    gap: .75rem;
}
.guide-step {
    display: grid;
    grid-template-columns: 3.5rem 1fr;
    gap: .8rem;
    align-items: stretch;
}
.guide-step-no {
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--accent);
    background: rgba(206, 164, 61, .14);
    color: var(--accent);
    border-radius: 6px;
    font-weight: 700;
    font-size: 1.05rem;
}
.guide-step-body {
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: .9rem 1rem;
    background: var(--bg-soft);
}
.guide-step-body strong {
    display: block;
    margin-bottom: .35rem;
}
.guide-step-body p {
    margin: 0;
    color: var(--text-muted);
    line-height: 1.75;
}
.guide-arrow {
    color: var(--text-muted);
    padding-left: 1.45rem;
    line-height: 1;
}
.guide-key-table {
    width: 100%;
    border-collapse: collapse;
}
.guide-key-table th,
.guide-key-table td {
    border-bottom: 1px solid var(--border);
    padding: .85rem;
    vertical-align: top;
}
.guide-key-table th {
    background: var(--bg-soft);
    text-align: left;
}
.guide-key-table td {
    color: var(--text-muted);
    line-height: 1.7;
}
.guide-checklist {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
    gap: .6rem;
}
.guide-checkitem {
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: .75rem .85rem;
    background: var(--bg-soft);
    color: var(--text-muted);
    line-height: 1.65;
}
.guide-code {
    display: block;
    margin-top: .35rem;
    padding: .55rem .65rem;
    background: rgba(0, 0, 0, .06);
    border: 1px solid var(--border);
    border-radius: 4px;
    color: var(--paper);
    overflow-x: auto;
    white-space: nowrap;
}
@media (max-width: 720px) {
    .guide-step {
        grid-template-columns: 1fr;
    }
    .guide-step-no {
        min-height: 2.4rem;
    }
    .guide-arrow {
        display: none;
    }
}
</style>

<div class="guide-hero">
    <h2>外部連携を始める前に見るページ</h2>
    <p class="guide-lead">
        このページは、sengoku-ai.comを代理店情報の中心として、戦国パスポート、AIアート教室、ショッピングカートなどの外部システムと連携するための手順書です。
        最初に何を決めるか、次にどの画面で設定するか、開発者へ何を渡すかを順番に確認できます。
    </p>
    <div class="guide-actions">
        <a class="btn" href="/admin/external_partners.php">外部API連携を設定</a>
        <a class="btn btn-outline" href="/admin/sso_settings.php">SSO連携を設定</a>
        <a class="btn btn-outline" href="/admin/integration_logs.php">連携ログを見る</a>
        <a class="btn btn-outline" href="/admin/integration_outbox.php">送信待ちを見る</a>
    </div>
</div>

<div class="guide-section">
    <h3>まず決めること</h3>
    <div class="guide-grid">
        <div class="guide-card">
            <h4>1. 何を連携するか</h4>
            <ul>
                <li>代理店の登録・更新・停止・削除</li>
                <li>代理店の親子関係、紹介者、共通ID</li>
                <li>外部ポータルへのSSOログイン</li>
                <li>商品・プロジェクト別のLPや成果情報</li>
            </ul>
        </div>
        <div class="guide-card">
            <h4>2. どちら向きに通信するか</h4>
            <ul>
                <li>外部システムからsengoku-ai.comへ登録する</li>
                <li>sengoku-ai.comから外部システムへ通知する</li>
                <li>外部システムが階層情報を取得する</li>
                <li>sengoku-ai.comからSSOで外部ポータルへログインする</li>
            </ul>
        </div>
        <div class="guide-card">
            <h4>3. 誰がAPIキーを発行するか</h4>
            <p>
                APIキーは原則、接続先ごとに分けます。sengoku-ai.comを呼び出すキーと、外部システムを呼び出すキーは別物として管理します。
            </p>
        </div>
    </div>
</div>

<div class="guide-section">
    <h3>ステップ別フロー</h3>
    <div class="guide-flow">
        <div class="guide-step">
            <div class="guide-step-no">STEP 1</div>
            <div class="guide-step-body">
                <strong>連携先システムを決める</strong>
                <p>例: 戦国パスポート、AIアート教室、ショッピングカート。連携先名、ドメイン、担当者、利用目的を整理します。</p>
            </div>
        </div>
        <div class="guide-arrow">↓</div>
        <div class="guide-step">
            <div class="guide-step-no">STEP 2</div>
            <div class="guide-step-body">
                <strong>連携方式を選ぶ</strong>
                <p>登録・更新を受け取るだけか、sengoku-ai.comから外部へ送信するか、SSOログインも使うかを決めます。</p>
            </div>
        </div>
        <div class="guide-arrow">↓</div>
        <div class="guide-step">
            <div class="guide-step-no">STEP 3</div>
            <div class="guide-step-body">
                <strong>外部API連携画面に連携先を登録する</strong>
                <p>「サイトキー」「連携先名」「送信先URL」「連携先の受信用APIキー」を登録します。送信先URLはドメインだけでも入力できます。独自エンドポイントがある場合はフルURLを入力します。</p>
            </div>
        </div>
        <div class="guide-arrow">↓</div>
        <div class="guide-step">
            <div class="guide-step-no">STEP 4</div>
            <div class="guide-step-body">
                <strong>sengoku-ai.com発行の受信用APIキーを相手に渡す</strong>
                <p>外部システムからsengoku-ai.comへ代理店を登録・更新する場合に使います。接続先ごとに発行し、使い回さない運用を推奨します。</p>
            </div>
        </div>
        <div class="guide-arrow">↓</div>
        <div class="guide-step">
            <div class="guide-step-no">STEP 5</div>
            <div class="guide-step-body">
                <strong>SSOが必要ならSSO連携も登録する</strong>
                <p>外部ポータルへログイン連携する場合は、SSO連携画面でサイトキー、aud、SSO受信URL、状態を設定します。</p>
            </div>
        </div>
        <div class="guide-arrow">↓</div>
        <div class="guide-step">
            <div class="guide-step-no">STEP 6</div>
            <div class="guide-step-body">
                <strong>接続テストとログ確認を行う</strong>
                <p>外部API連携画面の接続テスト、連携ログ、Outboxを確認します。失敗した場合はURL、APIキー、認証ヘッダー、レスポンス内容を確認します。</p>
            </div>
        </div>
        <div class="guide-arrow">↓</div>
        <div class="guide-step">
            <div class="guide-step-no">STEP 7</div>
            <div class="guide-step-body">
                <strong>本番運用を開始する</strong>
                <p>承認、登録、更新、停止、削除、SSOログインなどのイベントが想定通り流れているかを運用チェックで定期確認します。</p>
            </div>
        </div>
    </div>
</div>

<div class="guide-section">
    <h3>APIキーの考え方</h3>
    <div style="overflow-x:auto;">
        <table class="guide-key-table">
            <thead>
                <tr>
                    <th>キーの種類</th>
                    <th>使う場面</th>
                    <th>発行する側</th>
                    <th>設定する場所</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>sengoku-ai.com受信用APIキー</strong></td>
                    <td>外部システムがsengoku-ai.comへ代理店登録・更新をPOSTする時に使います。</td>
                    <td>sengoku-ai.com</td>
                    <td>外部API連携画面で接続先ごとに発行し、外部開発者へ渡します。</td>
                </tr>
                <tr>
                    <td><strong>連携先システム受信用APIキー</strong></td>
                    <td>sengoku-ai.comが外部システムへ承認・登録・更新などを送信する時に使います。</td>
                    <td>連携先システム</td>
                    <td>外部API連携画面の「連携先の受信用APIキー」に登録します。</td>
                </tr>
                <tr>
                    <td><strong>SSO署名鍵</strong></td>
                    <td>sengoku-ai.comが発行したSSO用JWTを連携先が検証する時に使います。</td>
                    <td>sengoku-ai.com</td>
                    <td>SSO連携画面で管理します。秘密鍵は外部へ渡さず、連携先はJWKSまたは公開鍵で検証します。</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="guide-section">
    <h3>外部開発者へ渡す情報</h3>
    <div class="guide-grid">
        <div class="guide-card">
            <h4>代理店同期API</h4>
            <p>外部システムからsengoku-ai.comへ代理店情報を登録・更新するAPIです。</p>
            <code class="guide-code">POST https://sengoku-ai.com/api/integrations/agencies</code>
        </div>
        <div class="guide-card">
            <h4>階層取得API</h4>
            <p>外部システムが代理店階層を取得するAPIです。format=flat/tree、root_code、include_contact=1を必要に応じて使います。</p>
            <code class="guide-code">GET https://sengoku-ai.com/api/hierarchy.php?format=tree</code>
        </div>
        <div class="guide-card">
            <h4>SSO起動URL</h4>
            <p>sengoku-ai.comにログイン済みの代理店が、外部ポータルへSSOで遷移するためのURLです。</p>
            <code class="guide-code">https://sengoku-ai.com/agent/sso_launch.php?client=サイトキー</code>
        </div>
    </div>
</div>

<div class="guide-section">
    <h3>接続前チェックリスト</h3>
    <div class="guide-checklist">
        <div class="guide-checkitem">連携先の正式名称とサイトキーを決めた</div>
        <div class="guide-checkitem">送信先URLまたはAPIエンドポイントを確認した</div>
        <div class="guide-checkitem">sengoku-ai.com受信用APIキーを接続先ごとに発行した</div>
        <div class="guide-checkitem">連携先が発行した受信用APIキーを登録した</div>
        <div class="guide-checkitem">代理店の一意キー、親子関係、メール、共通IDの扱いを確認した</div>
        <div class="guide-checkitem">SSOが必要な場合、audとSSO受信URLを登録した</div>
        <div class="guide-checkitem">接続テストを実行した</div>
        <div class="guide-checkitem">連携ログとOutboxの確認場所を運用担当者が把握した</div>
    </div>
</div>

<div class="guide-section">
    <h3>よくある流れ</h3>
    <div class="guide-grid">
        <div class="guide-card">
            <h4>戦国パスポートへログインさせたい</h4>
            <p>SSO連携を登録し、代理店マイページの外部ポータル連携から起動します。必要に応じて外部API連携も登録し、代理店情報の承認・更新を送信します。</p>
        </div>
        <div class="guide-card">
            <h4>AIアート教室で新規登録された人を紐づけたい</h4>
            <p>AIアート教室側から代理店同期APIへPOSTします。紹介者コード、親コード、共通ID候補、メールを送ることで、sengoku-ai.com側で代理店階層と紐づけます。</p>
        </div>
        <div class="guide-card">
            <h4>外部システムが代理店階層を参照したい</h4>
            <p>階層取得APIを使います。全体を見る場合はformat=tree、特定代理店配下だけを見る場合はroot_codeを指定します。</p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>

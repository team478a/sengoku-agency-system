<?php
$pageTitle = '外部連携ガイド';
require_once __DIR__ . '/header.php';
?>

<style>
.guide-hero,
.guide-section {
    border: 1px solid var(--border);
    background: var(--card);
    border-radius: 6px;
    padding: 1.25rem;
    margin-bottom: 1.25rem;
}
.guide-hero h2,
.guide-section h3 {
    margin: 0 0 .8rem;
}
.guide-lead,
.guide-section p,
.guide-section li {
    color: var(--text-muted);
    line-height: 1.8;
}
.guide-actions,
.guide-grid {
    display: flex;
    flex-wrap: wrap;
    gap: .7rem;
}
.guide-actions {
    margin-top: 1rem;
}
.guide-card {
    flex: 1 1 260px;
    border: 1px solid var(--border);
    background: var(--bg-soft);
    border-radius: 6px;
    padding: 1rem;
}
.guide-card h4 {
    margin: 0 0 .45rem;
}
.guide-flow {
    display: grid;
    gap: .75rem;
}
.guide-step {
    display: grid;
    grid-template-columns: 3.2rem 1fr;
    gap: .8rem;
    align-items: stretch;
}
.guide-step-no {
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    background: var(--accent);
    color: #fff;
    font-weight: 700;
}
.guide-step-body {
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: .85rem 1rem;
    background: var(--bg);
}
.guide-step-body strong {
    display: block;
    margin-bottom: .25rem;
}
.guide-table-wrap {
    overflow-x: auto;
}
.guide-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 760px;
}
.guide-table th,
.guide-table td {
    border-bottom: 1px solid var(--border);
    padding: .8rem;
    text-align: left;
    vertical-align: top;
}
.guide-table th {
    background: var(--bg-soft);
}
.guide-code {
    display: inline-block;
    padding: .12rem .35rem;
    border-radius: 4px;
    background: var(--bg-soft);
    font-family: ui-monospace, SFMono-Regular, Consolas, monospace;
    font-size: .92em;
}
@media (max-width: 720px) {
    .guide-step {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="guide-hero">
    <h2>外部連携の進め方</h2>
    <p class="guide-lead">
        戦国パスポート、ショッピングサイト、AIアート教室など外部サービスと代理店情報を連携するための入口です。
        まず連携先を1つ登録し、その連携先ごとにAPIキーと送信先を設定します。商品やLPの違いは
        <span class="guide-code">project_key</span> で分けます。
    </p>
    <div class="guide-actions">
        <a class="btn" href="external_partner_wizard.php">連携セットアップを開く</a>
        <a class="btn secondary" href="projects.php">project_keyを確認</a>
        <a class="btn secondary" href="external_partners.php">詳細設定を開く</a>
        <a class="btn secondary" href="sso_settings.php">SSO連携を設定</a>
        <a class="btn secondary" href="integration_logs.php">連携ログを見る</a>
        <a class="btn secondary" href="integration_outbox.php">送信待ちを見る</a>
    </div>
    <div class="guide-actions">
        <a class="btn secondary" href="developer_docs_download.php?doc=handoff">外部開発者向けMD</a>
        <a class="btn secondary" href="developer_docs_download.php?doc=setup-flow">セットアップ手順MD</a>
        <a class="btn secondary" href="developer_docs_download.php?doc=full-guide">詳細仕様MD</a>
    </div>
</div>

<div class="guide-section">
    <h3>最初に行うこと</h3>
    <div class="guide-flow">
        <div class="guide-step">
            <div class="guide-step-no">1</div>
            <div class="guide-step-body">
                <strong>連携先サービスを決める</strong>
                <span>例：戦国パスポート、ショッピングサイト、AIアート教室。1サービスにつき1件登録します。</span>
            </div>
        </div>
        <div class="guide-step">
            <div class="guide-step-no">2</div>
            <div class="guide-step-body">
                <strong>使うプロジェクトを確認する</strong>
                <span>商品やLPを分けたい場合は <span class="guide-code">project_key</span> を使います。値はプロジェクト管理のスラッグです。</span>
            </div>
        </div>
        <div class="guide-step">
            <div class="guide-step-no">3</div>
            <div class="guide-step-body">
                <strong>連携ウィザードで登録する</strong>
                <span>連携先名、送信先URL、連携先から発行された受信用APIキーを登録します。細かい調整は詳細設定で行います。</span>
            </div>
        </div>
        <div class="guide-step">
            <div class="guide-step-no">4</div>
            <div class="guide-step-body">
                <strong>代理店システムが発行したAPIキーを外部開発者へ渡す</strong>
                <span>外部サービスが sengoku-ai.com に送信するときに使うキーです。ウィザードの完了画面で、APIキーと主要API URLを確認できます。</span>
            </div>
        </div>
        <div class="guide-step">
            <div class="guide-step-no">5</div>
            <div class="guide-step-body">
                <strong>外部サービス側でAPIを実装する</strong>
                <span>紹介流入、登録確定、購入確定、共通顧客ID解決など、必要なAPIだけ実装します。</span>
            </div>
        </div>
        <div class="guide-step">
            <div class="guide-step-no">6</div>
            <div class="guide-step-body">
                <strong>必要ならSSOを登録する</strong>
                <span>代理店システムにログインした人を外部ポータルへ遷移させる場合に使います。</span>
            </div>
        </div>
        <div class="guide-step">
            <div class="guide-step-no">7</div>
            <div class="guide-step-body">
                <strong>接続テストを行う</strong>
                <span>外部API連携画面の接続テストと、連携ログで送受信結果を確認します。</span>
            </div>
        </div>
    </div>
</div>

<div class="guide-section">
    <h3>APIキーの考え方</h3>
    <div class="guide-table-wrap">
        <table class="guide-table">
            <thead>
                <tr>
                    <th>キー</th>
                    <th>発行元</th>
                    <th>使う場面</th>
                    <th>管理場所</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>代理店システムが発行するキー</td>
                    <td>sengoku-ai.com</td>
                    <td>外部サービスが sengoku-ai.com のAPIを呼ぶとき</td>
                    <td>連携セットアップ / 外部API連携の編集画面</td>
                </tr>
                <tr>
                    <td>連携先が発行するキー</td>
                    <td>外部サービス</td>
                    <td>sengoku-ai.com から外部サービスへWebhookを送るとき</td>
                    <td>外部API連携の送信先設定</td>
                </tr>
            </tbody>
        </table>
    </div>
    <p>商品が増えても、同じ外部サービスならAPIキーは1つで運用できます。商品や案件の区別は <span class="guide-code">project_key</span> で行います。</p>
</div>

<div class="guide-section">
    <h3>外部開発者へ渡すもの</h3>
    <div class="guide-table-wrap">
        <table class="guide-table">
            <thead>
                <tr>
                    <th>項目</th>
                    <th>内容</th>
                    <th>確認場所</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>ベースURL</td>
                    <td><span class="guide-code">https://sengoku-ai.com</span></td>
                    <td>固定</td>
                </tr>
                <tr>
                    <td>site_key</td>
                    <td>外部サービスを識別するキー。例：<span class="guide-code">sengoku-passport</span></td>
                    <td>連携セットアップ / 外部API連携</td>
                </tr>
                <tr>
                    <td>APIキー</td>
                    <td>外部サービスが代理店システムへ送信するときに使うキー</td>
                    <td>連携セットアップ完了画面 / 外部API連携の編集画面</td>
                </tr>
                <tr>
                    <td>project_key</td>
                    <td>商品、LP、案件を分けるキー。プロジェクト管理のスラッグと同じ値です。</td>
                    <td>プロジェクト管理</td>
                </tr>
                <tr>
                    <td>API URL一覧</td>
                    <td>共通顧客ID、紹介流入、登録確定、代理店同期、共通イベントなど</td>
                    <td>連携セットアップ完了画面 / このページの「主に使うAPI」</td>
                </tr>
                <tr>
                    <td>SSO情報</td>
                    <td>外部ポータルへログイン連携する場合のみ必要です。</td>
                    <td>SSO連携</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="guide-section">
    <h3>主に使うAPI</h3>
    <div class="guide-table-wrap">
        <table class="guide-table">
            <thead>
                <tr>
                    <th>用途</th>
                    <th>エンドポイント</th>
                    <th>補足</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>共通顧客IDの解決</td>
                    <td><span class="guide-code">POST /api/common-users/resolve</span></td>
                    <td><span class="guide-code">/api/v2/...</span> ではなく、このパスを正式とします。</td>
                </tr>
                <tr>
                    <td>紹介流入の記録</td>
                    <td><span class="guide-code">POST /api/referrals/capture</span></td>
                    <td>紹介URLから来たユーザーを記録し、<span class="guide-code">session_key</span> を返します。</td>
                </tr>
                <tr>
                    <td>登録・購入の確定</td>
                    <td><span class="guide-code">POST /api/referrals/confirm</span></td>
                    <td><span class="guide-code">session_key</span> と <span class="guide-code">project_key</span> を送ります。購入情報がある場合は利用権限も保存されます。</td>
                </tr>
                <tr>
                    <td>購入後の利用権限付与</td>
                    <td><span class="guide-code">POST /api/integrations/events</span></td>
                    <td><span class="guide-code">purchase.completed</span>、<span class="guide-code">payment.succeeded</span>、<span class="guide-code">entitlement.granted</span> などで、顧客が使える商品を記録します。</td>
                </tr>
                <tr>
                    <td>顧客プロフィール確認</td>
                    <td><span class="guide-code">GET /api/common-users/{common_user_id}</span></td>
                    <td>共通顧客、紐づく外部アカウント、代理店紐づけ、利用権限を確認できます。</td>
                </tr>
                <tr>
                    <td>顧客向けSSOトークン発行</td>
                    <td><span class="guide-code">POST /api/sso/customer-token</span></td>
                    <td>外部サービスが顧客をログインさせるための短時間JWTを発行します。署名は既存SSOと同じRS256/JWKSです。</td>
                </tr>
                <tr>
                    <td>代理店階層の取得</td>
                    <td><span class="guide-code">GET /api/hierarchy.php</span></td>
                    <td><span class="guide-code">format=tree</span> または <span class="guide-code">format=flat</span> を指定できます。</td>
                </tr>
                <tr>
                    <td>外部から代理店を登録・更新</td>
                    <td><span class="guide-code">POST /api/integrations/agencies</span></td>
                    <td>外部サービス側で登録されたユーザーを代理店システムへ同期します。</td>
                </tr>
                <tr>
                    <td>外部イベント受信</td>
                    <td><span class="guide-code">POST /api/integrations/events</span></td>
                    <td>購入、登録、会員状態変更、利用権限の付与・停止などのイベントを受け取ります。</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="guide-section">
    <h3>購入後の権限付与で送る主な項目</h3>
    <div class="guide-table-wrap">
        <table class="guide-table">
            <thead>
                <tr>
                    <th>項目</th>
                    <th>意味</th>
                    <th>例</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><span class="guide-code">event</span></td>
                    <td>起きた出来事。購入完了なら <span class="guide-code">purchase.completed</span> を使います。</td>
                    <td><span class="guide-code">purchase.completed</span></td>
                </tr>
                <tr>
                    <td><span class="guide-code">system_key</span></td>
                    <td>連携先サービスのキーです。</td>
                    <td><span class="guide-code">sengoku-passport</span></td>
                </tr>
                <tr>
                    <td><span class="guide-code">common_user_id</span></td>
                    <td>共通顧客IDです。未指定の場合は外部ユーザーIDから探します。</td>
                    <td><span class="guide-code">cusr_...</span></td>
                </tr>
                <tr>
                    <td><span class="guide-code">external_user_id</span></td>
                    <td>外部サービス側のユーザーIDです。</td>
                    <td><span class="guide-code">user_123</span></td>
                </tr>
                <tr>
                    <td><span class="guide-code">project_key</span></td>
                    <td>商品群やLPを分けるキーです。</td>
                    <td><span class="guide-code">sengoku-influencer</span></td>
                </tr>
                <tr>
                    <td><span class="guide-code">product_code</span></td>
                    <td>利用権限を付与する商品コードです。</td>
                    <td><span class="guide-code">passport_monthly</span></td>
                </tr>
                <tr>
                    <td><span class="guide-code">order_id</span></td>
                    <td>注文や決済のIDです。再送時の重複防止にも使います。</td>
                    <td><span class="guide-code">order_1001</span></td>
                </tr>
                <tr>
                    <td><span class="guide-code">entitlement_status</span></td>
                    <td>利用権限の状態です。通常は <span class="guide-code">active</span>、返金時は <span class="guide-code">revoked</span> です。</td>
                    <td><span class="guide-code">active</span></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="guide-section">
    <h3>確認チェックリスト</h3>
    <ul>
        <li>連携先ごとにAPIキーを分けていますか。</li>
        <li>商品やLPの区別に <span class="guide-code">project_key</span> を使っていますか。</li>
        <li>内部IDではなく、代理店コードを外部キーとして使っていますか。</li>
        <li>POST送信では <span class="guide-code">Idempotency-Key</span> を付けていますか。</li>
        <li>購入や申込完了時に <span class="guide-code">product_code</span> と <span class="guide-code">project_key</span> を送っていますか。</li>
        <li>顧客向けSSOを使う場合、SSO連携画面で対象サービスが有効になっていますか。</li>
        <li>接続テスト後に、外部連携ログで成功・失敗を確認しましたか。</li>
    </ul>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>

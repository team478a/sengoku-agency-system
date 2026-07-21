<?php
// $agent が lp.php からインジェクトされている前提
// テンプレート内では h() でエスケープして出力
$csrfToken = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($agent['agent_name']) ?> | 戦国経済圏</title>
<meta name="description" content="戦国経済圏NFT代理店 <?= h($agent['agent_name']) ?> の公式窓口です。">
<style>
:root {
    --gold:    #c9a84c;
    --gold-lt: #e8c96e;
    --ink:     #1a1410;
    --paper:   #f5f0e8;
    --red:     #8b1a1a;
    --smoke:   #2a2320;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'Noto Serif JP', '游明朝', 'Yu Mincho', serif;
    background: var(--ink);
    color: var(--paper);
    line-height: 1.8;
}

/* ── ヒーロー ── */
.hero {
    min-height: 100svh;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    text-align: center;
    padding: 3rem 1.5rem;
    background:
        linear-gradient(170deg, rgba(26,20,16,.85) 0%, rgba(139,26,26,.3) 50%, rgba(26,20,16,.95) 100%),
        repeating-linear-gradient(
            45deg,
            transparent,
            transparent 40px,
            rgba(201,168,76,.04) 40px,
            rgba(201,168,76,.04) 41px
        );
    position: relative;
    overflow: hidden;
}
.hero::before {
    content: '⌘';
    position: absolute;
    font-size: 60vw;
    opacity: .03;
    color: var(--gold);
    pointer-events: none;
    line-height: 1;
}
.kamon {
    font-size: 3.5rem;
    color: var(--gold);
    margin-bottom: 1.5rem;
    animation: rotateIn 1.2s ease-out both;
}
@keyframes rotateIn {
    from { opacity: 0; transform: rotate(-15deg) scale(.8); }
    to   { opacity: 1; transform: rotate(0) scale(1); }
}
.hero-eyebrow {
    font-size: .75rem;
    letter-spacing: .4em;
    color: var(--gold);
    text-transform: uppercase;
    margin-bottom: 1rem;
}
.hero-title {
    font-size: clamp(2rem, 6vw, 4rem);
    font-weight: 700;
    line-height: 1.3;
    margin-bottom: 1.5rem;
    text-shadow: 0 2px 24px rgba(0,0,0,.6);
}
.hero-title span { color: var(--gold-lt); }
.hero-sub {
    font-size: clamp(.95rem, 2vw, 1.15rem);
    color: rgba(245,240,232,.75);
    max-width: 560px;
    margin-bottom: 2.5rem;
}
.btn-primary {
    display: inline-block;
    padding: .9rem 2.5rem;
    background: linear-gradient(135deg, var(--gold), var(--gold-lt));
    color: var(--ink);
    font-weight: 700;
    font-size: 1rem;
    border-radius: 3px;
    text-decoration: none;
    letter-spacing: .05em;
    box-shadow: 0 4px 20px rgba(201,168,76,.4);
    transition: transform .2s, box-shadow .2s;
}
.btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(201,168,76,.5); }
.btn-line {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    padding: .9rem 2.5rem;
    background: #06c755;
    color: #fff;
    font-weight: 700;
    font-size: 1rem;
    border-radius: 3px;
    text-decoration: none;
    margin-left: 1rem;
    transition: transform .2s, opacity .2s;
}
.btn-line:hover { transform: translateY(-2px); opacity: .9; }
.hero-btns { display: flex; flex-wrap: wrap; gap: .75rem; justify-content: center; }

/* ── セクション共通 ── */
.section {
    padding: 5rem 1.5rem;
    max-width: 860px;
    margin: 0 auto;
}
.section-label {
    font-size: .7rem;
    letter-spacing: .5em;
    color: var(--gold);
    text-transform: uppercase;
    margin-bottom: .75rem;
}
.section-title {
    font-size: clamp(1.5rem, 4vw, 2.2rem);
    font-weight: 700;
    margin-bottom: 2rem;
    position: relative;
    padding-bottom: 1rem;
}
.section-title::after {
    content: '';
    position: absolute;
    bottom: 0; left: 0;
    width: 48px; height: 2px;
    background: var(--gold);
}
.divider {
    width: 100%;
    height: 1px;
    background: linear-gradient(90deg, transparent, var(--gold), transparent);
    opacity: .3;
    margin: 0 auto;
}

/* ── 特徴カード ── */
.features {
    background: var(--smoke);
    padding: 5rem 1.5rem;
}
.features-inner { max-width: 860px; margin: 0 auto; }
.cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 1.5rem;
    margin-top: 2.5rem;
}
.card {
    background: rgba(255,255,255,.04);
    border: 1px solid rgba(201,168,76,.2);
    border-radius: 4px;
    padding: 2rem 1.5rem;
    transition: border-color .2s, transform .2s;
}
.card:hover { border-color: var(--gold); transform: translateY(-4px); }
.card-icon { font-size: 2rem; margin-bottom: 1rem; }
.card-title { font-size: 1.05rem; font-weight: 700; color: var(--gold-lt); margin-bottom: .75rem; }
.card-body { font-size: .9rem; color: rgba(245,240,232,.7); }

/* ── 担当者プロフィール ── */
.profile-wrap {
    display: flex;
    gap: 2.5rem;
    align-items: flex-start;
    flex-wrap: wrap;
}
.profile-img {
    width: 160px;
    height: 160px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid var(--gold);
    flex-shrink: 0;
}
.profile-img-placeholder {
    width: 160px;
    height: 160px;
    border-radius: 50%;
    background: rgba(201,168,76,.15);
    border: 3px solid var(--gold);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 4rem;
    flex-shrink: 0;
}
.profile-name { font-size: 1.3rem; font-weight: 700; color: var(--gold-lt); margin-bottom: .25rem; }
.profile-role { font-size: .8rem; letter-spacing: .2em; color: var(--gold); margin-bottom: 1rem; }
.profile-text { font-size: .95rem; color: rgba(245,240,232,.8); line-height: 2; }

/* ── FAQ ── */
.faq { background: var(--smoke); padding: 5rem 1.5rem; }
.faq-inner { max-width: 860px; margin: 0 auto; }
details {
    border-bottom: 1px solid rgba(201,168,76,.2);
    padding: 1.25rem 0;
}
details summary {
    cursor: pointer;
    font-weight: 700;
    list-style: none;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
}
details summary::after {
    content: '＋';
    color: var(--gold);
    font-size: 1.2rem;
    flex-shrink: 0;
    transition: transform .2s;
}
details[open] summary::after { transform: rotate(45deg); }
details p { margin-top: 1rem; font-size: .9rem; color: rgba(245,240,232,.75); }

/* ── 問い合わせフォーム ── */
.contact-section { padding: 5rem 1.5rem; }
.contact-inner { max-width: 640px; margin: 0 auto; }
.form-group { margin-bottom: 1.5rem; }
label {
    display: block;
    font-size: .85rem;
    color: var(--gold);
    margin-bottom: .4rem;
    letter-spacing: .05em;
}
.required { color: var(--red); margin-left: .3rem; }
input[type="text"],
input[type="email"],
input[type="tel"],
textarea {
    width: 100%;
    padding: .85rem 1rem;
    background: rgba(255,255,255,.06);
    border: 1px solid rgba(201,168,76,.3);
    border-radius: 3px;
    color: var(--paper);
    font-family: inherit;
    font-size: .95rem;
    transition: border-color .2s;
}
input:focus, textarea:focus {
    outline: none;
    border-color: var(--gold);
    background: rgba(255,255,255,.08);
}
textarea { resize: vertical; min-height: 140px; }
.btn-submit {
    width: 100%;
    padding: 1rem;
    background: linear-gradient(135deg, var(--gold), var(--gold-lt));
    color: var(--ink);
    font-weight: 700;
    font-size: 1.05rem;
    font-family: inherit;
    border: none;
    border-radius: 3px;
    cursor: pointer;
    letter-spacing: .05em;
    transition: opacity .2s, transform .2s;
}
.btn-submit:hover:not(:disabled) { opacity: .9; transform: translateY(-1px); }
.btn-submit:disabled { opacity: .6; cursor: not-allowed; }
.form-msg {
    margin-top: 1rem;
    padding: .75rem 1rem;
    border-radius: 3px;
    font-size: .9rem;
    display: none;
}
.form-msg.success { background: rgba(6,199,85,.15); border: 1px solid #06c755; color: #06c755; }
.form-msg.error   { background: rgba(139,26,26,.2);  border: 1px solid var(--red); color: #e08080; }
.form-errors { list-style: none; }
.form-errors li::before { content: '✕ '; }

/* ── フッター ── */
footer {
    text-align: center;
    padding: 2.5rem 1.5rem;
    font-size: .8rem;
    color: rgba(245,240,232,.35);
    border-top: 1px solid rgba(201,168,76,.1);
}
footer a { color: var(--gold); text-decoration: none; }

/* ── レスポンシブ ── */
@media (max-width: 600px) {
    .btn-line { margin-left: 0; }
    .profile-wrap { flex-direction: column; align-items: center; text-align: center; }
}

@media (prefers-reduced-motion: reduce) {
    .kamon { animation: none; }
    * { transition: none !important; }
}
</style>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+JP:wght@400;700&display=swap" rel="stylesheet">
</head>
<body>

<!-- ヒーロー -->
<section class="hero">
    <div class="kamon">⚔</div>
    <p class="hero-eyebrow">Sengoku Keizaiken NFT</p>
    <h1 class="hero-title">
        デジタル時代の<span>領地</span>を、<br>あなたの手に。
    </h1>
    <p class="hero-sub">
        戦国経済圏は、NFTで土地・商業権・収益を束ねる<br>
        次世代デジタル資産プラットフォームです。
    </p>
    <div class="hero-btns">
        <?php if (!empty($agent['show_form'])): ?>
        <a href="#contact" class="btn-primary">無料で相談する</a>
        <?php endif; ?>
        <?php if (!empty($agent['show_line_btn']) && !empty($agent['line_url'])): ?>
        <a href="/line_click.php?agent_id=<?= $agent['id'] ?>" class="btn-line" target="_blank" rel="noopener">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.03 2 11c0 3.12 1.67 5.88 4.23 7.55L5 22l4.18-2.09C10.04 20.29 11 20.5 12 20.5c5.52 0 10-4.03 10-9S17.52 2 12 2z"/></svg>
            LINEで相談する
        </a>
        <?php endif; ?>
    </div>
</section>

<div class="divider"></div>

<!-- 特徴 -->
<section class="features">
    <div class="features-inner">
        <p class="section-label">Features</p>
        <h2 class="section-title">戦国経済圏の3つの強み</h2>
        <div class="cards">
            <div class="card">
                <div class="card-icon">🏯</div>
                <p class="card-title">デジタル土地所有</p>
                <p class="card-body">NFTで土地の所有権を確実に記録。転売・賃貸・開発の自由度が高い資産形成が可能です。</p>
            </div>
            <div class="card">
                <div class="card-icon">💹</div>
                <p class="card-title">収益化エコシステム</p>
                <p class="card-body">賃料収入・商業権販売・トークン還元など、複数の収益経路が設計されています。</p>
            </div>
            <div class="card">
                <div class="card-icon">🤝</div>
                <p class="card-title">担当者がつく安心感</p>
                <p class="card-body">専任担当者が参入から運用まで丁寧にサポート。NFT初心者でも安心してスタートできます。</p>
            </div>
        </div>
    </div>
</section>

<div class="divider"></div>

<!-- 担当者プロフィール -->
<section class="section">
    <p class="section-label">Your Partner</p>
    <h2 class="section-title">担当者紹介</h2>
    <div class="profile-wrap">
        <?php if (!empty($agent['profile_image'])): ?>
        <img src="<?= h($agent['profile_image']) ?>" alt="<?= h($agent['person_name']) ?>" class="profile-img">
        <?php else: ?>
        <div class="profile-img-placeholder">👤</div>
        <?php endif; ?>
        <div>
            <p class="profile-name"><?= h($agent['person_name']) ?></p>
            <p class="profile-role"><?= h($agent['agent_name']) ?></p>
            <?php if (!empty($agent['profile_text'])): ?>
            <p class="profile-text"><?= nl2br(h($agent['profile_text'])) ?></p>
            <?php else: ?>
            <p class="profile-text">戦国経済圏NFTの専任担当者として、資産形成から運用まで丁寧にサポートいたします。まずはお気軽にご相談ください。</p>
            <?php endif; ?>
        </div>
    </div>
</section>

<div class="divider"></div>

<!-- FAQ -->
<section class="faq">
    <div class="faq-inner">
        <p class="section-label">FAQ</p>
        <h2 class="section-title">よくある質問</h2>
        <details>
            <summary>NFT投資は初めてでも大丈夫ですか？</summary>
            <p>はい、問題ありません。担当者が基礎から丁寧にご説明します。まずはお問い合わせフォームからご連絡ください。</p>
        </details>
        <details>
            <summary>どのくらいの資金から始められますか？</summary>
            <p>参入プランによって異なります。詳細はお問い合わせいただいた後、担当者より個別にご案内いたします。</p>
        </details>
        <details>
            <summary>問い合わせ後、どのような流れになりますか？</summary>
            <p>担当者よりメールまたはLINEにてご連絡いたします。オンラインでの説明会・個別相談をご案内します。</p>
        </details>
        <details>
            <summary>相談は無料ですか？</summary>
            <p>はい、初回のご相談は完全無料です。お気軽にお問い合わせください。</p>
        </details>
    </div>
</section>

<div class="divider"></div>

<!-- 問い合わせフォーム -->
<section class="contact-section" id="contact">
    <div class="contact-inner">
        <p class="section-label">Contact</p>
        <h2 class="section-title">無料相談・お問い合わせ</h2>
        <p style="color:rgba(245,240,232,.7);margin-bottom:2rem;font-size:.95rem;">
            <?= h($agent['person_name']) ?>（<?= h($agent['agent_name']) ?>）が担当いたします。<br>
            内容確認後、担当者より順次ご連絡いたします。
        </p>

        <?php
        $showForm    = !empty($agent['show_form']);
        $showLinBtn  = !empty($agent['show_line_btn']) && !empty($agent['line_url']);
        ?>

        <?php if ($showLinBtn): ?>
        <div style="margin-bottom:<?= $showForm ? '2rem' : '0' ?>;">
            <a href="/line_click.php?agent_id=<?= $agent['id'] ?>" class="btn-line" target="_blank" rel="noopener"
               style="display:inline-flex;align-items:center;gap:.6rem;padding:1rem 2.5rem;font-size:1.05rem;width:<?= $showForm ? 'auto' : '100%' ?>;justify-content:center;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.03 2 11c0 3.12 1.67 5.88 4.23 7.55L5 22l4.18-2.09C10.04 20.29 11 20.5 12 20.5c5.52 0 10-4.03 10-9S17.52 2 12 2z"/></svg>
                LINEで相談する（無料）
            </a>
            <?php if ($showForm): ?>
            <p style="margin-top:1rem;font-size:.82rem;color:rgba(245,240,232,.4);text-align:center;">— または下のフォームからお問い合わせ —</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($showForm): ?>
        <form id="contactForm" novalidate>
            <input type="hidden" name="agent_id" value="<?= $agent['id'] ?>">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">

            <div class="form-group">
                <label>お名前<span class="required">*</span></label>
                <input type="text" name="name" placeholder="山田 太郎" required>
            </div>
            <div class="form-group">
                <label>メールアドレス<span class="required">*</span></label>
                <input type="email" name="email" placeholder="example@mail.com" required>
            </div>
            <div class="form-group">
                <label>電話番号</label>
                <input type="tel" name="phone" placeholder="090-0000-0000">
            </div>
            <div class="form-group">
                <label>お問い合わせ内容<span class="required">*</span></label>
                <textarea name="message" placeholder="ご質問・ご相談内容をご記入ください。" required></textarea>
            </div>

            <div class="form-msg" id="formMsg"></div>
            <button type="submit" class="btn-submit" id="submitBtn">送信する</button>
        </form>
        <?php endif; ?>

        <?php if (!empty($agent['phone'])): ?>
        <p style="margin-top:2rem;text-align:center;color:rgba(245,240,232,.5);font-size:.85rem;">
            電話でのお問い合わせ：<a href="tel:<?= h($agent['phone']) ?>" style="color:var(--gold);"><?= h($agent['phone']) ?></a>
        </p>
        <?php endif; ?>
    </div>
</section>

<footer>
    <p><?= h($agent['agent_name']) ?> &nbsp;|&nbsp; <a href="#">プライバシーポリシー</a></p>
    <p style="margin-top:.5rem;">本サービスは 戦国経済圏 の代理店によって運営されています。</p>
</footer>

<?php if ($showForm): ?>
<script>
document.getElementById('contactForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const form    = e.target;
    const btn     = document.getElementById('submitBtn');
    const msgEl   = document.getElementById('formMsg');

    btn.disabled  = true;
    btn.textContent = '送信中...';
    msgEl.style.display = 'none';
    msgEl.className = 'form-msg';

    const data = {};
    new FormData(form).forEach((v, k) => data[k] = v);

    try {
        const res  = await fetch('/contact.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data),
        });
        const json = await res.json();

        if (json.success) {
            msgEl.className = 'form-msg success';
            msgEl.textContent = json.message;
            form.reset();
        } else {
            msgEl.className = 'form-msg error';
            if (json.errors) {
                const ul = document.createElement('ul');
                ul.className = 'form-errors';
                json.errors.forEach(err => {
                    const li = document.createElement('li');
                    li.textContent = err;
                    ul.appendChild(li);
                });
                msgEl.innerHTML = '';
                msgEl.appendChild(ul);
            } else {
                msgEl.textContent = json.message || '送信に失敗しました。';
            }
        }
    } catch (err) {
        msgEl.className = 'form-msg error';
        msgEl.textContent = '通信エラーが発生しました。時間をおいて再度お試しください。';
    } finally {
        msgEl.style.display = 'block';
        btn.disabled = false;
        btn.textContent = '送信する';
        msgEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
});
</script>
<?php endif; ?>
</body>
</html>

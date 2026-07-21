<?php
/**
 * LPテンプレート: sengoku_influencer_nft_lp
 * $agent, $csrfToken はlp.phpからインジェクト済み
 */
$showForm   = !empty($agent['show_form']);
$showLinBtn = !empty($agent['show_line_btn']) && !empty($agent['line_url']);
$csrfToken  = $csrfToken ?? getCsrfToken();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($agent['person_name']) ?> | <?= h($agent['agent_name']) ?></title>
<meta name="description" content="あなたの「好き」が、戦国の未来をつくる。戦国文化や地域の魅力を楽しみながら広げていくコミュニティ。それが、戦国経済圏です。">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+JP:wght@400;600;700;900&family=Noto+Sans+JP:wght@300;400;500&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --white:      #FFFFFF;
  --bg:         #FAF8F5;
  --bg2:        #F4F0EA;
  --bg3:        #EEE8DC;
  --ink:        #2C2118;
  --ink-lt:     #5A4A38;
  --ink-xs:     #8A7A68;
  --gold:       #B8912A;
  --gold-lt:    #D4A840;
  --gold-pale:  #F0E2B8;
  --gold-bg:    #FBF5E6;
  --crimson:    #9B2020;
  --crimson-lt: #C13030;
  --sakura:     #D4708A;
  --sakura-lt:  #E8A0B4;
  --sakura-bg:  #FDF0F4;
  --sakura-pale:#FBE8EE;
  --border:     #DDD5C0;
  --border-lt:  #EDE6D5;
}

html { scroll-behavior: smooth; }
body {
  background: var(--bg);
  color: var(--ink);
  font-family: 'Noto Sans JP', sans-serif;
  overflow-x: hidden;
  line-height: 1.85;
}

/* ===== HERO ===== */
.hero {
  position: relative;
  width: 100%;
  min-height: 100vh;
  min-height: 100svh;
  overflow: hidden;
  background: #f5e8e0;
}
.hero-img-pc {
  position: absolute; inset: 0;
  width: 100%; height: 100%;
  object-fit: cover; object-position: center top;
  display: block;
}
.hero-img-sp {
  position: absolute; inset: 0;
  width: 100%; height: 100%;
  object-fit: cover; object-position: center top;
  display: none;
}
@media (max-width: 640px) {
  .hero-img-pc { display: none; }
  .hero-img-sp {
    display: block;
    position: relative;
    width: 100%;
    height: auto;
    object-fit: unset;
  }
  .hero { min-height: unset; background: transparent; }
  .hero-overlay { display: none; }
  .petals-canvas { display: none; }
  .hero-content { display: none; }
}
.hero-overlay {
  position: absolute; inset: 0;
  background: linear-gradient(
    120deg,
    rgba(250,248,245,0.88) 0%,
    rgba(250,248,245,0.72) 40%,
    rgba(250,248,245,0.10) 100%
  );
}
.petals-canvas {
  position: absolute; inset: 0;
  pointer-events: none; z-index: 2;
}
.hero-content {
  position: relative; z-index: 3;
  display: flex; flex-direction: column;
  justify-content: center;
  min-height: 100vh; min-height: 100svh;
  padding: 80px 52px 64px;
  max-width: 620px;
}
.hero-badge {
  display: inline-flex; align-items: center; gap: 8px;
  background: rgba(255,255,255,0.92);
  border: 1px solid var(--gold);
  border-radius: 4px;
  padding: 6px 16px;
  margin-bottom: 28px;
  font-size: 0.7rem;
  color: var(--gold);
  letter-spacing: 0.14em;
  font-family: 'Noto Serif JP', serif;
  width: fit-content;
  box-shadow: 0 2px 12px rgba(184,145,42,0.1);
}
.hero-main-copy {
  font-family: 'Noto Serif JP', serif;
  font-weight: 700;
  font-size: clamp(2rem, 5.5vw, 3.1rem);
  line-height: 1.5;
  color: var(--ink);
  margin-bottom: 18px;
}
.hero-main-copy em { font-style: normal; color: var(--crimson); }
.hero-sub-copy {
  font-size: clamp(0.88rem, 2vw, 0.98rem);
  color: var(--ink-lt);
  line-height: 2;
  margin-bottom: 32px;
}
.hero-nft-badge {
  background: rgba(255,255,255,0.95);
  border: 1.5px solid var(--gold);
  border-radius: 8px;
  padding: 18px 22px;
  display: flex; align-items: center; gap: 16px;
  margin-bottom: 32px;
  width: fit-content; max-width: 100%;
  box-shadow: 0 4px 20px rgba(184,145,42,0.12);
}
.hero-nft-kamon {
  width: 50px; height: 50px; flex-shrink: 0;
  border: 1.5px solid var(--gold);
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  background: var(--gold-bg);
}
.hero-nft-kamon svg { width: 30px; height: 30px; }
.hero-nft-label { font-size: 0.67rem; color: var(--gold); letter-spacing: 0.14em; font-family: 'Noto Serif JP', serif; margin-bottom: 4px; }
.hero-nft-title { font-family: 'Noto Serif JP', serif; font-weight: 700; font-size: clamp(1rem, 2.8vw, 1.28rem); color: var(--ink); letter-spacing: 0.03em; margin-bottom: 3px; }
.hero-nft-sub { font-size: 0.7rem; color: var(--gold); letter-spacing: 0.1em; }
.hero-perks {
  display: flex; flex-wrap: wrap; gap: 14px 18px;
  margin-bottom: 36px;
}
.hero-perk { display: flex; flex-direction: column; align-items: center; gap: 6px; text-align: center; width: 66px; }
.hero-perk-icon {
  width: 40px; height: 40px;
  background: var(--white);
  border: 1px solid var(--border);
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}
.hero-perk-icon svg { width: 17px; height: 17px; stroke: var(--gold); }
.hero-perk-label { font-size: 0.6rem; color: var(--ink-lt); line-height: 1.45; }
.hero-cta {
  display: inline-flex; align-items: center; gap: 10px;
  background: var(--crimson);
  border: none;
  color: #fff;
  font-family: 'Noto Serif JP', serif;
  font-size: clamp(0.88rem, 2.3vw, 1rem);
  font-weight: 600;
  padding: 16px 34px;
  border-radius: 50px;
  text-decoration: none;
  letter-spacing: 0.07em;
  transition: all 0.28s;
  box-shadow: 0 4px 18px rgba(155,32,32,0.3);
}
.hero-cta:hover { background: var(--crimson-lt); transform: translateY(-2px); box-shadow: 0 7px 24px rgba(155,32,32,0.38); }
.hero-cta svg { width: 15px; height: 15px; flex-shrink: 0; }

@media (max-width: 640px) {
  .hero-content { padding: 56px 22px 48px; justify-content: flex-end; }
  .hero-nft-badge { padding: 14px 16px; gap: 12px; }
}

/* ===== DIVIDERS ===== */
.gold-divider {
  width: 100%; height: 1px;
  background: linear-gradient(90deg, transparent 0%, var(--gold-pale) 30%, var(--gold) 50%, var(--gold-pale) 70%, transparent 100%);
}
.sakura-divider {
  display: flex; align-items: center; gap: 12px;
  padding: 0 24px; max-width: 760px; margin: 0 auto;
}
.sakura-divider::before, .sakura-divider::after {
  content: ''; flex: 1; height: 1px;
  background: var(--border-lt);
}
.sakura-divider-flower { color: var(--sakura-lt); font-size: 0.85rem; }

/* ===== SECTION BASE ===== */
.sec { padding: 76px 24px; max-width: 760px; margin: 0 auto; }
@media (max-width: 640px) { .sec { padding: 58px 20px; } }
.sec-eyebrow {
  font-size: 0.66rem; letter-spacing: 0.24em;
  color: var(--sakura); font-family: 'Noto Serif JP', serif;
  text-align: center; margin-bottom: 12px;
}
.sec-title {
  font-family: 'Noto Serif JP', serif; font-weight: 700;
  font-size: clamp(1.3rem, 4vw, 1.8rem);
  text-align: center; color: var(--ink);
  line-height: 1.55; margin-bottom: 44px;
}
.sec-title-line {
  display: block; width: 40px; height: 2px;
  background: linear-gradient(90deg, var(--sakura), var(--gold));
  margin: 12px auto 0; border-radius: 2px;
}

/* ===== ABOUT ===== */
.about-text {
  font-size: clamp(0.88rem, 2.2vw, 0.97rem);
  color: var(--ink-lt); line-height: 2; text-align: center; margin-bottom: 12px;
}
.about-activities { margin: 32px 0; display: flex; flex-direction: column; gap: 3px; }
.about-activity {
  display: flex; align-items: flex-start; gap: 14px;
  padding: 16px 20px;
  border-left: 3px solid var(--sakura-lt);
  background: var(--white);
  border-radius: 0 6px 6px 0;
}
.about-activity-icon {
  width: 34px; height: 34px; flex-shrink: 0;
  background: var(--sakura-pale); border-radius: 50%;
  display: flex; align-items: center; justify-content: center; margin-top: 2px;
}
.about-activity-icon svg { width: 15px; height: 15px; stroke: var(--sakura); }
.about-activity-title { font-family: 'Noto Serif JP', serif; font-size: 0.92rem; font-weight: 600; color: var(--ink); margin-bottom: 3px; }
.about-activity-desc { font-size: 0.82rem; color: var(--ink-lt); line-height: 1.75; }
.about-membership-box {
  background: var(--gold-bg);
  border: 1px solid var(--gold-pale); border-radius: 10px;
  padding: 28px 24px; margin-top: 36px; text-align: center;
}
.about-membership-title {
  font-family: 'Noto Serif JP', serif; font-weight: 700;
  font-size: 0.97rem; color: var(--gold); margin-bottom: 14px;
}
.about-membership-desc { font-size: 0.88rem; color: var(--ink-lt); line-height: 2; }

/* ===== PERKS ===== */
.perks-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
@media (max-width: 480px) { .perks-grid { grid-template-columns: 1fr; } }
.perk-card {
  background: var(--white);
  border: 1px solid var(--border-lt); border-radius: 10px;
  padding: 22px 18px;
  display: flex; flex-direction: column; gap: 10px;
  transition: border-color 0.25s, box-shadow 0.25s;
  box-shadow: 0 2px 10px rgba(0,0,0,0.04);
}
.perk-card:hover {
  border-color: var(--sakura-lt);
  box-shadow: 0 6px 20px rgba(212,112,138,0.1);
}
.perk-card-top { display: flex; align-items: center; gap: 10px; }
.perk-num { font-size: 0.67rem; color: var(--sakura-lt); font-family: 'Noto Serif JP', serif; letter-spacing: 0.1em; }
.perk-icon {
  width: 40px; height: 40px;
  background: var(--sakura-pale); border-radius: 50%;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.perk-icon svg { width: 18px; height: 18px; stroke: var(--sakura); }
.perk-title { font-family: 'Noto Serif JP', serif; font-weight: 600; font-size: 0.9rem; color: var(--ink); line-height: 1.45; }
.perk-desc { font-size: 0.8rem; color: var(--ink-lt); line-height: 1.75; }

/* ===== POINTS ===== */
.points-intro { text-align: center; font-size: 0.9rem; color: var(--ink-lt); line-height: 2; margin-bottom: 30px; }
.points-table { width: 100%; border-collapse: collapse; font-size: 0.84rem; }
.points-table th {
  background: var(--gold-bg); color: var(--gold);
  font-family: 'Noto Serif JP', serif; font-weight: 600;
  padding: 11px 14px; text-align: left;
  border-bottom: 1.5px solid var(--gold-pale); font-size: 0.77rem; letter-spacing: 0.07em;
}
.points-table td {
  padding: 13px 14px;
  border-bottom: 1px solid var(--border-lt);
  color: var(--ink-lt); vertical-align: top;
  background: var(--white);
}
.points-table tr:last-child td { border-bottom: none; }
.points-table tr:hover td { background: var(--bg); }
.point-badge {
  display: inline-block; background: var(--gold-bg);
  border: 1px solid var(--gold-pale); color: var(--gold);
  border-radius: 4px; padding: 2px 9px;
  font-size: 0.74rem; font-weight: 600; white-space: nowrap;
}
.point-note { margin-top: 16px; font-size: 0.75rem; color: var(--ink-xs); text-align: center; line-height: 1.8; }

/* ===== NFT INFO ===== */
.budget-cards { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 26px; }
@media (max-width: 480px) { .budget-cards { grid-template-columns: 1fr; } }
.budget-card {
  background: var(--white); border: 1px solid var(--border-lt); border-radius: 10px;
  padding: 26px 18px; text-align: center;
  box-shadow: 0 2px 10px rgba(0,0,0,0.04);
}
.budget-card-title { font-family: 'Noto Serif JP', serif; font-weight: 600; font-size: 0.84rem; color: var(--ink-lt); margin-bottom: 12px; letter-spacing: 0.06em; }
.budget-amount { font-family: 'Noto Serif JP', serif; font-weight: 900; font-size: 2rem; color: var(--gold); line-height: 1; margin-bottom: 4px; }
.budget-unit { font-size: 0.9rem; font-weight: 400; }
.budget-label { font-size: 0.73rem; color: var(--ink-xs); margin-top: 8px; }
.budget-note-box {
  background: var(--gold-bg); border: 1px solid var(--gold-pale); border-radius: 10px;
  padding: 20px 22px; font-size: 0.83rem; color: var(--ink-lt); line-height: 2;
}

/* ===== STEPS ===== */
.steps-list { display: flex; flex-direction: column; gap: 0; position: relative; }
.steps-list::before {
  content: ''; position: absolute; left: 22px; top: 36px; bottom: 36px;
  width: 1px; background: linear-gradient(180deg, var(--sakura-lt), var(--border-lt));
}
.step-item { display: flex; gap: 22px; padding: 20px 0; position: relative; }
.step-num {
  width: 44px; height: 44px; border-radius: 50%;
  background: var(--white); border: 2px solid var(--sakura-lt);
  display: flex; align-items: center; justify-content: center;
  font-family: 'Noto Serif JP', serif; font-weight: 700;
  font-size: 0.84rem; color: var(--sakura);
  flex-shrink: 0; position: relative; z-index: 1;
  box-shadow: 0 2px 10px rgba(212,112,138,0.15);
}
.step-body { flex: 1; padding-top: 8px; }
.step-title { font-family: 'Noto Serif JP', serif; font-weight: 600; font-size: 0.97rem; color: var(--ink); margin-bottom: 6px; }
.step-desc { font-size: 0.82rem; color: var(--ink-lt); line-height: 1.8; }
.step-tag {
  display: inline-block; margin-top: 8px;
  background: var(--sakura-pale); border: 1px solid var(--sakura-lt);
  color: var(--sakura); border-radius: 4px;
  padding: 3px 12px; font-size: 0.69rem; letter-spacing: 0.06em;
}

/* ===== WHY ===== */
.why-cards { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media (max-width: 480px) { .why-cards { grid-template-columns: 1fr; } }
.why-card {
  padding: 24px 20px; border-radius: 10px;
  border: 1px solid var(--border-lt); background: var(--white);
  position: relative; overflow: hidden;
  box-shadow: 0 2px 10px rgba(0,0,0,0.04);
  transition: box-shadow 0.25s;
}
.why-card:hover { box-shadow: 0 6px 20px rgba(0,0,0,0.08); }
.why-card::before {
  content: ''; position: absolute; top: 0; left: 0;
  width: 100%; height: 3px;
  background: linear-gradient(90deg, var(--sakura-lt), var(--gold-lt));
}
.why-card-icon { font-size: 1.65rem; margin-bottom: 10px; display: block; }
.why-card-title { font-family: 'Noto Serif JP', serif; font-weight: 700; font-size: 0.9rem; color: var(--ink); margin-bottom: 8px; line-height: 1.5; }
.why-card-desc { font-size: 0.79rem; color: var(--ink-lt); line-height: 1.8; }

/* ===== FAQ ===== */
.faq-list { display: flex; flex-direction: column; gap: 8px; }
.faq-item { border: 1px solid var(--border-lt); border-radius: 8px; overflow: hidden; background: var(--white); }
.faq-q {
  display: flex; justify-content: space-between; align-items: center;
  padding: 16px 18px; cursor: pointer; gap: 14px;
  transition: background 0.22s;
}
.faq-q:hover { background: var(--bg); }
.faq-q-text { font-family: 'Noto Serif JP', serif; font-size: 0.89rem; color: var(--ink); line-height: 1.55; flex: 1; }
.faq-q-icon {
  width: 22px; height: 22px; flex-shrink: 0;
  background: var(--sakura-pale); border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
}
.faq-q-icon svg { width: 12px; height: 12px; stroke: var(--sakura); transition: transform 0.28s; }
.faq-item.open .faq-q-icon svg { transform: rotate(180deg); }
.faq-item.open .faq-q { background: var(--sakura-pale); }
.faq-a { max-height: 0; overflow: hidden; transition: max-height 0.35s ease; }
.faq-a-inner {
  padding: 16px 18px 20px;
  font-size: 0.83rem; color: var(--ink-lt); line-height: 2;
  border-top: 1px solid var(--border-lt);
}
.faq-item.open .faq-a { max-height: 400px; }

/* ===== CTA BOTTOM ===== */
.cta-bg {
  width: 100%;
  background: linear-gradient(160deg, var(--sakura-pale) 0%, var(--gold-bg) 100%);
  position: relative; overflow: hidden;
}
.cta-bg::before {
  content: ''; position: absolute; inset: 0;
  background:
    radial-gradient(ellipse at 20% 80%, rgba(212,112,138,0.12) 0%, transparent 55%),
    radial-gradient(ellipse at 80% 20%, rgba(184,145,42,0.1) 0%, transparent 55%);
  pointer-events: none;
}
.cta-inner {
  position: relative; z-index: 1;
  text-align: center; padding: 76px 24px;
  max-width: 600px; margin: 0 auto;
}
.cta-eyebrow { font-size: 0.67rem; letter-spacing: 0.24em; color: var(--sakura); font-family: 'Noto Serif JP', serif; margin-bottom: 16px; }
.cta-title {
  font-family: 'Noto Serif JP', serif; font-weight: 700;
  font-size: clamp(1.4rem, 4vw, 1.9rem);
  color: var(--ink); line-height: 1.6; margin-bottom: 14px;
}
.cta-title em { font-style: normal; color: var(--crimson); }
.cta-sub { font-size: 0.87rem; color: var(--ink-lt); line-height: 2.1; margin-bottom: 36px; }
.cta-btn {
  display: inline-flex; align-items: center; gap: 10px;
  background: var(--crimson); color: #fff;
  font-family: 'Noto Serif JP', serif; font-size: 1rem; font-weight: 600;
  padding: 17px 40px; border-radius: 50px; text-decoration: none;
  letter-spacing: 0.08em; transition: all 0.28s;
  box-shadow: 0 4px 20px rgba(155,32,32,0.25);
}
.cta-btn:hover { background: var(--crimson-lt); transform: translateY(-3px); box-shadow: 0 8px 28px rgba(155,32,32,0.32); }
.cta-btn svg { width: 14px; height: 14px; }
.cta-note { margin-top: 20px; font-size: 0.72rem; color: var(--ink-xs); line-height: 1.85; }

/* ===== FOOTER ===== */
footer {
  background: var(--ink); padding: 32px 24px; text-align: center;
}
footer p { font-size: 0.72rem; color: rgba(200,185,155,0.55); line-height: 1.8; }

/* ===== BG ALTERNATING ===== */
.bg-white { background: var(--white); width: 100%; }
.bg-soft  { background: var(--bg);    width: 100%; }
.bg-soft2 { background: var(--bg2);   width: 100%; }
.bg-gold  { background: var(--gold-bg); width: 100%; }

/* ===== REVEAL ===== */
.js-reveal { opacity: 0; transform: translateY(20px); transition: opacity 0.58s ease, transform 0.58s ease; }
.js-reveal.visible { opacity: 1; transform: none; }
</style>
</head>
<body>
<!-- HERO -->
<section class="hero" style="max-width:none;padding:0;margin:0;">
  <img class="hero-img-pc" src="/uploads/lp/influencer/img_29548f51.jpg" alt="戦国インフルエンサーNFT">
  <img class="hero-img-sp" src="/uploads/lp/influencer/img_29548f51.jpg" alt="戦国インフルエンサーNFT">
  <div class="hero-overlay"></div>
  <canvas class="petals-canvas" id="petalsCanvas"></canvas>
  <div class="hero-content">
    <div class="hero-badge">✦ 戦国経済圏 公式</div>
    <h1 class="hero-main-copy">
      あなたの<em>「好き」</em>が、<br>戦国の未来をつくる。
    </h1>
    <p class="hero-sub-copy">
      戦国文化や地域の魅力を楽しみながら広げていく<br>コミュニティ、それが戦国経済圏です。
    </p>
    <div class="hero-nft-badge">
      <div class="hero-nft-kamon">
        <svg viewBox="0 0 40 40" fill="none">
          <circle cx="20" cy="20" r="17" stroke="#B8912A" stroke-width="1.5"/>
          <path d="M20 5C20 5 14 13 14 20C14 27 20 35 20 35C20 35 26 27 26 20C26 13 20 5 20 5Z" fill="#B8912A" opacity="0.65"/>
          <path d="M5 20 H35" stroke="#B8912A" stroke-width="1"/>
          <circle cx="20" cy="20" r="3" fill="#B8912A"/>
        </svg>
      </div>
      <div class="hero-nft-text">
        <div class="hero-nft-label">戦国経済圏への参加資格</div>
        <div class="hero-nft-title">戦国インフルエンサーNFT</div>
        <div class="hero-nft-sub">― メンバーシップNFT ―</div>
      </div>
    </div>
    <div class="hero-perks">
      <div class="hero-perk">
        <div class="hero-perk-icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
        <div class="hero-perk-label">限定コミュニティ<br>参加権</div>
      </div>
      <div class="hero-perk">
        <div class="hero-perk-icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></div>
        <div class="hero-perk-label">企画・投票への<br>参加権</div>
      </div>
      <div class="hero-perk">
        <div class="hero-perk-icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></div>
        <div class="hero-perk-label">イベント優先<br>ご案内</div>
      </div>
      <div class="hero-perk">
        <div class="hero-perk-icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div>
        <div class="hero-perk-label">活動案件への<br>参加機会</div>
      </div>
      <div class="hero-perk">
        <div class="hero-perk-icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg></div>
        <div class="hero-perk-label">限定特典の<br>取得機会</div>
      </div>
      <div class="hero-perk">
        <div class="hero-perk-icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/></svg></div>
        <div class="hero-perk-label">活動サポートの<br>対象機会</div>
      </div>
    </div>
    <a href="#join" class="hero-cta">
      戦国インフルエンサーNFTに参加する
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
    </a>
  </div>
</section>
<div class="gold-divider"></div>

<!-- ABOUT -->
<div class="bg-white">
<div class="sec js-reveal">
  <div class="sec-eyebrow">ABOUT</div>
  <h2 class="sec-title">戦国インフルエンサーとは？<span class="sec-title-line"></span></h2>
  <p class="about-text">戦国インフルエンサーとは、戦国経済圏に参加し、<br>戦国文化や地域の魅力を広げながら、<br>イベントや企画、コミュニティ活動に関わることができる<br><strong style="color:var(--ink);">メンバー制度</strong>です。</p>
  <p class="about-text" style="margin-top:8px;">SNSで発信することだけが活動ではありません。</p>
  <div class="about-activities js-reveal">
    <div class="about-activity">
      <div class="about-activity-icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" stroke="currentColor"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></div>
      <div><div class="about-activity-title">イベントへの参加</div><div class="about-activity-desc">戦国文化・地域イベントへの優先案内と参加機会を得られます。</div></div>
    </div>
    <div class="about-activity">
      <div class="about-activity-icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" stroke="currentColor"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg></div>
      <div><div class="about-activity-title">企画への意見・提案</div><div class="about-activity-desc">戦国経済圏の企画・方針に関する投票や提案に参加できます。</div></div>
    </div>
    <div class="about-activity">
      <div class="about-activity-icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" stroke="currentColor"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
      <div><div class="about-activity-title">地域との交流</div><div class="about-activity-desc">地域コミュニティや文化施設との交流プログラムに参加できます。</div></div>
    </div>
    <div class="about-activity">
      <div class="about-activity-icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" stroke="currentColor"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg></div>
      <div><div class="about-activity-title">コミュニティ活動</div><div class="about-activity-desc">限定コミュニティ内での情報交換・交流・メンバー間のつながりを楽しめます。</div></div>
    </div>
  </div>
  <p class="about-text js-reveal" style="margin-top:24px;color:var(--ink-xs);font-size:0.88rem;">それぞれの得意な形で、戦国経済圏に関わることができます。</p>
  <div class="about-membership-box js-reveal">
    <div class="about-membership-title">🌸　戦国インフルエンサーNFT = メンバーシップNFT　🌸</div>
    <p class="about-membership-desc">保有することで、限定コミュニティへの参加、<br>イベントや企画への参加機会、保有者限定の特典など、<br>さまざまな権利を得ることができます。</p>
  </div>
</div>
</div>

<div class="gold-divider"></div>

<!-- PERKS -->
<div class="bg-soft">
<div class="sec js-reveal">
  <div class="sec-eyebrow">MEMBERSHIP PERKS</div>
  <h2 class="sec-title">保有者が得られる6つの権利<span class="sec-title-line"></span></h2>
  <div class="perks-grid">
    <div class="perk-card js-reveal">
      <div class="perk-card-top"><span class="perk-num">01</span><div class="perk-icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div></div>
      <div class="perk-title">限定コミュニティ参加権</div>
      <div class="perk-desc">NFT保有者だけが参加できる限定コミュニティへのアクセス権を得られます。</div>
    </div>
    <div class="perk-card js-reveal">
      <div class="perk-card-top"><span class="perk-num">02</span><div class="perk-icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></div></div>
      <div class="perk-title">企画・投票への参加権</div>
      <div class="perk-desc">戦国経済圏の企画立案や方針決定の投票に参加できます。</div>
    </div>
    <div class="perk-card js-reveal">
      <div class="perk-card-top"><span class="perk-num">03</span><div class="perk-icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></div></div>
      <div class="perk-title">イベント優先ご案内</div>
      <div class="perk-desc">戦国関連イベントや特別企画への優先案内・優先参加の機会を得られます。</div>
    </div>
    <div class="perk-card js-reveal">
      <div class="perk-card-top"><span class="perk-num">04</span><div class="perk-icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div></div>
      <div class="perk-title">活動案件への参加機会</div>
      <div class="perk-desc">戦国経済圏に関連する活動案件へ参加できる機会が提供されます。</div>
    </div>
    <div class="perk-card js-reveal">
      <div class="perk-card-top"><span class="perk-num">05</span><div class="perk-icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg></div></div>
      <div class="perk-title">限定特典の取得機会</div>
      <div class="perk-desc">保有者限定のデジタル特典・リアル特典など、様々な特典を取得できます。</div>
    </div>
    <div class="perk-card js-reveal">
      <div class="perk-card-top"><span class="perk-num">06</span><div class="perk-icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/></svg></div></div>
      <div class="perk-title">活動サポートの対象機会</div>
      <div class="perk-desc">活動実績に応じて、戦国経済圏からのサポートプログラム対象となる機会があります。</div>
    </div>
  </div>
</div>
</div>



<div class="gold-divider"></div>

<!-- NFT INFO -->
<div class="bg-white">
<div class="sec js-reveal">
  <div class="sec-eyebrow">ABOUT NFT</div>
  <h2 class="sec-title">NFTについて<span class="sec-title-line"></span></h2>
  <div class="budget-cards">
    <div class="budget-card js-reveal"><div class="budget-card-title">保有期間</div><div class="budget-amount" style="font-size:1.5rem;">無期限</div><div class="budget-label">一度取得すれば期限なし</div></div>
  </div>
  <div class="budget-note-box js-reveal">
    <p style="margin-bottom:9px;color:var(--gold);font-family:'Noto Serif JP',serif;font-size:0.87rem;font-weight:600;">NFTについてのご説明</p>
    <p>戦国インフルエンサーNFTは、ブロックチェーン技術を活用したデジタル会員証です。NFTを保有することで、コミュニティへの参加資格や各種特典へのアクセス権が付与されます。NFTの購入・保有に際して特別な知識は不要です。取得方法についてはお問い合わせください。</p>
  </div>
</div>
</div>

<div class="gold-divider"></div>

<!-- STEPS -->
<div class="bg-soft">
<div class="sec js-reveal">
  <div class="sec-eyebrow">HOW TO JOIN</div>
  <h2 class="sec-title">参加するまでの流れ<span class="sec-title-line"></span></h2>
  <div class="steps-list">
    <div class="step-item js-reveal"><div class="step-num">01</div><div class="step-body"><div class="step-title">お問い合わせ</div><div class="step-desc">まずは下記のボタンからお気軽にお問い合わせください。担当者よりご連絡いたします。</div><span class="step-tag">無料・登録不要</span></div></div>
    <div class="step-item js-reveal"><div class="step-num">02</div><div class="step-body"><div class="step-title">説明・ご質問</div><div class="step-desc">戦国経済圏やインフルエンサーNFTの詳細について、丁寧にご説明します。ご不明な点は何でもお聞きください。</div></div></div>
    <div class="step-item js-reveal"><div class="step-num">03</div><div class="step-body"><div class="step-title">NFT取得</div><div class="step-desc">ご納得いただけたら、NFTを取得していただきます。取得方法は担当者がサポートします。</div><span class="step-tag">初心者でも安心</span></div></div>
    <div class="step-item js-reveal"><div class="step-num">04</div><div class="step-body"><div class="step-title">コミュニティ参加</div><div class="step-desc">NFT取得後、限定コミュニティへご招待します。メンバーとの交流やイベント情報をお楽しみください。</div><span class="step-tag">ようこそ、戦国経済圏へ</span></div></div>
  </div>
</div>
</div>

<div class="gold-divider"></div>

<!-- WHY -->
<div class="bg-white">
<div class="sec js-reveal">
  <div class="sec-eyebrow">WHY SENGOKU</div>
  <h2 class="sec-title">戦国経済圏を選ぶ理由<span class="sec-title-line"></span></h2>
  <div class="why-cards">
    <div class="why-card js-reveal"><span class="why-card-icon">🏯</span><div class="why-card-title">本格的な戦国世界観</div><div class="why-card-desc">歴史・文化・地域をテーマにした本格的なコミュニティ。「好き」を軸に集まったメンバーと交流できます。</div></div>
    <div class="why-card js-reveal"><span class="why-card-icon">🌸</span><div class="why-card-title">女性も参加しやすい</div><div class="why-card-desc">NFT初心者の方でも安心して参加できる丁寧なサポート体制。難しい知識は一切不要です。</div></div>
    <div class="why-card js-reveal"><span class="why-card-icon">🤝</span><div class="why-card-title">コミュニティファースト</div><div class="why-card-desc">投資・投機目的ではなく、コミュニティへの参加と文化活動を大切にした設計です。</div></div>
    <div class="why-card js-reveal"><span class="why-card-icon">✨</span><div class="why-card-title">あなたらしい関わり方</div><div class="why-card-desc">SNS発信だけでなく、イベント参加・企画提案・地域交流など、自分のペースで活動できます。</div></div>
  </div>
</div>
</div>

<div class="gold-divider"></div>

<!-- FAQ -->
<div class="bg-soft2">
<div class="sec js-reveal">
  <div class="sec-eyebrow">FAQ</div>
  <h2 class="sec-title">よくあるご質問<span class="sec-title-line"></span></h2>
  <div class="faq-list">
    <div class="faq-item"><div class="faq-q"><span class="faq-q-text">NFTを持ったことがないのですが、大丈夫ですか？</span><div class="faq-q-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></div></div><div class="faq-a"><div class="faq-a-inner">はい、大丈夫です。NFTが初めての方でも安心して参加いただけるよう、担当者が取得方法を丁寧にサポートします。特別な知識や機器は不要です。</div></div></div>
    <div class="faq-item"><div class="faq-q"><span class="faq-q-text">SNSで発信しないといけませんか？</span><div class="faq-q-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></div></div><div class="faq-a"><div class="faq-a-inner">SNSでの発信は必須ではありません。コミュニティへの参加、イベントへの参加、企画への意見提案など、それぞれのスタイルで活動いただけます。</div></div></div>
    <div class="faq-item"><div class="faq-q"><span class="faq-q-text">投資や稼ぐことを目的としたサービスですか？</span><div class="faq-q-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></div></div><div class="faq-a"><div class="faq-a-inner">いいえ、違います。戦国インフルエンサーNFTは、戦国経済圏のコミュニティに参加するためのメンバーシップです。投資・投機目的ではなく、文化活動やコミュニティへの参加を目的としています。ポイントは現金や暗号資産への換金はできません。</div></div></div>
    <div class="faq-item"><div class="faq-q"><span class="faq-q-text">NFTの取得費用はどのくらいですか？</span><div class="faq-q-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></div></div><div class="faq-a"><div class="faq-a-inner">詳細については担当者よりご案内いたします。まずはお気軽にお問い合わせください。</div></div></div>
    <div class="faq-item"><div class="faq-q"><span class="faq-q-text">男性でも参加できますか？</span><div class="faq-q-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></div></div><div class="faq-a"><div class="faq-a-inner">もちろんです。戦国文化や地域の魅力に興味のある方であれば、どなたでも参加いただけます。</div></div></div>
    <div class="faq-item"><div class="faq-q"><span class="faq-q-text">活動が忙しくなったり続けられなくなったらどうなりますか？</span><div class="faq-q-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></div></div><div class="faq-a"><div class="faq-a-inner">NFTは保有し続けていただけます。活動のペースはご自身で自由に調整いただけます。無理のない範囲で関わっていただければ十分です。</div></div></div>
  </div>
</div>
</div>

<div class="gold-divider"></div>

<!-- CTA BOTTOM -->
<div class="cta-bg" id="join">
<div class="cta-inner js-reveal">
  <div class="cta-eyebrow">JOIN US</div>
  <h2 class="cta-title">あなたの<em>「好き」</em>を、<br>戦国の世界で活かしませんか？</h2>
  <p class="cta-sub">戦国経済圏のコミュニティメンバーシップNFTです。<br>ご興味をお持ちの方は、まずはお気軽にお問い合わせください。</p>
  <a href="#" class="cta-btn">
    戦国インフルエンサーNFTに参加する
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
  </a>
  <p class="cta-note">※ お問い合わせは無料です。強引な勧誘は一切行っておりません。<br>※ NFTの購入は任意です。参加を強制するものではありません。</p>
</div>
</div>

<!-- FOOTER -->
<footer>
  <p>© 2024 戦国経済圏. All rights reserved.</p>
  <p style="margin-top:5px;">本ページに記載の内容は、投資勧誘・金融商品の提供を目的とするものではありません。</p>
</footer>

<script>
// 桜吹雪
(function() {
  const canvas = document.getElementById('petalsCanvas');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  let petals = [], W, H;
  function resize() { W = canvas.width = canvas.offsetWidth; H = canvas.height = canvas.offsetHeight; }
  window.addEventListener('resize', resize); resize();
  function rnd(a,b){return a+Math.random()*(b-a);}
  function newPetal(){return{x:rnd(0,W),y:rnd(-30,-5),size:rnd(4,9),vy:rnd(0.5,1.2),vx:rnd(-0.5,0.5),rot:rnd(0,Math.PI*2),rotV:rnd(-0.04,0.04),alpha:rnd(0.25,0.5),phase:rnd(0,Math.PI*2)};}
  for(let i=0;i<22;i++){const p=newPetal();p.y=rnd(0,H);petals.push(p);}
  let t=0;
  function loop(){
    ctx.clearRect(0,0,W,H); t+=0.012;
    petals.forEach((p,i)=>{
      p.x+=p.vx+Math.sin(t+p.phase)*0.28; p.y+=p.vy; p.rot+=p.rotV;
      if(p.y>H+20) Object.assign(p,newPetal());
      ctx.save(); ctx.translate(p.x,p.y); ctx.rotate(p.rot); ctx.globalAlpha=p.alpha;
      ctx.fillStyle='#e8a0b4';
      ctx.beginPath(); ctx.ellipse(0,0,p.size,p.size*0.5,0,0,Math.PI*2); ctx.fill();
      ctx.restore();
    });
    requestAnimationFrame(loop);
  }
  loop();
})();

// スクロールアニメーション
const observer = new IntersectionObserver(entries=>{
  entries.forEach(e=>{if(e.isIntersecting)e.target.classList.add('visible');});
},{threshold:0.08});
document.querySelectorAll('.js-reveal').forEach(el=>observer.observe(el));

// FAQ
document.querySelectorAll('.faq-q').forEach(q=>{
  q.addEventListener('click',()=>{
    const item=q.closest('.faq-item');
    const isOpen=item.classList.contains('open');
    document.querySelectorAll('.faq-item').forEach(i=>i.classList.remove('open'));
    if(!isOpen)item.classList.add('open');
  });
});
</script>

<!-- ===== 代理店問い合わせ導線 ===== -->
<section id="contact" style="background:#13100D;padding:5rem 1.5rem;border-top:1px solid rgba(201,168,76,.15);">
  <div style="max-width:640px;margin:0 auto;">
    <p style="font-size:.7rem;letter-spacing:.35em;color:#C9A84C;text-transform:uppercase;margin-bottom:.75rem;">Contact</p>
    <h2 style="font-family:'Noto Serif JP',serif;font-size:clamp(1.5rem,3.5vw,2.2rem);font-weight:900;margin-bottom:1rem;">無料相談・お問い合わせ</h2>
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
    <form id="__contactForm" novalidate>
      <input type="hidden" name="agent_id" value="<?= $agent['id'] ?>">
      <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
      <div style="margin-bottom:1.1rem;">
        <label style="display:block;font-size:.75rem;letter-spacing:.1em;color:#C9A84C;margin-bottom:.4rem;">お名前<span style="color:#e05555;margin-left:.3rem;">*</span></label>
        <input type="text" name="name" required placeholder="山田 太郎" style="width:100%;padding:.8rem 1rem;background:rgba(255,255,255,.05);border:1px solid rgba(201,168,76,.22);border-radius:3px;color:#E8E0CC;font-family:inherit;font-size:.9rem;">
      </div>
      <div style="margin-bottom:1.1rem;">
        <label style="display:block;font-size:.75rem;letter-spacing:.1em;color:#C9A84C;margin-bottom:.4rem;">メールアドレス<span style="color:#e05555;margin-left:.3rem;">*</span></label>
        <input type="email" name="email" required placeholder="example@mail.com" style="width:100%;padding:.8rem 1rem;background:rgba(255,255,255,.05);border:1px solid rgba(201,168,76,.22);border-radius:3px;color:#E8E0CC;font-family:inherit;font-size:.9rem;">
      </div>
      <div style="margin-bottom:1.1rem;">
        <label style="display:block;font-size:.75rem;letter-spacing:.1em;color:#C9A84C;margin-bottom:.4rem;">電話番号</label>
        <input type="tel" name="phone" placeholder="090-0000-0000" style="width:100%;padding:.8rem 1rem;background:rgba(255,255,255,.05);border:1px solid rgba(201,168,76,.22);border-radius:3px;color:#E8E0CC;font-family:inherit;font-size:.9rem;">
      </div>
      <div style="margin-bottom:1.1rem;">
        <label style="display:block;font-size:.75rem;letter-spacing:.1em;color:#C9A84C;margin-bottom:.4rem;">お問い合わせ内容<span style="color:#e05555;margin-left:.3rem;">*</span></label>
        <textarea name="message" required placeholder="ご質問・ご相談内容をご記入ください。" style="width:100%;padding:.8rem 1rem;background:rgba(255,255,255,.05);border:1px solid rgba(201,168,76,.22);border-radius:3px;color:#E8E0CC;font-family:inherit;font-size:.9rem;min-height:120px;resize:vertical;"></textarea>
      </div>
      <div id="__formMsg" style="display:none;padding:.8rem 1rem;border-radius:3px;font-size:.85rem;margin-bottom:.75rem;"></div>
      <button type="submit" id="__submitBtn" style="width:100%;padding:1rem;background:linear-gradient(135deg,#C9A84C,#E2C87A);color:#13100D;font-family:'Noto Serif JP',serif;font-weight:700;font-size:1rem;border:none;border-radius:3px;cursor:pointer;">送信する</button>
    </form>
    <script>
    document.getElementById('__contactForm').addEventListener('submit', async function(e) {
      e.preventDefault();
      const btn=document.getElementById('__submitBtn'), msg=document.getElementById('__formMsg');
      btn.disabled=true; btn.textContent='送信中...'; msg.style.display='none';
      const data={}; new FormData(this).forEach((v,k)=>data[k]=v);
      try {
        const res=await fetch('/contact.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});
        const json=await res.json(); msg.style.display='block';
        if(json.success){msg.style.cssText='display:block;background:rgba(6,199,85,.1);border:1px solid rgba(6,199,85,.4);color:#06c755;padding:.8rem 1rem;border-radius:3px;font-size:.85rem;';msg.textContent=json.message;this.reset();}
        else{msg.style.cssText='display:block;background:rgba(139,26,26,.15);border:1px solid rgba(178,34,34,.4);color:#e08080;padding:.8rem 1rem;border-radius:3px;font-size:.85rem;';msg.textContent=(json.errors||[]).join(' / ')||json.message||'送信に失敗しました。';}
      } catch{msg.style.display='block';msg.textContent='通信エラーが発生しました。';}
      finally{btn.disabled=false;btn.textContent='送信する';msg.scrollIntoView({behavior:'smooth',block:'nearest'});}
    });
    </script>
    <?php endif; ?>

    <?php if (!empty($agent['phone'])): ?>
    <p style="margin-top:1.5rem;text-align:center;font-size:.82rem;color:rgba(232,224,204,.4);">
      電話でのお問い合わせ：<a href="tel:<?= h($agent['phone']) ?>" style="color:#C9A84C;"><?= h($agent['phone']) ?></a>
    </p>
    <?php endif; ?>
  </div>
</section>

</body>
</html>
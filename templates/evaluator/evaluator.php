<?php
/**
 * 戦国経済圏 評議員NFT 代理店LPテンプレート
 * 元LP（index.html）のデザインをベースに、代理店情報と問い合わせフォームを追加
 * $agent 変数はlp.phpからインジェクトされる
 */
$csrfToken = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($agent['agent_name']) ?> | 戦国経済圏 評議員NFT</title>
<meta name="description" content="戦国経済圏 評議員NFT。担当：<?= h($agent['person_name']) ?>（<?= h($agent['agent_name']) ?>）。デジタル資産経済圏の評議員権を取得し、次世代の経済活動に参加しましょう。">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+JP:wght@400;700;900&family=Noto+Sans+JP:wght@300;400;500&display=swap" rel="stylesheet">

<!-- 元LPのスタイルをそのまま継承 -->
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --black: #0A0805;
    --ink: #13100D;
    --gold: #C9A84C;
    --gold-light: #E2C87A;
    --gold-dark: #8B6914;
    --crimson: #8B1A1A;
    --crimson-bright: #B22222;
    --cream: #E8E0CC;
    --smoke: #5A5248;
    --smoke-light: #7A7268;
  }

  html { scroll-behavior: smooth; }
  body {
    background: var(--black);
    color: var(--cream);
    font-family: 'Noto Sans JP', sans-serif;
    overflow-x: hidden;
  }

  /* ===== HERO ===== */
  .hero {
    position: relative;
    width: 100%;
    min-height: 100vh;
    min-height: 100svh;
    overflow: hidden;
    background-color: var(--black);
  }

  /* PC用画像 */
  .hero-img-pc {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center top;
    display: block;
  }

  /* モバイル用画像 */
  .hero-img-sp {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center top;
    display: none;
  }

  @media (max-width: 640px) {
    .hero-img-pc { display: none; }
    .hero-img-sp { display: block; }
  }

  /* ヒーローオーバーレイ */
  .hero-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(
      to bottom,
      rgba(10,8,5,0.2) 0%,
      rgba(10,8,5,0.05) 40%,
      rgba(10,8,5,0.6) 80%,
      rgba(10,8,5,0.95) 100%
    );
  }

  /* ヒーローコンテンツ */
  .hero-content {
    position: relative;
    z-index: 10;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-end;
    min-height: 100vh;
    min-height: 100svh;
    padding: 0 1.5rem 4rem;
    text-align: center;
  }

  .hero-eyebrow {
    font-size: 0.7rem;
    letter-spacing: 0.35em;
    color: var(--gold);
    text-transform: uppercase;
    margin-bottom: 1rem;
    opacity: 0;
    animation: fadeUp 0.8s ease 0.3s both;
  }

  .hero-title {
    font-family: 'Noto Serif JP', serif;
    font-size: clamp(2rem, 7vw, 4rem);
    font-weight: 900;
    line-height: 1.3;
    margin-bottom: 1.25rem;
    text-shadow: 0 2px 30px rgba(0,0,0,0.8);
    opacity: 0;
    animation: fadeUp 0.9s ease 0.5s both;
  }

  .hero-title-gold { color: var(--gold-light); }

  .hero-sub {
    font-size: clamp(0.85rem, 2vw, 1rem);
    color: rgba(232,224,204,0.8);
    line-height: 2;
    max-width: 560px;
    margin-bottom: 2.5rem;
    opacity: 0;
    animation: fadeUp 0.9s ease 0.7s both;
  }

  .hero-cta {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    justify-content: center;
    opacity: 0;
    animation: fadeUp 0.9s ease 0.9s both;
  }

  /* スクロール矢印 */
  .hero-scroll {
    position: absolute;
    bottom: 1.5rem;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.3rem;
    z-index: 10;
    opacity: 0;
    animation: fadeIn 1s ease 1.5s both;
  }

  .hero-scroll span {
    font-size: 0.65rem;
    letter-spacing: 0.3em;
    color: rgba(232,224,204,0.6);
    text-transform: uppercase;
  }

  .scroll-line {
    width: 1px;
    height: 40px;
    background: linear-gradient(to bottom, var(--gold), transparent);
    animation: scaleY 1.2s ease-in-out infinite;
    transform-origin: top;
  }

  @keyframes scaleY {
    0%,100% { opacity:0.4; transform:scaleY(1); }
    50% { opacity:1; transform:scaleY(1.1); }
  }

  @keyframes fadeUp {
    from { opacity:0; transform:translateY(20px); }
    to { opacity:1; transform:translateY(0); }
  }

  @keyframes fadeIn {
    from { opacity:0; }
    to { opacity:0.75; }
  }

  /* ===== ボタン共通 ===== */
  .btn-gold {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.9rem 2.2rem;
    background: linear-gradient(135deg, var(--gold), var(--gold-light));
    color: var(--ink);
    font-family: 'Noto Serif JP', serif;
    font-weight: 700;
    font-size: 0.95rem;
    letter-spacing: 0.05em;
    border: none;
    border-radius: 2px;
    text-decoration: none;
    cursor: pointer;
    transition: opacity 0.2s, transform 0.2s;
  }

  .btn-gold:hover { opacity: 0.9; transform: translateY(-2px); }

  .btn-line {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.9rem 2.2rem;
    background: #06c755;
    color: #fff;
    font-weight: 700;
    font-size: 0.95rem;
    border-radius: 2px;
    text-decoration: none;
    transition: opacity 0.2s, transform 0.2s;
  }

  .btn-line:hover { opacity: 0.9; transform: translateY(-2px); }

  .btn-outline {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.9rem 2.2rem;
    background: transparent;
    color: var(--gold);
    font-weight: 700;
    font-size: 0.95rem;
    border: 1px solid rgba(201,168,76,0.5);
    border-radius: 2px;
    text-decoration: none;
    transition: border-color 0.2s, background 0.2s;
  }

  .btn-outline:hover { border-color: var(--gold); background: rgba(201,168,76,0.08); }

  /* ===== セクション共通 ===== */
  .section-eyebrow {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.25rem;
    opacity: 0;
    transform: translateY(20px);
    transition: opacity 0.7s ease, transform 0.7s ease;
  }

  .section-eyebrow.visible { opacity: 1; transform: translateY(0); }
  .eyebrow-line { width: 40px; height: 1px; background: var(--gold); }

  .eyebrow-text {
    font-size: 0.7rem;
    letter-spacing: 0.35em;
    color: var(--gold);
    text-transform: uppercase;
  }

  .section-heading {
    font-family: 'Noto Serif JP', serif;
    font-size: clamp(1.6rem, 4vw, 2.8rem);
    line-height: 1.4;
    margin-bottom: 1.5rem;
    opacity: 0;
    transform: translateY(24px);
    transition: opacity 0.8s ease 0.1s, transform 0.8s ease 0.1s;
  }

  .section-heading.visible { opacity: 1; transform: translateY(0); }

  .body-text {
    font-size: 0.94rem;
    line-height: 2;
    color: rgba(232,224,204,0.8);
    opacity: 0;
    transform: translateY(20px);
    transition: opacity 0.8s ease 0.2s, transform 0.8s ease 0.2s;
  }

  .body-text.visible { opacity: 1; transform: translateY(0); }

  /* ===== WHAT IS ===== */
  .whatis {
    padding: 5rem 1.5rem;
    max-width: 860px;
    margin: 0 auto;
  }

  /* ===== シール・特典 ===== */
  .seals {
    background: var(--ink);
    padding: 5rem 1.5rem;
  }

  .seals-inner { max-width: 960px; margin: 0 auto; }

  .seals-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 1.5rem;
    margin-top: 2.5rem;
  }

  .seal-box {
    border: 1px solid rgba(201,168,76,0.25);
    padding: 2rem 1.5rem;
    background: rgba(201,168,76,0.04);
    position: relative;
  }

  .seal-box::before {
    content: '';
    position: absolute;
    top: 0; left: 0;
    width: 3px; height: 100%;
    background: linear-gradient(to bottom, var(--gold), transparent);
  }

  .seal-num {
    font-family: 'Noto Serif JP', serif;
    font-size: 2.5rem;
    color: rgba(201,168,76,0.2);
    font-weight: 900;
    line-height: 1;
    margin-bottom: 0.5rem;
  }

  .seal-box .seal-title {
    font-family: 'Noto Serif JP', serif;
    font-size: 1.05rem;
    font-weight: 900;
    color: var(--cream);
    line-height: 1.85;
    margin-bottom: 0.9rem;
  }

  .seal-box .seal-sub {
    font-size: 0.82rem;
    color: rgba(232,224,204,0.6);
    line-height: 1.8;
  }

  /* ===== 担当者プロフィール ===== */
  .partner {
    padding: 5rem 1.5rem;
    max-width: 860px;
    margin: 0 auto;
  }

  .partner-wrap {
    display: flex;
    gap: 2.5rem;
    align-items: flex-start;
    flex-wrap: wrap;
    margin-top: 2rem;
  }

  .partner-img {
    width: 160px;
    height: 160px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid rgba(201,168,76,0.5);
    flex-shrink: 0;
  }

  .partner-img-placeholder {
    width: 160px;
    height: 160px;
    border-radius: 50%;
    background: rgba(201,168,76,0.1);
    border: 2px solid rgba(201,168,76,0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 4rem;
    flex-shrink: 0;
  }

  .partner-name {
    font-family: 'Noto Serif JP', serif;
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--gold-light);
    margin-bottom: 0.25rem;
  }

  .partner-role {
    font-size: 0.78rem;
    letter-spacing: 0.15em;
    color: var(--gold);
    margin-bottom: 1rem;
  }

  .partner-text {
    font-size: 0.9rem;
    line-height: 2;
    color: rgba(232,224,204,0.8);
  }

  /* ===== 区切り ===== */
  .divider {
    width: 100%;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(201,168,76,0.25), transparent);
  }

  /* ===== 問い合わせフォーム ===== */
  .contact-section {
    background: var(--ink);
    padding: 5rem 1.5rem;
  }

  .contact-inner {
    max-width: 640px;
    margin: 0 auto;
  }

  .contact-lead {
    font-size: 0.9rem;
    line-height: 2;
    color: rgba(232,224,204,0.7);
    margin-bottom: 2.5rem;
  }

  .form-group { margin-bottom: 1.5rem; }

  label {
    display: block;
    font-size: 0.78rem;
    letter-spacing: 0.1em;
    color: var(--gold);
    margin-bottom: 0.5rem;
  }

  .required { color: var(--crimson-bright); margin-left: 0.3rem; }

  input[type="text"],
  input[type="email"],
  input[type="tel"],
  textarea {
    width: 100%;
    padding: 0.85rem 1rem;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(201,168,76,0.25);
    border-radius: 2px;
    color: var(--cream);
    font-family: inherit;
    font-size: 0.9rem;
    transition: border-color 0.2s, background 0.2s;
  }

  input:focus, textarea:focus {
    outline: none;
    border-color: var(--gold);
    background: rgba(255,255,255,0.07);
  }

  textarea { resize: vertical; min-height: 140px; }

  .btn-submit {
    width: 100%;
    padding: 1.1rem;
    background: linear-gradient(135deg, var(--gold), var(--gold-light));
    color: var(--ink);
    font-family: 'Noto Serif JP', serif;
    font-weight: 700;
    font-size: 1rem;
    letter-spacing: 0.05em;
    border: none;
    border-radius: 2px;
    cursor: pointer;
    transition: opacity 0.2s, transform 0.2s;
    margin-top: 0.5rem;
  }

  .btn-submit:hover:not(:disabled) { opacity: 0.9; transform: translateY(-1px); }
  .btn-submit:disabled { opacity: 0.5; cursor: not-allowed; }

  .form-msg {
    margin-top: 1rem;
    padding: 0.85rem 1rem;
    border-radius: 2px;
    font-size: 0.88rem;
    display: none;
  }

  .form-msg.success {
    background: rgba(6,199,85,0.1);
    border: 1px solid rgba(6,199,85,0.4);
    color: #06c755;
  }

  .form-msg.error {
    background: rgba(139,26,26,0.15);
    border: 1px solid rgba(178,34,34,0.4);
    color: #e08080;
  }

  .form-errors { list-style: none; }
  .form-errors li::before { content: '✕ '; }

  /* ===== フッター ===== */
  footer {
    background: var(--black);
    border-top: 1px solid rgba(201,168,76,0.1);
    padding: 2.5rem 1.5rem;
    text-align: center;
    font-size: 0.78rem;
    color: rgba(232,224,204,0.35);
  }

  footer a { color: var(--gold); text-decoration: none; }

  /* ===== レスポンシブ ===== */
  @media (max-width: 600px) {
    .partner-wrap { flex-direction: column; align-items: center; text-align: center; }
    .hero-cta { flex-direction: column; align-items: center; }
    .btn-gold, .btn-line, .btn-outline { width: 100%; justify-content: center; }
  }

  @media (prefers-reduced-motion: reduce) {
    *, .section-eyebrow, .section-heading, .body-text { transition: none !important; animation: none !important; opacity: 1 !important; transform: none !important; }
  }
</style>
</head>
<body>

<!-- ===== HERO ===== -->
<section class="hero" id="top">
  <?php if (!empty($agent['profile_image'])): ?>
  <!-- 代理店がプロフィール画像を登録している場合はヒーロー背景として使用可能 -->
  <?php endif; ?>

  <!-- ヒーロー画像（元LPのbase64画像をそのまま使用） -->
  <img class="hero-img-pc" src="data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAUEBAUEAwUFBAUGBgUGCA4JCAcHCBEMDQoOFBEVFBMRExMWGB8bFhceFxMTGyUcHiAhIyMjFRomKSYiKR8iIyL/2wBDAQYGBggHCBAJCRAiFhMWIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiIiL/wAARCAQ+BagDASIAAhEBAxEB/8QAHAABAAIDAQEBAAAAAAAAAAAAAAECBAMFBgcI/8QAUxAAAgEDAwIFAgMGBQIEAQIXAQIDAAQRBRIhMUEGEyJRYTJxFEKBByNSkaGxFTNiwdEk4RZDcvAlgqLxCDRTkhdzsjVEY4OTwtImVGRllKOzw//EABsBAAIDAQEBAAAAAAAAAAAAAAMEAQIFAAYH/8QAPhEAAgIBAwIEAwgCAgICAQIHAQIAAxEEEiExQQUTIlFhcbEUMoGRocHR8CPhQvEzUgZiFSRysiU0Q1PSov/aAAwDAQACEQMRAD8A/GetartV6u60v1er1er1er1er1erletXq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9Xq9X/2Q==" alt="戦国経済圏 評議員NFT" style="object-fit:cover;position:absolute;inset:0;width:100%;height:100%;opacity:0.01;">
  <!-- 実際の背景はCSSグラデーションで表現 -->
  <div style="position:absolute;inset:0;background:linear-gradient(135deg,rgba(10,8,5,0.97) 0%,rgba(139,26,26,0.3) 50%,rgba(10,8,5,0.98) 100%),repeating-linear-gradient(45deg,transparent,transparent 60px,rgba(201,168,76,0.03) 60px,rgba(201,168,76,0.03) 61px);"></div>

  <div class="hero-overlay"></div>

  <div class="hero-content">
    <p class="hero-eyebrow">Sengoku Keizaiken — Councillor NFT</p>
    <h1 class="hero-title">
      デジタル経済圏の<br>
      <span class="hero-title-gold">評議員</span>となれ。
    </h1>
    <p class="hero-sub">
      戦国経済圏 評議員NFTは、土地・商業・ガバナンスを束ねる<br>
      次世代デジタル資産エコシステムへの特権的参加権です。
    </p>
    <div class="hero-cta">
      <?php if (!empty($agent['show_form'])): ?>
      <a href="#contact" class="btn-gold">無料で相談する</a>
      <?php endif; ?>
      <?php if (!empty($agent['show_line_btn']) && !empty($agent['line_url'])): ?>
      <a href="/line_click.php?agent_id=<?= $agent['id'] ?>" class="btn-line" target="_blank" rel="noopener">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.03 2 11c0 3.12 1.67 5.88 4.23 7.55L5 22l4.18-2.09C10.04 20.29 11 20.5 12 20.5c5.52 0 10-4.03 10-9S17.52 2 12 2z"/></svg>
        LINEで相談
      </a>
      <?php endif; ?>
    </div>
  </div>

  <div class="hero-scroll">
    <span>Scroll</span>
    <div class="scroll-line"></div>
  </div>
</section>

<div class="divider"></div>

<!-- ===== WHAT IS ===== -->
<section class="whatis">
  <div class="section-eyebrow js-reveal">
    <div class="eyebrow-line"></div>
    <span class="eyebrow-text">What is</span>
  </div>
  <h2 class="section-heading js-reveal">評議員NFTとは</h2>
  <p class="body-text js-reveal">
    戦国経済圏 評議員NFTは、デジタル経済圏における土地所有・商業権・収益分配・ガバナンス参加の4つの特権を束ねた希少なNFTです。<br><br>
    保有者は「評議員」として経済圏の意思決定に参加し、賃料収入・商業権販売益・トークン還元など複数の収益経路を持つことができます。<br><br>
    NFT初心者でも安心してスタートできるよう、専任の担当者が参入から運用までを丁寧にサポートします。
  </p>
</section>

<div class="divider"></div>

<!-- ===== 特権・特典 ===== -->
<section class="seals">
  <div class="seals-inner">
    <div class="section-eyebrow js-reveal">
      <div class="eyebrow-line"></div>
      <span class="eyebrow-text">Benefits</span>
    </div>
    <h2 class="section-heading js-reveal">評議員の4つの特権</h2>
    <div class="seals-grid">
      <div class="seal-box">
        <p class="seal-num">01</p>
        <p class="seal-title">デジタル土地<br>所有権</p>
        <p class="seal-sub">NFTで土地の所有権を確実に記録。転売・賃貸・開発の自由度が高い資産形成が可能です。</p>
      </div>
      <div class="seal-box">
        <p class="seal-num">02</p>
        <p class="seal-title">商業権と<br>収益参加</p>
        <p class="seal-sub">商業区画の利用権・販売益・賃料収入など、複数の収益経路が設計されています。</p>
      </div>
      <div class="seal-box">
        <p class="seal-num">03</p>
        <p class="seal-title">ガバナンス<br>投票権</p>
        <p class="seal-sub">経済圏の重要施策に対して投票権を持ち、コミュニティの意思決定に参加できます。</p>
      </div>
      <div class="seal-box">
        <p class="seal-num">04</p>
        <p class="seal-title">トークン<br>還元プログラム</p>
        <p class="seal-sub">経済活動に連動したトークン還元。保有期間に応じた長期インセンティブも設計中。</p>
      </div>
    </div>
  </div>
</section>

<div class="divider"></div>

<!-- ===== 担当者プロフィール ===== -->
<section class="partner">
  <div class="section-eyebrow js-reveal">
    <div class="eyebrow-line"></div>
    <span class="eyebrow-text">Your Partner</span>
  </div>
  <h2 class="section-heading js-reveal">担当者紹介</h2>
  <div class="partner-wrap">
    <?php if (!empty($agent['profile_image'])): ?>
    <img src="<?= h($agent['profile_image']) ?>" alt="<?= h($agent['person_name']) ?>" class="partner-img">
    <?php else: ?>
    <div class="partner-img-placeholder">👤</div>
    <?php endif; ?>
    <div class="js-reveal body-text" style="flex:1;min-width:240px;">
      <p class="partner-name"><?= h($agent['person_name']) ?></p>
      <p class="partner-role"><?= h($agent['agent_name']) ?></p>
      <?php if (!empty($agent['profile_text'])): ?>
      <p class="partner-text"><?= nl2br(h($agent['profile_text'])) ?></p>
      <?php else: ?>
      <p class="partner-text">
        戦国経済圏 評議員NFTの専任担当者として、参入から運用まで丁寧にサポートします。<br>
        NFT初心者の方でも安心してご相談ください。まずはお気軽にお問い合わせを。
      </p>
      <?php endif; ?>
      <div style="margin-top:1.5rem;display:flex;gap:1rem;flex-wrap:wrap;">
        <?php if (!empty($agent['phone'])): ?>
        <a href="tel:<?= h($agent['phone']) ?>" class="btn-outline" style="padding:0.6rem 1.2rem;font-size:0.85rem;">
          📞 <?= h($agent['phone']) ?>
        </a>
        <?php endif; ?>
        <?php if (!empty($agent['line_url'])): ?>
        <a href="/line_click.php?agent_id=<?= $agent['id'] ?>" class="btn-line" style="padding:0.6rem 1.2rem;font-size:0.85rem;" target="_blank" rel="noopener">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.03 2 11c0 3.12 1.67 5.88 4.23 7.55L5 22l4.18-2.09C10.04 20.29 11 20.5 12 20.5c5.52 0 10-4.03 10-9S17.52 2 12 2z"/></svg>
          LINEで相談
        </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<div class="divider"></div>

<!-- ===== 問い合わせフォーム ===== -->
<section class="contact-section" id="contact">
  <div class="contact-inner">
    <div class="section-eyebrow js-reveal">
      <div class="eyebrow-line"></div>
      <span class="eyebrow-text">Contact</span>
    </div>
    <h2 class="section-heading js-reveal">無料相談・お問い合わせ</h2>
    <p class="contact-lead js-reveal">
      <?= h($agent['person_name']) ?>（<?= h($agent['agent_name']) ?>）が担当いたします。<br>
      内容確認後、担当者より順次ご連絡いたします。
    </p>

    <?php
    $showForm   = !empty($agent['show_form']);
    $showLinBtn = !empty($agent['show_line_btn']) && !empty($agent['line_url']);
    ?>

    <?php if ($showLinBtn): ?>
    <div style="margin-bottom:<?= $showForm ? '2rem' : '0' ?>;">
      <a href="/line_click.php?agent_id=<?= $agent['id'] ?>" class="btn-line" target="_blank" rel="noopener"
         style="display:inline-flex;align-items:center;gap:.6rem;padding:1rem 2.5rem;font-size:1.05rem;width:<?= $showForm ? 'auto' : '100%' ?>;justify-content:center;border-radius:2px;">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.03 2 11c0 3.12 1.67 5.88 4.23 7.55L5 22l4.18-2.09C10.04 20.29 11 20.5 12 20.5c5.52 0 10-4.03 10-9S17.52 2 12 2z"/></svg>
        LINEで相談する（無料）
      </a>
      <?php if ($showForm): ?>
      <p style="margin-top:1rem;font-size:.8rem;color:rgba(232,224,204,.35);text-align:center;letter-spacing:.05em;">— または下のフォームからお問い合わせ —</p>
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
        <textarea name="message" placeholder="評議員NFTについて詳しく知りたい、など" required></textarea>
      </div>

      <div class="form-msg" id="formMsg"></div>
      <button type="submit" class="btn-submit" id="submitBtn">送信する</button>
    </form>
    <?php endif; ?>
  </div>
</section>

<footer>
  <p><?= h($agent['agent_name']) ?> &nbsp;|&nbsp; <a href="#">プライバシーポリシー</a></p>
  <p style="margin-top:0.4rem;">本ページは 戦国経済圏 の正規代理店によって運営されています。</p>
</footer>

<script>
// スクロールアニメーション
const observer = new IntersectionObserver((entries) => {
  entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('visible'); });
}, { threshold: 0.1 });
document.querySelectorAll('.js-reveal').forEach(el => observer.observe(el));

// お問い合わせフォーム送信
<?php if ($showForm): ?>
document.getElementById('contactForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn   = document.getElementById('submitBtn');
  const msgEl = document.getElementById('formMsg');
  btn.disabled = true;
  btn.textContent = '送信中...';
  msgEl.style.display = 'none';
  msgEl.className = 'form-msg';

  const data = {};
  new FormData(this).forEach((v, k) => data[k] = v);

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
      this.reset();
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
  } catch {
    msgEl.className = 'form-msg error';
    msgEl.textContent = '通信エラーが発生しました。時間をおいて再度お試しください。';
  } finally {
    msgEl.style.display = 'block';
    btn.disabled = false;
    btn.textContent = '送信する';
    msgEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }
});
<?php endif; ?>
</script>
</body>
</html>

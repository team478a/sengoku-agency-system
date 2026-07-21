<?php
/**
 * LPテンプレート: sengoku-lp
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
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+JP:wght@300;400;500;600;700&family=Noto+Sans+JP:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  :root {
    --sakura: #E8A0B0;
    --sakura-light: #F5D0DA;
    --sakura-deep: #C4536E;
    --gold: #C9A84C;
    --gold-light: #E8D08A;
    --kinuneri: #F7F0E6;
    --kinuneri-dark: #EDE0CC;
    --white: #FFFFFF;
    --black-jo: #1A1008;
    --charcoal: #2D2416;
    --text-main: #2D2416;
    --text-sub: #5C4A35;
    --text-light: #8B7355;
    --beige: #D4C4A8;
  }

  * { margin:0; padding:0; box-sizing:border-box; }
  
  html { scroll-behavior: smooth; }

  body {
    font-family: 'Noto Sans JP', sans-serif;
    color: var(--text-main);
    background: var(--kinuneri);
    overflow-x: hidden;
  }

  /* ===== FIXED CTA BAR ===== */
  .fixed-cta {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 1000;
    background: rgba(26,16,8,0.92);
    backdrop-filter: blur(8px);
    padding: 12px 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 16px;
    border-top: 1px solid var(--gold);
  }
  .fixed-cta-text {
    color: var(--gold-light);
    font-family: 'Noto Serif JP', serif;
    font-size: 12px;
    letter-spacing: 0.1em;
    display: none;
  }
  .fixed-cta-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, var(--sakura-deep) 0%, #8B2040 100%);
    color: white;
    font-family: 'Noto Serif JP', serif;
    font-size: 15px;
    font-weight: 600;
    padding: 14px 32px;
    border-radius: 40px;
    text-decoration: none;
    letter-spacing: 0.05em;
    border: 1px solid rgba(255,255,255,0.2);
    transition: all 0.3s;
    white-space: nowrap;
  }
  .fixed-cta-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 8px 24px rgba(196,83,110,0.4);
  }
  .fixed-cta-btn span {
    font-size: 18px;
  }

  /* ===== HEADER ===== */
  header {
    position: fixed;
    top: 0; left: 0; right: 0;
    z-index: 999;
    padding: 16px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: linear-gradient(180deg, rgba(26,16,8,0.7) 0%, transparent 100%);
  }
  .logo {
    font-family: 'Noto Serif JP', serif;
    color: var(--gold-light);
    font-size: 18px;
    font-weight: 600;
    letter-spacing: 0.15em;
    text-decoration: none;
  }
  .header-cta {
    display: inline-flex;
    align-items: center;
    background: var(--sakura-deep);
    color: white;
    font-family: 'Noto Serif JP', serif;
    font-size: 12px;
    font-weight: 600;
    padding: 10px 20px;
    border-radius: 40px;
    text-decoration: none;
    letter-spacing: 0.05em;
    transition: all 0.3s;
  }
  .header-cta:hover { background: #a03458; }

  /* ===== HERO ===== */
  .hero {
    position: relative;
    width: 100%;
    overflow: hidden;
  }
  /* desktop: 16:9 landscape */
  .hero { aspect-ratio: 16/9; }
  @media (max-width: 640px) {
    /* mobile image is ~9:16 portrait */
    .hero { aspect-ratio: 9/16; }
  }
  .hero-bg {
    position: absolute;
    inset: 0;
    background-size: cover;
    background-position: center top;
    background-repeat: no-repeat;
  }
  .hero-bg-desktop {
    display: block;
    background-image: url('/uploads/lp/sengoku/img_083b4a5e.png');
  }
  .hero-bg-mobile {
    display: none;
    background-image: url('/uploads/lp/sengoku/img_5dd1a5dd.png');
  }
  @media (max-width: 640px) {
    .hero-bg-desktop { display: none; }
    .hero-bg-mobile  { display: block; background-position: center top; }
  }
  .hero-overlay { display: none; }
  .hero-content {
    position: relative;
    z-index: 2;
    padding: 0 24px 100px;
    max-width: 680px;
  }
  .hero-eyebrow {
    font-family: 'Noto Serif JP', serif;
    font-size: 13px;
    color: var(--gold-light);
    letter-spacing: 0.25em;
    margin-bottom: 16px;
    opacity: 0.9;
  }
  .hero-title {
    font-family: 'Noto Serif JP', serif;
    font-size: clamp(42px, 10vw, 72px);
    font-weight: 700;
    color: white;
    line-height: 1.2;
    letter-spacing: 0.05em;
    margin-bottom: 20px;
    text-shadow: 0 2px 20px rgba(0,0,0,0.5);
  }
  .hero-title em {
    color: var(--sakura-light);
    font-style: normal;
  }
  .hero-lead {
    font-family: 'Noto Serif JP', serif;
    font-size: 14px;
    color: rgba(255,255,255,0.85);
    line-height: 2;
    margin-bottom: 32px;
    letter-spacing: 0.05em;
  }
  .hero-lead strong {
    color: var(--sakura-light);
  }
  /* Benefits strip */
  .hero-benefits {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 24px;
  }
  .hero-benefit-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    min-width: 72px;
  }
  .hero-benefit-icon {
    width: 40px;
    height: 40px;
    background: rgba(201,168,76,0.15);
    border: 1px solid var(--gold);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
  }
  .hero-benefit-label {
    font-size: 10px;
    color: var(--gold-light);
    text-align: center;
    line-height: 1.4;
    letter-spacing: 0.05em;
  }
  .hero-cta-wrap {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }
  .hero-cta-main {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    background: linear-gradient(135deg, var(--sakura-deep) 0%, #8B2040 100%);
    color: white;
    font-family: 'Noto Serif JP', serif;
    font-size: 17px;
    font-weight: 700;
    padding: 18px 36px;
    border-radius: 50px;
    text-decoration: none;
    letter-spacing: 0.05em;
    border: 1px solid rgba(255,255,255,0.2);
    transition: all 0.3s;
    box-shadow: 0 4px 20px rgba(196,83,110,0.35);
  }
  .hero-cta-main:hover { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(196,83,110,0.5); }
  .hero-cta-note {
    font-size: 11px;
    color: rgba(255,255,255,0.5);
    text-align: center;
    letter-spacing: 0.1em;
  }

  /* ===== SECTION BASE ===== */
  section {
    padding: 80px 24px;
  }
  .section-inner {
    max-width: 800px;
    margin: 0 auto;
  }
  .section-eyebrow {
    font-family: 'Noto Serif JP', serif;
    font-size: 11px;
    letter-spacing: 0.35em;
    color: var(--gold);
    text-transform: uppercase;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .section-eyebrow::before, .section-eyebrow::after {
    content: '';
    flex: 1;
    height: 1px;
    background: linear-gradient(90deg, transparent, var(--gold));
  }
  .section-eyebrow::after {
    background: linear-gradient(90deg, var(--gold), transparent);
  }
  .section-title {
    font-family: 'Noto Serif JP', serif;
    font-size: clamp(26px, 6vw, 38px);
    font-weight: 700;
    color: var(--text-main);
    line-height: 1.4;
    letter-spacing: 0.05em;
    margin-bottom: 24px;
    text-align: center;
  }
  .section-lead {
    font-size: 15px;
    line-height: 2.1;
    color: var(--text-sub);
    text-align: center;
    letter-spacing: 0.05em;
    margin-bottom: 40px;
  }

  /* ===== SECTION 2: EMPATHY ===== */
  .empathy-section {
    background: var(--white);
  }
  .empathy-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-top: 40px;
  }
  .empathy-card {
    background: var(--kinuneri);
    border: 1px solid var(--kinuneri-dark);
    border-radius: 16px;
    padding: 24px 16px;
    text-align: center;
    transition: all 0.3s;
  }
  .empathy-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(196,83,110,0.1);
    border-color: var(--sakura);
  }
  .empathy-card-icon { font-size: 28px; margin-bottom: 10px; }
  .empathy-card-text {
    font-family: 'Noto Serif JP', serif;
    font-size: 14px;
    font-weight: 600;
    color: var(--text-main);
    line-height: 1.5;
    letter-spacing: 0.05em;
  }

  /* ===== SECTION 3: CULTURE CRISIS ===== */
  .crisis-section {
    background: var(--charcoal);
    color: white;
    position: relative;
    overflow: hidden;
  }
  .crisis-section::before {
    content: '文';
    position: absolute;
    right: -20px; top: -40px;
    font-family: 'Noto Serif JP', serif;
    font-size: 280px;
    color: rgba(255,255,255,0.03);
    font-weight: 700;
    pointer-events: none;
  }
  .crisis-section .section-title { color: white; }
  .crisis-section .section-lead { color: rgba(255,255,255,0.7); }
  .crisis-stats {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
    margin-top: 40px;
  }
  .crisis-stat {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(201,168,76,0.3);
    border-radius: 12px;
    padding: 24px 20px;
    text-align: center;
  }
  .crisis-stat-num {
    font-family: 'Noto Serif JP', serif;
    font-size: 36px;
    font-weight: 700;
    color: var(--gold-light);
    line-height: 1;
    margin-bottom: 8px;
  }
  .crisis-stat-label {
    font-size: 12px;
    color: rgba(255,255,255,0.6);
    line-height: 1.6;
    letter-spacing: 0.05em;
  }
  .crisis-message {
    margin-top: 40px;
    background: rgba(201,168,76,0.08);
    border-left: 3px solid var(--gold);
    border-radius: 0 12px 12px 0;
    padding: 24px 20px;
  }
  .crisis-message p {
    font-family: 'Noto Serif JP', serif;
    font-size: 15px;
    color: rgba(255,255,255,0.85);
    line-height: 2;
    letter-spacing: 0.05em;
  }
  .crisis-message p strong {
    color: var(--gold-light);
  }

  /* ===== SECTION 4: WHAT IS 戦国経済圏 ===== */
  .about-section {
    background: var(--kinuneri);
  }
  .about-cards {
    display: grid;
    gap: 16px;
    margin-top: 40px;
  }
  .about-card {
    background: white;
    border-radius: 16px;
    padding: 28px 24px;
    display: flex;
    gap: 20px;
    align-items: flex-start;
    border: 1px solid var(--kinuneri-dark);
  }
  .about-card-icon {
    width: 52px;
    height: 52px;
    background: linear-gradient(135deg, var(--sakura-light), var(--sakura));
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    flex-shrink: 0;
  }
  .about-card-body h3 {
    font-family: 'Noto Serif JP', serif;
    font-size: 17px;
    font-weight: 700;
    color: var(--text-main);
    margin-bottom: 8px;
    letter-spacing: 0.05em;
  }
  .about-card-body p {
    font-size: 13px;
    color: var(--text-sub);
    line-height: 1.9;
    letter-spacing: 0.03em;
  }

  /* ===== SECTION 5: INFLUENCER ===== */
  .influencer-section {
    background: white;
  }
  .influencer-activities {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
    margin-top: 32px;
  }
  .activity-item {
    display: flex;
    align-items: center;
    gap: 12px;
    background: var(--kinuneri);
    border-radius: 12px;
    padding: 16px;
    border: 1px solid var(--kinuneri-dark);
  }
  .activity-icon {
    font-size: 22px;
    width: 40px;
    text-align: center;
    flex-shrink: 0;
  }
  .activity-text {
    font-family: 'Noto Serif JP', serif;
    font-size: 14px;
    font-weight: 600;
    color: var(--text-main);
    letter-spacing: 0.03em;
  }
  .influencer-note {
    margin-top: 32px;
    text-align: center;
    background: linear-gradient(135deg, rgba(232,160,176,0.1), rgba(201,168,76,0.1));
    border: 1px solid var(--sakura);
    border-radius: 16px;
    padding: 24px 20px;
  }
  .influencer-note p {
    font-family: 'Noto Serif JP', serif;
    font-size: 15px;
    color: var(--sakura-deep);
    font-weight: 600;
    line-height: 1.8;
    letter-spacing: 0.05em;
  }

  /* ===== SECTION 6: NFT ===== */
  .nft-section {
    background: var(--black-jo);
    color: white;
    position: relative;
    overflow: hidden;
  }
  .nft-section::after {
    content: '';
    position: absolute;
    inset: 0;
    background: 
      radial-gradient(circle at 10% 50%, rgba(201,168,76,0.06) 0%, transparent 60%),
      radial-gradient(circle at 90% 50%, rgba(196,83,110,0.06) 0%, transparent 60%);
    pointer-events: none;
  }
  .nft-section .section-title { color: white; }
  .nft-card {
    position: relative;
    z-index: 1;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(201,168,76,0.4);
    border-radius: 20px;
    padding: 36px 24px;
    text-align: center;
    margin-top: 40px;
  }
  .nft-kamon {
    font-size: 48px;
    margin-bottom: 20px;
    filter: drop-shadow(0 0 12px rgba(201,168,76,0.4));
  }
  .nft-name {
    font-family: 'Noto Serif JP', serif;
    font-size: 24px;
    font-weight: 700;
    color: var(--gold-light);
    letter-spacing: 0.1em;
    margin-bottom: 8px;
  }
  .nft-sub {
    font-size: 12px;
    color: rgba(255,255,255,0.4);
    letter-spacing: 0.25em;
    margin-bottom: 24px;
  }
  .nft-desc {
    font-size: 14px;
    color: rgba(255,255,255,0.75);
    line-height: 2.1;
    letter-spacing: 0.05em;
    margin-bottom: 28px;
  }
  .nft-highlight {
    display: inline-block;
    color: var(--sakura-light);
    font-weight: 600;
  }
  .nft-cta {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: linear-gradient(135deg, var(--sakura-deep) 0%, #8B2040 100%);
    color: white;
    font-family: 'Noto Serif JP', serif;
    font-size: 16px;
    font-weight: 700;
    padding: 16px 36px;
    border-radius: 50px;
    text-decoration: none;
    letter-spacing: 0.05em;
    border: 1px solid rgba(255,255,255,0.15);
    transition: all 0.3s;
  }
  .nft-cta:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(196,83,110,0.4); }

  /* ===== SECTION 7: BENEFITS ===== */
  .benefits-section {
    background: var(--kinuneri);
  }
  .benefits-grid {
    display: grid;
    gap: 16px;
    margin-top: 40px;
  }
  .benefit-item {
    background: white;
    border-radius: 16px;
    padding: 24px 20px;
    display: flex;
    gap: 16px;
    align-items: flex-start;
    border: 1px solid var(--kinuneri-dark);
    position: relative;
    overflow: hidden;
  }
  .benefit-item::before {
    content: '';
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 3px;
    background: linear-gradient(180deg, var(--sakura), var(--gold));
  }
  .benefit-icon {
    font-size: 28px;
    width: 48px;
    text-align: center;
    flex-shrink: 0;
  }
  .benefit-body h3 {
    font-family: 'Noto Serif JP', serif;
    font-size: 16px;
    font-weight: 700;
    color: var(--text-main);
    margin-bottom: 6px;
    letter-spacing: 0.05em;
  }
  .benefit-body p {
    font-size: 13px;
    color: var(--text-sub);
    line-height: 1.8;
    letter-spacing: 0.03em;
  }

  /* ===== SECTION 8: HOW TO ENJOY ===== */
  .enjoy-section {
    background: white;
  }
  .enjoy-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
    margin-top: 40px;
  }
  .enjoy-card {
    background: var(--kinuneri);
    border: 1px solid var(--kinuneri-dark);
    border-radius: 16px;
    padding: 20px 16px;
    text-align: center;
    transition: all 0.3s;
  }
  .enjoy-card:hover {
    transform: translateY(-3px);
    border-color: var(--sakura);
    box-shadow: 0 8px 20px rgba(232,160,176,0.2);
  }
  .enjoy-card-icon { font-size: 32px; margin-bottom: 10px; }
  .enjoy-card-title {
    font-family: 'Noto Serif JP', serif;
    font-size: 15px;
    font-weight: 700;
    color: var(--text-main);
    margin-bottom: 6px;
    letter-spacing: 0.05em;
  }
  .enjoy-card-desc {
    font-size: 12px;
    color: var(--text-sub);
    line-height: 1.7;
  }

  /* ===== SECTION 9: FUTURE ===== */
  .future-section {
    background: linear-gradient(135deg, var(--charcoal) 0%, var(--black-jo) 100%);
    color: white;
  }
  .future-section .section-title { color: white; }
  .future-cols {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-top: 40px;
  }
  .future-col {
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 16px;
    padding: 24px 20px;
  }
  .future-col-title {
    font-family: 'Noto Serif JP', serif;
    font-size: 13px;
    letter-spacing: 0.2em;
    color: var(--gold-light);
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
  }
  .future-col-title::before {
    content: '';
    display: inline-block;
    width: 20px;
    height: 1px;
    background: var(--gold);
  }
  .future-items {
    display: flex;
    flex-direction: column;
    gap: 10px;
  }
  .future-item {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    color: rgba(255,255,255,0.75);
    letter-spacing: 0.03em;
  }
  .future-item::before {
    content: '◆';
    font-size: 8px;
    color: var(--sakura);
    flex-shrink: 0;
  }

  /* ===== SECTION 10: FAQ ===== */
  .faq-section {
    background: var(--kinuneri);
  }
  .faq-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-top: 40px;
  }
  .faq-item {
    background: white;
    border-radius: 16px;
    border: 1px solid var(--kinuneri-dark);
    overflow: hidden;
  }
  .faq-question {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 20px 20px;
    cursor: pointer;
    list-style: none;
  }
  .faq-question::-webkit-details-marker { display: none; }
  .faq-q-badge {
    font-family: 'Noto Serif JP', serif;
    font-size: 18px;
    font-weight: 700;
    color: var(--sakura-deep);
    flex-shrink: 0;
    min-width: 24px;
  }
  .faq-q-text {
    font-family: 'Noto Serif JP', serif;
    font-size: 15px;
    font-weight: 600;
    color: var(--text-main);
    flex: 1;
    letter-spacing: 0.03em;
    line-height: 1.5;
  }
  .faq-arrow {
    font-size: 12px;
    color: var(--text-light);
    transition: transform 0.3s;
    flex-shrink: 0;
  }
  details[open] .faq-arrow { transform: rotate(180deg); }
  .faq-answer {
    padding: 0 20px 20px 58px;
    font-size: 14px;
    color: var(--text-sub);
    line-height: 2;
    letter-spacing: 0.03em;
    border-top: 1px solid var(--kinuneri-dark);
    padding-top: 16px;
  }

  /* ===== SECTION 11: FINAL CTA ===== */
  .final-cta-section {
    background: linear-gradient(135deg, #2D1020 0%, var(--black-jo) 50%, #1A2010 100%);
    color: white;
    text-align: center;
    padding: 100px 24px 140px;
    position: relative;
    overflow: hidden;
  }
  .final-cta-section::before {
    content: '桜';
    position: absolute;
    left: -30px; bottom: -60px;
    font-family: 'Noto Serif JP', serif;
    font-size: 300px;
    color: rgba(232,160,176,0.04);
    pointer-events: none;
    font-weight: 700;
  }
  .final-cta-title {
    font-family: 'Noto Serif JP', serif;
    font-size: clamp(28px, 7vw, 48px);
    font-weight: 700;
    color: white;
    line-height: 1.4;
    letter-spacing: 0.05em;
    margin-bottom: 8px;
  }
  .final-cta-title em {
    color: var(--sakura-light);
    font-style: normal;
  }
  .final-cta-sub {
    font-size: 13px;
    color: rgba(255,255,255,0.4);
    letter-spacing: 0.35em;
    margin-bottom: 32px;
  }
  .final-cta-lead {
    font-family: 'Noto Serif JP', serif;
    font-size: 15px;
    color: rgba(255,255,255,0.7);
    line-height: 2.2;
    letter-spacing: 0.08em;
    margin-bottom: 48px;
  }
  .final-cta-btn {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    background: linear-gradient(135deg, var(--sakura-deep) 0%, #8B2040 100%);
    color: white;
    font-family: 'Noto Serif JP', serif;
    font-size: 18px;
    font-weight: 700;
    padding: 22px 48px;
    border-radius: 60px;
    text-decoration: none;
    letter-spacing: 0.05em;
    border: 1px solid rgba(255,255,255,0.2);
    transition: all 0.3s;
    box-shadow: 0 8px 32px rgba(196,83,110,0.4);
  }
  .final-cta-btn:hover { transform: translateY(-2px); box-shadow: 0 12px 40px rgba(196,83,110,0.6); }
  .final-cta-note {
    margin-top: 20px;
    font-size: 12px;
    color: rgba(255,255,255,0.35);
    letter-spacing: 0.15em;
  }

  /* ===== GOLD DIVIDER ===== */
  .gold-divider {
    width: 60px;
    height: 2px;
    background: linear-gradient(90deg, transparent, var(--gold), transparent);
    margin: 0 auto 32px;
  }

  /* ===== FOOTER ===== */
  footer {
    background: var(--black-jo);
    padding: 32px 24px;
    text-align: center;
  }
  .footer-logo {
    font-family: 'Noto Serif JP', serif;
    font-size: 16px;
    color: var(--gold-light);
    letter-spacing: 0.15em;
    margin-bottom: 16px;
  }
  .footer-links {
    display: flex;
    justify-content: center;
    gap: 24px;
    flex-wrap: wrap;
    margin-bottom: 16px;
  }
  .footer-links a {
    font-size: 12px;
    color: rgba(255,255,255,0.4);
    text-decoration: none;
    letter-spacing: 0.05em;
  }
  .footer-copy {
    font-size: 11px;
    color: rgba(255,255,255,0.25);
    letter-spacing: 0.1em;
  }

  /* ===== RESPONSIVE ===== */
  @media (max-width: 640px) {
    section { padding: 64px 20px; }
    .hero-content { padding: 0 20px 88px; }
    .hero-benefits { gap: 8px; }
    .hero-benefit-item { min-width: 60px; }
    .empathy-grid { grid-template-columns: repeat(2, 1fr); }
    .crisis-stats { grid-template-columns: 1fr; }
    .influencer-activities { grid-template-columns: 1fr; }
    .future-cols { grid-template-columns: 1fr; }
    .enjoy-grid { grid-template-columns: repeat(2, 1fr); }
    .faq-answer { padding-left: 20px; }
    .fixed-cta-text { display: none; }
    .fixed-cta-btn { font-size: 14px; padding: 14px 28px; }
    .header-cta { display: none; }
  }
  @media (min-width: 641px) {
    .fixed-cta-text { display: block; }
    .empathy-grid { grid-template-columns: repeat(3, 1fr); }
    .crisis-stats { grid-template-columns: repeat(2, 1fr); }
    .influencer-activities { grid-template-columns: repeat(2, 1fr); }
    .benefits-grid { grid-template-columns: 1fr; }
    .enjoy-grid { grid-template-columns: repeat(4, 1fr); }
    .future-cols { grid-template-columns: 1fr 1fr; }
  }
</style>
</head>
<body>

<!-- Fixed CTA -->
<div class="fixed-cta">
  <span class="fixed-cta-text" style="color:rgba(255,255,255,0.5);font-size:12px;letter-spacing:0.1em;">日本文化を楽しみながら応援する</span>
  <a href="#join" class="fixed-cta-btn">日本文化応援メンバーになる <span>›</span></a>
</div>

<!-- Header -->
<header>
  <a href="#" class="logo">戦国経済圏</a>
  <a href="#join" class="header-cta">メンバーになる</a>
</header>

<!-- ===== 1. HERO ===== -->
<section class="hero">
  <div class="hero-bg hero-bg-desktop"></div>
  <div class="hero-bg hero-bg-mobile"></div>
  <div class="hero-overlay"></div>
  <a id="join" style="position:absolute;bottom:0;"></a>
</section>

<!-- ===== 2. EMPATHY ===== -->
<section class="empathy-section">
  <div class="section-inner">
    <p class="section-eyebrow">こんな方におすすめ</p>
    <h2 class="section-title">あなたの「好き」が、<br>ここにあります。</h2>
    <div class="gold-divider"></div>
    <div class="empathy-grid">
      <div class="empathy-card">
        <div class="empathy-card-icon">🏯</div>
        <p class="empathy-card-text">お城巡りが好き</p>
      </div>
      <div class="empathy-card">
        <div class="empathy-card-icon">👘</div>
        <p class="empathy-card-text">着物・和文化が好き</p>
      </div>
      <div class="empathy-card">
        <div class="empathy-card-icon">✈️</div>
        <p class="empathy-card-text">国内旅行が好き</p>
      </div>
      <div class="empathy-card">
        <div class="empathy-card-icon">🗾</div>
        <p class="empathy-card-text">地域文化に興味がある</p>
      </div>
      <div class="empathy-card">
        <div class="empathy-card-icon">📷</div>
        <p class="empathy-card-text">Instagramで発信したい</p>
      </div>
      <div class="empathy-card">
        <div class="empathy-card-icon">🍵</div>
        <p class="empathy-card-text">カフェ・食文化が好き</p>
      </div>
    </div>
  </div>
</section>

<!-- ===== 3. CULTURE CRISIS ===== -->
<section class="crisis-section">
  <div class="section-inner">
    <p class="section-eyebrow" style="color:var(--gold);">日本文化の現状</p>
    <h2 class="section-title">美しい日本文化が、<br>失われつつあります。</h2>
    <div class="gold-divider"></div>
    <p class="section-lead" style="color:rgba(255,255,255,0.65);">
      伝統工芸の担い手不足、地方の観光地衰退、<br>
      若い世代に受け継がれない和の文化。<br>
      知らないうちに、大切なものが消えていく。
    </p>
    <div class="crisis-stats">
      <div class="crisis-stat">
        <div class="crisis-stat-num">約6割</div>
        <div class="crisis-stat-label">の伝統工芸品産地で<br>後継者不足が深刻化</div>
      </div>
      <div class="crisis-stat">
        <div class="crisis-stat-num">年々減少</div>
        <div class="crisis-stat-label">地方の城下町・<br>観光地の来訪者数</div>
      </div>
      <div class="crisis-stat">
        <div class="crisis-stat-num">30年以内</div>
        <div class="crisis-stat-label">に消える危機にある<br>伝統文化・伝統産業</div>
      </div>
      <div class="crisis-stat">
        <div class="crisis-stat-num">世界遺産</div>
        <div class="crisis-stat-label">登録される一方で<br>維持が困難な文化財</div>
      </div>
    </div>
    <div class="crisis-message">
      <p>しかし、<strong>楽しみながら応援できる</strong>方法があります。<br>
      お城を巡り、着物を着て、地域の味を楽しむ。<br>
      その「体験」が、日本文化を未来へつなぐ力になります。</p>
    </div>
  </div>
</section>

<!-- ===== 4. ABOUT 戦国経済圏 ===== -->
<section class="about-section">
  <div class="section-inner">
    <p class="section-eyebrow">戦国経済圏とは</p>
    <h2 class="section-title">日本文化を楽しみながら、<br>応援するコミュニティ。</h2>
    <div class="gold-divider"></div>
    <p class="section-lead">
      戦国経済圏は、お城・和文化・地域文化を愛する人たちが集まり、<br>
      楽しみながら日本の魅力を未来へつないでいくコミュニティです。
    </p>
    <div class="about-cards">
      <div class="about-card">
        <div class="about-card-icon">🏯</div>
        <div class="about-card-body">
          <h3>お城と城下町を中心に</h3>
          <p>岐阜城をはじめとする全国のお城・城下町を舞台に、観光・イベント・文化体験を企画。地域と連携した活動を展開しています。</p>
        </div>
      </div>
      <div class="about-card">
        <div class="about-card-icon">👘</div>
        <div class="about-card-body">
          <h3>和文化・伝統工芸の応援</h3>
          <p>着物・茶道・伝統工芸・和菓子など、日本の伝統文化を楽しみながら応援。職人さんや地域産業を次の世代につなぎます。</p>
        </div>
      </div>
      <div class="about-card">
        <div class="about-card-icon">🤝</div>
        <div class="about-card-body">
          <h3>つながりで広がるコミュニティ</h3>
          <p>同じ想いを持つ仲間と出会い、地域・人・文化とつながる場所。一人ひとりの得意な形で参加できます。</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ===== 5. INFLUENCER ===== -->
<section class="influencer-section">
  <div class="section-inner">
    <p class="section-eyebrow">戦国インフルエンサーとは</p>
    <h2 class="section-title">あなたらしい形で、<br>日本文化に関わる。</h2>
    <div class="gold-divider"></div>
    <p class="section-lead">
      戦国インフルエンサーとは、戦国経済圏に参加し、<br>
      日本文化や地域の魅力を楽しみながら、イベントや企画、<br>
      コミュニティ活動に関わることができるメンバー制度です。
    </p>
    <div class="influencer-activities">
      <div class="activity-item">
        <div class="activity-icon">🏯</div>
        <div class="activity-text">イベントへの参加</div>
      </div>
      <div class="activity-item">
        <div class="activity-icon">💡</div>
        <div class="activity-text">企画への意見提案</div>
      </div>
      <div class="activity-item">
        <div class="activity-icon">🗾</div>
        <div class="activity-text">地域との交流</div>
      </div>
      <div class="activity-item">
        <div class="activity-icon">👥</div>
        <div class="activity-text">コミュニティ活動</div>
      </div>
      <div class="activity-item">
        <div class="activity-icon">📱</div>
        <div class="activity-text">SNSでの文化発信</div>
      </div>
      <div class="activity-item">
        <div class="activity-icon">✨</div>
        <div class="activity-text">得意な形での参加</div>
      </div>
    </div>
    <div class="influencer-note">
      <p>SNS発信だけが活動ではありません。<br>
      それぞれの得意な形で参加することができます。</p>
    </div>
  </div>
</section>

<!-- ===== 6. NFT ===== -->
<section class="nft-section">
  <div class="section-inner">
    <p class="section-eyebrow" style="color:var(--gold);">戦国インフルエンサーNFTとは</p>
    <h2 class="section-title" style="color:white;">コミュニティへの<br>参加証です。</h2>
    <div class="gold-divider"></div>
    <div class="nft-card">
      <div class="nft-kamon">⚜️</div>
      <div class="nft-name">戦国インフルエンサーNFT</div>
      <div class="nft-sub">— メンバーシップNFT —</div>
      <p class="nft-desc">
        戦国インフルエンサーNFTは、<br>
        戦国経済圏へ参加するための<span class="nft-highlight">メンバーシップNFT</span>です。<br><br>
        単なるデジタル画像ではありません。<br>
        コミュニティへの参加資格として、<br>
        さまざまな特典や企画へ参加することができます。
      </p>
      <a href="#" class="nft-cta">戦国インフルエンサーNFTに参加する ›</a>
    </div>
  </div>
</section>

<!-- ===== 7. BENEFITS ===== -->
<section class="benefits-section">
  <div class="section-inner">
    <p class="section-eyebrow">メンバー特典</p>
    <h2 class="section-title">参加すると、<br>こんなことができます。</h2>
    <div class="gold-divider"></div>
    <div class="benefits-grid">
      <div class="benefit-item">
        <div class="benefit-icon">🏯</div>
        <div class="benefit-body">
          <h3>限定コミュニティ参加</h3>
          <p>戦国経済圏の限定コミュニティに参加できます。同じ想いを持つ仲間と繋がり、情報交換や交流を楽しめます。</p>
        </div>
      </div>
      <div class="benefit-item">
        <div class="benefit-icon">🗳️</div>
        <div class="benefit-body">
          <h3>企画・投票への参加</h3>
          <p>今後のイベントや企画に対して、メンバーとして意見を出したり投票に参加することができます。</p>
        </div>
      </div>
      <div class="benefit-item">
        <div class="benefit-icon">📩</div>
        <div class="benefit-body">
          <h3>イベント優先案内</h3>
          <p>お城巡りツアー、着物体験、地域イベントなどの情報をメンバー限定で優先的にご案内します。</p>
        </div>
      </div>
      <div class="benefit-item">
        <div class="benefit-icon">⚔️</div>
        <div class="benefit-body">
          <h3>活動案件への参加機会</h3>
          <p>地域観光PR、文化プロモーション、メディア企画など、様々な活動案件にご参加いただける機会があります。</p>
        </div>
      </div>
      <div class="benefit-item">
        <div class="benefit-icon">🎁</div>
        <div class="benefit-body">
          <h3>限定特典</h3>
          <p>メンバー限定のデジタルコンテンツ、特別割引、限定グッズなど様々な特典をご用意しています。</p>
        </div>
      </div>
      <div class="benefit-item">
        <div class="benefit-icon">🌸</div>
        <div class="benefit-body">
          <h3>活動サポート制度</h3>
          <p>一定の活動実績を持つメンバーには、より充実したサポートを受けられる制度があります。</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ===== 8. HOW TO ENJOY ===== -->
<section class="enjoy-section">
  <div class="section-inner">
    <p class="section-eyebrow">楽しみ方</p>
    <h2 class="section-title">日本文化の魅力を、<br>一緒に楽しもう。</h2>
    <div class="gold-divider"></div>
    <div class="enjoy-grid">
      <div class="enjoy-card">
        <div class="enjoy-card-icon">🏯</div>
        <div class="enjoy-card-title">お城巡り</div>
        <div class="enjoy-card-desc">全国のお城を巡り、歴史と絶景を堪能</div>
      </div>
      <div class="enjoy-card">
        <div class="enjoy-card-icon">🏘️</div>
        <div class="enjoy-card-title">城下町散策</div>
        <div class="enjoy-card-desc">古き良き城下町の街並みを歩く</div>
      </div>
      <div class="enjoy-card">
        <div class="enjoy-card-icon">👘</div>
        <div class="enjoy-card-title">着物体験</div>
        <div class="enjoy-card-desc">着物で街を歩く特別な体験</div>
      </div>
      <div class="enjoy-card">
        <div class="enjoy-card-icon">🎆</div>
        <div class="enjoy-card-title">地域イベント</div>
        <div class="enjoy-card-desc">祭り・花火・季節の行事に参加</div>
      </div>
      <div class="enjoy-card">
        <div class="enjoy-card-icon">🍡</div>
        <div class="enjoy-card-title">和菓子</div>
        <div class="enjoy-card-desc">四季の和菓子で季節を感じる</div>
      </div>
      <div class="enjoy-card">
        <div class="enjoy-card-icon">🍶</div>
        <div class="enjoy-card-title">日本酒</div>
        <div class="enjoy-card-desc">地域の酒蔵と文化を知る</div>
      </div>
      <div class="enjoy-card">
        <div class="enjoy-card-icon">🎨</div>
        <div class="enjoy-card-title">伝統工芸</div>
        <div class="enjoy-card-desc">職人の技と伝統の美しさに触れる</div>
      </div>
      <div class="enjoy-card">
        <div class="enjoy-card-icon">🌸</div>
        <div class="enjoy-card-title">四季の風景</div>
        <div class="enjoy-card-desc">桜・紅葉・雪景色…日本の四季を楽しむ</div>
      </div>
    </div>
  </div>
</section>

<!-- ===== 9. FUTURE ===== -->
<section class="future-section">
  <div class="section-inner">
    <p class="section-eyebrow" style="color:var(--gold);">将来構想</p>
    <h2 class="section-title">リアルとデジタルで、<br>広がっていく世界。</h2>
    <div class="gold-divider"></div>
    <p class="section-lead" style="color:rgba(255,255,255,0.6);">
      戦国経済圏は、リアルな体験とデジタルの世界が融合した、<br>新しいコミュニティを目指しています。
    </p>
    <div class="future-cols">
      <div class="future-col">
        <div class="future-col-title">🏯 リアル体験</div>
        <div class="future-items">
          <div class="future-item">全国お城巡りイベント</div>
          <div class="future-item">城下町観光ツアー</div>
          <div class="future-item">地域交流・産地訪問</div>
          <div class="future-item">着物・伝統工芸体験</div>
          <div class="future-item">季節の文化イベント</div>
        </div>
      </div>
      <div class="future-col">
        <div class="future-col-title">✨ デジタル展開</div>
        <div class="future-items">
          <div class="future-item">戦国メタバース世界</div>
          <div class="future-item">デジタル会員証・NFT</div>
          <div class="future-item">オンラインコミュニティ</div>
          <div class="future-item">デジタルコンテンツ配信</div>
          <div class="future-item">バーチャル城下町体験</div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ===== 10. FAQ ===== -->
<section class="faq-section">
  <div class="section-inner">
    <p class="section-eyebrow">よくある質問</p>
    <h2 class="section-title">はじめての方へ</h2>
    <div class="gold-divider"></div>
    <div class="faq-list">
      <div class="faq-item">
        <details>
          <summary class="faq-question">
            <span class="faq-q-badge">Q</span>
            <span class="faq-q-text">NFTがよくわからないのですが、参加できますか？</span>
            <span class="faq-arrow">▼</span>
          </summary>
          <div class="faq-answer">
            はい、NFTの知識がなくても大丈夫です。戦国インフルエンサーNFTは、コミュニティへの「参加証」として発行されるデジタルカードです。スマートフォンがあれば、特別な知識がなくてもお手続きいただけます。サポートスタッフが丁寧にご案内しますのでご安心ください。
          </div>
        </details>
      </div>
      <div class="faq-item">
        <details>
          <summary class="faq-question">
            <span class="faq-q-badge">Q</span>
            <span class="faq-q-text">どんな活動をするのですか？</span>
            <span class="faq-arrow">▼</span>
          </summary>
          <div class="faq-answer">
            お城巡りや城下町散策、着物体験などのリアルイベントへの参加から、SNSでの日本文化発信、コミュニティでの交流まで様々です。「参加しなければいけない活動」はありません。自分のペースで、得意な形で関わることができます。
          </div>
        </details>
      </div>
      <div class="faq-item">
        <details>
          <summary class="faq-question">
            <span class="faq-q-badge">Q</span>
            <span class="faq-q-text">SNSをやっていないと参加できませんか？</span>
            <span class="faq-arrow">▼</span>
          </summary>
          <div class="faq-answer">
            SNSをやっていない方でもご参加いただけます。SNS発信はあくまで活動の一つ。イベント参加、企画への意見提案、地域交流など、SNSなしでも十分に楽しんでいただけるコミュニティです。
          </div>
        </details>
      </div>
      <div class="faq-item">
        <details>
          <summary class="faq-question">
            <span class="faq-q-badge">Q</span>
            <span class="faq-q-text">月々の費用はかかりますか？</span>
            <span class="faq-arrow">▼</span>
          </summary>
          <div class="faq-answer">
            戦国インフルエンサーNFTは買い切りのメンバーシップです。月額料金は発生しません。NFT購入後はコミュニティへの参加資格が継続されます。詳細な料金については、お申し込みページをご確認ください。
          </div>
        </details>
      </div>
      <div class="faq-item">
        <details>
          <summary class="faq-question">
            <span class="faq-q-badge">Q</span>
            <span class="faq-q-text">地方在住でも参加できますか？</span>
            <span class="faq-arrow">▼</span>
          </summary>
          <div class="faq-answer">
            もちろんです！むしろ地方在住の方こそ大歓迎です。地域の文化・観光・名産品を発信してくださる方は、戦国経済圏にとって大切な存在。オンラインでも参加できる企画を多数ご用意しています。
          </div>
        </details>
      </div>
      <div class="faq-item">
        <details>
          <summary class="faq-question">
            <span class="faq-q-badge">Q</span>
            <span class="faq-q-text">男性でも参加できますか？</span>
            <span class="faq-arrow">▼</span>
          </summary>
          <div class="faq-answer">
            もちろんご参加いただけます。歴史好き・お城好きの男性の方も大歓迎です。地方創生や日本文化の継承に関心をお持ちの方ならどなたでもご参加いただけます。
          </div>
        </details>
      </div>
    </div>
  </div>
</section>

<!-- ===== 11. FINAL CTA ===== -->
<section class="final-cta-section">
  <div class="section-inner" style="text-align:center;">
    <p class="section-eyebrow" style="color:var(--gold);justify-content:center;">参加のご案内</p>
    <h2 class="final-cta-title">日本の美しさを、<br><em>次の世代へ。</em></h2>
    <div class="gold-divider"></div>
    <p class="final-cta-lead">
      日本文化を楽しみながら、<br>
      人とつながり、<br>
      地域とつながり、<br>
      未来へつないでいく。<br><br>
      あなたも戦国経済圏に参加しませんか？
    </p>
    <a href="#" class="final-cta-btn">日本文化応援メンバーになる ›</a>
    <p class="final-cta-note">※ 戦国インフルエンサーNFT（メンバーシップ）への参加となります</p>
  </div>
</section>

<!-- Footer -->
<footer>
  <div class="footer-logo">戦国経済圏</div>
  <div class="footer-links">
    <a href="#">特定商取引法に基づく表記</a>
    <a href="#">プライバシーポリシー</a>
    <a href="#">利用規約</a>
    <a href="#">お問い合わせ</a>
  </div>
  <p class="footer-copy">© 2025 戦国経済圏 All Rights Reserved.</p>
</footer>


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